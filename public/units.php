<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

require_login();

$branchId = user_branch_id();
$branchName = user_branch_name();
$userName = user_name();

$error = $_SESSION['units_error'] ?? '';
$success = $_SESSION['units_success'] ?? '';

unset($_SESSION['units_error'], $_SESSION['units_success']);

/*
|--------------------------------------------------------------------------
| TAMBAH UNIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['units_error'] = "Token keamanan tidak valid.";
        header("Location: units.php");
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'tambah_unit') {

        $kodeUnit = trim($_POST['kode_unit'] ?? '');
        $namaUnit = trim($_POST['nama_unit'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $jumlah = (int) ($_POST['jumlah'] ?? 0);

        if ($kodeUnit === '' || $namaUnit === '' || $kategori === '') {
            $_SESSION['units_error'] = "Semua data unit wajib diisi.";
            header("Location: units.php");
            exit;
        }

        if ($jumlah < 1) {
            $_SESSION['units_error'] = "Jumlah unit minimal 1.";
            header("Location: units.php");
            exit;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Cek kode unit
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM units
                WHERE kode_unit = ?
                LIMIT 1
            ");

            $stmt->execute([$kodeUnit]);

            if ($stmt->fetch()) {
                $_SESSION['units_error'] = "Kode unit tersebut sudah digunakan.";
                header("Location: units.php");
                exit;
            }

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Insert Unit
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO units
                (
                    kode_unit,
                    nama_unit,
                    kategori,
                    jumlah,
                    owner_branch_id,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'TERSEDIA',
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $kodeUnit,
                $namaUnit,
                $kategori,
                $jumlah,
                $branchId
            ]);

            $unitId = $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | History
            |--------------------------------------------------------------------------
            */

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
                    'TAMBAH_UNIT',
                    ?,
                    NOW()
                )
            ");

            $stmt->execute([
                $unitId,
                user_id(),
                $branchId,
                "Menambahkan unit {$kodeUnit} - {$namaUnit}"
            ]);

            $pdo->commit();

            $_SESSION['units_success'] = "Unit berhasil ditambahkan.";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['units_error'] = "Gagal menambahkan unit.";
        }

        header("Location: units.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER / SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Ambil Semua Unit
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.owner_branch_id,
            u.status,
            b.nama_cabang
        FROM units u
        INNER JOIN branches b
            ON b.id = u.owner_branch_id
        WHERE
            u.kode_unit LIKE ?
            OR u.nama_unit LIKE ?
            OR u.kategori LIKE ?
            OR b.nama_cabang LIKE ?
        ORDER BY
            b.nama_cabang ASC,
            u.kategori ASC,
            u.nama_unit ASC
    ");

    $keyword = '%' . $search . '%';

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.owner_branch_id,
            u.status,
            b.nama_cabang
        FROM units u
        INNER JOIN branches b
            ON b.id = u.owner_branch_id
        ORDER BY
            b.nama_cabang ASC,
            u.kategori ASC,
            u.nama_unit ASC
    ");
}

$units = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Grouping Cabang -> Kategori
|--------------------------------------------------------------------------
*/

$groupedUnits = [];

