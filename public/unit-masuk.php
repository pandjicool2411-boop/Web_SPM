<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$branchId = user_branch_id();
$userId   = user_id();

$success = '';
$error   = '';

/*
|--------------------------------------------------------------------------
| PESAN DARI REDIRECT
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'selesai') {
        $success = 'Unit berhasil ditandai sebagai masuk.';
    }

    elseif ($_GET['success'] === 'dibatalkan') {
        $success = 'Status selesai berhasil dibatalkan. Unit kembali menjadi disewakan.';
    }
}

if (isset($_SESSION['unit_masuk_error'])) {

    $error = $_SESSION['unit_masuk_error'];

    unset($_SESSION['unit_masuk_error']);
}


/*
|--------------------------------------------------------------------------
| PROSES POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CEK CSRF
    |--------------------------------------------------------------------------
    */

    if (!verify_csrf($token)) {

        $_SESSION['unit_masuk_error'] =
            'Token keamanan tidak valid. Silakan coba lagi.';

        header('Location: unit-masuk.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDASI RENTAL ITEM
    |--------------------------------------------------------------------------
    */

    $rentalItemId = (int)($_POST['rental_item_id'] ?? 0);

    if ($rentalItemId <= 0) {

        $_SESSION['unit_masuk_error'] =
            'Data unit tidak valid.';

        header('Location: unit-masuk.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UNIT MASUK / SELESAI
    |--------------------------------------------------------------------------
    */

    if ($action === 'selesai') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | AMBIL RENTAL ITEM
            |--------------------------------------------------------------------------
            | FOR UPDATE digunakan agar data tidak berubah bersamaan.
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    ri.id AS rental_item_id,
                    ri.rental_id,
                    ri.unit_id,
                    ri.status AS rental_item_status,

                    r.branch_id,
                    r.renter_code,
                    r.checkout_by,
                    r.checkout_at,

                    u.kode_unit,
                    u.nama_unit,
                    u.owner_branch_id,
                    u.status AS unit_status

                FROM rental_items ri

                INNER JOIN rentals r
                    ON r.id = ri.rental_id

                INNER JOIN units u
                    ON u.id = ri.unit_id

                WHERE ri.id = ?

                FOR UPDATE
            ");

            $stmt->execute([
                $rentalItemId
            ]);

            $item = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | CEK DATA
            |--------------------------------------------------------------------------
            */

            if (!$item) {

                throw new Exception(
                    'Data transaksi unit tidak ditemukan.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PASTIKAN TRANSAKSI MILIK CABANG LOGIN
            |--------------------------------------------------------------------------
            */

            if ((int)$item['branch_id'] !== (int)$branchId) {

                throw new Exception(
                    'Anda tidak memiliki akses ke transaksi unit ini.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PASTIKAN BELUM SELESAI
            |--------------------------------------------------------------------------
            */

            if ($item['rental_item_status'] === 'SELESAI') {

                throw new Exception(
                    'Unit ini sudah ditandai sebagai selesai.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PASTIKAN STATUS UNIT DISEWAKAN
            |--------------------------------------------------------------------------
            */

            if ($item['unit_status'] !== 'DISEWAKAN') {

                throw new Exception(
                    'Status unit tidak sesuai untuk proses pengembalian.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE RENTAL ITEM
            |--------------------------------------------------------------------------
            */

            $stmtUpdateItem = $pdo->prepare("
                UPDATE rental_items
                SET
                    status = 'SELESAI',
                    returned_by = ?,
                    returned_at = NOW()
                WHERE id = ?
            ");

            $stmtUpdateItem->execute([
                $userId,
                $rentalItemId
            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS UNIT
            |--------------------------------------------------------------------------
            */

            $stmtUpdateUnit = $pdo->prepare("
                UPDATE units
                SET
                    status = 'TERSEDIA',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmtUpdateUnit->execute([
                $item['unit_id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | SIMPAN HISTORY
            |--------------------------------------------------------------------------
            */

            $description =
                'Unit ' .
                $item['kode_unit'] .
                ' telah masuk kembali dari penyewaan dengan kode penyewa ' .
                $item['renter_code'] .
                '. Transaksi rental #' .
                $item['rental_id'] .
                '.';


            $stmtHistory = $pdo->prepare("
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
                    ?,
                    ?,
                    NOW()
                )
            ");

            $stmtHistory->execute([
                $item['unit_id'],
                $userId,
                $branchId,
                'MASUK',
                $description
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: unit-masuk.php?success=selesai'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['unit_masuk_error'] =
                'Unit gagal diproses: ' .
                $e->getMessage();

            header('Location: unit-masuk.php');
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BATALKAN STATUS SELESAI
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'batalkan') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | AMBIL RENTAL ITEM
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    ri.id AS rental_item_id,
                    ri.rental_id,
                    ri.unit_id,
                    ri.status AS rental_item_status,

                    r.branch_id,
                    r.renter_code,

                    u.kode_unit,
                    u.nama_unit,
                    u.status AS unit_status

                FROM rental_items ri

                INNER JOIN rentals r
                    ON r.id = ri.rental_id

                INNER JOIN units u
                    ON u.id = ri.unit_id

                WHERE ri.id = ?

                FOR UPDATE
            ");

            $stmt->execute([
                $rentalItemId
            ]);

            $item = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | CEK DATA
            |--------------------------------------------------------------------------
            */

            if (!$item) {

                throw new Exception(
                    'Data transaksi unit tidak ditemukan.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CEK CABANG
            |--------------------------------------------------------------------------
            */

            if ((int)$item['branch_id'] !== (int)$branchId) {

                throw new Exception(
                    'Anda tidak memiliki akses ke transaksi unit ini.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | HARUS SELESAI UNTUK DIBATALKAN
            |--------------------------------------------------------------------------
            */

            if ($item['rental_item_status'] !== 'SELESAI') {

                throw new Exception(
                    'Unit ini belum berstatus selesai.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE RENTAL ITEM KEMBALI KE DISEWAKAN
            |--------------------------------------------------------------------------
            */

            $stmtUpdateItem = $pdo->prepare("
                UPDATE rental_items
                SET
                    status = 'DISEWAKAN',
                    returned_by = NULL,
                    returned_at = NULL
                WHERE id = ?
            ");

            $stmtUpdateItem->execute([
                $rentalItemId
            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE UNIT KEMBALI KE DISEWAKAN
            |--------------------------------------------------------------------------
            */

            $stmtUpdateUnit = $pdo->prepare("
                UPDATE units
                SET
                    status = 'DISEWAKAN',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmtUpdateUnit->execute([
                $item['unit_id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | SIMPAN HISTORY
            |--------------------------------------------------------------------------
            */

            $description =
                'Status masuk unit ' .
                $item['kode_unit'] .
                ' dibatalkan. Unit dikembalikan menjadi DISEWAKAN. ' .
                'Transaksi rental #' .
                $item['rental_id'] .
                '.';


            $stmtHistory = $pdo->prepare("
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
                    ?,
                    ?,
                    NOW()
                )
            ");

            $stmtHistory->execute([
                $item['unit_id'],
                $userId,
                $branchId,
                'BATAL_MASUK',
                $description
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: unit-masuk.php?success=dibatalkan'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['unit_masuk_error'] =
                'Status unit gagal dibatalkan: ' .
                $e->getMessage();

            header('Location: unit-masuk.php');
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA UNIT DISEWAKAN
|--------------------------------------------------------------------------
|
| Menampilkan unit yang sedang disewakan pada cabang login.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ri.id AS rental_item_id,
        ri.rental_id,
        ri.unit_id,
        ri.status AS rental_item_status,

        r.renter_code,
        r.checkout_at,

        u.kode_unit,
        u.nama_unit,
        u.kategori,
        u.jumlah,
        u.status AS unit_status

    FROM rental_items ri

    INNER JOIN rentals r
        ON r.id = ri.rental_id

    INNER JOIN units u
        ON u.id = ri.unit_id

    WHERE r.branch_id = ?
      AND ri.status = 'DISEWAKAN'

    ORDER BY
        r.checkout_at DESC,
        ri.id DESC
");

$stmt->execute([
    $branchId
]);

$activeRentals = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| AMBIL DATA UNIT SELESAI
|--------------------------------------------------------------------------
|
| Ditampilkan sebagai riwayat singkat agar status selesai yang salah
| masih bisa dibatalkan.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ri.id AS rental_item_id,
        ri.rental_id,
        ri.unit_id,
        ri.status AS rental_item_status,

        r.renter_code,
        r.checkout_at,

        u.kode_unit,
        u.nama_unit,
        u.kategori,

        ri.returned_at

    FROM rental_items ri

    INNER JOIN rentals r
        ON r.id = ri.rental_id

    INNER JOIN units u
        ON u.id = ri.unit_id

    WHERE r.branch_id = ?
      AND ri.status = 'SELESAI'

    ORDER BY
        ri.returned_at DESC,
        ri.id DESC

    LIMIT 50
");

$stmt->execute([
    $branchId
]);

$completedRentals = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Unit Masuk</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 25px 20px 50px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 28px;
        }

        .back-btn {
            display: inline-block;
            text-decoration: none;
            background: #374151;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #1f2937;
        }

        .info-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .info-box h2 {
            margin: 0 0 8px;
        }

        .branch-info {
            color: #6b7280;
            font-size: 14px;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 21px;
        }

        .badge {
            background: #e5e7eb;
            color: #374151;
            border-radius: 20px;
            padding: 6px 11px;
            font-size: 13px;
            font-weight: bold;
        }

        .rental-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rental-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
        }

        .rental-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }

        .unit-code {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .unit-name {
            font-size: 15px;
            color: #374151;
            margin-bottom: 6px;
        }

        .meta {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .status {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-done {
            background: #dcfce7;
            color: #166534;
        }

        .action-area {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            border: none;
            cursor: pointer;
            padding: 11px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .empty {
            text-align: center;
            padding: 30px 15px;
            color: #6b7280;
        }

        .small-note {
            color: #6b7280;
            font-size: 13px;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        @media (max-width: 700px) {

            .container {
                padding: 15px 12px 35px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 23px;
            }

            .rental-top {
                flex-direction: column;
            }

            .status {
                align-self: flex-start;
            }

            .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- ==========================================================
         HEADER
    =========================================================== -->

    <div class="topbar">

        <h1>📦 Unit Masuk</h1>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Kembali ke Dashboard
        </a>

    </div>


    <!-- ==========================================================
         INFORMASI CABANG
    =========================================================== -->

    <div class="info-box">

        <h2>
            Cabang: <?= htmlspecialchars(user_branch_name()) ?>
        </h2>

        <div class="branch-info">
            Berikut adalah unit yang sedang disewakan
            dan unit yang baru saja diselesaikan.
        </div>

    </div>


    <!-- ==========================================================
         PESAN
    =========================================================== -->

    <?php if ($success !== ''): ?>

        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ==========================================================
         UNIT YANG MASIH DISEWAKAN
    =========================================================== -->

    <div class="section">

        <div class="section-title">

            <h2>
                Unit Sedang Disewakan
            </h2>

            <span class="badge">
                <?= count($activeRentals) ?> unit
            </span>

        </div>

        <div class="small-note">
            Klik <strong>Unit Masuk</strong> setelah unit benar-benar dikembalikan.
        </div>


        <?php if (count($activeRentals) === 0): ?>

            <div class="empty">
                Tidak ada unit yang sedang disewakan.
            </div>

        <?php else: ?>

            <div class="rental-list">

                <?php foreach ($activeRentals as $item): ?>

                    <div class="rental-card">

                        <div class="rental-top">

                            <div>

                                <div class="unit-code">
                                    <?= htmlspecialchars($item['kode_unit']) ?>
                                </div>

                                <div class="unit-name">
                                    <?= htmlspecialchars($item['nama_unit']) ?>
                                </div>

                                <div class="meta">

                                    Kategori:
                                    <?= htmlspecialchars($item['kategori']) ?>

                                    <br>

                                    Kode Penyewa:
                                    <strong>
                                        <?= htmlspecialchars($item['renter_code']) ?>
                                    </strong>

                                    <br>

                                    Transaksi:
                                    #<?= (int)$item['rental_id'] ?>

                                    <br>

                                    Keluar:
                                    <?= htmlspecialchars($item['checkout_at']) ?>

                                </div>

                            </div>


                            <span class="status">
                                DISEWAKAN
                            </span>

                        </div>


                        <div class="action-area">

                            <form
                                method="POST"
                                action="unit-masuk.php"
                                onsubmit="return confirm('Tandai unit ini sebagai sudah masuk?');"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="selesai"
                                >

                                <input
                                    type="hidden"
                                    name="rental_item_id"
                                    value="<?= (int)$item['rental_item_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token()) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >
                                    ✓ Unit Masuk / Selesai
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>


    <!-- ==========================================================
         UNIT YANG SUDAH SELESAI
    =========================================================== -->

    <div class="section">

        <div class="section-title">

            <h2>
                Riwayat Unit Masuk
            </h2>

            <span class="badge">
                <?= count($completedRentals) ?>
            </span>

        </div>

        <div class="small-note">
            Jika tidak sengaja menekan selesai, gunakan tombol
            <strong>Batalkan Selesai</strong>.
        </div>


        <?php if (count($completedRentals) === 0): ?>

            <div class="empty">
                Belum ada unit yang diselesaikan.
            </div>

        <?php else: ?>

            <div class="rental-list">

                <?php foreach ($completedRentals as $item): ?>

                    <div class="rental-card">

                        <div class="rental-top">

                            <div>

                                <div class="unit-code">
                                    <?= htmlspecialchars($item['kode_unit']) ?>
                                </div>

                                <div class="unit-name">
                                    <?= htmlspecialchars($item['nama_unit']) ?>
                                </div>

                                <div class="meta">

                                    Kode Penyewa:
                                    <strong>
                                        <?= htmlspecialchars($item['renter_code']) ?>
                                    </strong>

                                    <br>

                                    Transaksi:
                                    #<?= (int)$item['rental_id'] ?>

                                    <br>

                                    Masuk:
                                    <?= htmlspecialchars($item['returned_at']) ?>

                                </div>

                            </div>


                            <span class="status status-done">
                                SELESAI
                            </span>

                        </div>


                        <div class="action-area">

                            <form
                                method="POST"
                                action="unit-masuk.php"
                                onsubmit="return confirm('Batalkan status selesai unit ini? Unit akan kembali menjadi DISEWAKAN.');"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="batalkan"
                                >

                                <input
                                    type="hidden"
                                    name="rental_item_id"
                                    value="<?= (int)$item['rental_item_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token()) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-warning"
                                >
                                    ↩ Batalkan Selesai
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>