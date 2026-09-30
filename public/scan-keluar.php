<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$branchId = user_branch_id();

if (!isset($_SESSION['scan_keluar_units'])) {
    $_SESSION['scan_keluar_units'] = [];
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| PESAN DARI PROSES KELUAR
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    $success = 'Unit berhasil disewakan.';

    if (isset($_GET['rental_id'])) {
        $success .=
            ' ID transaksi: #' .
            htmlspecialchars($_GET['rental_id']);
    }
}

if (isset($_SESSION['scan_keluar_error'])) {

    $error = $_SESSION['scan_keluar_error'];

    unset($_SESSION['scan_keluar_error']);
}

/*
|--------------------------------------------------------------------------
| PROSES POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {

        $error =
            'Token keamanan tidak valid. Silakan coba lagi.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | SCAN UNIT
        |--------------------------------------------------------------------------
        */

        if ($action === 'scan') {

            $kodeUnit = trim(
                $_POST['kode_unit'] ?? ''
            );

            if ($kodeUnit === '') {

                $error =
                    'Kode unit tidak boleh kosong.';

            } else {

                try {

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
                            htmlspecialchars($kodeUnit) .
                            '" tidak ditemukan.';

                    } elseif (
                        (int)$unit['owner_branch_id']
                        !==
                        (int)$branchId
                    ) {

                        $error =
                            'Unit "' .
                            htmlspecialchars($kodeUnit) .
                            '" bukan milik cabang Anda.';

                    } elseif (
                        $unit['status'] !== 'TERSEDIA'
                    ) {

                        $error =
                            'Unit "' .
                            htmlspecialchars($kodeUnit) .
                            '" sedang disewakan.';

                    } else {

                        $alreadyScanned = false;

                        foreach (
                            $_SESSION['scan_keluar_units']
                            as $scannedUnit
                        ) {

                            if (
                                (int)$scannedUnit['id']
                                ===
                                (int)$unit['id']
                            ) {

                                $alreadyScanned = true;
                                break;
                            }
                        }

                        if ($alreadyScanned) {

                            $error =
                                'Unit "' .
                                htmlspecialchars($kodeUnit) .
                                '" sudah ada di daftar scan.';

                        } else {

                            $_SESSION[
                                'scan_keluar_units'
                            ][] = [

                                'id' =>
                                    (int)$unit['id'],

                                'kode_unit' =>
                                    $unit['kode_unit'],

                                'nama_unit' =>
                                    $unit['nama_unit'],

                                'kategori' =>
                                    $unit['kategori'],

                                'jumlah' =>
                                    (int)$unit['jumlah']
                            ];

                            $success =
                                'Unit "' .
                                htmlspecialchars($kodeUnit) .
                                '" berhasil ditambahkan.';
                        }
                    }

                } catch (PDOException $e) {

                    $error =
                        'Terjadi kesalahan saat memproses unit.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HAPUS UNIT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'remove') {

            $unitId =
                (int)($_POST['unit_id'] ?? 0);

            if ($unitId > 0) {

                $newList = [];

                foreach (
                    $_SESSION['scan_keluar_units']
                    as $scannedUnit
                ) {

                    if (
                        (int)$scannedUnit['id']
                        !==
                        $unitId
                    ) {

                        $newList[] =
                            $scannedUnit;
                    }
                }

                $_SESSION[
                    'scan_keluar_units'
                ] = $newList;
            }

            header(
                'Location: scan-keluar.php'
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | KOSONGKAN SEMUA
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'clear') {

            $_SESSION[
                'scan_keluar_units'
            ] = [];

            header(
                'Location: scan-keluar.php'
            );

            exit;
        }
    }
}

