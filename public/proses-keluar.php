<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

/*
|--------------------------------------------------------------------------
| Hanya POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: scan-keluar.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (!verify_csrf($_POST['csrf_token'] ?? '')) {

    http_response_code(403);

    die("Permintaan tidak valid.");
}


/*
|--------------------------------------------------------------------------
| Ambil data
|--------------------------------------------------------------------------
*/

$renterCode = trim($_POST['renter_code'] ?? '');

$unitIds = $_POST['unit_ids'] ?? [];


/*
|--------------------------------------------------------------------------
| Validasi kode penyewa
|--------------------------------------------------------------------------
*/

if ($renterCode === '') {

    $_SESSION['scan_keluar_error'] =
        'Kode penyewa wajib diisi.';

    header("Location: scan-keluar.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Validasi unit
|--------------------------------------------------------------------------
*/

if (!is_array($unitIds) || empty($unitIds)) {

    $_SESSION['scan_keluar_error'] =
        'Belum ada unit yang dipilih.';

    header("Location: scan-keluar.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Bersihkan ID unit
|--------------------------------------------------------------------------
*/

$unitIds = array_values(
    array_unique(
        array_filter(
            array_map('intval', $unitIds),
            function ($id) {
                return $id > 0;
            }
        )
    )
);


if (empty($unitIds)) {

    $_SESSION['scan_keluar_error'] =
        'Unit yang dipilih tidak valid.';

    header("Location: scan-keluar.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Data user
|--------------------------------------------------------------------------
*/

$branchId = user_branch_id();
$userId = user_id();


/*
|--------------------------------------------------------------------------
| Mulai transaksi
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Ambil dan kunci semua unit
    |--------------------------------------------------------------------------
    */

    $placeholders = implode(
        ',',
        array_fill(0, count($unitIds), '?')
    );


    $stmt = $pdo->prepare("
        SELECT
            id,
            kode_unit,
            nama_unit,
            kategori,
            jumlah,
            owner_branch_id,
            status
        FROM units
        WHERE id IN ($placeholders)
        FOR UPDATE
    ");

    $stmt->execute($unitIds);

    $units = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Pastikan semua unit ditemukan
    |--------------------------------------------------------------------------
    */

    if (count($units) !== count($unitIds)) {

        throw new Exception(
            'Ada unit yang tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cek satu per satu
    |--------------------------------------------------------------------------
    */

    foreach ($units as $unit) {


        /*
        |--------------------------------------------------------------------------
        | Unit harus milik cabang sendiri
        |--------------------------------------------------------------------------
        */

        if ((int) $unit['owner_branch_id'] !== (int) $branchId) {

            throw new Exception(
                'Unit ' .
                $unit['kode_unit'] .
                ' bukan milik cabang Anda.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Unit harus tersedia
        |--------------------------------------------------------------------------
        */

        if ($unit['status'] !== 'TERSEDIA') {

            throw new Exception(
                'Unit ' .
                $unit['kode_unit'] .
                ' tidak tersedia.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cegah bentrok dengan transfer aktif
        |--------------------------------------------------------------------------
        */

        $stmtTransfer = $pdo->prepare("
            SELECT id, status
            FROM unit_transfers
            WHERE unit_id = ?
              AND status IN (
                  'PENDING',
                  'SIAP_DI_KONFIRMASI'
              )
            LIMIT 1
            FOR UPDATE
        ");

        $stmtTransfer->execute([
            $unit['id']
        ]);

        $activeTransfer = $stmtTransfer->fetch();


        if ($activeTransfer) {

            throw new Exception(
                'Unit ' .
                $unit['kode_unit'] .
                ' sedang dalam proses transfer.'
            );
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Buat transaksi rental
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO rentals (
            branch_id,
            renter_code,
            checkout_by,
            checkout_at
        )
        VALUES (?, ?, ?, NOW())
    ");

    $stmt->execute([
        $branchId,
        $renterCode,
        $userId
    ]);


    $rentalId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Masukkan rental items
    |--------------------------------------------------------------------------
    */

    $stmtItem = $pdo->prepare("
        INSERT INTO rental_items (
            rental_id,
            unit_id,
            status,
            catatan
        )
        VALUES (?, ?, 'DISEWAKAN', NULL)
    ");


    /*
    |--------------------------------------------------------------------------
    | Update status unit
    |--------------------------------------------------------------------------
    */

    $stmtUnit = $pdo->prepare("
        UPDATE units
        SET
            status = 'DISEWAKAN',
            updated_at = NOW()
        WHERE id = ?
    ");


    /*
    |--------------------------------------------------------------------------
    | Riwayat
    |--------------------------------------------------------------------------
    */

    $stmtHistory = $pdo->prepare("
        INSERT INTO unit_history (
            unit_id,
            performed_by,
            branch_id,
            action,
            description,
            created_at
        )
        VALUES (?, ?, ?, 'KELUAR', ?, NOW())
    ");


    foreach ($units as $unit) {


        /*
        |--------------------------------------------------------------------------
        | Rental item
        |--------------------------------------------------------------------------
        */

        $stmtItem->execute([
            $rentalId,
            $unit['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Status unit
        |--------------------------------------------------------------------------
        */

        $stmtUnit->execute([
            $unit['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Riwayat
        |--------------------------------------------------------------------------
        */

        $description =
            'Unit ' .
            $unit['kode_unit'] .
            ' disewakan kepada penyewa ' .
            $renterCode .
            '. Transaksi #' .
            $rentalId;


        $stmtHistory->execute([
            $unit['id'],
            $userId,
            $branchId,
            $description
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Bersihkan session scan
    |--------------------------------------------------------------------------
    */

    unset($_SESSION['scan_keluar_units']);

    unset($_SESSION['scan_keluar_error']);


    /*
    |--------------------------------------------------------------------------
    | Redirect berhasil
    |--------------------------------------------------------------------------
    */

    header(
        "Location: cek-keluar.php?renter_code=" .
        urlencode($renterCode) .
        "&success=1"
    );

    exit;


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | Simpan pesan error
    |--------------------------------------------------------------------------
    */

    $_SESSION['scan_keluar_error'] =
        $e->getMessage();


    /*
    |--------------------------------------------------------------------------
    | Kembali ke halaman scan
    |--------------------------------------------------------------------------
    */

    header("Location: scan-keluar.php");

    exit;
}