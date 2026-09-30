<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$branchId = user_branch_id();
$csrf = csrf_token();

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Notifikasi
|--------------------------------------------------------------------------
*/

if (isset($_GET['created'])) {
    $success = 'Unit berhasil ditambahkan.';
}

if (isset($_GET['updated'])) {
    $success = 'Data unit berhasil diperbarui.';
}


/*
|--------------------------------------------------------------------------
| Tambah Unit
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_unit') {

        if (!verify_csrf($_POST['csrf_token'] ?? '')) {

            $error = 'Permintaan tidak valid. Silakan coba lagi.';

        } else {

            $kodeUnit = trim($_POST['kode_unit'] ?? '');
            $namaUnit = trim($_POST['nama_unit'] ?? '');
            $kategori = trim($_POST['kategori'] ?? '');
            $jumlah = (int) ($_POST['jumlah'] ?? 0);


            /*
            |--------------------------------------------------------------------------
            | Validasi
            |--------------------------------------------------------------------------
            */

            if ($kodeUnit === '') {

                $error = 'Kode unit wajib diisi.';

            } elseif ($namaUnit === '') {

                $error = 'Nama unit wajib diisi.';

            } elseif ($kategori === '') {

                $error = 'Kategori unit wajib diisi.';

            } elseif ($jumlah < 1) {

                $error = 'Jumlah unit minimal 1.';

            } else {

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

                    $stmt->execute([
                        $kodeUnit
                    ]);

                    if ($stmt->fetch()) {

                        $error =
                            'Kode unit "' .
                            htmlspecialchars($kodeUnit) .
                            '" sudah digunakan.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Simpan Unit
                        |--------------------------------------------------------------------------
                        |
                        | CATATAN:
                        | jumlah TIDAK membuat banyak baris.
                        |
                        | Contoh:
                        |
                        | kode_unit = A10
                        | nama_unit = HT
                        | jumlah = 10
                        |
                        | Tetap hanya 1 record unit.
                        |
                        */

                        $pdo->beginTransaction();


                        $stmt = $pdo->prepare("
                            INSERT INTO units (
                                kode_unit,
                                nama_unit,
                                kategori,
                                jumlah,
                                owner_branch_id,
                                status,
                                created_at,
                                updated_at
                            )
                            VALUES (
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


                        $unitId =
                            (int) $pdo->lastInsertId();


                        /*
                        |--------------------------------------------------------------------------
                        | Catat History
                        |--------------------------------------------------------------------------
                        */

                        $stmtHistory = $pdo->prepare("
                            INSERT INTO unit_history (
                                unit_id,
                                performed_by,
                                branch_id,
                                action,
                                description,
                                created_at
                            )
                            VALUES (
                                ?,
                                ?,
                                ?,
                                'TAMBAH_UNIT',
                                ?,
                                NOW()
                            )
                        ");

                        $description =
                            'Menambahkan unit ' .
                            $kodeUnit .
                            ' - ' .
                            $namaUnit .
                            ' dengan jumlah ' .
                            $jumlah;


                        $stmtHistory->execute([
                            $unitId,
                            user_id(),
                            $branchId,
                            $description
                        ]);


                        $pdo->commit();


                        header(
                            "Location: units.php?created=1"
                        );

                        exit;
                    }

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        'Unit gagal ditambahkan. Silakan coba lagi.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Ambil Semua Unit
|--------------------------------------------------------------------------
*/

$units = [];

try {

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.status,
            u.owner_branch_id,
            b.nama_cabang

        FROM units u

        INNER JOIN branches b
            ON b.id = u.owner_branch_id

        ORDER BY
            b.id ASC,
            u.kategori ASC,
            u.kode_unit ASC
    ");

    $units = $stmt->fetchAll();

} catch (PDOException $e) {

    $error =
        'Data unit gagal dimuat.';
}


/*
|--------------------------------------------------------------------------
| Grouping
|--------------------------------------------------------------------------
*/

$groupedUnits = [];

foreach ($units as $unit) {

    $branchName =
        $unit['nama_cabang'];

    $category =
        $unit['kategori'];


    if (!isset($groupedUnits[$branchName])) {

        $groupedUnits[$branchName] = [];

    }


    if (!isset(
        $groupedUnits[$branchName][$category]
    )) {

        $groupedUnits[$branchName][$category] = [];

    }


    $groupedUnits[$branchName][$category][] =
        $unit;
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

    <title>Kelola Unit - Sistem Scan Unit</title>


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
            width: min(1100px, 94%);
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
            font-size: 26px;
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
            margin-bottom: 22px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }


        .card h2 {
            margin-top: 0;
            font-size: 19px;
        }


        /*
        |--------------------------------------------------------------------------
        | Alert
        |--------------------------------------------------------------------------
        */

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 18px;
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
        | Form
        |--------------------------------------------------------------------------
        */

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 14px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        label {
            font-size: 13px;
            font-weight: bold;
        }


        input,
        select {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }


        input:focus,
        select:focus {
            border-color: #2563eb;
        }


        .help {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .button-row {
            display: flex;
            gap: 9px;
            flex-wrap: wrap;
            margin-top: 16px;
        }


        .btn {
            border: none;
            text-decoration: none;
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            display: inline-block;
        }


        .btn-primary {
            background: #2563eb;
            color: #ffffff;
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
            color: #ffffff;
        }


        .btn-green:hover {
            background: #15803d;
        }


        /*
        |--------------------------------------------------------------------------
        | Branch
        |--------------------------------------------------------------------------
        */

        .branch-section {
            margin-bottom: 25px;
        }


        .branch-title {
            background: #111827;
            color: white;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        .category-section {
            margin-bottom: 18px;
        }


        .category-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 9px;
            color: #374151;
        }


        /*
        |--------------------------------------------------------------------------
        | Unit List
        |--------------------------------------------------------------------------
        */

        .unit-list {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 10px;
        }


        .unit-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
        }


        .unit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }


        .unit-code {
            font-size: 17px;
            font-weight: bold;
        }


        .unit-name {
            margin-top: 4px;
            font-size: 14px;
            color: #4b5563;
        }


        .unit-info {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 8px;
            margin-top: 13px;
        }


        .info-box {
            background: #ffffff;
            border-radius: 7px;
            padding: 9px;
        }


        .info-label {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 3px;
        }


        .info-value {
            font-size: 13px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 999px;
            font-size: 11px;
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
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {
            text-align: center;
            padding: 40px 15px;
            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 750px) {

            .container {
                margin: 20px auto;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .form-group.full {
                grid-column: auto;
            }


            .unit-list {
                grid-template-columns: 1fr;
            }


            .title h1 {
                font-size: 23px;
            }


            .card {
                padding: 17px;
            }

        }


        @media (max-width: 450px) {

            .unit-info {
                grid-template-columns: 1fr;
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
                📦 Kelola Unit
            </h1>

            <p>
                Tambahkan dan kelola unit milik cabang Anda.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- ALERT -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- TAMBAH UNIT -->

    <div class="card">

        <h2>
            Tambah Unit
        </h2>


        <form
            method="POST"
            action="units.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf) ?>"
            >


            <input
                type="hidden"
                name="action"
                value="tambah_unit"
            >


            <div class="form-grid">


                <!-- KODE -->

                <div class="form-group">

                    <label for="kode_unit">
                        Kode Unit
                    </label>

                    <input
                        type="text"
                        id="kode_unit"
                        name="kode_unit"
                        placeholder="Contoh: A1"
                        maxlength="100"
                        required
                    >

                    <div class="help">

                        Kode harus unik karena digunakan
                        sebagai identitas QR unit.

                    </div>

                </div>


                <!-- NAMA -->

                <div class="form-group">

                    <label for="nama_unit">
                        Nama Unit
                    </label>

                    <input
                        type="text"
                        id="nama_unit"
                        name="nama_unit"
                        placeholder="Contoh: Projector Epson"
                        maxlength="255"
                        required
                    >

                </div>


                <!-- KATEGORI -->

                <div class="form-group">

                    <label for="kategori">
                        Kategori
                    </label>

                    <input
                        type="text"
                        id="kategori"
                        name="kategori"
                        placeholder="Contoh: Projector"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- JUMLAH -->

                <div class="form-group">

                    <label for="jumlah">
                        Jumlah
                    </label>

                    <input
                        type="number"
                        id="jumlah"
                        name="jumlah"
                        min="1"
                        value="1"
                        required
                    >

                    <div class="help">

                        Jumlah tidak membuat kode unit otomatis.
                        Satu unit tetap memiliki satu kode QR.

                    </div>

                </div>


            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    + Tambah Unit
                </button>

            </div>

        </form>

    </div>


    <!-- DAFTAR UNIT -->

    <div class="card">

        <h2>
            Daftar Semua Unit
        </h2>


        <?php if (empty($groupedUnits)): ?>

            <div class="empty">

                Belum ada unit yang terdaftar.

            </div>


        <?php else: ?>


            <?php foreach (
                $groupedUnits as $branchName => $categories
            ): ?>


                <div class="branch-section">


                    <div class="branch-title">

                        🏢
                        <?= htmlspecialchars(
                            $branchName
                        ) ?>


                        <?php if (
                            $branchName ===
                            user_branch_name()
                        ): ?>

                            <span style="
                                font-size: 11px;
                                font-weight: normal;
                                opacity: .8;
                            ">
                                Cabang Anda
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php foreach (
                        $categories as $category => $categoryUnits
                    ): ?>


                        <div class="category-section">


                            <div class="category-title">

                                📁
                                <?= htmlspecialchars(
                                    $category
                                ) ?>

                            </div>


                            <div class="unit-list">


                                <?php foreach (
                                    $categoryUnits
                                    as $unit
                                ): ?>


                                    <div class="unit-item">


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
                                                $unit['status'] ===
                                                'TERSEDIA'
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


                                        <div class="unit-info">


                                            <div class="info-box">

                                                <div class="info-label">
                                                    Jumlah
                                                </div>

                                                <div class="info-value">

                                                    <?= (int) $unit['jumlah'] ?>

                                                </div>

                                            </div>


                                            <div class="info-box">

                                                <div class="info-label">
                                                    Cabang Pemilik
                                                </div>

                                                <div class="info-value">

                                                    <?= htmlspecialchars(
                                                        $unit['nama_cabang']
                                                    ) ?>

                                                </div>

                                            </div>


                                        </div>


                                        <!-- ACTION -->

                                        <div class="button-row">


                                            <?php if (
                                                (int) $unit['owner_branch_id']
                                                ===
                                                (int) $branchId
                                            ): ?>


                                                <a
                                                    href="edit-unit.php?id=<?= (int) $unit['id'] ?>"
                                                    class="btn btn-primary"
                                                >
                                                    Edit Unit
                                                </a>


                                            <?php endif; ?>


                                            <a
                                                href="qr.php?id=<?= (int) $unit['id'] ?>"
                                                class="btn btn-green"
                                            >
                                                Lihat QR
                                            </a>


                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>


</div>

</body>

</html>