foreach ($units as $unit) {

    $branch = $unit['nama_cabang'];
    $category = $unit['kategori'];

    if (!isset($groupedUnits[$branch])) {
        $groupedUnits[$branch] = [];
    }

    if (!isset($groupedUnits[$branch][$category])) {
        $groupedUnits[$branch][$category] = [];
    }

    $groupedUnits[$branch][$category][] = $unit;
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

    <title>Unit - Sistem Scan Unit</title>

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

            --red: #dc2626;
            --red-soft: #fff1f2;

            --shadow: 0 8px 25px rgba(20,20,40,.06);
        }

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: var(--bg);
            color: var(--text);

        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select {

            font-family: inherit;

        }

        /* ==================================================
           APP
        ================================================== */

        .app {

            min-height: 100vh;
            display: flex;

        }

        /* ==================================================
           SIDEBAR
        ================================================== */

        .sidebar {

            width: 250px;

            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            background: var(--white);

            border-right: 1px solid var(--border);

            padding: 24px 16px;

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

            font-size: 19px;
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

        /* ==================================================
           MAIN
        ================================================== */

        .main {

            margin-left: 250px;

            width: calc(100% - 250px);

            min-height: 100vh;

            padding: 28px 32px;

        }

        /* ==================================================
           TOPBAR
        ================================================== */

        .topbar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;

        }

        .page-title h2 {

            font-size: 25px;

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

        /* ==================================================
           ACTION BAR
        ================================================== */

        .action-bar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 24px;

        }

        .search-form {

            flex: 1;

            display: flex;

            gap: 8px;

        }

        .search-input {

            width: 100%;

            height: 44px;

            border: 1px solid var(--border);

            border-radius: 11px;

            padding: 0 14px;

            background: var(--white);

            color: var(--text);

            outline: none;

            font-size: 13px;

        }

        .search-input:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(109,63,209,.10);

        }

        .btn {

            height: 44px;

            border: none;

            border-radius: 11px;

            padding: 0 17px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 700;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            transition: .2s;

            white-space: nowrap;

        }

        .btn-primary {

            background: var(--primary);

            color: white;

        }

        .btn-primary:hover {

            background: var(--primary-dark);

            transform: translateY(-1px);

        }

        .btn-light {

            background: var(--white);

            color: #4b5563;

            border: 1px solid var(--border);

        }

        .btn-light:hover {

            border-color: #d5c9f6;

            color: var(--primary);

        }

        /* ==================================================
           ALERT
        ================================================== */

        .alert {

            padding: 13px 15px;

            border-radius: 11px;

            margin-bottom: 18px;

            font-size: 13px;

        }

        .alert-success {

            background: var(--green-soft);

            color: #15803d;

            border: 1px solid #ccebd7;

        }

        .alert-error {

            background: var(--red-soft);

            color: #b91c1c;

            border: 1px solid #fecdd3;

        }

        /* ==================================================
           MODAL
        ================================================== */

        .modal-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background: rgba(15,23,42,.55);

            z-index: 500;

            padding: 20px;

            align-items: center;

            justify-content: center;

        }

        .modal-overlay.show {

            display: flex;

        }

        .modal {

            width: 100%;

            max-width: 520px;

            background: var(--white);

            border-radius: 18px;

            box-shadow: 0 20px 60px rgba(0,0,0,.20);

            overflow: hidden;

        }

        .modal-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 20px 22px;

            border-bottom: 1px solid var(--border);

        }

        .modal-header h3 {

            font-size: 17px;

        }

        .modal-close {

            width: 34px;
            height: 34px;

            border: none;

            border-radius: 9px;

            background: #f4f4f7;

            cursor: pointer;

            font-size: 18px;

            color: #6b7280;

        }

        .modal-body {

            padding: 22px;

        }

        .form-group {

            margin-bottom: 16px;

        }

        .form-group:last-child {

            margin-bottom: 0;

        }

        .form-label {

            display: block;

            margin-bottom: 7px;

            font-size: 12px;

            font-weight: 700;

            color: #374151;

        }

        .form-input {

            width: 100%;

            height: 44px;

            border: 1px solid var(--border);

            border-radius: 10px;

            padding: 0 12px;

            outline: none;

            font-size: 13px;

            background: white;

        }

        .form-input:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(109,63,209,.10);

        }

        .modal-footer {

            display: flex;

            justify-content: flex-end;

            gap: 9px;

            padding: 17px 22px;

            border-top: 1px solid var(--border);

        }

        /* ==================================================
           BRANCH SECTION
        ================================================== */

        .branch-section {

            margin-bottom: 28px;

        }

        .branch-header {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 14px;

        }

        .branch-icon {

            width: 38px;
            height: 38px;

            background: var(--primary-soft);

            color: var(--primary);

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 17px;

        }

        .branch-title {

            font-size: 17px;

            font-weight: 700;

        }

        .branch-subtitle {

            color: var(--muted);

            font-size: 11px;

            margin-top: 3px;

        }

        .own-label {

            margin-left: auto;

            font-size: 10px;

            background: var(--green-soft);

            color: var(--green);

            padding: 5px 9px;

            border-radius: 20px;

            font-weight: 700;

        }

        /* ==================================================
           CATEGORY
        ================================================== */

        .category-section {

            margin-bottom: 20px;

        }

        .category-title {

            font-size: 12px;

            color: #6b7280;

            font-weight: 700;

            margin-bottom: 9px;

            text-transform: uppercase;

            letter-spacing: .4px;

        }

        /* ==================================================
           UNIT GRID
        ================================================== */

        .unit-grid {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 13px;

        }

        .unit-card {

            background: var(--white);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 16px;

            box-shadow: var(--shadow);

            transition: .2s;

            min-width: 0;

        }

        .unit-card:hover {

            transform: translateY(-2px);

            border-color: #d9cef5;

            box-shadow:
                0 10px 28px
                rgba(20,20,40,.08);

        }

        .unit-top {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 10px;

        }

        .unit-code {

            display: inline-flex;

            align-items: center;

            background: var(--primary-soft);

            color: var(--primary);

            border-radius: 8px;

            padding: 5px 8px;

            font-size: 11px;

            font-weight: 700;

        }

        .status {

            font-size: 10px;

            font-weight: 700;

            padding: 5px 8px;

            border-radius: 20px;

            white-space: nowrap;

        }

        .status-available {

            color: var(--green);

            background: var(--green-soft);

        }

        .status-rented {

            color: var(--orange);

            background: var(--orange-soft);

        }

        .unit-name {

            margin-top: 14px;

            font-size: 15px;

            font-weight: 700;

            line-height: 1.35;

        }

        .unit-meta {

            margin-top: 7px;

            color: var(--muted);

            font-size: 11px;

            line-height: 1.5;

        }

        .unit-actions {

            display: flex;

            gap: 7px;

            margin-top: 15px;

        }

        .unit-action {

            flex: 1;

            min-width: 0;

            height: 36px;

            border-radius: 9px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            font-size: 11px;

            font-weight: 700;

            transition: .2s;

        }

        .edit-action {

            background: var(--primary-soft);

            color: var(--primary);

        }

        .edit-action:hover {

            background: #e5ddff;

        }

        .qr-action {

            background: #f4f4f7;

            color: #4b5563;

        }

        .qr-action:hover {

            background: #e9e9ee;

        }

        /* ==================================================
           EMPTY STATE
        ================================================== */

        .empty {

            background: var(--white);

            border: 1px dashed #d7d7df;

            border-radius: 15px;

            padding: 45px 20px;

            text-align: center;

        }

        .empty-icon {

            width: 50px;
            height: 50px;

            margin: 0 auto 14px;

            border-radius: 14px;

            background: var(--primary-soft);

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }

        .empty h3 {

            font-size: 15px;

            margin-bottom: 6px;

        }

        .empty p {

            color: var(--muted);

            font-size: 12px;

        }

        /* ==================================================
           MOBILE NAV
        ================================================== */

        .mobile-nav {

            display: none;

        }

        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1150px) {

            .unit-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }

        }

        @media (max-width: 760px) {

            .sidebar {

                display: none;

            }

            .main {

                margin-left: 0;

                width: 100%;

                padding:
                    20px
                    15px
                    90px;

            }

            .topbar {

                align-items: flex-start;

            }

            .page-title h2 {

                font-size: 21px;

            }

            .page-title p {

                font-size: 12px;

            }

            .branch-badge {

                display: none;

            }

            .action-bar {

                flex-direction: column;

                align-items: stretch;

            }

            .search-form {

                width: 100%;

            }

            .action-bar > .btn {

                width: 100%;

            }

            .unit-grid {

                grid-template-columns: 1fr;

            }

            .branch-title {

                font-size: 15px;

            }

            .own-label {

                font-size: 9px;

            }

            .modal-overlay {

                align-items: flex-end;

                padding: 10px;

            }

            .modal {

                max-height: 92vh;

                overflow-y: auto;

                border-radius: 18px 18px 14px 14px;

            }

            .mobile-nav {

                display: flex;

                position: fixed;

                left: 10px;

                right: 10px;

                bottom: 10px;

                height: 64px;

                background:
                    rgba(255,255,255,.96);

                border:
                    1px solid
                    var(--border);

                border-radius: 16px;

                box-shadow:
                    0 10px 30px
                    rgba(0,0,0,.12);

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

            .unit-card {

                padding: 14px;

            }

            .unit-name {

                font-size: 14px;

            }

            .unit-actions {

                flex-direction: column;

            }

            .unit-action {

                width: 100%;

            }

        }

    </style>

</head>

<body>

<div class="app">


    <!-- ==================================================
         SIDEBAR
    ================================================== -->

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

            <a href="dashboard.php">
                <span class="menu-icon">⌂</span>
                Dashboard
            </a>

            <a href="units.php" class="active">
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

                <a
                    href="logout.php"
                    class="logout"
                >

                    <span class="menu-icon">
                        ↪
                    </span>

                    Keluar

                </a>

            </nav>

        </div>

    </aside>


    <!-- ==================================================
         MAIN
    ================================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <div class="topbar">

            <div class="page-title">

                <h2>
                    Unit
                </h2>

                <p>
                    Kelola unit rental berdasarkan cabang dan kategori.
                </p>

            </div>


            <div class="branch-badge">

                <span class="branch-dot"></span>

                <?= htmlspecialchars($branchName) ?>

            </div>

        </div>


        <!-- ALERT -->

        <?php if ($success): ?>

            <div class="alert alert-success">

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- ACTION BAR -->

        <div class="action-bar">


            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Cari kode, nama, kategori, atau cabang..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button
                    type="submit"
                    class="btn btn-light"
                >
                    Cari
                </button>

            </form>


            <button
                type="button"
                class="btn btn-primary"
                onclick="openModal()"
            >
                + Tambah Unit
            </button>


        </div>


        <!-- UNIT LIST -->

        <?php if (empty($groupedUnits)): ?>

            <div class="empty">

                <div class="empty-icon">
                    ▣
                </div>

                <h3>
                    Belum ada unit
                </h3>

                <p>
                    Tambahkan unit pertama untuk cabang Anda.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($groupedUnits as $branch => $categories): ?>

                <?php

                /*
                |--------------------------------------------------------------------------
                | Cari apakah ini cabang sendiri
                |--------------------------------------------------------------------------
                */

                $isOwnBranch = false;

                foreach ($categories as $categoryUnits) {

                    foreach ($categoryUnits as $unit) {

                        if ((int) $unit['owner_branch_id'] === (int) $branchId) {

                            $isOwnBranch = true;

                            break 2;

                        }

                    }

                }

                ?>

                <section class="branch-section">


                    <div class="branch-header">

                        <div class="branch-icon">
                            ⌂
                        </div>


                        <div>

                            <div class="branch-title">

                                <?= htmlspecialchars($branch) ?>

                            </div>

                            <div class="branch-subtitle">

                                <?= count(array_merge(...array_values($categories))) ?>
                                unit terdaftar

                            </div>

                        </div>


                        <?php if ($isOwnBranch): ?>

                            <span class="own-label">
                                Cabang Anda
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php foreach ($categories as $category => $categoryUnits): ?>

                        <div class="category-section">


                            <div class="category-title">

                                <?= htmlspecialchars($category) ?>

                            </div>


                            <div class="unit-grid">


                                <?php foreach ($categoryUnits as $unit): ?>

                                    <?php

                                    $isOwnUnit =
                                        (int) $unit['owner_branch_id']
                                        ===
                                        (int) $branchId;

                                    $isAvailable =
                                        $unit['status'] === 'TERSEDIA';

                                    ?>


                                    <article class="unit-card">


                                        <div class="unit-top">


                                            <span class="unit-code">

                                                <?= htmlspecialchars($unit['kode_unit']) ?>

                                            </span>


                                            <?php if ($isAvailable): ?>

                                                <span class="status status-available">

                                                    TERSEDIA

                                                </span>

                                            <?php else: ?>

                                                <span class="status status-rented">

                                                    DISEWAKAN

                                                </span>

                                            <?php endif; ?>


                                        </div>


                                        <div class="unit-name">

                                            <?= htmlspecialchars($unit['nama_unit']) ?>

                                        </div>


                                        <div class="unit-meta">

                                            Jumlah:
                                            <strong>
                                                <?= (int) $unit['jumlah'] ?>
                                            </strong>

                                            <br>

                                            Pemilik:
                                            <?= htmlspecialchars($unit['nama_cabang']) ?>

                                        </div>


                                        <div class="unit-actions">


                                            <?php if ($isOwnUnit): ?>

                                                <a
                                                    href="edit-unit.php?id=<?= (int) $unit['id'] ?>"
                                                    class="unit-action edit-action"
                                                >
                                                    Edit Unit
                                                </a>

                                            <?php endif; ?>


                                            <a
                                                href="qr.php?id=<?= (int) $unit['id'] ?>"
                                                class="unit-action qr-action"
                                            >
                                                Lihat QR
                                            </a>


                                        </div>


                                    </article>

                                <?php endforeach; ?>


                            </div>

                        </div>

                    <?php endforeach; ?>


                </section>

            <?php endforeach; ?>


        <?php endif; ?>


    </main>


    <!-- ==================================================
         MODAL TAMBAH UNIT
    ================================================== -->

    <div
        class="modal-overlay"
        id="unitModal"
    >

        <div class="modal">


            <div class="modal-header">

                <h3>
                    Tambah Unit
                </h3>

                <button
                    type="button"
                    class="modal-close"
                    onclick="closeModal()"
                >
                    ×
                </button>

            </div>


            <form
                method="POST"
                action="units.php"
            >

                <div class="modal-body">


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="tambah_unit"
                    >


                    <div class="form-group">

                        <label class="form-label">
                            Kode Unit
                        </label>

                        <input
                            type="text"
                            name="kode_unit"
                            class="form-input"
                            placeholder="Contoh: A1"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            Nama Unit
                        </label>

                        <input
                            type="text"
                            name="nama_unit"
                            class="form-input"
                            placeholder="Contoh: Projector Epson"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            Kategori
                        </label>

                        <input
                            type="text"
                            name="kategori"
                            class="form-input"
                            placeholder="Contoh: Projector"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            Jumlah
                        </label>

                        <input
                            type="number"
                            name="jumlah"
                            class="form-input"
                            min="1"
                            value="1"
                            required
                        >

                    </div>


                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="closeModal()"
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Simpan Unit
                    </button>

                </div>

            </form>


        </div>

    </div>


    <!-- ==================================================
         MOBILE NAVIGATION
    ================================================== -->

    <nav class="mobile-nav">

        <a href="dashboard.php">

            <span class="mobile-nav-icon">
                ⌂
            </span>

            Home

        </a>


        <a
            href="units.php"
            class="active"
        >

            <span class="mobile-nav-icon">
                ▣
            </span>

            Unit

        </a>


        <a href="scan-keluar.php">

            <span class="mobile-nav-icon">
                ⌕
            </span>

            Scan

        </a>


        <a href="unit-masuk.php">

            <span class="mobile-nav-icon">
                ↩
            </span>

            Masuk

        </a>

    </nav>


</div>


<script>

    function openModal() {

        document
            .getElementById('unitModal')
            .classList
            .add('show');

    }


    function closeModal() {

        document
            .getElementById('unitModal')
            .classList
            .remove('show');

    }


    /*
    |--------------------------------------------------------------------------
    | Klik area luar modal
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('unitModal')
        .addEventListener('click', function(event) {

            if (event.target === this) {

                closeModal();

            }

        });


    /*
    |--------------------------------------------------------------------------
    | ESC untuk menutup modal
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            closeModal();

        }

    });

</script>

</body>
</html>