$scannedUnits =
    $_SESSION['scan_keluar_units'];

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Scan Unit Keluar</title>

    <script
        src="https://unpkg.com/html5-qrcode"
        type="text/javascript"
    ></script>

    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            width: 100%;
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f3f4f6;
            color: #111827;

            overflow-x: hidden;
        }

        button,
        input {
            font-family: inherit;
        }

        /* =========================================================
           CONTAINER
        ========================================================= */

        .container {
            width: 100%;
            max-width: 1080px;

            margin: 0 auto;

            padding:
                28px
                22px
                60px;
        }

        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            display: flex;

            justify-content: space-between;
            align-items: center;

            gap: 18px;

            margin-bottom: 22px;
        }

        .page-title {
            min-width: 0;
        }

        .page-title h1 {
            margin: 0;

            font-size: 28px;
            line-height: 1.2;

            letter-spacing: -0.4px;
        }

        .page-title p {
            margin: 8px 0 0;

            color: #6b7280;

            font-size: 14px;
            line-height: 1.5;
        }

        .back-btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            text-decoration: none;

            background: #374151;
            color: white;

            padding:
                11px
                16px;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 600;

            white-space: nowrap;

            transition:
                background .2s,
                transform .2s;
        }

        .back-btn:hover {
            background: #1f2937;
        }

        .back-btn:active {
            transform: scale(.98);
        }

        /* =========================================================
           CARD UMUM
        ========================================================= */

        .card,
        .scanner-card,
        .selected-card {

            width: 100%;

            background: white;

            border-radius: 16px;

            box-shadow:
                0 4px 18px
                rgba(15, 23, 42, .06);
        }

        .card {
            padding: 22px;
            margin-bottom: 18px;
        }

        .scanner-card {
            padding: 24px;
            margin-bottom: 18px;
        }

        .selected-card {
            padding: 24px;
        }

        /* =========================================================
           CABANG
        ========================================================= */

        .branch-name {
            font-size: 20px;
            font-weight: 700;

            margin-bottom: 6px;
        }

        .branch-description {
            color: #6b7280;

            font-size: 14px;
            line-height: 1.6;
        }

        /* =========================================================
           MESSAGE
        ========================================================= */

        .message {
            width: 100%;

            padding:
                13px
                15px;

            border-radius: 10px;

            margin-bottom: 18px;

            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;

            border:
                1px solid
                #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;

            border:
                1px solid
                #fecaca;
        }

        /* =========================================================
           SCANNER
        ========================================================= */

        .scanner-card h2 {
            margin: 0 0 8px;

            font-size: 20px;
        }

        .scanner-description {
            margin:
                0 0 20px;

            color: #6b7280;

            font-size: 14px;
            line-height: 1.6;
        }

        .scanner-wrapper {
            width: 100%;

            display: flex;
            justify-content: center;
        }

        #reader {
            width: 100%;
            max-width: 500px;

            overflow: hidden;

            border-radius: 14px;
        }

        #reader video {
            width: 100% !important;
            max-width: 100% !important;

            border-radius: 14px;
        }

        #reader img {
            max-width: 100%;
        }

        #reader button {
            cursor: pointer;

            border: none;

            padding:
                9px
                13px;

            border-radius: 8px;

            background: #2563eb;
            color: white;

            font-weight: 600;
        }

        /* =========================================================
           MANUAL INPUT
        ========================================================= */

        .manual-section {
            width: 100%;

            max-width: 700px;

            margin:
                22px auto
                0;
        }

        .manual-title {
            margin:
                0 0 9px;

            font-size: 14px;
            font-weight: 700;

            color: #374151;
        }

        .manual-form {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                auto;

            gap: 10px;

            width: 100%;
        }

        .manual-form input {
            width: 100%;
            min-width: 0;

            padding:
                13px
                14px;

            border:
                1px solid
                #d1d5db;

            border-radius: 10px;

            font-size: 15px;

            outline: none;

            background: #fff;

            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .manual-form input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .10);
        }

        /* =========================================================
           BUTTON
        ========================================================= */

        .btn {
            border: none;

            cursor: pointer;

            padding:
                12px
                17px;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 700;

            text-decoration: none;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 6px;

            white-space: nowrap;

            transition:
                background .2s,
                transform .15s;
        }

        .btn:active {
            transform: scale(.98);
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-success:hover {
            background: #15803d;
        }

        /* =========================================================
           SELECTED HEADER
        ========================================================= */

        .selected-header {

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 12px;

            padding-bottom: 17px;

            border-bottom:
                1px solid
                #e5e7eb;
        }

        .selected-title {
            margin: 0;

            font-size: 20px;
        }

        .count-badge {

            flex-shrink: 0;

            background: #eff6ff;
            color: #1d4ed8;

            padding:
                7px
                11px;

            border-radius: 999px;

            font-size: 13px;
            font-weight: 700;
        }

        /* =========================================================
           CLEAR
        ========================================================= */

        .clear-row {
            margin:
                16px 0;
        }

        /* =========================================================
           UNIT LIST
        ========================================================= */

        .unit-list {
            display: flex;

            flex-direction: column;

            gap: 10px;

            width: 100%;
        }

        .unit-item {

            width: 100%;

            display: grid;

            grid-template-columns:
                28px
                minmax(0, 1fr)
                38px;

            align-items: center;

            gap: 12px;

            padding: 14px;

            border:
                1px solid
                #e5e7eb;

            border-radius: 12px;

            background: #fff;

            min-width: 0;

            transition:
                border-color .2s,
                background .2s;
        }

        .unit-item:hover {
            border-color: #bfdbfe;
            background: #f8fbff;
        }

        /* =========================================================
           CHECKBOX
        ========================================================= */

        .unit-checkbox {

            width: 20px;
            height: 20px;

            margin: 0;

            cursor: pointer;

            accent-color: #2563eb;
        }

        /* =========================================================
           UNIT INFO
        ========================================================= */

        .unit-info {
            min-width: 0;
            overflow: hidden;
        }

        .unit-code {
            font-size: 17px;
            font-weight: 800;

            color: #111827;

            margin-bottom: 4px;

            word-break: break-word;
        }

        .unit-name {
            font-size: 14px;

            color: #374151;

            margin-bottom: 5px;

            line-height: 1.4;

            overflow-wrap: anywhere;
        }

        .unit-meta {

            display: flex;

            flex-wrap: wrap;

            gap: 5px 14px;

            color: #6b7280;

            font-size: 13px;

            line-height: 1.4;
        }

        /* =========================================================
           REMOVE
        ========================================================= */

        .remove-btn {

            width: 36px;
            height: 36px;

            border: none;

            border-radius: 50%;

            background: #fee2e2;
            color: #b91c1c;

            cursor: pointer;

            font-size: 19px;
            font-weight: 700;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            transition:
                background .2s,
                transform .15s;
        }

        .remove-btn:hover {
            background: #fecaca;
        }

        .remove-btn:active {
            transform: scale(.92);
        }

        /* =========================================================
           RENTAL FORM
        ========================================================= */

        .rental-form-section {

            width: 100%;

            margin-top: 20px;

            padding-top: 20px;

            border-top:
                1px solid
                #e5e7eb;
        }

        .renter-label {

            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: 700;
        }

        .renter-input {

            width: 100%;

            padding:
                13px
                14px;

            border:
                1px solid
                #d1d5db;

            border-radius: 10px;

            font-size: 15px;

            outline: none;

            margin-bottom: 13px;

            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .renter-input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .10);
        }

        .submit-rental {

            width: 100%;

            min-height: 50px;

            border: none;

            border-radius: 10px;

            background: #16a34a;
            color: white;

            font-size: 15px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background .2s,
                transform .15s;
        }

        .submit-rental:hover {
            background: #15803d;
        }

        .submit-rental:active {
            transform: scale(.99);
        }

        /* =========================================================
           EMPTY
        ========================================================= */

        .empty {

            text-align: center;

            padding:
                38px
                15px;

            color: #6b7280;

            font-size: 14px;

            line-height: 1.7;
        }

        /* =========================================================
           TABLET
        ========================================================= */

        @media (max-width: 760px) {

            .container {
                padding:
                    20px
                    14px
                    45px;
            }

            .topbar {
                align-items: flex-start;

                flex-direction: column;

                gap: 13px;
            }

            .back-btn {
                width: 100%;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .card,
            .scanner-card,
            .selected-card {
                border-radius: 13px;
            }

            .card {
                padding: 18px;
            }

            .scanner-card,
            .selected-card {
                padding: 18px;
            }

            .manual-form {
                grid-template-columns: 1fr;
            }

            .manual-form button {
                width: 100%;
            }

            .selected-header {
                align-items: flex-start;
            }

            .selected-title {
                font-size: 18px;
            }
        }

        /* =========================================================
           HP
        ========================================================= */

        @media (max-width: 480px) {

            .container {
                padding:
                    14px
                    10px
                    30px;
            }

            .topbar {
                margin-bottom: 16px;
            }

            .page-title h1 {
                font-size: 21px;
            }

            .page-title p {
                font-size: 13px;
            }

            .branch-name {
                font-size: 18px;
            }

            .branch-description {
                font-size: 13px;
            }

            .card,
            .scanner-card,
            .selected-card {
                padding: 14px;

                border-radius: 12px;
            }

            .scanner-card h2 {
                font-size: 18px;
            }

            .scanner-description {
                font-size: 13px;
            }

            #reader {
                max-width: 100%;
            }

            .unit-item {

                grid-template-columns:
                    25px
                    minmax(0, 1fr)
                    34px;

                gap: 9px;

                padding: 11px;
            }

            .unit-code {
                font-size: 16px;
            }

            .unit-name {
                font-size: 13px;
            }

            .unit-meta {
                font-size: 12px;

                gap:
                    4px
                    9px;
            }

            .remove-btn {
                width: 32px;
                height: 32px;

                font-size: 17px;
            }

            .count-badge {
                font-size: 12px;

                padding:
                    6px
                    9px;
            }

            .clear-row .btn {
                width: 100%;
            }

            .rental-form-section {
                margin-top: 16px;
                padding-top: 16px;
            }

            .submit-rental {
                min-height: 48px;
            }
        }

        /* =========================================================
           HP SANGAT KECIL
        ========================================================= */

        @media (max-width: 360px) {

            .container {
                padding-left: 8px;
                padding-right: 8px;
            }

            .page-title h1 {
                font-size: 20px;
            }

            .unit-item {

                grid-template-columns:
                    23px
                    minmax(0, 1fr)
                    32px;

                gap: 7px;

                padding: 10px;
            }

            .unit-code {
                font-size: 15px;
            }

            .unit-name {
                font-size: 12px;
            }

            .unit-meta {
                display: block;
            }

            .unit-meta span {
                display: block;

                margin-bottom: 2px;
            }

            .remove-btn {
                width: 30px;
                height: 30px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="topbar">

        <div class="page-title">

            <h1>
                📷 Scan Unit Keluar
            </h1>

            <p>
                Pilih satu atau beberapa unit untuk disewakan.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Kembali ke Dashboard
        </a>

    </div>


    <!-- =========================================================
         CABANG
    ========================================================== -->

    <div class="card">

        <div class="branch-name">

            Cabang:
            <?= htmlspecialchars(
                user_branch_name()
            ) ?>

        </div>

        <div class="branch-description">

            Hanya unit milik cabang ini dan berstatus
            <strong>TERSEDIA</strong>
            yang dapat disewakan.

        </div>

    </div>


    <!-- =========================================================
         MESSAGE
    ========================================================== -->

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


    <!-- =========================================================
         SCANNER
    ========================================================== -->

    <div class="scanner-card">

        <h2>
            📷 Scan QR Unit
        </h2>

        <p class="scanner-description">

            Arahkan kamera ke QR unit.
            QR berisi kode unit seperti
            <strong>A1</strong>,
            <strong>A2</strong>,
            dan seterusnya.

        </p>

        <div class="scanner-wrapper">

            <div id="reader"></div>

        </div>

        <div class="manual-section">

            <div class="manual-title">
                Atau masukkan kode unit secara manual
            </div>

            <form
                method="POST"
                action="scan-keluar.php"
                class="manual-form"
                id="manualScanForm"
            >

                <input
                    type="text"
                    name="kode_unit"
                    id="kode_unit"
                    placeholder="Contoh: A1"
                    autocomplete="off"
                    required
                >

                <input
                    type="hidden"
                    name="action"
                    value="scan"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrf_token()
                    ) ?>"
                >

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    + Tambah Unit
                </button>

            </form>

        </div>

    </div>


    <!-- =========================================================
         UNIT YANG DIPILIH
    ========================================================== -->

    <div class="selected-card">

        <div class="selected-header">

            <h2 class="selected-title">
                Unit yang Dipilih
            </h2>

            <span class="count-badge">
                <?= count($scannedUnits) ?> unit
            </span>

        </div>


        <?php if (
            count($scannedUnits) === 0
        ): ?>

            <div class="empty">

                Belum ada unit yang dipilih.

                <br>

                Silakan scan QR atau masukkan
                kode unit secara manual.

            </div>

        <?php else: ?>


            <!-- =================================================
                 CLEAR
            ================================================== -->

            <div class="clear-row">

                <form
                    method="POST"
                    action="scan-keluar.php"
                    onsubmit="
                        return confirm(
                            'Kosongkan semua unit yang sudah dipilih?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="action"
                        value="clear"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            csrf_token()
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        🗑 Kosongkan Semua
                    </button>

                </form>

            </div>


            <!-- =================================================
                 RENTAL
            ================================================== -->

            <form
                method="POST"
                action="proses-keluar.php"
                id="rentalForm"
            >

                <div class="unit-list">

                    <?php foreach (
                        $scannedUnits
                        as $unit
                    ): ?>

                        <div class="unit-item">

                            <!-- CHECKBOX -->

                            <input
                                type="checkbox"
                                name="unit_ids[]"
                                value="<?= (int)$unit['id'] ?>"
                                class="unit-checkbox"
                                checked
                            >


                            <!-- INFO -->

                            <div class="unit-info">

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

                                <div class="unit-meta">

                                    <span>
                                        Kategori:
                                        <?= htmlspecialchars(
                                            $unit['kategori']
                                        ) ?>
                                    </span>

                                    <span>
                                        Jumlah:
                                        <?= (int)$unit['jumlah'] ?>
                                    </span>

                                </div>

                            </div>


                            <!-- REMOVE -->

                            <button
                                type="button"
                                class="remove-btn"
                                title="Hapus unit"
                                onclick="
                                    removeScannedUnit(
                                        <?= (int)$unit['id'] ?>
                                    )
                                "
                            >
                                ×
                            </button>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- =================================================
                     KODE PENYEWA
                ================================================== -->

                <div class="rental-form-section">

                    <label
                        for="renter_code"
                        class="renter-label"
                    >
                        Kode Penyewa
                    </label>

                    <input
                        type="text"
                        name="renter_code"
                        id="renter_code"
                        class="renter-input"
                        placeholder="Masukkan kode penyewa"
                        autocomplete="off"
                        required
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            csrf_token()
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="submit-rental"
                        onclick="return validateRental();"
                    >
                        🚚 Sewakan Unit yang Dipilih
                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| SCAN QR
|--------------------------------------------------------------------------
*/

let scanProcessing = false;

function onScanSuccess(
    decodedText,
    decodedResult
) {

    if (scanProcessing) {
        return;
    }

    scanProcessing = true;

    const kodeInput =
        document.getElementById(
            'kode_unit'
        );

    const manualForm =
        document.getElementById(
            'manualScanForm'
        );

    if (!kodeInput || !manualForm) {
        return;
    }

    kodeInput.value =
        decodedText.trim();

    manualForm.submit();
}


/*
|--------------------------------------------------------------------------
| QR SCANNER
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const readerElement =
            document.getElementById(
                'reader'
            );

        if (!readerElement) {
            return;
        }

        try {

            const html5QrCode =
                new Html5Qrcode(
                    "reader"
                );

            Html5Qrcode
                .getCameras()

                .then(function (devices) {

                    if (
                        !devices ||
                        devices.length === 0
                    ) {

                        readerElement.innerHTML =
                            '<div style="' +
                            'padding:15px;' +
                            'text-align:center;' +
                            'color:#b91c1c;' +
                            'background:#fee2e2;' +
                            'border-radius:10px;' +
                            '">' +
                            'Kamera tidak ditemukan. ' +
                            'Gunakan input kode unit manual.' +
                            '</div>';

                        return;
                    }

                    let cameraId =
                        devices[0].id;


                    /*
                    |--------------------------------------------------------------------------
                    | PRIORITASKAN KAMERA BELAKANG
                    |--------------------------------------------------------------------------
                    */

                    if (
                        devices.length > 1
                    ) {

                        const backCamera =
                            devices.find(
                                function (device) {

                                    const label =
                                        (
                                            device.label
                                            || ''
                                        ).toLowerCase();

                                    return (
                                        label.includes(
                                            'back'
                                        ) ||

                                        label.includes(
                                            'rear'
                                        ) ||

                                        label.includes(
                                            'environment'
                                        )
                                    );

                                }
                            );

                        if (backCamera) {

                            cameraId =
                                backCamera.id;

                        }
                    }


                    html5QrCode.start(

                        cameraId,

                        {

                            fps: 10,

                            qrbox: {
                                width: 250,
                                height: 250
                            }

                        },

                        onScanSuccess,

                        function () {
                            // Error scan tidak ditampilkan.
                        }

                    )

                    .catch(
                        function () {

                            readerElement.innerHTML =
                                '<div style="' +
                                'padding:15px;' +
                                'text-align:center;' +
                                'color:#b91c1c;' +
                                'background:#fee2e2;' +
                                'border-radius:10px;' +
                                '">' +
                                'Kamera tidak dapat digunakan. ' +
                                'Pastikan izin kamera diberikan, ' +
                                'atau gunakan input kode unit manual.' +
                                '</div>';

                        }
                    );

                })

                .catch(
                    function () {

                        readerElement.innerHTML =
                            '<div style="' +
                            'padding:15px;' +
                            'text-align:center;' +
                            'color:#b91c1c;' +
                            'background:#fee2e2;' +
                            'border-radius:10px;' +
                            '">' +
                            'Tidak dapat mengakses kamera. ' +
                            'Gunakan input kode unit manual.' +
                            '</div>';

                    }
                );

        } catch (error) {

            readerElement.innerHTML =
                '<div style="' +
                'padding:15px;' +
                'text-align:center;' +
                'color:#b91c1c;' +
                'background:#fee2e2;' +
                'border-radius:10px;' +
                '">' +
                'Scanner QR gagal dimuat. ' +
                'Gunakan input kode unit manual.' +
                '</div>';

        }

    }
);


