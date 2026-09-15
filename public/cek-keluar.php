<?php

require_once "../config/auth.php";
require_once "../config/database.php";

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
| AMBIL QR TOKEN
|--------------------------------------------------------------------------
*/

$qr_token = trim($_GET["qr_token"] ?? "");

/*
|--------------------------------------------------------------------------
| VALIDASI QR TOKEN
|--------------------------------------------------------------------------
*/

if ($qr_token === "" || mb_strlen($qr_token) > 255) {

    json_response([
        "success" => false,
        "message" => "QR Code tidak valid."
    ], 400);
}

/*
|--------------------------------------------------------------------------
| CARI UNIT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            units.id,
            units.kode_unit,
            units.nama_unit,
            units.kategori,
            units.status,
            units.owner_branch_id,
            branches.nama_cabang
        FROM units

        INNER JOIN branches
            ON units.owner_branch_id = branches.id

        WHERE units.qr_token = ?

        LIMIT 1
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

        json_response([
            "success" => false,
            "message" => "Unit tidak ditemukan."
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK KEPEMILIKAN CABANG
    |--------------------------------------------------------------------------
    |
    | Checkout hanya boleh dilakukan oleh cabang
    | yang saat ini memiliki unit tersebut.
    |
    */

    $user_branch_id = (int) $_SESSION["branch_id"];
    $unit_branch_id = (int) $unit["owner_branch_id"];

    if ($unit_branch_id !== $user_branch_id) {

        json_response([
            "success" => true,
            "can_checkout" => false,
            "unit" => [
                "id" => (int) $unit["id"],
                "kode_unit" => $unit["kode_unit"],
                "nama_unit" => $unit["nama_unit"],
                "kategori" => $unit["kategori"],
                "status" => $unit["status"],
                "nama_cabang" => $unit["nama_cabang"]
            ],
            "message" => "Unit ini bukan milik cabang Anda."
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK STATUS UNIT
    |--------------------------------------------------------------------------
    |
    | Unit hanya boleh dikeluarkan jika statusnya TERSEDIA.
    |
    */

    if ($unit["status"] !== "TERSEDIA") {

        json_response([
            "success" => true,
            "can_checkout" => false,
            "unit" => [
                "id" => (int) $unit["id"],
                "kode_unit" => $unit["kode_unit"],
                "nama_unit" => $unit["nama_unit"],
                "kategori" => $unit["kategori"],
                "status" => $unit["status"],
                "nama_cabang" => $unit["nama_cabang"]
            ],
            "message" => "Unit sedang disewakan."
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UNIT BOLEH DIKELUARKAN
    |--------------------------------------------------------------------------
    */

    json_response([
        "success" => true,
        "can_checkout" => true,
        "unit" => [
            "id" => (int) $unit["id"],
            "kode_unit" => $unit["kode_unit"],
            "nama_unit" => $unit["nama_unit"],
            "kategori" => $unit["kategori"],
            "status" => $unit["status"],
            "nama_cabang" => $unit["nama_cabang"]
        ],
        "message" => "Unit dapat disewakan."
    ]);

} catch (PDOException $e) {

    error_log(
        "cek-keluar.php database error: " .
        $e->getMessage()
    );

    json_response([
        "success" => false,
        "message" => "Terjadi kesalahan pada sistem."
    ], 500);
}