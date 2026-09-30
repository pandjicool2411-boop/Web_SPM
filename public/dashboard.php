<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$branchId = user_branch_id();
$branchName = user_branch_name();

/*
|--------------------------------------------------------------------------
| Statistik Dashboard
|--------------------------------------------------------------------------
*/

$totalUnit = 0;
$unitTersedia = 0;
$unitDisewa = 0;
$totalTransaksiAktif = 0;

try {

    // Total unit milik cabang sendiri
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_unit,
            SUM(status = 'TERSEDIA') AS tersedia,
            SUM(status = 'DISEWAKAN') AS disewa
        FROM units
        WHERE owner_branch_id = ?
    ");

    $stmt->execute([$branchId]);

    $stats = $stmt->fetch();

    if ($stats) {
        $totalUnit = (int) ($stats['total_unit'] ?? 0);
        $unitTersedia = (int) ($stats['tersedia'] ?? 0);
        $unitDisewa = (int) ($stats['disewa'] ?? 0);
    }


    // Jumlah transaksi yang masih memiliki unit DISEWAKAN
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT r.id)
        FROM rentals r
        INNER JOIN rental_items ri
            ON ri.rental_id = r.id
        WHERE r.branch_id = ?
          AND ri.status = 'DISEWAKAN'
    ");

    $stmt->execute([$branchId]);

    $totalTransaksiAktif = (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    // Dashboard tetap tampil walaupun statistik gagal.
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

    <title>Dashboard - Sistem Scan Unit</title>

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
            width: min(1200px, 94%);
            margin: 0 auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 0;
        }

        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .brand h1 {
            margin: 0;
            font-size: 22px;
        }

        .brand p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 13px;
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: bold;
            font-size: 14px;
        }

        .user-branch {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }

        .logout {
            text-decoration: none;
            background: #fee2e2;
            color: #991b1b;
            padding: 9px 13px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
        }

        .logout:hover {
            background: #fecaca;
        }


        /*
        |--------------------------------------------------------------------------
        | Main
        |--------------------------------------------------------------------------
        */

        main {
            padding: 30px 0 50px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h2 {
            margin: 0 0 7px;
            font-size: 25px;
        }

        .welcome p {
            margin: 0;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Statistik
        |--------------------------------------------------------------------------
        */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 13px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .stat-title {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }

        .stat-description {
            margin-top: 7px;
            color: #6b7280;
            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Menu
        |--------------------------------------------------------------------------
        */

        .section-title {
            margin-bottom: 15px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 20px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .menu-card {
            background: #ffffff;
            border-radius: 13px;
            padding: 21px;
            text-decoration: none;
            color: #1f2937;
            border: 1px solid transparent;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            transition: 0.2s;
        }

        .menu-card:hover {
            transform: translateY(-2px);
            border-color: #dbeafe;
        }

        .menu-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px;
        }

        .menu-card h3 {
            margin: 0 0 7px;
            font-size: 16px;
        }

        .menu-card p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            padding: 20px 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 600px) {

            .header-inner {
                align-items: flex-start;
            }

            .user-area {
                align-items: flex-end;
                flex-direction: column;
                gap: 7px;
            }

            .user-info {
                text-align: right;
            }

            main {
                padding-top: 22px;
            }

            .welcome h2 {
                font-size: 22px;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-number {
                font-size: 23px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- HEADER -->

<header class="header">

    <div class="container header-inner">

        <div class="brand">

            <h1>
                Sistem Scan Unit
            </h1>

            <p>
                Manajemen unit rental berbasis QR
            </p>

        </div>


        <div class="user-area">

            <div class="user-info">

                <div class="user-name">
                    <?= htmlspecialchars(user_name()) ?>
                </div>

                <div class="user-branch">
                    <?= htmlspecialchars($branchName) ?>
                </div>

            </div>

            <a
                href="logout.php"
                class="logout"
                onclick="return confirm('Yakin ingin keluar?')"
            >
                Keluar
            </a>

        </div>

    </div>

</header>


<!-- MAIN -->

<main>

    <div class="container">


        <!-- WELCOME -->

        <div class="welcome">

            <h2>
                Dashboard
            </h2>

            <p>
                Selamat datang, <?= htmlspecialchars(user_name()) ?>.
                Kelola unit rental cabang
                <strong><?= htmlspecialchars($branchName) ?></strong>
                dari sini.
            </p>

        </div>


        <!-- STATISTIK -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-title">
                    Total Unit
                </div>

                <div class="stat-number">
                    <?= $totalUnit ?>
                </div>

                <div class="stat-description">
                    Unit milik cabang Anda
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Unit Tersedia
                </div>

                <div class="stat-number">
                    <?= $unitTersedia ?>
                </div>

                <div class="stat-description">
                    Siap digunakan / disewakan
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Unit Disewa
                </div>

                <div class="stat-number">
                    <?= $unitDisewa ?>
                </div>

                <div class="stat-description">
                    Sedang berada di penyewa
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Transaksi Aktif
                </div>

                <div class="stat-number">
                    <?= $totalTransaksiAktif ?>
                </div>

                <div class="stat-description">
                    Masih memiliki unit disewa
                </div>

            </div>

        </div>


        <!-- MENU -->

        <div class="section-title">

            <h2>
                Menu Utama
            </h2>

        </div>


        <div class="menu-grid">


            <!-- DATA UNIT -->

            <a
                href="units.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    📦
                </div>

                <h3>
                    Data Unit
                </h3>

                <p>
                    Kelola unit milik cabang,
                    tambah unit, edit informasi,
                    dan lihat QR.
                </p>

            </a>


            <!-- SCAN UNIT KELUAR -->

            <a
                href="scan-keluar.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    📷
                </div>

                <h3>
                    Scan Unit Keluar
                </h3>

                <p>
                    Scan beberapa unit sekaligus
                    sebelum membuat transaksi rental.
                </p>

            </a>


            <!-- CEK UNIT KELUAR -->

            <a
                href="cek-keluar.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    🔎
                </div>

                <h3>
                    Cek Unit Keluar
                </h3>

                <p>
                    Cek unit yang sedang disewa
                    berdasarkan kode unit atau kode penyewa.
                </p>

            </a>


            <!-- UNIT MASUK -->

            <a
                href="unit-masuk.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    ↩️
                </div>

                <h3>
                    Unit Masuk
                </h3>

                <p>
                    Tandai unit yang sudah kembali
                    dan batalkan jika terjadi kesalahan.
                </p>

            </a>


            <!-- CEK SEMUA UNIT -->

            <a
                href="cek-unit.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    🏢
                </div>

                <h3>
                    Cek Semua Unit
                </h3>

                <p>
                    Lihat unit dari seluruh cabang
                    beserta status dan pemiliknya.
                </p>

            </a>


            <!-- SCAN CEK UNIT -->

            <a
                href="scan-cek.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    🔍
                </div>

                <h3>
                    Scan Cek Unit
                </h3>

                <p>
                    Scan QR untuk melihat informasi
                    dan status sebuah unit.
                </p>

            </a>


            <!-- RIWAYAT -->

            <a
                href="riwayat.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    📋
                </div>

                <h3>
                    Riwayat
                </h3>

                <p>
                    Lihat aktivitas unit berdasarkan
                    bulan dan tahun.
                </p>

            </a>


            <!-- TRANSFER -->

            <a
                href="transfer.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    🔄
                </div>

                <h3>
                    Transfer Unit
                </h3>

                <p>
                    Kirim unit ke cabang lain
                    dan konfirmasi unit yang masuk.
                </p>

            </a>


        </div>


    </div>

</main>


<footer>

    Sistem Scan Unit V2

</footer>


</body>

</html>