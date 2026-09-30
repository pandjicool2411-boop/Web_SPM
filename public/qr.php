<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_login();

$unit_id = (int) ($_GET['id'] ?? 0);

if ($unit_id <= 0) {
    header("Location: units.php");
    exit;
}

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
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$unit_id]);

$unit = $stmt->fetch();

if (!$unit) {
    header("Location: units.php");
    exit;
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

    <title>QR <?= htmlspecialchars($unit['kode_unit']) ?></title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .card {
            width: 100%;
            max-width: 430px;
            background: white;
            border-radius: 18px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        h1 {
            margin-top: 0;
        }

        .code {
            font-size: 30px;
            font-weight: bold;
            margin: 10px 0;
        }

        .name {
            color: #555;
            margin-bottom: 20px;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        #qrcode img {
            margin: auto;
        }

        .info {
            background: #f3f4f6;
            padding: 14px;
            border-radius: 10px;
            text-align: left;
            margin-top: 15px;
        }

        .info div {
            padding: 5px 0;
        }

        .label {
            color: #6b7280;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        button,
        a {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            border: 0;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #111827;
            color: white;
        }

        a {
            background: #e5e7eb;
            color: #111827;
        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .card {
                box-shadow: none;
                max-width: none;
            }

            .buttons {
                display: none;
            }

        }

    </style>

</head>

<body>

<div class="card">

    <h1>QR Unit</h1>

    <div class="code">
        <?= htmlspecialchars($unit['kode_unit']) ?>
    </div>

    <div class="name">
        <?= htmlspecialchars($unit['nama_unit']) ?>
    </div>

    <div id="qrcode"></div>

    <div class="info">

        <div>
            <span class="label">Kategori:</span>
            <?= htmlspecialchars($unit['kategori']) ?>
        </div>

        <div>
            <span class="label">Jumlah:</span>
            <?= (int) $unit['jumlah'] ?>
        </div>

        <div>
            <span class="label">Cabang:</span>
            <?= htmlspecialchars($unit['nama_cabang']) ?>
        </div>

    </div>

    <div class="buttons">

        <button onclick="window.print()">
            🖨️ Cetak QR
        </button>

        <a href="units.php">
            ← Kembali
        </a>

    </div>

</div>

<script>

    new QRCode(
        document.getElementById("qrcode"),
        {
            text: <?= json_encode($unit['kode_unit']) ?>,
            width: 240,
            height: 240
        }
    );

</script>

</body>

</html>