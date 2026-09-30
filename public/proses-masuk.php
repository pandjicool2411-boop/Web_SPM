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
    header("Location: unit-masuk.php");
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

$itemId = (int) ($_POST['item_id'] ?? 0);
$action = $_POST['action'] ?? '';


if ($itemId <= 0) {

    $_SESSION['unit_masuk_error'] =
        'Data unit tidak valid.';

    header("Location: unit-masuk.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Validasi action
|--------------------------------------------------------------------------
*/

if (!in_array($action, ['selesai', 'batalkan'], true)) {

    $_SESSION['unit_masuk_error'] =
        'Tindakan tidak valid.';

    header("Location: unit-masuk.php");

    exit;
}


$branchId = user_branch_id();
$userId = user_id();


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Ambil rental item + unit + rental
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            ri.id AS rental_item_id,
            ri.rental_id,
            ri.unit_id,
            ri.status AS item_status,

            r.branch_id,
            r.renter_code,

            u.kode_unit,
            u.nama_unit,
            u.status AS unit_status,
            u.owner_branch_id

        FROM rental_items ri

        INNER JOIN rentals r
            ON r.id = ri.rental_id

        INNER JOIN units u
            ON u.id = ri.unit_id

        WHERE ri.id = ?

        FOR UPDATE
    ");

    $stmt->execute([
        $itemId
    ]);

    $item = $stmt->fetch();


    if (!$item) {

        throw new Exception(
            'Data unit tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Pastikan transaksi milik cabang sendiri
    |--------------------------------------------------------------------------
    */

    if ((int) $item['branch_id'] !== (int) $branchId) {

        throw new Exception(
            'Anda tidak memiliki akses ke transaksi ini.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTION: SELESAI
    |--------------------------------------------------------------------------
    */

    if ($action === 'selesai') {


        /*
        |--------------------------------------------------------------------------
        | Harus masih DISEWAKAN
        |--------------------------------------------------------------------------
        */

        if ($item['item_status'] !== 'DISEWAKAN') {

            throw new Exception(
                'Unit ini sudah tidak berstatus disewa.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Kunci unit
        |--------------------------------------------------------------------------
        */

        $stmtUnit = $pdo->prepare("
            SELECT
                id,
                kode_unit,
                status,
                owner_branch_id
            FROM units
            WHERE id = ?
            FOR UPDATE
        ");

        $stmtUnit->execute([
            $item['unit_id']
        ]);

        $unit = $stmtUnit->fetch();


        if (!$unit) {

            throw new Exception(
                'Unit tidak ditemukan.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan unit milik cabang
        |--------------------------------------------------------------------------
        */

        if ((int) $unit['owner_branch_id'] !== (int) $branchId) {

            throw new Exception(
                'Unit bukan milik cabang Anda.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update rental item
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE rental_items
            SET
                status = 'SELESAI',
                returned_by = ?,
                returned_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $userId,
            $itemId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Update unit
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE units
            SET
                status = 'TERSEDIA',
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $item['unit_id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Riwayat
        |--------------------------------------------------------------------------
        */

        $description =
            'Unit ' .
            $unit['kode_unit'] .
            ' kembali dari penyewa ' .
            $item['renter_code'] .
            '. Transaksi #' .
            $item['rental_id'];


        $stmt = $pdo->prepare("
            INSERT INTO unit_history (
                unit_id,
                performed_by,
                branch_id,
                action,
                description,
                created_at
            )
            VALUES (?, ?, ?, 'MASUK', ?, NOW())
        ");

        $stmt->execute([
            $item['unit_id'],
            $userId,
            $branchId,
            $description
        ]);


        $pdo->commit();


        $_SESSION['unit_masuk_success'] =
            'Unit ' .
            $unit['kode_unit'] .
            ' berhasil ditandai sebagai selesai.';


        header("Location: unit-masuk.php");

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ACTION: BATALKAN
    |--------------------------------------------------------------------------
    */

    if ($action === 'batalkan') {


        /*
        |--------------------------------------------------------------------------
        | Harus sudah SELESAI
        |--------------------------------------------------------------------------
        */

        if ($item['item_status'] !== 'SELESAI') {

            throw new Exception(
                'Unit ini belum berstatus selesai.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Kunci unit
        |--------------------------------------------------------------------------
        */

        $stmtUnit = $pdo->prepare("
            SELECT
                id,
                kode_unit,
                status,
                owner_branch_id
            FROM units
            WHERE id = ?
            FOR UPDATE
        ");

        $stmtUnit->execute([
            $item['unit_id']
        ]);

        $unit = $stmtUnit->fetch();


        if (!$unit) {

            throw new Exception(
                'Unit tidak ditemukan.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan unit milik cabang sendiri
        |--------------------------------------------------------------------------
        */

        if ((int) $unit['owner_branch_id'] !== (int) $branchId) {

            throw new Exception(
                'Unit bukan milik cabang Anda.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Kembalikan rental item ke DISEWAKAN
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE rental_items
            SET
                status = 'DISEWAKAN',
                returned_by = NULL,
                returned_at = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $itemId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Kembalikan status unit
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE units
            SET
                status = 'DISEWAKAN',
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $item['unit_id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Riwayat
        |--------------------------------------------------------------------------
        */

        $description =
            'Status masuk unit ' .
            $unit['kode_unit'] .
            ' dibatalkan. Unit dikembalikan menjadi DISEWAKAN. ' .
            'Transaksi #' .
            $item['rental_id'];


        $stmt = $pdo->prepare("
            INSERT INTO unit_history (
                unit_id,
                performed_by,
                branch_id,
                action,
                description,
                created_at
            )
            VALUES (?, ?, ?, 'BATAL_MASUK', ?, NOW())
        ");

        $stmt->execute([
            $item['unit_id'],
            $userId,
            $branchId,
            $description
        ]);


        $pdo->commit();


        $_SESSION['unit_masuk_success'] =
            'Status unit ' .
            $unit['kode_unit'] .
            ' berhasil dibatalkan.';


        header("Location: unit-masuk.php");

        exit;
    }


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    $_SESSION['unit_masuk_error'] =
        $e->getMessage();


    header("Location: unit-masuk.php");

    exit;
}