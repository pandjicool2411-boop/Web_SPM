<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

/*
|--------------------------------------------------------------------------
| Pastikan request menggunakan POST
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
    $_SESSION['transfer_error'] = 'Token keamanan tidak valid.';
    header("Location: transfer.php");
    exit;
}

$transfer_id = (int) ($_POST['transfer_id'] ?? 0);

if ($transfer_id <= 0) {
    $_SESSION['transfer_error'] = 'Transfer tidak valid.';
    header("Location: transfer.php");
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
        SELECT
            ut.*,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.owner_branch_id,
            u.status AS unit_status,
            fb.nama_cabang AS from_branch_name,
            tb.nama_cabang AS to_branch_name
        FROM unit_transfers ut
        INNER JOIN units u
            ON u.id = ut.unit_id
        INNER JOIN branches fb
            ON fb.id = ut.from_branch_id
        INNER JOIN branches tb
            ON tb.id = ut.to_branch_id
        WHERE ut.id = ?
        FOR UPDATE
    ");

    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();

    if (!$transfer) {
        throw new Exception('Data transfer tidak ditemukan.');
    }

    /*
    |--------------------------------------------------------------------------
    | Hanya cabang tujuan yang boleh mengonfirmasi
    |--------------------------------------------------------------------------
    */
    if ((int) $transfer['to_branch_id'] !== (int) user_branch_id()) {
        throw new Exception(
            'Anda tidak memiliki akses untuk mengonfirmasi transfer ini.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status harus SIAP_DI_KONFIRMASI
    |--------------------------------------------------------------------------
    */
    if ($transfer['status'] !== 'SIAP_DI_KONFIRMASI') {

        if ($transfer['status'] === 'PENDING') {
            throw new Exception(
                'Transfer belum discan oleh cabang asal.'
            );
        }

        if ($transfer['status'] === 'COMPLETED') {
            throw new Exception(
                'Transfer ini sudah selesai.'
            );
        }

        if ($transfer['status'] === 'REJECTED') {
            throw new Exception(
                'Transfer ini sudah ditolak.'
            );
        }

        throw new Exception(
            'Transfer tidak dapat dikonfirmasi dengan status saat ini.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan transfer sudah benar-benar discan
    |--------------------------------------------------------------------------
    */
    if (empty($transfer['scanned_by']) || empty($transfer['scanned_at'])) {
        throw new Exception(
            'Transfer belum memiliki data scan dari cabang asal.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lock unit
    |--------------------------------------------------------------------------
    */
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
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $transfer['unit_id']
    ]);

    $unit = $stmt->fetch();

    if (!$unit) {
        throw new Exception('Unit transfer tidak ditemukan.');
    }

    /*
    |--------------------------------------------------------------------------
    | Pastikan unit masih dimiliki cabang asal
    |--------------------------------------------------------------------------
    */
    if ((int) $unit['owner_branch_id'] !== (int) $transfer['from_branch_id']) {
        throw new Exception(
            'Kepemilikan unit sudah berubah sehingga transfer tidak dapat dilanjutkan.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unit harus masih tersedia
    |--------------------------------------------------------------------------
    */
    if ($unit['status'] !== 'TERSEDIA') {
        throw new Exception(
            'Unit tidak dapat diterima karena status unit bukan TERSEDIA.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update kepemilikan unit
    |--------------------------------------------------------------------------
    |
    | BARU DI SINI owner_branch_id berubah.
    |
    */
    $stmt = $pdo->prepare("
        UPDATE units
        SET
            owner_branch_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $transfer['to_branch_id'],
        $unit['id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Update status transfer
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        UPDATE unit_transfers
        SET
            approved_by = ?,
            approved_at = NOW(),
            completed_at = NOW(),
            status = 'COMPLETED'
        WHERE id = ?
    ");

    $stmt->execute([
        user_id(),
        $transfer_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | Riwayat di cabang tujuan
    |--------------------------------------------------------------------------
    */
    $description =
        'Unit ' . $unit['kode_unit'] .
        ' diterima dari ' .
        $transfer['from_branch_name'] .
        ' dan kepemilikan unit berpindah ke ' .
        $transfer['to_branch_name'] . '.';

    $stmt = $pdo->prepare("
        INSERT INTO unit_history
        (
            unit_id,
            performed_by,
            branch_id,
            action,
            description,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'TRANSFER_MASUK',
            ?,
            NOW()
        )
    ");

    $stmt->execute([
        $unit['id'],
        user_id(),
        user_branch_id(),
        $description
    ]);

    /*
    |--------------------------------------------------------------------------
    | Riwayat tambahan di cabang asal
    |--------------------------------------------------------------------------
    |
    | Dicatat agar histori cabang asal juga menunjukkan
    | bahwa unit sudah keluar dari kepemilikannya.
    |
    */
    $description_asal =
        'Unit ' . $unit['kode_unit'] .
        ' telah diterima oleh ' .
        $transfer['to_branch_name'] .
        '. Transfer selesai.';

    $stmt = $pdo->prepare("
        INSERT INTO unit_history
        (
            unit_id,
            performed_by,
            branch_id,
            action,
            description,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'TRANSFER_SELESAI',
            ?,
            NOW()
        )
    ");

    $stmt->execute([
        $unit['id'],
        user_id(),
        $transfer['from_branch_id'],
        $description_asal
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
        ' berhasil dikonfirmasi. Kepemilikan unit sekarang berada di cabang ' .
        $transfer['to_branch_name'] .
        '.';

    header("Location: transfer.php");
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['transfer_error'] = $e->getMessage();

    header("Location: transfer.php");
    exit;
}