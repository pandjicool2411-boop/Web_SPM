<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$branchId = user_branch_id();

$kodeUnit = trim($_GET['kode_unit'] ?? '');
$renterCode = trim($_GET['renter_code'] ?? '');

$transaction = null;
$error = '';

/*
|--------------------------------------------------------------------------
| Validasi pencarian
|--------------------------------------------------------------------------
*/

if ($kodeUnit !== '' || $renterCode !== '') {

    try {

        if ($kodeUnit !== '') {

            /*
            |--------------------------------------------------------------------------
            | Cari berdasarkan kode unit
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    r.id AS rental_id,
                    r.renter_code,
                    r.checkout_at,

                    u.id AS unit_id,
                    u.kode_unit,
                    u.nama_unit,
                    u.kategori,
                    u.jumlah,

                    ri.status AS item_status,

                    cu.nama AS checkout_nama,
                    b.nama_cabang

                FROM rental_items ri

                INNER JOIN rentals r
                    ON r.id = ri.rental_id

                INNER JOIN units u
                    ON u.id = ri.unit_id

                INNER JOIN users cu
                    ON cu.id = r.checkout_by

                INNER JOIN branches b
                    ON b.id = r.branch_id

                WHERE r.branch_id = ?
                  AND ri.status = 'DISEWAKAN'
                  AND u.kode_unit = ?

                ORDER BY r.checkout_at DESC

                LIMIT 1
            ");

            $stmt->execute([
                $branchId,
                $kodeUnit
            ]);

            $result = $stmt->fetch();

            if ($result) {

                $transaction = [
                    'rental_id' => $result['rental_id'],
                    'renter_code' => $result['renter_code'],
                    'checkout_at' => $result['checkout_at'],
                    'checkout_nama' => $result['checkout_nama'],
                    'nama_cabang' => $result['nama_cabang'],
                    'units' => []
                ];


                /*
                |--------------------------------------------------------------------------
                | Ambil semua unit dalam transaksi yang sama
                |--------------------------------------------------------------------------
                */

                $stmtItems = $pdo->prepare("
                    SELECT
                        u.id,
                        u.kode_unit,
                        u.nama_unit,
                        u.kategori,
                        u.jumlah,
                        ri.status

                    FROM rental_items ri

                    INNER JOIN units u
                        ON u.id = ri.unit_id

                    WHERE ri.rental_id = ?
                      AND ri.status = 'DISEWAKAN'

                    ORDER BY u.kode_unit ASC
                ");

                $stmtItems->execute([
                    $result['rental_id']
                ]);

                $transaction['units'] =
                    $stmtItems->fetchAll();

            } else {

                $error =
                    'Tidak ditemukan transaksi aktif untuk kode unit "' .
                    htmlspecialchars($kodeUnit) .
                    '".';
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Cari berdasarkan kode penyewa
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    r.id AS rental_id,
                    r.renter_code,
                    r.checkout_at,

                    cu.nama AS checkout_nama,
                    b.nama_cabang

                FROM rentals r

                INNER JOIN users cu
                    ON cu.id = r.checkout_by

                INNER JOIN branches b
                    ON b.id = r.branch_id

                WHERE r.branch_id = ?
                  AND r.renter_code = ?

                  AND EXISTS (
                      SELECT 1
                      FROM rental_items ri
                      WHERE ri.rental_id = r.id
                        AND ri.status = 'DISEWAKAN'
                  )

                ORDER BY r.checkout_at DESC

                LIMIT 1
            ");

            $stmt->execute([
                $branchId,
                $renterCode
            ]);

            $result = $stmt->fetch();

            if ($result) {

                $transaction = [
                    'rental_id' => $result['rental_id'],
                    'renter_code' => $result['renter_code'],
                    'checkout_at' => $result['checkout_at'],
                    'checkout_nama' => $result['checkout_nama'],
                    'nama_cabang' => $result['nama_cabang'],
                    'units' => []
                ];


                /*
                |--------------------------------------------------------------------------
                | Ambil semua unit transaksi
                |--------------------------------------------------------------------------
                */

                $stmtItems = $pdo->prepare("
                    SELECT
                        u.id,
                        u.kode_unit,
                        u.nama_unit,
                        u.kategori,
                        u.jumlah,
                        ri.status

                    FROM rental_items ri

                    INNER JOIN units u
                        ON u.id = ri.unit_id

                    WHERE ri.rental_id = ?
                      AND ri.status = 'DISEWAKAN'

                    ORDER BY u.kode_unit ASC
                ");

                $stmtItems->execute([
                    $result['rental_id']
                ]);

                $transaction['units'] =
                    $stmtItems->fetchAll();

            } else {

                $error =
                    'Tidak ditemukan transaksi aktif dengan kode penyewa "' .
                    htmlspecialchars($renterCode) .
                    '".';
            }
        }

    } catch (PDOException $e) {

        $error =
            'Terjadi kesalahan saat mengambil data transaksi.';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Cek Unit Keluar - Sistem Scan Unit</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }


        .container {
            width: min(900px, 94%);
            margin: 30px auto;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }


        .title h1 {
            margin: 0;
            font-size: 25px;
        }


        .title p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        .back {
            text-decoration: none;
            background: #e5e7eb;
            color: #111827;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
        }


        .back:hover {
            background: #d1d5db;
        }


        /*
        |--------------------------------------------------------------------------
        | Card
        |--------------------------------------------------------------------------
        */

        .card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }


        .card h2 {
            margin-top: 0;
            font-size: 19px;
        }


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        .search-form {
            display: flex;
            gap: 10px;
        }


        .input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }


        .input:focus {
            border-color: #2563eb;
        }


        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }


        .btn {
            border: none;
            text-decoration: none;
            cursor: pointer;
            padding: 11px 16px;
            border-radius: 8px;
            font-size: 14px;
            display: inline-block;
        }


        .btn-primary {
            background: #2563eb;
            color: white;
        }


        .btn-primary:hover {
            background: #1d4ed8;
        }


        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }


        .btn-secondary:hover {
            background: #d1d5db;
        }


        /*
        |--------------------------------------------------------------------------
        | Alert
        |--------------------------------------------------------------------------
        */

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }


        .alert-success {
            background: #dcfce7;
            color: #166534;
        }


        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }


        /*
        |--------------------------------------------------------------------------
        | Transaction Info
        |--------------------------------------------------------------------------
        */

        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }


        .transaction-code {
            font-size: 24px;
            font-weight: bold;
        }


        .transaction-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }


        .transaction-id {
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
        }


        .status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Info Grid
        |--------------------------------------------------------------------------
        */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }


        .info-item {
            background: #f8fafc;
            padding: 14px;
            border-radius: 9px;
        }


        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }


        .info-value {
            font-size: 14px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Unit List
        |--------------------------------------------------------------------------
        */

        .section-title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 12px;
        }


        .unit-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }


        .unit-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }


        .unit-left {
            min-width: 0;
        }


        .unit-code {
            font-weight: bold;
            font-size: 16px;
        }


        .unit-name {
            margin-top: 4px;
            font-size: 14px;
            color: #4b5563;
        }


        .unit-category {
            margin-top: 3px;
            color: #6b7280;
            font-size: 12px;
        }


        .unit-quantity {
            white-space: nowrap;
            font-size: 13px;
            color: #4b5563;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {
            text-align: center;
            padding: 40px 15px;
            color: #6b7280;
        }


        .empty-icon {
            font-size: 42px;
            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .container {
                margin: 20px auto;
            }


            .search-form {
                flex-direction: column;
            }


            .info-grid {
                grid-template-columns: 1fr;
            }


            .unit-item {
                align-items: flex-start;
                flex-direction: column;
            }


            .card {
                padding: 17px;
            }


            .title h1 {
                font-size: 22px;
            }

        }

    </style>

