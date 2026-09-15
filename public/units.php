<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

verify_csrf();

$branch_id = (int) $_SESSION["branch_id"];
$user_id = (int) $_SESSION["user_id"];

$message = "";
$error = "";


// ==========================================================================
// TAMBAH UNIT
// ==========================================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "tambah") {

        $kode_unit = trim($_POST["kode_unit"] ?? "");
        $nama_unit = trim($_POST["nama_unit"] ?? "");
        $kategori = trim($_POST["kategori"] ?? "");


        // ------------------------------------------------------------------
        // VALIDASI
        // ------------------------------------------------------------------

        if (
            $kode_unit === "" ||
            $nama_unit === "" ||
            $kategori === ""
        ) {

            $error = "Semua data unit wajib diisi.";

        } elseif (mb_strlen($kode_unit) > 50) {

            $error = "Kode unit maksimal 50 karakter.";

        } elseif (mb_strlen($nama_unit) > 100) {

            $error = "Nama unit maksimal 100 karakter.";

        } elseif (mb_strlen($kategori) > 100) {

            $error = "Kategori maksimal 100 karakter.";

        } else {

            try {

                // ----------------------------------------------------------
                // Pastikan branch dari session masih valid
                // ----------------------------------------------------------

                $check_branch = $pdo->prepare(
                    "SELECT id
                     FROM branches
                     WHERE id = ?
                     LIMIT 1"
                );

                $check_branch->execute([$branch_id]);

                if (!$check_branch->fetch()) {

                    $error = "Cabang akun tidak valid.";

                } else {

                    // ------------------------------------------------------
                    // Cek kode unit
                    // ------------------------------------------------------

                    $check_unit = $pdo->prepare(
                        "SELECT id
                         FROM units
                         WHERE kode_unit = ?
                         LIMIT 1"
                    );

                    $check_unit->execute([$kode_unit]);

                    if ($check_unit->fetch()) {

                        $error = "Kode unit sudah digunakan.";

                    } else {

                        // --------------------------------------------------
                        // Generate QR token permanen
                        // --------------------------------------------------

                        $qr_token = bin2hex(
                            random_bytes(16)
                        );


                        // --------------------------------------------------
                        // TRANSAKSI DATABASE
                        // --------------------------------------------------

                        $pdo->beginTransaction();

                        try {

                            // ------------------------------------------------
                            // Simpan unit
                            //
                            // owner_branch_id selalu berasal dari session.
                            // ------------------------------------------------

                            $stmt = $pdo->prepare(
                                "INSERT INTO units
                                (
                                    kode_unit,
                                    nama_unit,
                                    kategori,
                                    qr_token,
                                    owner_branch_id,
                                    status
                                )
                                VALUES (?, ?, ?, ?, ?, 'TERSEDIA')"
                            );

                            $stmt->execute([
                                $kode_unit,
                                $nama_unit,
                                $kategori,
                                $qr_token,
                                $branch_id
                            ]);


                            // ------------------------------------------------
                            // Ambil ID unit
                            // ------------------------------------------------

                            $unit_id = (int) $pdo->lastInsertId();


                            // ------------------------------------------------
                            // Simpan history
                            // ------------------------------------------------

                            $history = $pdo->prepare(
                                "INSERT INTO unit_history
                                (
                                    unit_id,
                                    performed_by,
                                    branch_id,
                                    action,
                                    description
                                )
                                VALUES (?, ?, ?, ?, ?)"
                            );

                            $history->execute([
                                $unit_id,
                                $user_id,
                                $branch_id,
                                "UNIT_DIDAFTARKAN",
                                "Unit baru didaftarkan ke cabang."
                            ]);


                            // ------------------------------------------------
                            // Commit
                            // ------------------------------------------------

                            $pdo->commit();

                            $message = "Unit berhasil ditambahkan.";

                        } catch (PDOException $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            error_log(
                                "Gagal menambahkan unit: " .
                                $e->getMessage()
                            );

                            $error =
                                "Unit gagal ditambahkan. Silakan coba lagi.";
                        }
                    }
                }

            } catch (PDOException $e) {

                error_log(
                    "Gagal memproses penambahan unit: " .
                    $e->getMessage()
                );

                $error =
                    "Terjadi kesalahan saat memproses unit.";
            }
        }
    }
}


// ==========================================================================
// AMBIL SEMUA UNIT
//
// Semua cabang boleh melihat unit.
// Edit hanya untuk owner_branch_id yang sama dengan branch user.
// ==========================================================================

try {

    $stmt = $pdo->prepare(
        "SELECT
            units.id,
            units.kode_unit,
            units.nama_unit,
            units.kategori,
            units.qr_token,
            units.owner_branch_id,
            units.status,
            units.created_at,
            units.updated_at,
            branches.nama_cabang
        FROM units
        INNER JOIN branches
            ON units.owner_branch_id = branches.id
        ORDER BY units.id DESC"
    );

    $stmt->execute();

    $units = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "Gagal mengambil daftar unit: " .
        $e->getMessage()
    );

    $units = [];

    if ($message === "") {

        $error =
            "Daftar unit tidak dapat dimuat.";
    }
}


