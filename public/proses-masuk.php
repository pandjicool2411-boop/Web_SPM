<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

// ============================================================
// CACHE CONTROL
// ============================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ============================================================
// WAJIB POST
// ============================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    die("Method tidak diizinkan.");
}

// ============================================================
// CEK CSRF
// ============================================================

verify_csrf();

// ============================================================
// AMBIL DATA
// ============================================================

$rental_id = $_POST["rental_id"] ?? "";

// ============================================================
// VALIDASI RENTAL ID
// ============================================================

if (
    $rental_id === "" ||
    !ctype_digit((string) $rental_id) ||
    (int) $rental_id <= 0
) {
    http_response_code(400);
    die("Data rental tidak valid.");
}

$rental_id = (int) $rental_id;

// ============================================================
// DATA USER LOGIN
// ============================================================

$user_id = (int) $_SESSION["user_id"];
$branch_id = (int) $_SESSION["branch_id"];

// ============================================================
// PROSES DATABASE
// ============================================================

try {

    $pdo->beginTransaction();

    // ========================================================
    // CARI RENTAL + UNIT
    // LOCK ROW
    // ========================================================

    $stmt = $pdo->prepare(
        "SELECT
            rentals.id AS rental_id,
            rentals.unit_id,
            rentals.status AS rental_status,
            units.kode_unit,
            units.nama_unit,
            units.owner_branch_id,
            units.status AS unit_status
         FROM rentals
         INNER JOIN units
            ON rentals.unit_id = units.id
         WHERE rentals.id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([
        $rental_id
    ]);

    $rental = $stmt->fetch();

    // ========================================================
    // RENTAL TIDAK DITEMUKAN
    // ========================================================

    if (!$rental) {

        $pdo->rollBack();

        http_response_code(404);
        die("Data rental tidak ditemukan.");
    }

    // ========================================================
    // PASTIKAN UNIT MILIK CABANG USER
    // ========================================================

    if (
        (int) $rental["owner_branch_id"] !== $branch_id
    ) {

        $pdo->rollBack();

        http_response_code(403);
        die("Unit ini bukan milik cabang Anda.");
    }

    // ========================================================
    // PASTIKAN RENTAL MASIH AKTIF
    // ========================================================

    if (
        $rental["rental_status"] !== "AKTIF"
    ) {

        $pdo->rollBack();

        http_response_code(409);
        die("Rental ini sudah selesai.");
    }

    // ========================================================
    // PASTIKAN UNIT MASIH DISEWAKAN
    // ========================================================

    if (
        $rental["unit_status"] !== "DISEWAKAN"
    ) {

        $pdo->rollBack();

        http_response_code(409);
        die("Status unit tidak sesuai.");
    }

    // ========================================================
    // UPDATE RENTAL
    // ========================================================

    $stmt = $pdo->prepare(
        "UPDATE rentals
         SET
            checkin_by = ?,
            checkin_at = NOW(),
            status = 'SELESAI'
         WHERE id = ?
           AND status = 'AKTIF'"
    );

    $stmt->execute([
        $user_id,
        $rental_id
    ]);

    // Pastikan tepat satu rental diperbarui
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            "Gagal memperbarui status rental."
        );
    }

    // ========================================================
    // UPDATE STATUS UNIT
    // ========================================================

    $stmt = $pdo->prepare(
        "UPDATE units
         SET status = 'TERSEDIA'
         WHERE id = ?
           AND owner_branch_id = ?
           AND status = 'DISEWAKAN'"
    );

    $stmt->execute([
        (int) $rental["unit_id"],
        $branch_id
    ]);

    // Pastikan tepat satu unit diperbarui
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            "Gagal memperbarui status unit."
        );
    }

    // ========================================================
    // SIMPAN HISTORY
    // ========================================================

    $description =
        "Unit " .
        $rental["kode_unit"] .
        " telah dikonfirmasi masuk kembali.";

    $stmt = $pdo->prepare(
        "INSERT INTO unit_history
        (
            unit_id,
            performed_by,
            branch_id,
            action,
            description
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'UNIT_MASUK',
            ?
        )"
    );

    $stmt->execute([
        (int) $rental["unit_id"],
        $user_id,
        $branch_id,
        $description
    ]);

    // ========================================================
    // COMMIT
    // ========================================================

    $pdo->commit();

    // ========================================================
    // REDIRECT
    // ========================================================

    header(
        "Location: unit-masuk.php?success=1"
    );

    exit;

} catch (PDOException $e) {

    // ========================================================
    // ROLLBACK
    // ========================================================

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // ========================================================
    // LOG ERROR DATABASE
    // ========================================================

    error_log(
        "proses-masuk.php PDO error: " .
        $e->getMessage()
    );

    http_response_code(500);

    die(
        "Terjadi kesalahan saat memproses unit masuk."
    );

} catch (Throwable $e) {

    // ========================================================
    // ROLLBACK
    // ========================================================

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // ========================================================
    // LOG ERROR
    // ========================================================

    error_log(
        "proses-masuk.php error: " .
        $e->getMessage()
    );

    http_response_code(500);

    die(
        "Terjadi kesalahan saat memproses unit masuk."
    );
}