</head>


<body>

<div class="container">


    <!-- HEADER -->

    <div class="topbar">

        <div class="title">

            <h1>
                📦 Cek Unit Keluar
            </h1>

            <p>
                Melihat transaksi unit yang sedang disewakan.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- NOTIFIKASI BERHASIL -->

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">

            Unit berhasil disewakan.

            <br>

            Silakan cek daftar unit di bawah.

        </div>

    <?php endif; ?>


    <!-- PENCARIAN -->

    <div class="card">

        <h2>
            Cari Transaksi
        </h2>


        <form
            method="GET"
            action="cek-keluar.php"
        >

            <div class="search-form">

                <input
                    type="text"
                    name="renter_code"
                    class="input"
                    placeholder="Masukkan kode penyewa"
                    value="<?= htmlspecialchars($renterCode) ?>"
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    🔍 Cari
                </button>

            </div>

        </form>


        <div class="button-row">

            <a
                href="scan-keluar.php"
                class="btn btn-secondary"
            >
                📷 Scan Unit Keluar
            </a>


            <a
                href="cek-keluar.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </div>

    </div>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            <?= $error ?>

        </div>

    <?php endif; ?>


    <!-- TRANSAKSI -->

    <?php if ($transaction): ?>

        <div class="card">


            <div class="transaction-header">

                <div>

                    <div class="transaction-label">
                        Kode Penyewa
                    </div>

                    <div class="transaction-code">

                        <?= htmlspecialchars(
                            $transaction['renter_code']
                        ) ?>

                    </div>

                    <div class="transaction-id">

                        Transaksi #
                        <?= (int) $transaction['rental_id'] ?>

                    </div>

                </div>


                <span class="status">
                    DISEWAKAN
                </span>

            </div>


            <!-- INFO TRANSAKSI -->

            <div class="info-grid">


                <div class="info-item">

                    <div class="info-label">
                        Cabang
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $transaction['nama_cabang']
                        ) ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Petugas
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $transaction['checkout_nama']
                        ) ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Waktu Keluar
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $transaction['checkout_at']
                        ) ?>

                    </div>

                </div>


            </div>


            <!-- DAFTAR UNIT -->

            <div class="section-title">

                Unit yang Sedang Disewakan
                (<?= count($transaction['units']) ?>)

            </div>


            <div class="unit-list">


                <?php foreach (
                    $transaction['units']
                    as $item
                ): ?>

                    <div class="unit-item">


                        <div class="unit-left">

                            <div class="unit-code">

                                <?= htmlspecialchars(
                                    $item['kode_unit']
                                ) ?>

                            </div>


                            <div class="unit-name">

                                <?= htmlspecialchars(
                                    $item['nama_unit']
                                ) ?>

                            </div>


                            <div class="unit-category">

                                Kategori:
                                <?= htmlspecialchars(
                                    $item['kategori']
                                ) ?>

                            </div>

                        </div>


                        <div class="unit-quantity">

                            Jumlah:
                            <?= (int) $item['jumlah'] ?>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>


            <div class="button-row">

                <a
                    href="unit-masuk.php"
                    class="btn btn-primary"
                >
                    Unit Masuk / Selesai
                </a>

            </div>


        </div>


    <?php elseif (
        $kodeUnit === '' &&
        $renterCode === '' &&
        $error === ''
    ): ?>


        <div class="card">

            <div class="empty">

                <div class="empty-icon">
                    🔎
                </div>

                <strong>
                    Belum ada transaksi yang dicari.
                </strong>

                <p>
                    Masukkan kode penyewa untuk melihat
                    unit yang sedang disewakan.
                </p>

            </div>

        </div>


    <?php endif; ?>


</div>

</body>

</html>