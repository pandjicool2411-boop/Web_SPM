<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

require_login();

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| PESAN SETELAH REDIRECT
|--------------------------------------------------------------------------
*/

if (isset($_GET['updated'])) {
    $success = "Data unit berhasil diperbarui.";
}


/*
|--------------------------------------------------------------------------
| TAMBAH UNIT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {

        /*
        |--------------------------------------------------------------------------
        | Validasi CSRF
        |--------------------------------------------------------------------------
        */

        if (!verify_csrf($_POST['csrf_token'] ?? '')) {

            $error = "Permintaan tidak valid.";

        } else {

            $kode_unit = trim($_POST['kode_unit'] ?? '');
            $nama_unit = trim($_POST['nama_unit'] ?? '');
            $kategori = trim($_POST['kategori'] ?? '');
            $jumlah = (int) ($_POST['jumlah'] ?? 0);

            /*
            |--------------------------------------------------------------------------
            | Validasi input
            |--------------------------------------------------------------------------
            */

            if (
                $kode_unit === '' ||
                $nama_unit === '' ||
                $kategori === '' ||
                $jumlah < 1
            ) {

                $error = "Semua data unit wajib diisi dengan benar.";

            } else {

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

                $stmt->execute([$kode_unit]);

                if ($stmt->fetch()) {

                    $error = "Kode unit tersebut sudah digunakan.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Simpan unit
                    |--------------------------------------------------------------------------
                    */

                    try {

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
                            $kode_unit,
                            $nama_unit,
                            $kategori,
                            $jumlah,
                            user_branch_id()
                        ]);

                        $unit_id = $pdo->lastInsertId();

                        /*
                        |--------------------------------------------------------------------------
                        | Simpan history
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            INSERT INTO unit_history (
                                unit_id,
                                performed_by,
                                branch_id,
                                action,
                                description,
                                created_at
                            )
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");

                        $stmt->execute([
                            $unit_id,
                            user_id(),
                            user_branch_id(),
                            'TAMBAH_UNIT',
                            'Menambahkan unit ' . $kode_unit
                        ]);

                        $pdo->commit();

                        $success = "Unit berhasil ditambahkan.";

                    } catch (Exception $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $error = "Unit gagal ditambahkan.";
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| AMBIL SEMUA UNIT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        u.id,
        u.kode_unit,
        u.nama_unit,
        u.kategori,
        u.jumlah,
        u.owner_branch_id,
        u.status,
        u.created_at,
        u.updated_at,
        b.nama_cabang
    FROM units u
    INNER JOIN branches b
        ON b.id = u.owner_branch_id
    ORDER BY
        u.owner_branch_id ASC,
        u.kategori ASC,
        u.kode_unit ASC
");

$units = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| KELOMPOKKAN:
| CABANG → KATEGORI → UNIT
|--------------------------------------------------------------------------
*/

$grouped_units = [];

foreach ($units as $unit) {

    $branch_id = (int) $unit['owner_branch_id'];

    if (!isset($grouped_units[$branch_id])) {

        $grouped_units[$branch_id] = [
            'nama_cabang' => $unit['nama_cabang'],
            'categories' => []
        ];
    }

    $kategori = $unit['kategori'];

    if (!isset(
        $grouped_units[$branch_id]['categories'][$kategori]
    )) {

        $grouped_units[$branch_id]['categories'][$kategori] = [];
    }

    $grouped_units[$branch_id]['categories'][$kategori][] = $unit;
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

    <title>Data Unit - Sistem Scan Unit</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #111827;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: auto;
            padding: 25px 18px 50px;
        }

        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 28px;
        }

        .back {
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | BOX
        |--------------------------------------------------------------------------
        */

        .box {
            background: #ffffff;
            padding: 22px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .05);
        }

        .box h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #2563eb;
        }

        .form-submit {
            margin-top: 15px;
        }

        .btn-primary {
            width: 100%;
            padding: 13px;
            border: 0;
            border-radius: 10px;
            background: #111827;
            color: #ffffff;
            font-size: 15px;
            cursor: pointer;
        }

        .btn-primary:hover {
            opacity: .9;
        }


        /*
        |--------------------------------------------------------------------------
        | CABANG
        |--------------------------------------------------------------------------
        */

        .branch {
            margin-bottom: 30px;
        }

        .branch:last-child {
            margin-bottom: 0;
        }

        .branch-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #111827;
            color: #ffffff;
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .branch-title h3 {
            margin: 0;
            font-size: 19px;
        }


        /*
        |--------------------------------------------------------------------------
        | KATEGORI
        |--------------------------------------------------------------------------
        */

        .category {
            margin-bottom: 22px;
        }

        .category:last-child {
            margin-bottom: 0;
        }

        .category-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 10px;
            padding-left: 4px;
        }

        .category-title::before {
            content: "";
            width: 4px;
            height: 18px;
            background: #2563eb;
            border-radius: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | UNIT GRID
        |--------------------------------------------------------------------------
        */

        .unit-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fill, minmax(280px, 1fr));
            gap: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | UNIT CARD
        |--------------------------------------------------------------------------
        */

        .unit-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 17px;
            transition: .2s;
        }

        .unit-card:hover {
            box-shadow: 0 5px 18px rgba(0, 0, 0, .07);
            transform: translateY(-1px);
        }

        .unit-code {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .unit-name {
            color: #4b5563;
            margin-bottom: 15px;
            min-height: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | INFO UNIT
        |--------------------------------------------------------------------------
        */

        .unit-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .info-item {
            background: #f9fafb;
            padding: 9px;
            border-radius: 8px;
            font-size: 14px;
        }

        .label {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 4px;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .tersedia {
            background: #dcfce7;
            color: #166534;
        }

        .disewa {
            background: #fef3c7;
            color: #92400e;
        }


        /*
        |--------------------------------------------------------------------------
        | OWNER
        |--------------------------------------------------------------------------
        */

        .owner {
            margin-top: 12px;
            color: #2563eb;
            font-size: 13px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION BUTTONS
        |--------------------------------------------------------------------------
        */

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 12px;
        }

        .action-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 40px;
            padding: 9px;
            border-radius: 9px;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
        }

        .btn-edit {
            background: #2563eb;
        }

        .btn-qr {
            background: #059669;
        }

        .btn-edit:hover,
        .btn-qr:hover {
            opacity: .9;
        }


        /*
        |--------------------------------------------------------------------------
        | UNIT CABANG LAIN
        |--------------------------------------------------------------------------
        */

        .other-branch {
            margin-top: 12px;
            color: #6b7280;
            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .empty {
            background: #ffffff;
            padding: 30px 20px;
            border-radius: 14px;
            text-align: center;
            color: #6b7280;
            border: 1px dashed #d1d5db;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {

            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .container {
                padding: 18px 12px 40px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar h1 {
                font-size: 24px;
            }

            .box {
                padding: 17px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .unit-grid {
                grid-template-columns: 1fr;
            }

            .branch-title {
                padding: 13px 15px;
            }

            .branch-title h3 {
                font-size: 17px;
            }

        }

    </style>

</head>

<body>

<div class="container">


    <!--
    |--------------------------------------------------------------------------
    | TOPBAR
    |--------------------------------------------------------------------------
    -->

    <div class="topbar">

        <h1>
            Data Unit
        </h1>

        <a
            class="back"
            href="dashboard.php"
        >
            ← Dashboard
        </a>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | ALERT ERROR
    |--------------------------------------------------------------------------
    -->

    <?php if ($error): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | ALERT SUCCESS
    |--------------------------------------------------------------------------
    -->

    <?php if ($success): ?>

        <div class="alert success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | FORM TAMBAH UNIT
    |--------------------------------------------------------------------------
    -->

    <div class="box">

        <h2>
            Tambah Unit
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="tambah"
            >


            <div class="form-grid">


                <!-- KODE UNIT -->

                <div class="form-group">

                    <label>
                        Kode Unit
                    </label>

                    <input
                        type="text"
                        name="kode_unit"
                        placeholder="Contoh: A1"
                        autocomplete="off"
                        required
                    >

                </div>


                <!-- NAMA UNIT -->

                <div class="form-group">

                    <label>
                        Nama Unit
                    </label>

                    <input
                        type="text"
                        name="nama_unit"
                        placeholder="Contoh: Projector Epson"
                        required
                    >

                </div>


                <!-- KATEGORI -->

                <div class="form-group">

                    <label>
                        Kategori
                    </label>

                    <input
                        type="text"
                        name="kategori"
                        placeholder="Contoh: Projector"
                        required
                    >

                </div>


                <!-- JUMLAH -->

                <div class="form-group">

                    <label>
                        Jumlah
                    </label>

                    <input
                        type="number"
                        name="jumlah"
                        min="1"
                        value="1"
                        required
                    >

                </div>


            </div>


            <div class="form-submit">

                <button
                    type="submit"
                    class="btn-primary"
                >
                    + Tambah Unit
                </button>

            </div>

        </form>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | DAFTAR UNIT
    |--------------------------------------------------------------------------
    -->

    <div class="box">

        <h2>
            Daftar Semua Unit
        </h2>


        <?php if (empty($grouped_units)): ?>

            <div class="empty">

                Belum ada unit yang terdaftar.

                <br>

                Tambahkan unit pertama menggunakan form di atas.

            </div>

        <?php else: ?>


            <?php foreach ($grouped_units as $branch_id => $branch_data): ?>


                <!--
                |--------------------------------------------------------------------------
                | CABANG
                |--------------------------------------------------------------------------
                -->

                <div class="branch">


                    <div class="branch-title">

                        <h3>
                            <?= htmlspecialchars(
                                $branch_data['nama_cabang']
                            ) ?>
                        </h3>

                    </div>


                    <?php foreach (
                        $branch_data['categories']
                        as $kategori => $category_units
                    ): ?>


                        <!--
                        |--------------------------------------------------------------------------
                        | KATEGORI
                        |--------------------------------------------------------------------------
                        -->

                        <div class="category">


                            <div class="category-title">

                                <?= htmlspecialchars($kategori) ?>

                            </div>


                            <div class="unit-grid">


                                <?php foreach (
                                    $category_units
                                    as $unit
                                ): ?>


                                    <!--
                                    |--------------------------------------------------------------------------
                                    | UNIT CARD
                                    |--------------------------------------------------------------------------
                                    -->

                                    <div class="unit-card">


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


                                        <div class="unit-info">


                                            <!-- JUMLAH -->

                                            <div class="info-item">

                                                <span class="label">
                                                    Jumlah
                                                </span>

                                                <?= (int) $unit['jumlah'] ?>

                                            </div>


                                            <!-- STATUS -->

                                            <div class="info-item">

                                                <span class="label">
                                                    Status
                                                </span>


                                                <?php if (
                                                    $unit['status']
                                                    === 'TERSEDIA'
                                                ): ?>

                                                    <span class="status tersedia">
                                                        TERSEDIA
                                                    </span>

                                                <?php elseif (
                                                    $unit['status']
                                                    === 'DISEWAKAN'
                                                ): ?>

                                                    <span class="status disewa">
                                                        DISEWAKAN
                                                    </span>

                                                <?php endif; ?>

                                            </div>


                                        </div>


                                        <?php if (
                                            (int) $unit['owner_branch_id']
                                            === (int) user_branch_id()
                                        ): ?>


                                            <!--
                                            |--------------------------------------------------------------------------
                                            | UNIT MILIK CABANG SENDIRI
                                            |--------------------------------------------------------------------------
                                            -->

                                            <div class="owner">

                                                ● Unit cabang Anda

                                            </div>


                                            <div class="actions">


                                                <!-- EDIT -->

                                                <a
                                                    class="action-btn btn-edit"
                                                    href="edit-unit.php?id=<?= (int) $unit['id'] ?>"
                                                >
                                                    ✏️ Edit
                                                </a>


                                                <!-- QR -->

                                                <a
                                                    class="action-btn btn-qr"
                                                    href="qr.php?id=<?= (int) $unit['id'] ?>"
                                                >
                                                    ▣ Lihat QR
                                                </a>


                                            </div>


                                        <?php else: ?>


                                            <!--
                                            |--------------------------------------------------------------------------
                                            | UNIT CABANG LAIN
                                            |--------------------------------------------------------------------------
                                            -->

                                            <div class="other-branch">

                                                Unit milik cabang lain.

                                            </div>


                                            <div class="actions">


                                                <!--
                                                | QR tetap boleh dilihat
                                                | karena QR bukan data rahasia
                                                -->

                                                <a
                                                    class="action-btn btn-qr"
                                                    href="qr.php?id=<?= (int) $unit['id'] ?>"
                                                >
                                                    ▣ Lihat QR
                                                </a>


                                            </div>


                                        <?php endif; ?>


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