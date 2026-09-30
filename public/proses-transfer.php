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
    header("Location: transfer.php");
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

$unitId = (int) ($_POST['unit_id'] ?? 0);
$targetBranchId = (int) ($_POST['target_branch_id'] ?? 0);

$branchId = user_branch_id();
$userId = user_id();


/*
|--------------------------------------------------------------------------
| Validasi
|--------------------------------------------------------------------------
*/

if ($unitId <= 0 || $targetBranchId <= 0) {

    $_SESSION['transfer_error'] =
        'Data transfer tidak valid.';

    header("Location: transfer.php");

    exit;
}


if ($targetBranchId === (int) $branchId) {

    $_SESSION['transfer_error'] =
        'Unit tidak dapat ditransfer ke cabang yang sama.';

    header("Location: transfer.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Proses transfer
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Kunci unit
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            kode_unit,
            nama_unit,
            owner_branch_id,
            status
        FROM units
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $unitId
    ]);

    $unit = $stmt->fetch();


    if (!$unit) {

        throw new Exception(
            'Unit tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Unit harus milik cabang sendiri
    |--------------------------------------------------------------------------
    */

    if ((int) $unit['owner_branch_id'] !== (int) $branchId) {

        throw new Exception(
            'Unit bukan milik cabang Anda.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Unit harus tersedia
    |--------------------------------------------------------------------------
    */

    if ($unit['status'] !== 'TERSEDIA') {

        throw new Exception(
            'Unit sedang disewa dan belum dapat ditransfer.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cek cabang tujuan
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            nama_cabang
        FROM branches
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $targetBranchId
    ]);

    $targetBranch = $stmt->fetch();


    if (!$targetBranch) {

        throw new Exception(
            'Cabang tujuan tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cek transfer aktif
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM unit_transfers
        WHERE unit_id = ?
          AND status IN (
              'PENDING',
              'SIAP_DI_KONFIRMASI'
          )
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $unitId
    ]);

    $activeTransfer = $stmt->fetch();


    if ($activeTransfer) {

        throw new Exception(
            'Unit sedang memiliki transfer aktif.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Buat transfer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO unit_transfers (
            unit_id,
            from_branch_id,
            to_branch_id,
            requested_by,
            scanned_by,
            approved_by,
            status,
            requested_at,
            scanned_at,
            approved_at,
            completed_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            NULL,
            NULL,
            'PENDING',
            NOW(),
            NULL,
            NULL,
            NULL
        )
    ");

    $stmt->execute([
        $unitId,
        $branchId,
        $targetBranchId,
        $userId
    ]);


    $transferId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Simpan riwayat
    |--------------------------------------------------------------------------
    */

    $description =
        'Transfer unit ' .
        $unit['kode_unit'] .
        ' ke cabang ' .
        $targetBranch['nama_cabang'] .
        '. Transfer #' .
        $transferId .
        ' dibuat dan menunggu scan QR.';


    $stmt = $pdo->prepare("
        INSERT INTO unit_history (
            unit_id,
            performed_by,
            branch_id,
            action,
            description,
            created_at
        )
        VALUES (?, ?, ?, 'TRANSFER', ?, NOW())
    ");

    $stmt->execute([
        $unitId,
        $userId,
        $branchId,
        $description
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    $_SESSION['transfer_success'] =
        'Transfer unit ' .
        $unit['kode_unit'] .
        ' berhasil dibuat. Silakan scan QR unit.';


    header("Location: transfer.php");

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


    $_SESSION['transfer_error'] =
        $e->getMessage();


    header("Location: transfer.php");

    exit;
}