// ==========================================================================
// HELPER OUTPUT
// ==========================================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
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

    <title>
        Daftar Unit - Sistem Scan Unit
    </title>


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

        button,
        input {
            font-family: inherit;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }


        /* =========================================================
           TOP BAR
        ========================================================= */

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back:hover {
            color: #1d4ed8;
        }


        /* =========================================================
           PAGE HEADER
        ========================================================= */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;

            padding: 24px;
            margin-bottom: 20px;

            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.04);
        }

        .page-header-left {
            min-width: 0;
        }

        .eyebrow {
            margin: 0 0 6px;

            color: #2563eb;

            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.08em;
        }

        .page-header h1 {
            margin: 0;

            font-size: 28px;
            line-height: 1.25;
            letter-spacing: -0.4px;
        }

        .page-description {
            margin: 7px 0 0;

            color: #64748b;

            font-size: 14px;
            line-height: 1.5;
        }

        .branch-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 10px 14px;

            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 10px;

            color: #1d4ed8;

            font-size: 13px;
            font-weight: bold;

            white-space: nowrap;
        }


        /* =========================================================
           ALERT
        ========================================================= */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            padding: 13px 15px;

            border-radius: 11px;

            margin-bottom: 18px;

            font-size: 14px;
            line-height: 1.5;
        }

        .alert.success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }


        /* =========================================================
           CARD
        ========================================================= */

        .card {
            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 16px;

            padding: 22px;
            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;

            margin-bottom: 20px;
        }

        .card-title {
            margin: 0;

            font-size: 18px;
            line-height: 1.35;
        }

        .card-description {
            margin: 5px 0 0;

            color: #64748b;

            font-size: 13px;
            line-height: 1.5;
        }

        .unit-count {
            display: inline-flex;
            align-items: center;

            padding: 7px 11px;

            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 9px;

            color: #475569;

            font-size: 12px;
            font-weight: bold;

            white-space: nowrap;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 7px;

            color: #334155;

            font-size: 13px;
            font-weight: bold;
        }

        input {
            width: 100%;

            padding: 12px 13px;

            background: #ffffff;

            border: 1px solid #cbd5e1;
            border-radius: 10px;

            color: #172033;

            font-size: 14px;

            outline: none;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(
                    37,
                    99,
                    235,
                    0.10
                );
        }

        .form-button {
            margin-top: 17px;
        }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            padding: 11px 17px;

            background: #2563eb;
            color: #ffffff;

            border: none;
            border-radius: 10px;

            font-size: 14px;
            font-weight: bold;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:active {
            transform: translateY(1px);
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .table-wrapper {
            overflow-x: auto;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;
        }

        table {
            width: 100%;

            min-width: 900px;

            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 14px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;

            color: #475569;

            font-size: 12px;
            font-weight: bold;

            white-space: nowrap;
        }

        td {
            background: #ffffff;

            color: #334155;

            font-size: 13px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: #f8fafc;
        }


        /* =========================================================
           KODE UNIT
        ========================================================= */

        .unit-code {
            display: inline-flex;

            padding: 6px 8px;

            background: #f1f5f9;

            border-radius: 7px;

            color: #334155;

            font-family: monospace;

            font-size: 12px;
            font-weight: bold;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;
            font-weight: bold;

            white-space: nowrap;
        }

        .status::before {
            content: "";

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: currentColor;
        }

        .tersedia {
            background: #ecfdf5;
            color: #15803d;
        }

        .disewakan {
            background: #fef2f2;
            color: #dc2626;
        }


        /* =========================================================
           OWNER
        ========================================================= */

        .owner {
            display: inline-flex;
            flex-direction: column;
            gap: 3px;
        }

        .owner-name {
            color: #334155;
            font-size: 13px;
        }

        .owner-me {
            display: inline-flex;
            width: fit-content;

            padding: 3px 7px;

            background: #eff6ff;

            border-radius: 6px;

            color: #2563eb;

            font-size: 10px;
            font-weight: bold;
        }


        /* =========================================================
           QR TOKEN
        ========================================================= */

        .qr-token {
            display: block;

            max-width: 180px;

            overflow-wrap: anywhere;

            color: #64748b;

            font-family: monospace;
            font-size: 10px;

            line-height: 1.4;
        }


        /* =========================================================
           ACTION
        ========================================================= */

        .edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 7px 11px;

            background: #eff6ff;
            color: #2563eb;

            border: 1px solid #dbeafe;
            border-radius: 8px;

            text-decoration: none;

            font-size: 12px;
            font-weight: bold;

            transition: 0.2s;
        }

        .edit-btn:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .readonly {
            color: #94a3b8;

            font-size: 11px;
            font-weight: bold;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty-state {
            padding: 45px 20px;

            text-align: center;
        }

        .empty-icon {
            width: 55px;
            height: 55px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 12px;

            background: #f1f5f9;

            border-radius: 14px;

            font-size: 25px;
        }

        .empty-state h3 {
            margin: 0 0 6px;

            font-size: 16px;
        }

        .empty-state p {
            margin: 0;

            color: #64748b;

            font-size: 13px;
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 850px) {

            .container {
                padding: 16px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .branch-badge {
                white-space: normal;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 600px) {

            .container {
                padding: 12px;
            }

            .top {
                margin-bottom: 14px;
            }

            .back {
                font-size: 13px;
            }

            .page-header {
                padding: 20px;

                border-radius: 15px;

                margin-bottom: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .page-description {
                font-size: 13px;
            }

            .card {
                padding: 17px;

                border-radius: 14px;
            }

            .card-header {
                flex-direction: column;

                margin-bottom: 17px;
            }

            .card-title {
                font-size: 17px;
            }

            .form-grid {
                gap: 13px;
            }

            .form-button {
                margin-top: 15px;
            }

            button {
                width: 100%;
            }

            .table-wrapper {
                border-radius: 10px;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         TOP
    ====================================================== -->

    <div class="top">

        <a
            href="dashboard.php"
            class="back"
        >
            ← Kembali ke Dashboard
        </a>

    </div>


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <div class="page-header-left">

            <p class="eyebrow">
                MANAJEMEN UNIT
            </p>

            <h1>
                Daftar Unit
            </h1>

            <p class="page-description">
                Kelola data unit dan lihat status unit
                dari seluruh cabang.
            </p>

        </div>


        <div class="branch-badge">

            🏢

            Cabang Anda:

            <?= e(
                $_SESSION["nama_cabang"] ?? ""
            ) ?>

        </div>

    </div>


    <!-- =====================================================
         ALERT SUCCESS
    ====================================================== -->

    <?php if ($message !== ""): ?>

        <div class="alert success">

            <span>
                ✓
            </span>

            <span>
                <?= e($message) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ALERT ERROR
    ====================================================== -->

    <?php if ($error !== ""): ?>

        <div class="alert error">

            <span>
                !
            </span>

            <span>
                <?= e($error) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         TAMBAH UNIT
    ====================================================== -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2 class="card-title">
                    Tambah Unit
                </h2>

                <p class="card-description">
                    Unit baru akan otomatis terdaftar
                    sebagai milik cabang Anda.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action=""
        >

            <?= csrf_field() ?>


            <input
                type="hidden"
                name="action"
                value="tambah"
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
                        placeholder="Contoh: INF-001"
                        maxlength="50"
                        autocomplete="off"
                        required
                    >

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
                        placeholder="Contoh: Epson EB-X06"
                        maxlength="100"
                        autocomplete="off"
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
                        placeholder="Contoh: Proyektor"
                        maxlength="100"
                        autocomplete="off"
                        required
                    >

                </div>


            </div>


            <div class="form-button">

                <button type="submit">

                    <span>
                        +
                    </span>

                    Tambah Unit

                </button>

            </div>


        </form>

    </div>


    <!-- =====================================================
         DAFTAR UNIT
    ====================================================== -->

    <div class="card">


        <div class="card-header">

            <div>

                <h2 class="card-title">
                    Semua Unit
                </h2>

                <p class="card-description">
                    Semua cabang dapat melihat unit.
                    Edit hanya tersedia untuk unit
                    milik cabang Anda.
                </p>

            </div>


            <div class="unit-count">

                <?= count($units) ?>

                Unit

            </div>


        </div>


        <?php if (count($units) === 0): ?>


            <div class="empty-state">

                <div class="empty-icon">
                    📦
                </div>

                <h3>
                    Belum ada unit
                </h3>

                <p>
                    Tambahkan unit pertama melalui
                    formulir di atas.
                </p>

            </div>


        <?php else: ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Kode
                            </th>

                            <th>
                                Nama Unit
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Cabang
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                QR Token
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($units as $unit): ?>


                        <tr>


                            <!-- KODE -->

                            <td>

                                <span class="unit-code">

                                    <?= e(
                                        $unit["kode_unit"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- NAMA -->

                            <td>

                                <strong>

                                    <?= e(
                                        $unit["nama_unit"]
                                    ) ?>

                                </strong>

                            </td>


                            <!-- KATEGORI -->

                            <td>

                                <?= e(
                                    $unit["kategori"]
                                ) ?>

                            </td>


                            <!-- CABANG -->

                            <td>

                                <div class="owner">

                                    <span class="owner-name">

                                        <?= e(
                                            $unit["nama_cabang"]
                                        ) ?>

                                    </span>


                                    <?php if (
                                        (int) $unit["owner_branch_id"]
                                        === $branch_id
                                    ): ?>

                                        <span class="owner-me">
                                            CABANG ANDA
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= strtolower(
                                        e($unit["status"])
                                    ) ?>"
                                >

                                    <?= e(
                                        $unit["status"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- QR TOKEN -->

                            <td>

                                <span class="qr-token">

                                    <?= e(
                                        $unit["qr_token"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- AKSI -->

                            <td>

                                <?php if (
                                    (int) $unit["owner_branch_id"]
                                    === $branch_id
                                ): ?>

                                    <a
                                        href="edit-unit.php?id=<?= (int) $unit["id"] ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>

                                <?php else: ?>

                                    <span class="readonly">
                                        Read Only
                                    </span>

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>