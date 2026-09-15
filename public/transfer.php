<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

verify_csrf();

$branch_id = (int) $_SESSION["branch_id"];
$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Ambil nama cabang user dari database
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT nama_cabang
    FROM branches
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$branch_id]);

$current_branch = $stmt->fetch();

if (!$current_branch) {

    http_response_code(403);
    die("Cabang pengguna tidak valid.");

}

/*
|--------------------------------------------------------------------------
| Unit milik cabang sendiri yang tersedia untuk ditransfer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        units.id,
        units.kode_unit,
        units.nama_unit,
        units.kategori,
        units.status
    FROM units
    WHERE units.owner_branch_id = ?
      AND units.status = 'TERSEDIA'
      AND NOT EXISTS (
          SELECT 1
          FROM unit_transfers
          WHERE unit_transfers.unit_id = units.id
            AND unit_transfers.status IN ('PENDING', 'APPROVED')
      )
    ORDER BY units.kode_unit ASC
");

$stmt->execute([$branch_id]);

$units = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Daftar cabang tujuan
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        nama_cabang
    FROM branches
    WHERE id != ?
    ORDER BY nama_cabang ASC
");

$stmt->execute([$branch_id]);

$branches = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Transfer yang dibuat oleh user ini
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        unit_transfers.id,
        unit_transfers.status,
        unit_transfers.requested_at,
        unit_transfers.approved_at,
        unit_transfers.completed_at,
        units.kode_unit,
        units.nama_unit,
        from_branch.nama_cabang AS dari_cabang,
        to_branch.nama_cabang AS ke_cabang
    FROM unit_transfers
    INNER JOIN units
        ON unit_transfers.unit_id = units.id
    INNER JOIN branches AS from_branch
        ON unit_transfers.from_branch_id = from_branch.id
    INNER JOIN branches AS to_branch
        ON unit_transfers.to_branch_id = to_branch.id
    WHERE unit_transfers.requested_by = ?
    ORDER BY unit_transfers.id DESC
");

$stmt->execute([$user_id]);

$transfers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Transfer APPROVED yang masuk ke cabang saya
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        unit_transfers.id,
        unit_transfers.status,
        unit_transfers.approved_at,
        units.kode_unit,
        units.nama_unit,
        from_branch.nama_cabang AS dari_cabang,
        to_branch.nama_cabang AS ke_cabang
    FROM unit_transfers
    INNER JOIN units
        ON unit_transfers.unit_id = units.id
    INNER JOIN branches AS from_branch
        ON unit_transfers.from_branch_id = from_branch.id
    INNER JOIN branches AS to_branch
        ON unit_transfers.to_branch_id = to_branch.id
    WHERE unit_transfers.to_branch_id = ?
      AND unit_transfers.status = 'APPROVED'
    ORDER BY unit_transfers.approved_at DESC
");

$stmt->execute([$branch_id]);

$incoming_transfers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Transfer Unit - Sistem Scan Unit</title>

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

            background: #f5f7fb;
            color: #111827;
        }

        .container {
            width: 92%;
            max-width: 1150px;

            margin: 32px auto 50px;
        }

        /* =====================================================
           BACK
           ===================================================== */

        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            margin-bottom: 22px;

            color: #2563eb;
            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            transition: 0.2s;
        }

        .back:hover {
            color: #1d4ed8;
        }

        /* =====================================================
           HEADER
           ===================================================== */

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 22px;
        }

        .page-title {
            margin: 0 0 7px;

            font-size: 30px;
            line-height: 1.2;
            font-weight: 700;

            letter-spacing: -0.5px;
        }

        .page-description {
            margin: 0;

            max-width: 700px;

            color: #6b7280;

            font-size: 15px;
            line-height: 1.6;
        }

        .branch-badge {
            flex-shrink: 0;

            padding: 9px 13px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;
            border-radius: 9px;

            color: #1e40af;

            font-size: 13px;
            font-weight: 600;
        }

        /* =====================================================
           SUMMARY
           ===================================================== */

        .summary-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 22px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 17px;

            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(15, 23, 42, 0.04);
        }

        .summary-icon {
            width: 42px;
            height: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 11px;

            font-size: 19px;
        }

        .icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .icon-yellow {
            background: #fffbeb;
            color: #d97706;
        }

        .icon-green {
            background: #ecfdf5;
            color: #16a34a;
        }

        .summary-label {
            margin: 0 0 3px;

            color: #6b7280;

            font-size: 12px;
        }

        .summary-value {
            margin: 0;

            color: #111827;

            font-size: 19px;
            font-weight: 700;
        }

        /* =====================================================
           CARD
           ===================================================== */

        .card {
            margin-bottom: 20px;

            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 16px;

            overflow: hidden;

            box-shadow:
                0 4px 18px rgba(15, 23, 42, 0.05);
        }

        .card-header {
            padding: 20px 22px;

            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h2 {
            margin: 0 0 5px;

            font-size: 18px;
        }

        .card-header p {
            margin: 0;

            color: #6b7280;

            font-size: 13px;
            line-height: 1.5;
        }

        .card-body {
            padding: 22px;
        }

        /* =====================================================
           FORM
           ===================================================== */

        .form-grid {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 18px;
        }

        .form-group {
            margin-bottom: 0;
        }

        label {
            display: block;

            margin-bottom: 7px;

            color: #374151;

            font-size: 13px;
            font-weight: 700;
        }

        select {
            width: 100%;

            padding: 12px 13px;

            background: #ffffff;

            border: 1px solid #d1d5db;
            border-radius: 9px;

            color: #111827;

            font-size: 14px;

            outline: none;

            cursor: pointer;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }

        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .form-action {
            margin-top: 18px;
        }

        .primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            width: 100%;

            padding: 12px 15px;

            border: none;
            border-radius: 9px;

            background: #2563eb;
            color: #ffffff;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.1s;
        }

        .primary-button:hover {
            background: #1d4ed8;
        }

        .primary-button:active {
            transform: scale(0.99);
        }

        /* =====================================================
           NOTICE
           ===================================================== */

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-bottom: 18px;

            padding: 13px 15px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;
            border-radius: 10px;

            color: #1e40af;

            font-size: 13px;
            line-height: 1.5;
        }

        .notice-icon {
            flex-shrink: 0;

            font-size: 16px;
        }

        /* =====================================================
           TABLE
           ===================================================== */

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            min-width: 850px;

            border-collapse: collapse;
        }

        th {
            padding: 13px 16px;

            background: #f8fafc;

            color: #6b7280;

            font-size: 11px;
            font-weight: 700;

            text-align: left;

            text-transform: uppercase;
            letter-spacing: 0.3px;

            border-bottom: 1px solid #e5e7eb;

            white-space: nowrap;
        }

        td {
            padding: 15px 16px;

            border-bottom: 1px solid #f1f5f9;

            vertical-align: middle;

            font-size: 14px;
        }

        tbody tr {
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: #fafcff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* =====================================================
           UNIT
           ===================================================== */

        .unit-code {
            display: inline-flex;

            padding: 6px 9px;

            background: #f3f4f6;

            border-radius: 7px;

            color: #111827;

            font-family: monospace;

            font-size: 13px;
            font-weight: 700;
        }

        .unit-name {
            margin-top: 5px;

            color: #6b7280;

            font-size: 12px;
        }

        /* =====================================================
           TRANSFER ROUTE
           ===================================================== */

        .branch-route {
            display: flex;
            align-items: center;
            gap: 7px;

            white-space: nowrap;
        }

        .branch {
            color: #374151;

            font-size: 13px;
            font-weight: 600;
        }

        .arrow {
            color: #9ca3af;
        }

        /* =====================================================
           STATUS
           ===================================================== */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;
            font-weight: 700;

            white-space: nowrap;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .completed {
            background: #dbeafe;
            color: #1e40af;
        }

        /* =====================================================
           ACTIONS
           ===================================================== */

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-form {
            margin: 0;
        }

        .action-button {
            padding: 8px 11px;

            border: none;
            border-radius: 8px;

            color: #ffffff;

            font-size: 12px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.1s;
        }

        .action-button:active {
            transform: scale(0.98);
        }

        .approve-button {
            background: #16a34a;
        }

        .approve-button:hover {
            background: #15803d;
        }

        .reject-button {
            background: #dc2626;
        }

        .reject-button:hover {
            background: #b91c1c;
        }

        .scan-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            padding: 9px 12px;

            background: #2563eb;
            color: #ffffff;

            border-radius: 8px;

            text-decoration: none;

            font-size: 12px;
            font-weight: 700;

            transition: background 0.2s;
        }

        .scan-button:hover {
            background: #1d4ed8;
        }

        /* =====================================================
           TIME
           ===================================================== */

        .time {
            white-space: nowrap;
        }

        .date {
            display: block;

            color: #374151;

            font-size: 13px;
            font-weight: 600;
        }

        .clock {
            display: block;

            margin-top: 3px;

            color: #9ca3af;

            font-size: 11px;
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty {
            padding: 45px 25px;

            text-align: center;
        }

        .empty-icon {
            width: 54px;
            height: 54px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 13px;

            background: #f3f4f6;

            border-radius: 50%;

            color: #6b7280;

            font-size: 22px;
        }

        .empty h3 {
            margin: 0 0 6px;

            font-size: 16px;
        }

        .empty p {
            margin: 0;

            color: #6b7280;

            font-size: 13px;
            line-height: 1.5;
        }

        /* =====================================================
           INCOMING HIGHLIGHT
           ===================================================== */

        .incoming-card {
            border-color: #bfdbfe;
        }

        .incoming-card .card-header {
            background: #f8fbff;
        }

        /* =====================================================
           MOBILE
           ===================================================== */

        @media (max-width: 800px) {

            .container {
                width: 94%;

                margin:
                    22px auto
                    35px;
            }

            .page-header {
                display: block;
            }

            .branch-badge {
                display: inline-block;

                margin-top: 13px;
            }

            .page-title {
                font-size: 25px;
            }

            .page-description {
                font-size: 14px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 17px;
            }

            table {
                min-width: 850px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- =====================================================
         BACK
         ===================================================== -->

    <a
        href="dashboard.php"
        class="back"
    >
        ← Kembali ke Dashboard
    </a>


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="page-header">

        <div>

            <h1 class="page-title">
                Transfer / Oper Unit
            </h1>

            <p class="page-description">
                Kelola perpindahan unit antar cabang dengan
                proses persetujuan dan penerimaan menggunakan QR.
            </p>

        </div>

        <div class="branch-badge">

            Cabang:
            <?= htmlspecialchars(
                $current_branch["nama_cabang"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    </div>


    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon icon-blue">
                ⇄
            </div>

            <div>

                <p class="summary-label">
                    Unit siap ditransfer
                </p>

                <p class="summary-value">
                    <?= count($units) ?>
                </p>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-yellow">
                ◷
            </div>

            <div>

                <p class="summary-label">
                    Transfer saya
                </p>

                <p class="summary-value">
                    <?= count($transfers) ?>
                </p>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon icon-green">
                ↓
            </div>

            <div>

                <p class="summary-label">
                    Transfer masuk
                </p>

                <p class="summary-value">
                    <?= count($incoming_transfers) ?>
                </p>

            </div>

        </div>

    </div>


    <!-- =====================================================
         AJUKAN TRANSFER
         ===================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                Ajukan Transfer Unit
            </h2>

            <p>
                Pilih unit yang tersedia dan tentukan cabang
                tujuan transfer.
            </p>

        </div>

        <div class="card-body">

            <?php if (count($units) === 0): ?>

                <div class="empty">

                    <div class="empty-icon">
                        ✓
                    </div>

                    <h3>
                        Tidak ada unit yang siap ditransfer
                    </h3>

                    <p>
                        Unit harus berstatus tersedia dan tidak
                        sedang dalam proses transfer.
                    </p>

                </div>

            <?php elseif (count($branches) === 0): ?>

                <div class="empty">

                    <div class="empty-icon">
                        !
                    </div>

                    <h3>
                        Belum ada cabang tujuan
                    </h3>

                    <p>
                        Tidak tersedia cabang lain sebagai
                        tujuan transfer.
                    </p>

                </div>

            <?php else: ?>

                <div class="notice">

                    <div class="notice-icon">
                        ℹ
                    </div>

                    <div>
                        Setelah transfer diajukan, permintaan
                        harus disetujui terlebih dahulu sebelum
                        cabang tujuan dapat menerima unit.
                    </div>

                </div>


                <form
                    action="proses-transfer.php"
                    method="POST"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="buat"
                    >

                    <div class="form-grid">

                        <!-- UNIT -->

                        <div class="form-group">

                            <label for="unit_id">
                                Unit
                            </label>

                            <select
                                id="unit_id"
                                name="unit_id"
                                required
                            >

                                <option value="">
                                    -- Pilih Unit --
                                </option>

                                <?php foreach ($units as $unit): ?>

                                    <option
                                        value="<?= (int) $unit["id"] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $unit["kode_unit"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                        -
                                        <?= htmlspecialchars(
                                            $unit["nama_unit"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- CABANG TUJUAN -->

                        <div class="form-group">

                            <label for="to_branch_id">
                                Cabang Tujuan
                            </label>

                            <select
                                id="to_branch_id"
                                name="to_branch_id"
                                required
                            >

                                <option value="">
                                    -- Pilih Cabang Tujuan --
                                </option>

                                <?php foreach ($branches as $branch): ?>

                                    <option
                                        value="<?= (int) $branch["id"] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $branch["nama_cabang"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <div class="form-action">

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            ⇄ Ajukan Transfer
                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         TRANSFER SAYA
         ===================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                Transfer yang Saya Ajukan
            </h2>

            <p>
                Pantau status transfer unit yang diajukan
                oleh akun Anda.
            </p>

        </div>


        <?php if (count($transfers) === 0): ?>

            <div class="empty">

                <div class="empty-icon">
                    ◷
                </div>

                <h3>
                    Belum ada transfer
                </h3>

                <p>
                    Transfer yang Anda ajukan akan muncul
                    di bagian ini.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Unit
                            </th>

                            <th>
                                Perpindahan
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Waktu Pengajuan
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($transfers as $transfer): ?>

                        <?php

                        $status_class = strtolower(
                            $transfer["status"]
                        );

                        ?>

                        <tr>

                            <!-- UNIT -->

                            <td>

                                <span class="unit-code">

                                    <?= htmlspecialchars(
                                        $transfer["kode_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                                <div class="unit-name">

                                    <?= htmlspecialchars(
                                        $transfer["nama_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>


                            <!-- ROUTE -->

                            <td>

                                <div class="branch-route">

                                    <span class="branch">

                                        <?= htmlspecialchars(
                                            $transfer["dari_cabang"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                    <span class="arrow">
                                        →
                                    </span>

                                    <span class="branch">

                                        <?= htmlspecialchars(
                                            $transfer["ke_cabang"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        $status_class,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                    <?php if (
                                        $transfer["status"] === "PENDING"
                                    ): ?>

                                        ◷

                                    <?php elseif (
                                        $transfer["status"] === "APPROVED"
                                    ): ?>

                                        ✓

                                    <?php elseif (
                                        $transfer["status"] === "REJECTED"
                                    ): ?>

                                        ×

                                    <?php elseif (
                                        $transfer["status"] === "COMPLETED"
                                    ): ?>

                                        ✓

                                    <?php endif; ?>

                                    <?= htmlspecialchars(
                                        $transfer["status"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>


                                <?php if (
                                    $transfer["status"] === "PENDING"
                                ): ?>

                                    <div class="actions" style="margin-top: 10px;">

                                        <!-- SETUJUI -->

                                        <form
                                            class="action-form"
                                            action="proses-transfer.php"
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Setujui transfer unit ini?'
                                                );
                                            "
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve"
                                            >

                                            <input
                                                type="hidden"
                                                name="transfer_id"
                                                value="<?= (int) $transfer["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button approve-button"
                                            >
                                                ✓ Setujui
                                            </button>

                                        </form>


                                        <!-- TOLAK -->

                                        <form
                                            class="action-form"
                                            action="proses-transfer.php"
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Tolak transfer unit ini?'
                                                );
                                            "
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <input
                                                type="hidden"
                                                name="transfer_id"
                                                value="<?= (int) $transfer["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button reject-button"
                                            >
                                                × Tolak
                                            </button>

                                        </form>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- WAKTU -->

                            <td class="time">

                                <?php

                                $requestedTimestamp = strtotime(
                                    $transfer["requested_at"]
                                );

                                ?>

                                <span class="date">

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            $requestedTimestamp
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                                <span class="clock">

                                    <?= htmlspecialchars(
                                        date(
                                            "H:i",
                                            $requestedTimestamp
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    WIB

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         TRANSFER MASUK
         ===================================================== -->

    <div class="card incoming-card">

        <div class="card-header">

            <h2>
                Transfer Masuk
            </h2>

            <p>
                Unit dari cabang lain yang sudah disetujui
                dan siap diterima melalui scan QR.
            </p>

        </div>


        <?php if (count($incoming_transfers) === 0): ?>

            <div class="empty">

                <div class="empty-icon">
                    ↓
                </div>

                <h3>
                    Belum ada transfer masuk
                </h3>

                <p>
                    Transfer yang sudah disetujui untuk
                    cabang Anda akan muncul di sini.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Unit
                            </th>

                            <th>
                                Dari
                            </th>

                            <th>
                                Ke
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $incoming_transfers
                        as $transfer
                    ): ?>

                        <tr>

                            <!-- UNIT -->

                            <td>

                                <span class="unit-code">

                                    <?= htmlspecialchars(
                                        $transfer["kode_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                                <div class="unit-name">

                                    <?= htmlspecialchars(
                                        $transfer["nama_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>


                            <!-- DARI -->

                            <td>

                                <span class="branch">

                                    <?= htmlspecialchars(
                                        $transfer["dari_cabang"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- KE -->

                            <td>

                                <span class="branch">

                                    <?= htmlspecialchars(
                                        $transfer["ke_cabang"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="status approved">

                                    ✓ APPROVED

                                </span>

                            </td>


                            <!-- AKSI -->

                            <td>

                                <a
                                    class="scan-button"
                                    href="scan-transfer.php?transfer_id=<?= (int) $transfer["id"] ?>"
                                >
                                    ▣ Scan QR & Terima
                                </a>

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