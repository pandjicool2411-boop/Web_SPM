<?php

require_once "../config/auth.php";

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
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f4f6f8;
            color: #172033;
        }

        a {
            color: inherit;
        }

        .container {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            padding: 24px;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.04);
        }

        .header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .header-left {
            min-width: 0;
        }

        .welcome-label {
            margin: 0 0 7px;
            color: #2563eb;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.04em;
        }

        .header h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .header-description {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            background: #fff1f2;
            color: #dc2626;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
            transition: 0.2s;
        }

        .logout:hover {
            background: #fee2e2;
        }

        .user-info {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 20px;
        }

        .info-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            color: #64748b;
            font-size: 13px;
        }

        .info-item strong {
            color: #172033;
        }

        /* =========================
           SECTION
        ========================= */

        .section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 13px;
        }

        .section-title {
            margin: 0;
            font-size: 19px;
            line-height: 1.3;
        }

        .section-description {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        /* =========================
           MENU GRID
        ========================= */

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        /* =========================
           MENU CARD
        ========================= */

        .menu-card {
            position: relative;
            display: block;
            min-height: 155px;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            color: #172033;
            text-decoration: none;
            overflow: hidden;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .menu-card:hover {
            transform: translateY(-3px);
            border-color: #cbd5e1;
            box-shadow:
                0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .menu-card:active {
            transform: translateY(-1px);
        }

        .icon-box {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            background: #eff6ff;
            border-radius: 12px;
            font-size: 22px;
        }

        .menu-card h3 {
            margin: 0 28px 7px 0;
            font-size: 16px;
            line-height: 1.35;
        }

        .menu-card p {
            margin: 0;
            max-width: 290px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .arrow {
            position: absolute;
            right: 18px;
            bottom: 18px;
            color: #94a3b8;
            font-size: 19px;
            transition: 0.2s;
        }

        .menu-card:hover .arrow {
            color: #2563eb;
            transform: translateX(4px);
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 900px) {

            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 650px) {

            .container {
                padding: 14px;
            }

            .header {
                padding: 20px;
                margin-bottom: 24px;
                border-radius: 16px;
            }

            .header-top {
                flex-direction: column;
                gap: 15px;
            }

            .header h1 {
                font-size: 25px;
            }

            .header-description {
                font-size: 13px;
            }

            .logout {
                width: 100%;
            }

            .user-info {
                display: grid;
                grid-template-columns: 1fr;
                gap: 7px;
            }

            .info-item {
                width: 100%;
            }

            .section {
                margin-bottom: 26px;
            }

            .section-title {
                font-size: 17px;
            }

            .section-description {
                font-size: 12px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .menu-card {
                min-height: 140px;
                padding: 17px;
            }

            .icon-box {
                width: 42px;
                height: 42px;
                margin-bottom: 13px;
                font-size: 20px;
            }

            .menu-card h3 {
                font-size: 15px;
            }

            .menu-card p {
                font-size: 12px;
            }

        }

    </style>

</head>

<body>

<div class="container">


    <!-- =========================
         HEADER
    ========================== -->

    <div class="header">

        <div class="header-top">

            <div class="header-left">

                <p class="welcome-label">
                    SISTEM SCAN UNIT
                </p>

                <h1>
                    Dashboard
                </h1>

                <p class="header-description">
                    Kelola unit, peminjaman, scan QR,
                    dan perpindahan unit dengan mudah.
                </p>

            </div>

            <a
                href="logout.php"
                class="logout"
            >
                Keluar
            </a>

        </div>


        <div class="user-info">

            <div class="info-item">

                👤

                <span>
                    Pengguna:
                    <strong>
                        <?= htmlspecialchars(
                            $_SESSION["nama"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </strong>
                </span>

            </div>


            <div class="info-item">

                🔑

                <span>
                    Username:
                    <strong>
                        <?= htmlspecialchars(
                            $_SESSION["username"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </strong>
                </span>

            </div>


            <div class="info-item">

                🏢

                <span>
                    Cabang:
                    <strong>
                        <?= htmlspecialchars(
                            $_SESSION["nama_cabang"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </strong>
                </span>

            </div>

        </div>

    </div>


    <!-- =========================
         MANAJEMEN UNIT
    ========================== -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    📦 Manajemen Unit
                </h2>

                <p class="section-description">
                    Kelola dan lihat seluruh data unit.
                </p>

            </div>

        </div>


        <div class="menu-grid">


            <a
                href="units.php"
                class="menu-card"
            >

                <div class="icon-box">
                    📋
                </div>

                <h3>
                    Daftar Unit
                </h3>

                <p>
                    Melihat seluruh unit yang
                    terdaftar dalam sistem.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="units.php"
                class="menu-card"
            >

                <div class="icon-box">
                    ➕
                </div>

                <h3>
                    Tambah Unit
                </h3>

                <p>
                    Mendaftarkan unit baru
                    ke cabang Anda.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="qr.php"
                class="menu-card"
            >

                <div class="icon-box">
                    🔳
                </div>

                <h3>
                    QR Unit
                </h3>

                <p>
                    Melihat dan mencetak
                    QR Code unit.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


        </div>

    </div>


    <!-- =========================
         SCAN
    ========================== -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    📷 Scan Unit
                </h2>

                <p class="section-description">
                    Gunakan kamera untuk membaca QR Code.
                </p>

            </div>

        </div>


        <div class="menu-grid">


            <a
                href="scan-keluar.php"
                class="menu-card"
            >

                <div class="icon-box">
                    📤
                </div>

                <h3>
                    Scan Unit Keluar
                </h3>

                <p>
                    Scan unit sebelum
                    disewakan kepada penyewa.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="scan-cek.php"
                class="menu-card"
            >

                <div class="icon-box">
                    🔍
                </div>

                <h3>
                    Scan Unit Cek
                </h3>

                <p>
                    Cek informasi, cabang,
                    dan status unit.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


        </div>

    </div>


    <!-- =========================
         OPERASIONAL
    ========================== -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    🔄 Operasional
                </h2>

                <p class="section-description">
                    Kelola pergerakan dan perpindahan unit.
                </p>

            </div>

        </div>


        <div class="menu-grid">


            <a
                href="unit-masuk.php"
                class="menu-card"
            >

                <div class="icon-box">
                    📥
                </div>

                <h3>
                    Unit Masuk
                </h3>

                <p>
                    Konfirmasi unit yang
                    sudah kembali.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="transfer.php"
                class="menu-card"
            >

                <div class="icon-box">
                    🔄
                </div>

                <h3>
                    Transfer / Oper Unit
                </h3>

                <p>
                    Memindahkan unit
                    ke cabang lain.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


        </div>

    </div>


    <!-- =========================
         RIWAYAT
    ========================== -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    📋 Riwayat
                </h2>

                <p class="section-description">
                    Melihat aktivitas unit.
                </p>

            </div>

        </div>


        <div class="menu-grid">


            <a
                href="riwayat.php"
                class="menu-card"
            >

                <div class="icon-box">
                    🕘
                </div>

                <h3>
                    Riwayat Aktivitas
                </h3>

                <p>
                    Lihat catatan keluar,
                    masuk, dan transfer unit.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


        </div>

    </div>


    <!-- =========================
         MANAJEMEN SISTEM
    ========================== -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    ⚙️ Manajemen Sistem
                </h2>

                <p class="section-description">
                    Pengaturan data dasar sistem.
                </p>

            </div>

        </div>


        <div class="menu-grid">


            <a
                href="branches.php"
                class="menu-card"
            >

                <div class="icon-box">
                    🏢
                </div>

                <h3>
                    Manajemen Cabang
                </h3>

                <p>
                    Menambah dan melihat
                    daftar cabang.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="register.php"
                class="menu-card"
            >

                <div class="icon-box">
                    👤
                </div>

                <h3>
                    Registrasi Akun
                </h3>

                <p>
                    Membuat akun pengguna
                    untuk cabang.
                </p>

                <span class="arrow">
                    →
                </span>

            </a>


        </div>

    </div>


</div>

</body>

</html>