/*
|--------------------------------------------------------------------------
| HAPUS UNIT
|--------------------------------------------------------------------------
*/

function removeScannedUnit(
    unitId
) {

    if (
        !confirm(
            'Hapus unit ini dari daftar scan?'
        )
    ) {

        return;
    }

    const form =
        document.createElement(
            'form'
        );

    form.method = 'POST';

    form.action =
        'scan-keluar.php';


    const actionInput =
        document.createElement(
            'input'
        );

    actionInput.type = 'hidden';

    actionInput.name =
        'action';

    actionInput.value =
        'remove';


    const unitInput =
        document.createElement(
            'input'
        );

    unitInput.type = 'hidden';

    unitInput.name =
        'unit_id';

    unitInput.value =
        unitId;


    const csrfInput =
        document.createElement(
            'input'
        );

    csrfInput.type = 'hidden';

    csrfInput.name =
        'csrf_token';

    csrfInput.value =
        '<?= htmlspecialchars(
            csrf_token()
        ) ?>';


    form.appendChild(
        actionInput
    );

    form.appendChild(
        unitInput
    );

    form.appendChild(
        csrfInput
    );

    document.body.appendChild(
        form
    );

    form.submit();
}


/*
|--------------------------------------------------------------------------
| VALIDASI TRANSAKSI
|--------------------------------------------------------------------------
*/

function validateRental() {

    const checkedUnits =
        document.querySelectorAll(
            '#rentalForm input[name="unit_ids[]"]:checked'
        );

    if (
        checkedUnits.length === 0
    ) {

        alert(
            'Pilih minimal satu unit untuk disewakan.'
        );

        return false;
    }

    const renterCode =
        document
            .getElementById(
                'renter_code'
            )
            .value
            .trim();

    if (
        renterCode === ''
    ) {

        alert(
            'Kode penyewa wajib diisi.'
        );

        document
            .getElementById(
                'renter_code'
            )
            .focus();

        return false;
    }

    return confirm(
        'Sewakan ' +
        checkedUnits.length +
        ' unit yang dipilih?'
    );
}

</script>

</body>
</html>