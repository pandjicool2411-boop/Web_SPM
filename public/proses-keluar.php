<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/*
|--------------------------------------------------------------------------
| HELPER RESPONSE
|--------------------------------------------------------------------------
*/

function json_response($data, $status = 200)
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| HANYA IZINKAN POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    json_response([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ], 405);
}

/*
|--------------------------------------------------------------------------
| CEK CSRF
|--------------------------------------------------------------------------
*/

verify_csrf();

/*
|--------------------------------------------------------------------------
| AMBIL SESSION USER
|--------------------------------------------------------------------------
*/

$user_id = (int) $_SESSION["user_id"];
$user_branch_id = (int) $_SESSION["branch_id"];

/*
|--------------------------------------------------------------------------
| AMBIL DATA FORM
|--------------------------------------------------------------------------
*/

$qr_token = trim($_POST["qr_token"] ?? "");
$renter_code = trim($_POST["renter_code"] ?? "");

/*
|--------------------------------------------------------------------------
| VALIDASI QR TOKEN
|--------------------------------------------------------------------------
*/

if (
    $qr_token === "" ||
    mb_strlen($qr_token) > 255
) {

    json_response([
        "success" => false,
        "message" => "QR Code tidak valid."
    ], 400);
}

/*
|--------------------------------------------------------------------------
| VALIDASI KODE PENYEWA
|--------------------------------------------------------------------------
|
| Harus tepat 4 angka.
|
*/

if (!preg_match("/^[0-9]{4}$/", $renter_code)) {

    json_response([
        "success" => false,
        "message" => "Kode penyewa harus terdiri dari 4 angka."
    ], 400);
}

/*
|--------------------------------------------------------------------------
| PROSES UNIT KELUAR
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | CARI UNIT + LOCK BARIS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.owner_branch_id,
            u.status,
            b.nama_cabang
        FROM units u

        INNER JOIN branches b
            ON b.id = u.owner_branch_id

        WHERE u.qr_token = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $qr_token
    ]);

    $unit = $stmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | UNIT TIDAK DITEMUKAN
    |--------------------------------------------------------------------------
    */

    if (!$unit) {

        $pdo->rollBack();

        json_response([
            "success" => false,
            "message" => "Unit tidak ditemukan."
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK KEPEMILIKAN CABANG
    |--------------------------------------------------------------------------
    */

    if (
        (int) $unit["owner_branch_id"]
        !==
        $user_branch_id
    ) {

        $pdo->rollBack();

        json_response([
            "success" => false,
            "message" => "Unit ini bukan milik cabang Anda."
        ], 403);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK STATUS UNIT
    |--------------------------------------------------------------------------
    */

    if ($unit["status"] !== "TERSEDIA") {

        $pdo->rollBack();

        json_response([
            "success" => false,
            "message" => "Unit sedang disewakan."
        ], 409);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK RENTAL AKTIF
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM rentals
        WHERE unit_id = ?
          AND status = 'AKTIF'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $unit["id"]
    ]);

    $active_rental = $stmt->fetch();

    if ($active_rental) {

        $pdo->rollBack();

        json_response([
            "success" => false,
            "message" =>
                "Unit masih memiliki transaksi rental aktif."
        ], 409);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS UNIT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE units
        SET status = 'DISEWAKAN'
        WHERE id = ?
          AND owner_branch_id = ?
          AND status = 'TERSEDIA'
    ");

    $stmt->execute([
        $unit["id"],
        $user_branch_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | PASTIKAN UPDATE BERHASIL
    |--------------------------------------------------------------------------
    */

    if ($stmt->rowCount() !== 1) {

        throw new RuntimeException(
            "Status unit gagal diperbarui."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN TRANSAKSI RENTAL
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO rentals
        (
            unit_id,
            renter_code,
            checkout_by,
            checkout_at,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NOW(),
            'AKTIF'
        )
    ");

    $stmt->execute([
        $unit["id"],
        $renter_code,
        $user_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | SIMPAN HISTORY
    |--------------------------------------------------------------------------
    */

    $description =
        "Unit " .
        $unit["kode_unit"] .
        " berhasil dikeluarkan dan disewakan.";

    $stmt = $pdo->prepare("
        INSERT INTO unit_history
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
            'UNIT_KELUAR',
            ?
        )
    ");

    $stmt->execute([
        $unit["id"],
        $user_id,
        $user_branch_id,
        $description
    ]);

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    json_response([
        "success" => true,
        "message" =>
            "Unit " .
            $unit["kode_unit"] .
            " berhasil dikeluarkan dan status menjadi DISEWAKAN."
    ]);

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "proses-keluar.php runtime error: " .
        $e->getMessage()
    );

    json_response([
        "success" => false,
        "message" => "Proses unit keluar gagal."
    ], 500);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "proses-keluar.php database error: " .
        $e->getMessage()
    );

    json_response([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ], 500);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "proses-keluar.php unexpected error: " .
        $e->getMessage()
    );

    json_response([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ], 500);
}