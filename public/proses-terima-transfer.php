<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

header("Content-Type: application/json; charset=UTF-8");

/*
|--------------------------------------------------------------------------
| Hanya POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

verify_csrf();

/*
|--------------------------------------------------------------------------
| Ambil session
|--------------------------------------------------------------------------
*/

$user_id = (int) $_SESSION["user_id"];
$branch_id = (int) $_SESSION["branch_id"];

/*
|--------------------------------------------------------------------------
| Ambil input
|--------------------------------------------------------------------------
*/

$transfer_id = $_POST["transfer_id"] ?? "";
$qr_token = trim($_POST["qr_token"] ?? "");

/*
|--------------------------------------------------------------------------
| Validasi transfer ID
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit((string) $transfer_id) ||
    (int) $transfer_id <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID transfer tidak valid."
    ]);

    exit;
}

$transfer_id = (int) $transfer_id;

/*
|--------------------------------------------------------------------------
| Validasi QR
|--------------------------------------------------------------------------
*/

if ($qr_token === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "QR Code tidak boleh kosong."
    ]);

    exit;
}

if (strlen($qr_token) > 255) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "QR Code tidak valid."
    ]);

    exit;
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock transfer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM unit_transfers
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $transfer_id
    ]);

    $transfer = $stmt->fetch();

    if (!$transfer) {

        throw new Exception(
            "Transfer tidak ditemukan."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan cabang login adalah cabang tujuan
    |--------------------------------------------------------------------------
    */

    if (
        (int) $transfer["to_branch_id"] !== $branch_id
    ) {

        throw new Exception(
            "Transfer ini bukan untuk cabang Anda."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan transfer sudah APPROVED
    |--------------------------------------------------------------------------
    */

    if ($transfer["status"] !== "APPROVED") {

        throw new Exception(
            "Transfer belum disetujui atau sudah selesai."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ambil unit berdasarkan QR
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM units
        WHERE qr_token = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $qr_token
    ]);

    $unit = $stmt->fetch();

    if (!$unit) {

        throw new Exception(
            "QR Code tidak terdaftar."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan QR adalah unit yang benar
    |--------------------------------------------------------------------------
    */

    if (
        (int) $unit["id"] !==
        (int) $transfer["unit_id"]
    ) {

        throw new Exception(
            "QR Code tidak sesuai dengan unit yang sedang ditransfer."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan unit masih dimiliki cabang asal
    |--------------------------------------------------------------------------
    */

    if (
        (int) $unit["owner_branch_id"] !==
        (int) $transfer["from_branch_id"]
    ) {

        throw new Exception(
            "Kepemilikan unit sudah berubah."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unit harus tersedia
    |--------------------------------------------------------------------------
    */

    if ($unit["status"] !== "TERSEDIA") {

        throw new Exception(
            "Unit sedang disewakan dan belum dapat diterima."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pindahkan kepemilikan
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE units
        SET owner_branch_id = ?
        WHERE id = ?
          AND owner_branch_id = ?
          AND status = 'TERSEDIA'
    ");

    $stmt->execute([
        $branch_id,
        (int) $unit["id"],
        (int) $transfer["from_branch_id"]
    ]);

    if ($stmt->rowCount() !== 1) {

        throw new Exception(
            "Kepemilikan unit gagal diperbarui."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Selesaikan transfer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE unit_transfers
        SET
            status = 'COMPLETED',
            completed_at = NOW()
        WHERE id = ?
          AND status = 'APPROVED'
    ");

    $stmt->execute([
        $transfer_id
    ]);

    if ($stmt->rowCount() !== 1) {

        throw new Exception(
            "Status transfer gagal diperbarui."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan history
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO unit_history
        (
            unit_id,
            performed_by,
            branch_id,
            action,
            description
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        (int) $unit["id"],
        $user_id,
        $branch_id,
        "TRANSFER_SELESAI",
        "Unit berhasil diterima dan kepemilikan berpindah ke cabang tujuan."
    ]);

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Transfer berhasil diselesaikan."
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Jangan tampilkan error database/internal ke user
    |--------------------------------------------------------------------------
    */

    $known_messages = [
        "Transfer tidak ditemukan.",
        "Transfer ini bukan untuk cabang Anda.",
        "Transfer belum disetujui atau sudah selesai.",
        "QR Code tidak terdaftar.",
        "QR Code tidak sesuai dengan unit yang sedang ditransfer.",
        "Kepemilikan unit sudah berubah.",
        "Unit sedang disewakan dan belum dapat diterima.",
        "Kepemilikan unit gagal diperbarui.",
        "Status transfer gagal diperbarui."
    ];

    if (in_array($e->getMessage(), $known_messages, true)) {

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);

    } else {

        error_log(
            "proses-terima-transfer.php: " .
            $e->getMessage()
        );

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Terjadi kesalahan pada server."
        ]);
    }
}