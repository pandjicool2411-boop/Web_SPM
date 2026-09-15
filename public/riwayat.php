<?php

require_once "../config/auth.php";
require_once "../config/database.php";

// ============================================================
// AMBIL SEMUA RIWAYAT
// ============================================================

try {

    $stmt = $pdo->query(
        "SELECT
            unit_history.id,
            units.kode_unit,
            units.nama_unit,
            unit_history.action,
            unit_history.description,
            users.nama AS nama_user,
            branches.nama_cabang,
            unit_history.created_at
         FROM unit_history
         INNER JOIN units
            ON unit_history.unit_id = units.id
         INNER JOIN users
            ON unit_history.performed_by = users.id
         INNER JOIN branches
            ON unit_history.branch_id = branches.id
         ORDER BY unit_history.created_at DESC"
    );

    $history = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "riwayat.php database error: " .
        $e->getMessage()
    );

    $history = [];
}

$totalHistory = count($history);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Riwayat Unit - Sistem Scan Unit</title>

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
            max-width: 1200px;
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

            max-width: 720px;

            color: #6b7280;

            font-size: 15px;
            line-height: 1.6;
        }

        /* =====================================================
           SUMMARY
           ===================================================== */

        .summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            margin-bottom: 20px;

            padding: 18px 20px;

            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(15, 23, 42, 0.04);
        }

        .summary-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .summary-icon {
            width: 42px;
            height: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;

            border-radius: 11px;

            color: #2563eb;

            font-size: 20px;
        }

        .summary-title {
            margin: 0 0 3px;

            color: #6b7280;

            font-size: 14px;
        }

        .summary-text {
            margin: 0;

            font-size: 16px;
            font-weight: 700;
        }

        .summary-number {
            min-width: 42px;
            height: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 0 12px;

            background: #2563eb;
            color: white;

            border-radius: 10px;

            font-size: 18px;
            font-weight: 700;
        }

        /* =====================================================
           MAIN CARD
           ===================================================== */

        .card {
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
            margin: 0 0 4px;

            font-size: 18px;
        }

        .card-header p {
            margin: 0;

            color: #6b7280;

            font-size: 13px;
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

            min-width: 1000px;

            border-collapse: collapse;
        }

        th {
            padding: 13px 16px;

            background: #f8fafc;

            color: #6b7280;

            font-size: 12px;
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
           TIME
           ===================================================== */

        .time {
            min-width: 125px;
        }

        .date {
            display: block;

            color: #111827;

            font-size: 13px;
            font-weight: 600;
        }

        .clock {
            display: block;

            margin-top: 3px;

            color: #9ca3af;

            font-size: 12px;
        }

        /* =====================================================
           UNIT
           ===================================================== */

        .unit-code {
            display: inline-flex;
            align-items: center;

            padding: 6px 9px;

            background: #f3f4f6;

            border-radius: 7px;

            color: #111827;

            font-family: monospace;

            font-size: 13px;
            font-weight: 700;
        }

        .unit-name {
            color: #111827;

            font-size: 14px;
            font-weight: 600;
        }

        /* =====================================================
           ACTION BADGE
           ===================================================== */

        .action {
            display: inline-flex;
            align-items: center;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;
            font-weight: 700;

            white-space: nowrap;
        }

        .keluar {
            background: #fee2e2;
            color: #991b1b;
        }

        .masuk {
            background: #dcfce7;
            color: #166534;
        }

        .transfer {
            background: #dbeafe;
            color: #1e40af;
        }

        .lainnya {
            background: #f3f4f6;
            color: #374151;
        }

        /* =====================================================
           DESCRIPTION
           ===================================================== */

        .description {
            max-width: 300px;

            color: #4b5563;

            font-size: 13px;
            line-height: 1.5;
        }

        /* =====================================================
           USER & BRANCH
           ===================================================== */

        .user-name {
            color: #111827;

            font-weight: 600;
        }

        .branch-name {
            display: inline-block;

            padding: 5px 8px;

            background: #f8fafc;

            border: 1px solid #e5e7eb;
            border-radius: 7px;

            color: #4b5563;

            font-size: 12px;
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty {
            padding: 60px 25px;

            text-align: center;
        }

        .empty-icon {
            width: 58px;
            height: 58px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 15px;

            background: #f3f4f6;

            border-radius: 50%;

            color: #6b7280;

            font-size: 25px;
        }

        .empty h3 {
            margin: 0 0 7px;

            font-size: 17px;
        }

        .empty p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
            line-height: 1.5;
        }

        /* =====================================================
           MOBILE
           ===================================================== */

        @media (max-width: 700px) {

            .container {
                width: 94%;
                margin: 22px auto 35px;
            }

            .page-title {
                font-size: 25px;
            }

            .page-description {
                font-size: 14px;
            }

            .summary {
                padding: 15px;
            }

            .summary-text {
                font-size: 14px;
            }

            .summary-number {
                min-width: 38px;
                height: 38px;

                font-size: 16px;
            }

            .card-header {
                padding: 17px;
            }

            th,
            td {
                padding: 12px;
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
         PAGE HEADER
         ===================================================== -->

    <div class="page-header">

        <h1 class="page-title">
            Riwayat Unit
        </h1>

        <p class="page-description">
            Pantau seluruh aktivitas unit yang tercatat
            dalam sistem, termasuk unit keluar, unit masuk,
            dan proses transfer antar cabang.
        </p>

    </div>


    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="summary">

        <div class="summary-left">

            <div class="summary-icon">
                ◷
            </div>

            <div>

                <p class="summary-title">
                    Total aktivitas
                </p>

                <p class="summary-text">
                    Riwayat tercatat dalam sistem
                </p>

            </div>

        </div>

        <div class="summary-number">
            <?= $totalHistory ?>
        </div>

    </div>


    <!-- =====================================================
         MAIN CARD
         ===================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                Aktivitas Terbaru
            </h2>

            <p>
                Data diurutkan dari aktivitas terbaru.
            </p>

        </div>


        <?php if ($totalHistory > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Waktu
                            </th>

                            <th>
                                Kode Unit
                            </th>

                            <th>
                                Nama Unit
                            </th>

                            <th>
                                Aktivitas
                            </th>

                            <th>
                                Keterangan
                            </th>

                            <th>
                                Dilakukan Oleh
                            </th>

                            <th>
                                Cabang
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($history as $item): ?>

                        <?php

                        $actionClass = "lainnya";

                        if (
                            $item["action"] === "UNIT_KELUAR"
                        ) {

                            $actionClass = "keluar";

                        } elseif (
                            $item["action"] === "UNIT_MASUK"
                        ) {

                            $actionClass = "masuk";

                        } elseif (
                            strpos(
                                $item["action"],
                                "TRANSFER"
                            ) !== false
                        ) {

                            $actionClass = "transfer";
                        }

                        $timestamp = strtotime(
                            $item["created_at"]
                        );

                        ?>

                        <tr>

                            <!-- WAKTU -->

                            <td class="time">

                                <span class="date">

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            $timestamp
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                                <span class="clock">

                                    <?= htmlspecialchars(
                                        date(
                                            "H:i",
                                            $timestamp
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    WIB

                                </span>

                            </td>


                            <!-- KODE UNIT -->

                            <td>

                                <span class="unit-code">

                                    <?= htmlspecialchars(
                                        $item["kode_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- NAMA UNIT -->

                            <td>

                                <span class="unit-name">

                                    <?= htmlspecialchars(
                                        $item["nama_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- AKTIVITAS -->

                            <td>

                                <span
                                    class="action <?= htmlspecialchars(
                                        $actionClass,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $item["action"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- KETERANGAN -->

                            <td>

                                <div class="description">

                                    <?= htmlspecialchars(
                                        $item["description"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>


                            <!-- USER -->

                            <td>

                                <span class="user-name">

                                    <?= htmlspecialchars(
                                        $item["nama_user"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- CABANG -->

                            <td>

                                <span class="branch-name">

                                    <?= htmlspecialchars(
                                        $item["nama_cabang"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <!-- =================================================
                 EMPTY STATE
                 ================================================= -->

            <div class="empty">

                <div class="empty-icon">
                    ◷
                </div>

                <h3>
                    Belum ada riwayat aktivitas
                </h3>

                <p>
                    Aktivitas unit seperti keluar, masuk,
                    dan transfer akan muncul di halaman ini.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>