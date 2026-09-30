<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_login();

$branchId = user_branch_id();
$branchName = user_branch_name();
$userName = user_name();

/*
|--------------------------------------------------------------------------
| Statistik Dashboard
|--------------------------------------------------------------------------
*/

// Total unit cabang sendiri
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM units
    WHERE owner_branch_id = ?
");
$stmt->execute([$branchId]);
$totalUnits = (int) $stmt->fetchColumn();

// Unit tersedia
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM units
    WHERE owner_branch_id = ?
    AND status = 'TERSEDIA'
");
$stmt->execute([$branchId]);
$availableUnits = (int) $stmt->fetchColumn();

// Unit sedang disewa
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM units
    WHERE owner_branch_id = ?
    AND status = 'DISEWAKAN'
");
$stmt->execute([$branchId]);
$rentedUnits = (int) $stmt->fetchColumn();

// Transaksi aktif
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT r.id)
    FROM rentals r
    INNER JOIN rental_items ri
        ON ri.rental_id = r.id
    WHERE r.branch_id = ?
    AND ri.status = 'DISEWAKAN'
");
$stmt->execute([$branchId]);
$activeTransactions = (int) $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Sistem Scan Unit</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #6d3fd1;
            --primary-dark: #5630ae;
            --primary-soft: #f1edff;

            --text: #1f2937;
            --muted: #6b7280;

            --bg: #f7f7fb;
            --white: #ffffff;

            --border: #e8e8ef;

            --green: #16a34a;
            --green-soft: #eaf8ef;

            --orange: #ea580c;
            --orange-soft: #fff1e8;

            --blue: #2563eb;
            --blue-soft: #edf4ff;

            --shadow: 0 8px 25px rgba(20, 20, 40, .06);
        }

        html {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            font-family: inherit;
        }

        /* =========================
           LAYOUT
        ========================= */

        .app {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            width: 250px;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 24px 16px;

            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            display: flex;
            flex-direction: column;

            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 10px 26px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--primary);
            color: white;
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
            font-weight: 700;
        }

        .brand-text h1 {
            font-size: 16px;
            line-height: 1.2;
        }

        .brand-text p {
            margin-top: 3px;
            font-size: 11px;
            color: var(--muted);
        }

        .menu-title {
            font-size: 11px;
            font-weight: 700;
            color: #9ca3af;

            padding: 0 12px;
            margin: 8px 0 10px;

            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px;

            border-radius: 10px;

            font-size: 14px;
            color: #4b5563;

            transition: .2s;
        }

        .menu a:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .menu a.active {
            background: var(--primary);
            color: white;
        }

        .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-bottom {
            margin-top: auto;
        }

        .user-box {
            background: #f8f8fc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
        }

        .user-branch {
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.4;
        }

        .logout {
            color: #dc2626 !important;
        }

        .logout:hover {
            background: #fff1f2 !important;
            color: #dc2626 !important;
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
            max-width: calc(100% - 250px);
            min-width: 0;
            min-height: 100vh;
            padding: 28px 32px;
        }

        .topbar {
            width: 100%;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 28px;
        }

        .page-title h2 {
            font-size: 25px;
            font-weight: 700;
        }

        .page-title p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 6px;
        }

        .branch-badge {
            display: flex;
            align-items: center;
            gap: 8px;

            background: var(--white);
            border: 1px solid var(--border);

            padding: 9px 13px;
            border-radius: 10px;

            font-size: 12px;
            color: #4b5563;
        }

        .branch-dot {
            width: 8px;
            height: 8px;
            background: var(--green);
            border-radius: 50%;
        }

        /* =========================
           HERO
        ========================= */

        .hero {
            width: 100%;
            min-width: 0;
            overflow: hidden;
            background: linear-gradient(
                135deg,
                #6d3fd1,
                #8058df
            );

            border-radius: 18px;

            padding: 26px 28px;

            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            box-shadow: 0 12px 30px rgba(109, 63, 209, .18);

            margin-bottom: 24px;
        }

        .hero-content {
            min-width: 0;
            max-width: 100%;
        }

        .hero-content h3 {
            font-size: 22px;
            margin-bottom: 8px;
        }

        .hero-content p {
            font-size: 13px;
            opacity: .88;
            line-height: 1.5;
            max-width: 600px;
        }

        .hero-action {
            background: white;
            color: var(--primary);

            padding: 12px 18px;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 700;

            white-space: nowrap;

            transition: .2s;
        }

        .hero-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,.12);
        }

        /* =========================
           STATISTICS
        ========================= */

        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .stats {
            width: 100%;
            min-width: 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;

            margin-bottom: 26px;
        }

        .stat-card {
            min-width: 0;
            background: var(--white);
            border: 1px solid var(--border);

            border-radius: 15px;

            padding: 18px;

            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
        }

        .stat-icon {
            width: 38px;
            height: 38px;

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 17px;
        }

        .icon-purple {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .icon-green {
            background: var(--green-soft);
            color: var(--green);
        }

        .icon-orange {
            background: var(--orange-soft);
            color: var(--orange);
        }

        .icon-blue {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .stat-number {
            margin-top: 15px;
            font-size: 27px;
            font-weight: 700;
        }

        /* =========================
           QUICK MENU
        ========================= */

        .quick-grid {
            width: 100%;
            min-width: 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .quick-card {
            min-width: 0;
            background: var(--white);

            border: 1px solid var(--border);
            border-radius: 15px;

            padding: 19px;

            transition: .2s;
        }

        .quick-card:hover {
            transform: translateY(-3px);
            border-color: #d8cdf8;
            box-shadow: 0 10px 25px rgba(30, 20, 60, .08);
        }

        .quick-icon {
            width: 42px;
            height: 42px;

            border-radius: 11px;

            background: var(--primary-soft);
            color: var(--primary);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 18px;

            margin-bottom: 13px;
        }

        .quick-card h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .quick-card p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        /* =========================
           MOBILE NAV
        ========================= */

        .mobile-nav {
            display: none;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1050px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 760px) {

            html,
            body {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: hidden;
            }

            .app {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: hidden;
            }

            .sidebar {
                display: none;
            }

            .main {
                display: block;
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                padding: 20px 15px 90px;
                overflow-x: hidden;
            }

            .topbar {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                align-items: flex-start;
                gap: 10px;
            }

            .page-title {
                min-width: 0;
                width: 100%;
            }

            .page-title h2 {
                font-size: 21px;
                line-height: 1.2;
            }

            .page-title p {
                font-size: 12px;
                line-height: 1.5;
                max-width: 100%;
            }

            .branch-badge {
                display: none;
            }

            .hero {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                padding: 22px;
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
                overflow: hidden;
            }

            .hero-content {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            .hero-content h3 {
                font-size: 19px;
                line-height: 1.3;
                overflow-wrap: anywhere;
            }

            .hero-content p {
                width: 100%;
                max-width: 100%;
                font-size: 12px;
                line-height: 1.5;
                overflow-wrap: anywhere;
            }

            .hero-action {
                display: flex;
                width: 100%;
                max-width: 100%;
                text-align: center;
                justify-content: center;
                white-space: normal;
            }

            .stats {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat-card {
                width: 100%;
                min-width: 0;
                padding: 15px;
            }

            .stat-top {
                min-width: 0;
                gap: 8px;
            }

            .stat-label {
                min-width: 0;
                overflow-wrap: anywhere;
            }

            .stat-icon {
                flex: 0 0 38px;
            }

            .stat-number {
                font-size: 23px;
            }

            .quick-grid {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .quick-card {
                width: 100%;
                min-width: 0;
                padding: 15px;
            }

            .quick-card h4,
            .quick-card p {
                overflow-wrap: anywhere;
            }

            .mobile-nav {
                display: flex;
                position: fixed;
                left: 10px;
                right: 10px;
                bottom: 10px;
                width: auto;
                max-width: calc(100% - 20px);
                height: 64px;
                background: rgba(255,255,255,.96);
                border: 1px solid var(--border);
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0,0,0,.12);
                z-index: 200;
                align-items: center;
                justify-content: space-around;
            }

            .mobile-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                width: 25%;
                min-width: 0;
                color: #777;
                font-size: 10px;
            }

            .mobile-nav a.active {
                color: var(--primary);
                font-weight: 700;
            }

            .mobile-nav-icon {
                font-size: 17px;
            }
        }

        @media (max-width: 420px) {

            .main {
                padding-left: 12px;
                padding-right: 12px;
            }

            .hero {
                padding: 18px;
                border-radius: 15px;
            }

            .hero-content h3 {
                font-size: 18px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stat-card {
                padding: 13px;
            }

            .stat-label {
                font-size: 11px;
            }

            .stat-number {
                font-size: 22px;
            }

            .stat-icon {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
                font-size: 15px;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }

            .quick-card {
                padding: 15px;
            }

            .hero-content p {
                font-size: 12px;
            }

        }
    </style>
</head>

<body>

<div class="app">

    <!-- =========================
         SIDEBAR
    ========================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">
                QR
            </div>

            <div class="brand-text">
                <h1>Scan Unit</h1>
                <p>Sistem Rental Unit</p>
            </div>

        </div>


        <div class="menu-title">
            Menu Utama
        </div>

        <nav class="menu">

            <a href="dashboard.php" class="active">
                <span class="menu-icon">⌂</span>
                Dashboard
            </a>

            <a href="units.php">
                <span class="menu-icon">▣</span>
                Unit
            </a>

            <a href="scan-keluar.php">
                <span class="menu-icon">⌕</span>
                Scan Unit Keluar
            </a>

            <a href="unit-masuk.php">
                <span class="menu-icon">↩</span>
                Unit Masuk
            </a>

            <a href="cek-keluar.php">
                <span class="menu-icon">◉</span>
                Cek Unit Keluar
            </a>

            <a href="cek-unit.php">
                <span class="menu-icon">⌑</span>
                Cek Unit
            </a>

            <a href="transfer.php">
                <span class="menu-icon">⇄</span>
                Transfer Unit
            </a>

            <a href="riwayat.php">
                <span class="menu-icon">◷</span>
                Riwayat
            </a>

            <a href="branches.php">
                <span class="menu-icon">⌂</span>
                Cabang
            </a>

        </nav>


        <div class="sidebar-bottom">

            <div class="user-box">

                <div class="user-name">
                    <?= htmlspecialchars($userName) ?>
                </div>

                <div class="user-branch">
                    <?= htmlspecialchars($branchName) ?>
                </div>

            </div>

            <nav class="menu">

                <a href="logout.php" class="logout">
                    <span class="menu-icon">↪</span>
                    Keluar
                </a>

            </nav>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="page-title">

                <h2>Dashboard</h2>

                <p>
                    Kelola unit rental cabang dengan mudah.
                </p>

            </div>

            <div class="branch-badge">

                <span class="branch-dot"></span>

                <?= htmlspecialchars($branchName) ?>

            </div>

        </div>


        <!-- HERO -->

        <section class="hero">

            <div class="hero-content">

                <h3>
                    Halo, <?= htmlspecialchars($userName) ?> 👋
                </h3>

                <p>
                    Semua aktivitas unit rental cabang
                    <?= htmlspecialchars($branchName) ?>
                    dapat dikelola dari dashboard ini.
                </p>

            </div>

            <a
                href="scan-keluar.php"
                class="hero-action"
            >
                + Scan Unit Keluar
            </a>

        </section>


        <!-- STATISTIK -->

        <div class="section-title">
            Ringkasan Unit
        </div>

        <section class="stats">

            <!-- TOTAL UNIT -->

            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Total Unit
                    </span>

                    <div class="stat-icon icon-purple">
                        ▣
                    </div>

                </div>

                <div class="stat-number">
                    <?= $totalUnits ?>
                </div>

            </div>


            <!-- TERSEDIA -->

            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Unit Tersedia
                    </span>

                    <div class="stat-icon icon-green">
                        ✓
                    </div>

                </div>

                <div class="stat-number">
                    <?= $availableUnits ?>
                </div>

            </div>


            <!-- DISEWAKAN -->

            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Sedang Disewa
                    </span>

                    <div class="stat-icon icon-orange">
                        ◷
                    </div>

                </div>

                <div class="stat-number">
                    <?= $rentedUnits ?>
                </div>

            </div>


            <!-- TRANSAKSI -->

            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Transaksi Aktif
                    </span>

                    <div class="stat-icon icon-blue">
                        #
                    </div>

                </div>

                <div class="stat-number">
                    <?= $activeTransactions ?>
                </div>

            </div>

        </section>


        <!-- MENU CEPAT -->

        <div class="section-title">
            Akses Cepat
        </div>

        <section class="quick-grid">

            <a
                href="units.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ▣
                </div>

                <h4>
                    Kelola Unit
                </h4>

                <p>
                    Lihat dan kelola unit berdasarkan cabang dan kategori.
                </p>

            </a>


            <a
                href="scan-keluar.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ⌕
                </div>

                <h4>
                    Scan Unit Keluar
                </h4>

                <p>
                    Scan beberapa unit sekaligus untuk satu transaksi rental.
                </p>

            </a>


            <a
                href="unit-masuk.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ↩
                </div>

                <h4>
                    Unit Masuk
                </h4>

                <p>
                    Tandai unit yang telah kembali dan kelola statusnya.
                </p>

            </a>


            <a
                href="transfer.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ⇄
                </div>

                <h4>
                    Transfer Unit
                </h4>

                <p>
                    Kirim unit ke cabang lain melalui proses konfirmasi.
                </p>

            </a>


            <a
                href="cek-unit.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ⌑
                </div>

                <h4>
                    Cek Unit
                </h4>

                <p>
                    Cari informasi unit dari seluruh cabang.
                </p>

            </a>


            <a
                href="cek-keluar.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ◉
                </div>

                <h4>
                    Cek Unit Keluar
                </h4>

                <p>
                    Cek transaksi dan unit yang sedang disewa.
                </p>

            </a>


            <a
                href="riwayat.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ◷
                </div>

                <h4>
                    Riwayat
                </h4>

                <p>
                    Lihat aktivitas unit berdasarkan bulan dan tahun.
                </p>

            </a>


            <a
                href="branches.php"
                class="quick-card"
            >

                <div class="quick-icon">
                    ⌂
                </div>

                <h4>
                    Daftar Cabang
                </h4>

                <p>
                    Lihat informasi dan jumlah unit setiap cabang.
                </p>

            </a>

        </section>

    </main>


    <!-- =========================
         MOBILE NAVIGATION
    ========================== -->

    <nav class="mobile-nav">

        <a
            href="dashboard.php"
            class="active"
        >
            <span class="mobile-nav-icon">⌂</span>
            Home
        </a>

        <a href="units.php">
            <span class="mobile-nav-icon">▣</span>
            Unit
        </a>

        <a href="scan-keluar.php">
            <span class="mobile-nav-icon">⌕</span>
            Scan
        </a>

        <a href="unit-masuk.php">
            <span class="mobile-nav-icon">↩</span>
            Masuk
        </a>

    </nav>

</div>

</body>
</html>