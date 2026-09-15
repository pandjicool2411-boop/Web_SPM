<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

// ============================================================
// AMBIL UNIT YANG SEDANG DISEWAKAN
// HANYA UNIT MILIK CABANG USER YANG LOGIN
// ============================================================

try {

    $stmt = $pdo->prepare(
        "SELECT
            rentals.id AS rental_id,
            units.id AS unit_id,
            units.kode_unit,
            units.nama_unit,
            units.kategori,
            rentals.renter_code,
            rentals.checkout_at
         FROM rentals
         INNER JOIN units
            ON rentals.unit_id = units.id
         WHERE rentals.status = 'AKTIF'
           AND units.owner_branch_id = ?
           AND units.status = 'DISEWAKAN'
         ORDER BY rentals.checkout_at DESC"
    );

    $stmt->execute([
        (int) $_SESSION["branch_id"]
    ]);

    $rentals = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "unit-masuk.php database error: " .
        $e->getMessage()
    );

    $rentals = [];
}

$totalRentals = count($rentals);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Unit Masuk - Sistem Scan Unit</title>

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
            max-width: 1100px;
            margin: 32px auto 50px;
        }

        /* =====================================================
           TOP NAVIGATION
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

            max-width: 700px;

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

            font-size: 14px;
            color: #6b7280;
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
           ALERT
           ===================================================== */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 11px;

            margin-bottom: 20px;

            padding: 15px 17px;

            border-radius: 12px;

            font-size: 14px;
            line-height: 1.5;
        }

        .alert-icon {
            flex-shrink: 0;

            font-size: 17px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
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

            border-collapse: collapse;

            min-width: 820px;
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
        }

        td {
            padding: 15px 16px;

            font-size: 14px;

            border-bottom: 1px solid #f1f5f9;

            vertical-align: middle;
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
           UNIT INFO
           ===================================================== */

        .unit-code {
            display: inline-flex;
            align-items: center;

            padding: 6px 9px;

            background: #f3f4f6;

            border-radius: 7px;

            color: #111827;

            font-size: 13px;
            font-weight: 700;
            font-family: monospace;
        }

        .unit-name {
            font-weight: 600;
            color: #111827;
        }

        .category {
            color: #6b7280;
        }

        .renter-code {
            display: inline-flex;
            align-items: center;

            padding: 6px 10px;

            background: #fef3c7;

            color: #92400e;

            border-radius: 7px;

            font-size: 13px;
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 1px;
        }

        .checkout-time {
            color: #6b7280;
            font-size: 13px;
            white-space: nowrap;
        }

        /* =====================================================
           ACTION BUTTON
           ===================================================== */

        .action-form {
            margin: 0;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            padding: 9px 13px;

            border: none;
            border-radius: 8px;

            background: #16a34a;
            color: #ffffff;

            font-size: 13px;
            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.1s;
        }

        .button:hover {
            background: #15803d;
        }

        .button:active {
            transform: scale(0.98);
        }

        .button:focus-visible {
            outline: 3px solid rgba(22, 163, 74, 0.25);
            outline-offset: 2px;
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty {
            padding: 55px 25px;

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

            /*
             * Tabel tetap bisa digeser horizontal
             * agar data tidak dipotong di HP.
             */

            th,
            td {
                padding: 12px;
            }

            .button {
                width: 100%;
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
            Unit Masuk
        </h1>

        <p class="page-description">
            Kelola unit yang masih dalam status disewakan
            dan konfirmasi ketika unit sudah kembali
            ke cabang Anda.
        </p>

    </div>


    <!-- =====================================================
         SUCCESS MESSAGE
         ===================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success">

            <div class="alert-icon">
                ✓
            </div>

            <div>
                <strong>Unit berhasil dikonfirmasi masuk.</strong>
                Status unit sekarang sudah kembali menjadi
                tersedia.
            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="summary">

        <div class="summary-left">

            <div class="summary-icon">
                ↩
            </div>

            <div>

                <p class="summary-title">
                    Unit sedang disewakan
                </p>

                <p class="summary-text">
                    Perlu dikonfirmasi saat kembali
                </p>

            </div>

        </div>

        <div class="summary-number">
            <?= $totalRentals ?>
        </div>

    </div>


    <!-- =====================================================
         INFO
         ===================================================== -->

    <?php if ($totalRentals > 0): ?>

        <div class="alert alert-info">

            <div class="alert-icon">
                ℹ
            </div>

            <div>
                Pastikan unit sudah benar-benar kembali
                sebelum menekan tombol
                <strong>Konfirmasi Masuk</strong>.
            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         MAIN CARD
         ===================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                Daftar Unit Aktif
            </h2>

            <p>
                Unit yang sedang berada dalam status
                disewakan di cabang Anda.
            </p>

        </div>


        <?php if ($totalRentals > 0): ?>

            <!-- =================================================
                 TABLE
                 ================================================= -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Kode Unit
                            </th>

                            <th>
                                Nama Unit
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Kode Penyewa
                            </th>

                            <th>
                                Waktu Keluar
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($rentals as $rental): ?>

                        <tr>

                            <!-- KODE UNIT -->

                            <td>

                                <span class="unit-code">

                                    <?= htmlspecialchars(
                                        $rental["kode_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- NAMA UNIT -->

                            <td>

                                <div class="unit-name">

                                    <?= htmlspecialchars(
                                        $rental["nama_unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>


                            <!-- KATEGORI -->

                            <td>

                                <span class="category">

                                    <?= htmlspecialchars(
                                        $rental["kategori"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- KODE PENYEWA -->

                            <td>

                                <span class="renter-code">

                                    <?= htmlspecialchars(
                                        $rental["renter_code"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- WAKTU KELUAR -->

                            <td>

                                <span class="checkout-time">

                                    <?= htmlspecialchars(
                                        $rental["checkout_at"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- AKSI -->

                            <td>

                                <form
                                    method="POST"
                                    action="proses-masuk.php"
                                    class="action-form"
                                    onsubmit="
                                        return confirm(
                                            'Konfirmasi unit sudah benar-benar kembali?'
                                        );
                                    "
                                >

                                    <!-- RENTAL ID -->

                                    <input
                                        type="hidden"
                                        name="rental_id"
                                        value="<?= (int) $rental["rental_id"] ?>"
                                    >

                                    <!-- CSRF TOKEN -->

                                    <?= csrf_field() ?>

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        ✓ Konfirmasi Masuk
                                    </button>

                                </form>

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
                    ✓
                </div>

                <h3>
                    Tidak ada unit yang sedang disewakan
                </h3>

                <p>
                    Semua unit di cabang Anda saat ini
                    sudah tersedia.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>