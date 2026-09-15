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

if ($qr_token === "") {

    json_response([
        "success" => false,
        "message" => "QR Code tidak valid."
    ], 400);
}

if (mb_strlen($qr_token) > 255) {

    json_response([
        "success" => false,
        "message" => "QR Code tidak valid."
    ], 400);
}

/*
|--------------------------------------------------------------------------
| CARI UNIT
|--------------------------------------------------------------------------
|
| Scan Cek bersifat READ-ONLY.
|
| User boleh mengecek unit dari cabang mana pun.
| Karena itu TIDAK ada filter branch_id di query.
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            units.kode_unit,
            units.nama_unit,
            units.kategori,
            units.status,
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
    | RESPONSE BERHASIL
    |--------------------------------------------------------------------------
    */

    json_response([
        "success" => true,
        "unit" => [
            "kode_unit"   => $unit["kode_unit"],
            "nama_unit"   => $unit["nama_unit"],
            "kategori"    => $unit["kategori"],
            "status"      => $unit["status"],
            "nama_cabang" => $unit["nama_cabang"]
        ]
    ]);

} catch (PDOException $e) {

    error_log(
        "cek-unit.php database error: " .
        $e->getMessage()
    );

    json_response([
        "success" => false,
        "message" => "Terjadi kesalahan pada sistem."
    ], 500);
}