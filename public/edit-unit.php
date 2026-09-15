<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

$user_id  = (int) $_SESSION["user_id"];
$branch_id = (int) $_SESSION["branch_id"];

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    http_response_code(400);
    die("ID unit tidak valid.");
}

$errors = [];
$success = "";

/*
|--------------------------------------------------------------------------
| HELPER ESCAPE
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA UNIT
|--------------------------------------------------------------------------
|
| Hanya unit milik cabang user yang boleh dibuka untuk diedit.
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.qr_token,
            u.status,
            u.owner_branch_id,
            b.nama_cabang
        FROM units u
        INNER JOIN branches b
            ON b.id = u.owner_branch_id
        WHERE u.id = ?
          AND u.owner_branch_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $branch_id
    ]);

    $unit = $stmt->fetch();

} catch (PDOException $e) {

    error_log(
        "edit-unit.php SELECT error: " .
        $e->getMessage()
    );

    http_response_code(500);
    die("Terjadi kesalahan pada server.");
}

if (!$unit) {

    http_response_code(403);

    die("
        <h2>Akses Ditolak</h2>
        <p>
            Unit ini bukan milik cabang Anda
            atau unit tidak ditemukan.
        </p>
        <p>
            <a href='units.php'>
                ← Kembali ke Daftar Unit
            </a>
        </p>
    ");
}

/*
|--------------------------------------------------------------------------
| PROSES UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CEK CSRF
    |--------------------------------------------------------------------------
    */

    verify_csrf();

    $kode_unit = trim($_POST["kode_unit"] ?? "");
    $nama_unit = trim($_POST["nama_unit"] ?? "");
    $kategori  = trim($_POST["kategori"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | VALIDASI WAJIB
    |--------------------------------------------------------------------------
    */

    if ($kode_unit === "") {
        $errors[] = "Kode unit wajib diisi.";
    }

    if ($nama_unit === "") {
        $errors[] = "Nama unit wajib diisi.";
    }

    if ($kategori === "") {
        $errors[] = "Kategori wajib diisi.";
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI PANJANG DATA
    |--------------------------------------------------------------------------
    */

    if (strlen($kode_unit) > 50) {
        $errors[] = "Kode unit maksimal 50 karakter.";
    }

    if (strlen($nama_unit) > 100) {
        $errors[] = "Nama unit maksimal 100 karakter.";
    }

    if (strlen($kategori) > 100) {
        $errors[] = "Kategori maksimal 100 karakter.";
    }

    /*
    |--------------------------------------------------------------------------
    | CEK KODE UNIT DUPLIKAT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM units
                WHERE kode_unit = ?
                  AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $kode_unit,
                $id
            ]);

            if ($stmt->fetch()) {

                $errors[] =
                    "Kode unit tersebut sudah digunakan oleh unit lain.";
            }

        } catch (PDOException $e) {

            error_log(
                "edit-unit.php duplicate check error: " .
                $e->getMessage()
            );

            $errors[] =
                "Terjadi kesalahan saat memeriksa kode unit.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DATA
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | CEK ULANG OWNERSHIP DI DALAM TRANSAKSI
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    kode_unit,
                    nama_unit,
                    kategori,
                    qr_token,
                    status,
                    owner_branch_id
                FROM units
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$id]);

            $current_unit = $stmt->fetch();

            /*
            |--------------------------------------------------------------------------
            | UNIT TIDAK DITEMUKAN
            |--------------------------------------------------------------------------
            */

            if (!$current_unit) {

                throw new RuntimeException(
                    "Unit tidak ditemukan."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CEK PEMILIK UNIT
            |--------------------------------------------------------------------------
            */

            if (
                (int) $current_unit["owner_branch_id"]
                !== $branch_id
            ) {

                throw new RuntimeException(
                    "Unit bukan milik cabang user."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            |
            | owner_branch_id TIDAK diubah.
            | qr_token TIDAK diubah.
            |
            */

            $stmt = $pdo->prepare("
                UPDATE units
                SET
                    kode_unit = ?,
                    nama_unit = ?,
                    kategori = ?
                WHERE id = ?
                  AND owner_branch_id = ?
            ");

            $stmt->execute([
                $kode_unit,
                $nama_unit,
                $kategori,
                $id,
                $branch_id
            ]);

            /*
            |--------------------------------------------------------------------------
            | CATAT HISTORY
            |--------------------------------------------------------------------------
            */

            $description =
                "Data unit diperbarui. " .
                "Kode: {$kode_unit}, " .
                "Nama: {$nama_unit}, " .
                "Kategori: {$kategori}";

            $stmt = $pdo->prepare("
                INSERT INTO unit_history
                (
                    unit_id,
                    performed_by,
                    branch_id,
                    action,
                    description
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $id,
                $user_id,
                $branch_id,
                "UNIT_DIPERBARUI",
                $description
            ]);

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            $success =
                "Data unit berhasil diperbarui.";

            /*
            |--------------------------------------------------------------------------
            | UPDATE DATA DI HALAMAN
            |--------------------------------------------------------------------------
            */

            $unit["kode_unit"] = $kode_unit;
            $unit["nama_unit"] = $nama_unit;
            $unit["kategori"]  = $kategori;

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                "edit-unit.php UPDATE error: " .
                $e->getMessage()
            );

            $errors[] =
                "Gagal memperbarui data unit.";
        }
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

    <title>Edit Unit - Sistem Scan Unit</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
            background: #f5f7fb;
            color: #172033;
        }

        .page {
            min-height: 100vh;
            padding: 32px 20px;
        }

        .container {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        /* HEADER */

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            color: #2563eb;
        }

        .page-title {
            margin: 0;
            font-size: 27px;
            font-weight: 750;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        /* CARD */

        .card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 18px;
            box-shadow:
                0 10px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 24px 26px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .card-header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .card-body {
            padding: 26px;
        }

        /* BRANCH INFO */

        .branch-info {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 15px 16px;
            margin-bottom: 24px;
            background: #f0f7ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
        }

        .branch-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #dbeafe;
            border-radius: 10px;
            font-size: 18px;
        }

        .branch-label {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .branch-name {
            color: #1e3a8a;
            font-size: 14px;
            font-weight: 700;
        }

        /* ALERT */

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .alert-error ul {
            margin: 0;
            padding-left: 19px;
        }

        .alert-error li + li {
            margin-top: 5px;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            color: #273449;
            font-size: 13px;
            font-weight: 700;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 0 13px;
            border: 1px solid #d7dee8;
            border-radius: 10px;
            background: #ffffff;
            color: #172033;
            font-size: 14px;
            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .10);
        }

        .form-help {
            display: block;
            margin-top: 6px;
            color: #94a3b8;
            font-size: 12px;
        }

        /* INFORMATION */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 25px;
            margin-bottom: 25px;
        }

        .info-box {
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e7ebf0;
            border-radius: 12px;
        }

        .info-label {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }

        .info-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 700;
        }

        .token {
            word-break: break-all;
            color: #475569;
            font-family: Consolas, monospace;
            font-size: 11px;
            font-weight: 500;
            line-height: 1.5;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status.available {
            color: #166534;
            background: #dcfce7;
        }

        .status.rented {
            color: #92400e;
            background: #fef3c7;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ACTIONS */

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 22px;
            border-top: 1px solid #edf0f5;
        }

        .btn {
            min-height: 44px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition:
                transform .15s,
                background .2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #eef1f5;
            color: #334155;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* RESPONSIVE */

        @media (max-width: 600px) {

            .page {
                padding: 20px 12px;
            }

            .topbar {
                align-items: flex-start;
            }

            .page-title {
                font-size: 23px;
            }

            .card-header,
            .card-body {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            .back-link {
                font-size: 13px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <div class="container">

        <!-- HEADER -->

        <div class="topbar">

            <div>
                <h1 class="page-title">
                    Edit Unit
                </h1>

                <p class="page-subtitle">
                    Perbarui informasi unit yang Anda kelola.
                </p>
            </div>

            <a
                href="units.php"
                class="back-link"
            >
                ← Daftar Unit
            </a>

        </div>


        <!-- MAIN CARD -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Informasi Unit
                </h2>

                <p>
                    Perubahan akan tercatat pada riwayat sistem.
                </p>

            </div>


            <div class="card-body">

                <!-- CABANG -->

                <div class="branch-info">

                    <div class="branch-icon">
                        🏢
                    </div>

                    <div>

                        <div class="branch-label">
                            Cabang Pemilik
                        </div>

                        <div class="branch-name">
                            <?= e($unit["nama_cabang"]) ?>
                        </div>

                    </div>

                </div>


                <!-- SUCCESS -->

                <?php if ($success): ?>

                    <div class="alert alert-success">
                        ✓ <?= e($success) ?>
                    </div>

                <?php endif; ?>


                <!-- ERROR -->

                <?php if (!empty($errors)): ?>

                    <div class="alert alert-error">

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= e($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form
                    method="POST"
                    autocomplete="off"
                >

                    <?= csrf_field() ?>


                    <!-- KODE -->

                    <div class="form-group">

                        <label
                            for="kode_unit"
                            class="form-label"
                        >
                            Kode Unit
                        </label>

                        <input
                            type="text"
                            id="kode_unit"
                            name="kode_unit"
                            class="form-input"
                            maxlength="50"
                            value="<?= e($unit["kode_unit"]) ?>"
                            required
                        >

                        <small class="form-help">
                            Maksimal 50 karakter.
                        </small>

                    </div>


                    <!-- NAMA -->

                    <div class="form-group">

                        <label
                            for="nama_unit"
                            class="form-label"
                        >
                            Nama Unit
                        </label>

                        <input
                            type="text"
                            id="nama_unit"
                            name="nama_unit"
                            class="form-input"
                            maxlength="100"
                            value="<?= e($unit["nama_unit"]) ?>"
                            required
                        >

                        <small class="form-help">
                            Nama yang akan ditampilkan pada daftar dan scanner.
                        </small>

                    </div>


                    <!-- KATEGORI -->

                    <div class="form-group">

                        <label
                            for="kategori"
                            class="form-label"
                        >
                            Kategori
                        </label>

                        <input
                            type="text"
                            id="kategori"
                            name="kategori"
                            class="form-input"
                            maxlength="100"
                            value="<?= e($unit["kategori"]) ?>"
                            required
                        >

                        <small class="form-help">
                            Contoh: Proyektor, Kamera, Laptop, dan sebagainya.
                        </small>

                    </div>


                    <!-- INFORMASI READ ONLY -->

                    <div class="info-grid">

                        <div class="info-box">

                            <span class="info-label">
                                QR Token
                            </span>

                            <div class="token">
                                <?= e($unit["qr_token"]) ?>
                            </div>

                            <small class="form-help">
                                QR Token bersifat permanen.
                            </small>

                        </div>


                        <div class="info-box">

                            <span class="info-label">
                                Status Unit
                            </span>

                            <?php if ($unit["status"] === "TERSEDIA"): ?>

                                <span class="status available">
                                    <span class="status-dot"></span>
                                    Tersedia
                                </span>

                            <?php else: ?>

                                <span class="status rented">
                                    <span class="status-dot"></span>
                                    Disewakan
                                </span>

                            <?php endif; ?>

                            <small class="form-help">
                                Status berubah melalui proses unit keluar/masuk.
                            </small>

                        </div>

                    </div>


                    <!-- ACTION -->

                    <div class="actions">

                        <a
                            href="units.php"
                            class="btn btn-secondary"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>

</html>