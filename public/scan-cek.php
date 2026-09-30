<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$kodeUnit = trim($_GET['kode_unit'] ?? '');

$unit = null;
$error = '';

/*
|--------------------------------------------------------------------------
| Jika ada kode unit dari QR / input
|--------------------------------------------------------------------------
*/

if ($kodeUnit !== '') {

    try {

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.kode_unit,
                u.nama_unit,
                u.kategori,
                u.jumlah,
                u.status,
                u.owner_branch_id,
                u.created_at,
                u.updated_at,

                b.nama_cabang

            FROM units u

            INNER JOIN branches b
                ON b.id = u.owner_branch_id

            WHERE u.kode_unit = ?

            LIMIT 1
        ");

        $stmt->execute([
            $kodeUnit
        ]);

        $unit = $stmt->fetch();


        if (!$unit) {

            $error =
                'Unit dengan kode "' .
                $kodeUnit .
                '" tidak ditemukan.';

        }

    } catch (PDOException $e) {

        $error =
            'Terjadi kesalahan saat mengambil data unit.';
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

    <title>Scan Cek Unit - Sistem Scan Unit</title>

    <script
        src="https://unpkg.com/html5-qrcode"
        type="text/javascript"
    ></script>


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
            width: min(850px, 94%);
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
            background: #ffffff;
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
        | Manual Search
        |--------------------------------------------------------------------------
        */

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        .search-input:focus {
            border-color: #2563eb;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn {
            border: none;
            cursor: pointer;
            text-decoration: none;
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

        .btn-green {
            background: #16a34a;
            color: white;
        }

        .btn-green:hover {
            background: #15803d;
        }


        /*
        |--------------------------------------------------------------------------
        | Scanner
        |--------------------------------------------------------------------------
        */

        .scanner-box {
            display: none;
            margin-top: 20px;
        }

        .scanner-info {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 14px;
        }

        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | Error
        |--------------------------------------------------------------------------
        */

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }


        /*
        |--------------------------------------------------------------------------
        | Unit Detail
        |--------------------------------------------------------------------------
        */

        .unit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 22px;
        }

        .unit-code {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .unit-name {
            color: #4b5563;
            font-size: 15px;
        }

        .status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-tersedia {
            background: #dcfce7;
            color: #166534;
        }

        .status-disewa {
            background: #fef3c7;
            color: #92400e;
        }


        /*
        |--------------------------------------------------------------------------
        | Detail Grid
        |--------------------------------------------------------------------------
        */

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .detail-item {
            background: #f8fafc;
            border-radius: 9px;
            padding: 14px;
        }

        .detail-label {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 650px) {

            .container {
                margin: 20px auto;
            }

            .title h1 {
                font-size: 22px;
            }

            .search-form {
                flex-direction: column;
            }

            .unit-header {
                flex-direction: column;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 17px;
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
                📷 Scan Cek Unit
            </h1>

            <p>
                Scan QR untuk melihat informasi unit.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- PENCARIAN -->

    <div class="card">

        <h2>
            Cari Unit
        </h2>

        <form
            method="GET"
            action="scan-cek.php"
        >

            <div class="search-form">

                <input
                    type="text"
                    name="kode_unit"
                    class="search-input"
                    value="<?= htmlspecialchars($kodeUnit) ?>"
                    placeholder="Masukkan kode unit, contoh: A1"
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

            <button
                type="button"
                class="btn btn-green"
                onclick="toggleScanner()"
            >
                📷 Buka Kamera
            </button>


            <?php if ($kodeUnit !== ''): ?>

                <a
                    href="scan-cek.php"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            <?php endif; ?>

        </div>


        <!-- SCANNER -->

        <div
            id="scannerBox"
            class="scanner-box"
        >

            <div class="scanner-info">

                Arahkan kamera ke QR unit.

                <br>

                QR unit berisi kode seperti
                <strong>A1</strong>.

            </div>


            <div id="reader"></div>


            <div class="button-row">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="stopScanner()"
                >
                    Tutup Kamera
                </button>

            </div>

        </div>

    </div>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- HASIL UNIT -->

    <?php if ($unit): ?>

        <div class="card">


            <div class="unit-header">

                <div>

                    <div class="unit-code">

                        <?= htmlspecialchars(
                            $unit['kode_unit']
                        ) ?>

                    </div>

                    <div class="unit-name">

                        <?= htmlspecialchars(
                            $unit['nama_unit']
                        ) ?>

                    </div>

                </div>


                <?php if (
                    $unit['status'] === 'TERSEDIA'
                ): ?>

                    <span class="
                        status
                        status-tersedia
                    ">
                        TERSEDIA
                    </span>

                <?php else: ?>

                    <span class="
                        status
                        status-disewa
                    ">
                        DISEWAKAN
                    </span>

                <?php endif; ?>

            </div>


            <div class="detail-grid">


                <div class="detail-item">

                    <div class="detail-label">
                        Kode Unit
                    </div>

                    <div class="detail-value">
                        <?= htmlspecialchars(
                            $unit['kode_unit']
                        ) ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="detail-label">
                        Nama Unit
                    </div>

                    <div class="detail-value">
                        <?= htmlspecialchars(
                            $unit['nama_unit']
                        ) ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="detail-label">
                        Kategori
                    </div>

                    <div class="detail-value">
                        <?= htmlspecialchars(
                            $unit['kategori']
                        ) ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="detail-label">
                        Jumlah
                    </div>

                    <div class="detail-value">
                        <?= (int) $unit['jumlah'] ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="detail-label">
                        Pemilik Unit
                    </div>

                    <div class="detail-value">
                        <?= htmlspecialchars(
                            $unit['nama_cabang']
                        ) ?>
                    </div>

                </div>


                <div class="detail-item">

                    <div class="detail-label">
                        Status
                    </div>

                    <div class="detail-value">

                        <?php if (
                            $unit['status'] === 'TERSEDIA'
                        ): ?>

                            <span style="color:#166534;">
                                Tersedia
                            </span>

                        <?php else: ?>

                            <span style="color:#92400e;">
                                Sedang Disewa
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


            </div>


            <div class="button-row">

                <a
                    href="qr.php?id=<?= (int) $unit['id'] ?>"
                    class="btn btn-primary"
                >
                    Lihat QR Unit
                </a>


                <?php if (
                    $unit['status'] === 'DISEWAKAN'
                ): ?>

                    <a
                        href="cek-keluar.php?kode_unit=<?= urlencode($unit['kode_unit']) ?>"
                        class="btn btn-secondary"
                    >
                        Lihat Transaksi
                    </a>

                <?php endif; ?>

            </div>


        </div>

    <?php elseif ($kodeUnit === '' && $error === ''): ?>

        <div class="card">

            <div class="empty">

                <div class="empty-icon">
                    📦
                </div>

                <strong>
                    Belum ada unit yang dicari.
                </strong>

                <p>
                    Masukkan kode unit atau scan QR untuk melihat
                    detail unit.
                </p>

            </div>

        </div>

    <?php endif; ?>


</div>


<script>

    let scanner = null;
    let scannerRunning = false;


    function toggleScanner() {

        const box =
            document.getElementById('scannerBox');


        if (box.style.display === 'block') {

            stopScanner();

        } else {

            startScanner();

        }

    }


    function startScanner() {

        const box =
            document.getElementById('scannerBox');


        box.style.display = 'block';


        if (scannerRunning) {
            return;
        }


        scanner =
            new Html5Qrcode("reader");


        scanner.start(
            {
                facingMode: "environment"
            },
            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                }
            },

            function(decodedText) {

                const kode =
                    decodedText.trim();


                if (!kode) {
                    return;
                }


                stopScanner();


                window.location.href =
                    "scan-cek.php?kode_unit=" +
                    encodeURIComponent(kode);

            },

            function(errorMessage) {

                // Tidak perlu menampilkan
                // error scanner ke pengguna.

            }

        )
        .then(function() {

            scannerRunning = true;

        })
        .catch(function(error) {

            scannerRunning = false;

            alert(
                "Kamera tidak dapat digunakan. " +
                "Silakan masukkan kode unit secara manual."
            );

        });

    }


    function stopScanner() {

        if (
            scanner &&
            scannerRunning
        ) {

            scanner.stop()
                .then(function() {

                    scanner.clear();

                    scannerRunning = false;

                })
                .catch(function() {

                    scannerRunning = false;

                });

        }


        document.getElementById(
            'scannerBox'
        ).style.display = 'none';

    }


    window.addEventListener(
        'beforeunload',
        function() {

            if (
                scanner &&
                scannerRunning
            ) {

                scanner
                    .stop()
                    .catch(function() {});

            }

        }
    );

</script>

</body>

</html>