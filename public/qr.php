<?php

require_once "../config/auth.php";
require_once "../config/database.php";

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

try {

    // Ambil semua unit
    $stmt = $pdo->query(
        "SELECT
            units.id,
            units.kode_unit,
            units.nama_unit,
            units.kategori,
            units.qr_token,
            units.status,
            branches.nama_cabang
         FROM units
         INNER JOIN branches
            ON units.owner_branch_id = branches.id
         ORDER BY units.id DESC"
    );

    $units = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "QR Unit database error: " . $e->getMessage()
    );

    $units = [];
    $database_error = true;
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

    <title>QR Unit - Sistem Scan Unit</title>

    <!-- Library QR Code -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

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
            margin: 0 auto;
            padding: 32px 0 50px;
        }

        /* =========================
           HEADER
        ========================= */

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back:hover {
            text-decoration: underline;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 7px;
            font-size: 30px;
            letter-spacing: -0.5px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.6;
        }

        /* =========================
           INFO
        ========================= */

        .info-box {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }

        /* =========================
           QR GRID
        ========================= */

        .qr-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        /* =========================
           QR CARD
        ========================= */

        .qr-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            text-align: center;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.05);

            display: flex;
            flex-direction: column;
            align-items: center;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .qr-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 10px 25px rgba(15, 23, 42, 0.08);
        }

        /* =========================
           QR
        ========================= */

        .qr-wrapper {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 18px;
        }

        .qr-box {
            width: 200px;
            height: 200px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-box img,
        .qr-box canvas {
            display: block;
            max-width: 100%;
            height: auto;
        }

        /* =========================
           UNIT INFO
        ========================= */

        .unit-info {
            width: 100%;
        }

        .kode {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 5px;
        }

        .nama {
            font-size: 15px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
        }

        .details {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 12px;
        }

        .detail {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.4;
        }

        .detail strong {
            color: #374151;
        }

        /* =========================
           STATUS
        ========================= */

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 6px 11px;
            border-radius: 999px;

            font-size: 12px;
            font-weight: 700;
        }

        .status.tersedia {
            background: #dcfce7;
            color: #166534;
        }

        .status.disewakan {
            background: #fee2e2;
            color: #991b1b;
        }

        /* =========================
           BUTTON
        ========================= */

        .print-button {
            width: 100%;
            margin-top: 18px;

            border: none;
            border-radius: 10px;

            padding: 11px 15px;

            background: #2563eb;
            color: #ffffff;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease;
        }

        .print-button:hover {
            background: #1d4ed8;
        }

        .print-button:active {
            transform: scale(0.98);
        }

        /* =========================
           EMPTY / ERROR
        ========================= */

        .empty {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 45px 25px;
            text-align: center;
            color: #6b7280;
        }

        .empty-title {
            font-size: 17px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 7px;
        }

        .empty-text {
            font-size: 14px;
            line-height: 1.5;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 16px;
            border-radius: 12px;
            font-size: 14px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 950px) {

            .qr-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            .container {
                width: 94%;
                padding-top: 22px;
            }

            .header h1 {
                font-size: 26px;
            }

            .qr-grid {
                grid-template-columns: 1fr;
            }

            .qr-card {
                padding: 20px;
            }

            .qr-box {
                width: 190px;
                height: 190px;
            }

        }

        /* =========================
           PRINT
        ========================= */

        @media print {

            body {
                background: #ffffff;
            }

            .container {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .top-bar,
            .header,
            .info-box,
            .print-button {
                display: none !important;
            }

            .qr-grid {
                display: block;
            }

            .qr-card {
                display: none;
                box-shadow: none;
                border: 1px solid #000000;
                border-radius: 0;
                width: 100%;
                max-width: 400px;
                margin: 0 auto;
                padding: 25px;
            }

            .qr-card.printing {
                display: flex;
            }

            .qr-wrapper {
                border: none;
            }

            .qr-box {
                width: 240px;
                height: 240px;
            }

            .kode {
                font-size: 22px;
            }

            .nama {
                font-size: 16px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="top-bar">

        <a
            href="dashboard.php"
            class="back"
        >
            ← Kembali ke Dashboard
        </a>

    </div>

    <div class="header">

        <h1>QR Unit</h1>

        <p>
            QR Code digunakan sebagai identitas
            permanen setiap unit.
        </p>

    </div>

    <?php if (isset($database_error)): ?>

        <div class="error-box">
            Data QR unit tidak dapat dimuat saat ini.
            Silakan coba lagi beberapa saat.
        </div>

    <?php elseif (count($units) > 0): ?>

        <div class="info-box">
            Setiap QR Code terhubung dengan satu unit.
            QR Code tetap sama meskipun unit berpindah
            cabang.
        </div>

        <div class="qr-grid">

            <?php foreach ($units as $unit): ?>

                <?php
                    $unit_id = (int) $unit["id"];

                    $status_class =
                        $unit["status"] === "TERSEDIA"
                            ? "tersedia"
                            : "disewakan";
                ?>

                <div
                    class="qr-card"
                    id="card-<?= $unit_id ?>"
                >

                    <div class="qr-wrapper">

                        <div
                            class="qr-box"
                            id="qr-<?= $unit_id ?>"
                        ></div>

                    </div>

                    <div class="unit-info">

                        <div class="kode">
                            <?= e($unit["kode_unit"]) ?>
                        </div>

                        <div class="nama">
                            <?= e($unit["nama_unit"]) ?>
                        </div>

                        <div class="details">

                            <div class="detail">
                                Kategori:
                                <strong>
                                    <?= e($unit["kategori"]) ?>
                                </strong>
                            </div>

                            <div class="detail">
                                Cabang:
                                <strong>
                                    <?= e($unit["nama_cabang"]) ?>
                                </strong>
                            </div>

                        </div>

                        <span
                            class="status <?= $status_class ?>"
                        >
                            <?= e($unit["status"]) ?>
                        </span>

                    </div>

                    <button
                        type="button"
                        class="print-button"
                        onclick="printQR(<?= $unit_id ?>)"
                    >
                        Cetak QR
                    </button>

                </div>

                <script>
                    new QRCode(
                        document.getElementById(
                            "qr-<?= $unit_id ?>"
                        ),
                        {
                            text: <?= json_encode(
                                $unit["qr_token"],
                                JSON_HEX_TAG |
                                JSON_HEX_AMP |
                                JSON_HEX_APOS |
                                JSON_HEX_QUOT
                            ) ?>,

                            width: 200,
                            height: 200
                        }
                    );
                </script>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-title">
                Belum ada unit
            </div>

            <div class="empty-text">
                Belum ada unit yang terdaftar.
                Silakan tambahkan unit terlebih dahulu
                melalui menu Daftar Unit.
            </div>

        </div>

    <?php endif; ?>

</div>

<script>

    function printQR(unitId) {

        const card = document.getElementById(
            "card-" + unitId
        );

        if (!card) {
            return;
        }

        document
            .querySelectorAll(".qr-card.printing")
            .forEach(function (item) {

                item.classList.remove("printing");

            });

        card.classList.add("printing");

        window.print();

    }

    window.addEventListener(
        "afterprint",
        function () {

            document
                .querySelectorAll(".qr-card.printing")
                .forEach(function (item) {

                    item.classList.remove("printing");

                });

        }
    );

</script>

</body>
</html>