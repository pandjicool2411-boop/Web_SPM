<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

verify_csrf();

$message = "";
$message_type = "";

// ==========================
// PROSES TAMBAH CABANG
// ==========================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama_cabang = trim($_POST["nama_cabang"] ?? "");

    // Validasi
    if ($nama_cabang === "") {

        $message = "Nama cabang wajib diisi.";
        $message_type = "error";

    } elseif (mb_strlen($nama_cabang) > 100) {

        $message = "Nama cabang maksimal 100 karakter.";
        $message_type = "error";

    } else {

        try {

            // Cek apakah nama cabang sudah ada
            $check = $pdo->prepare(
                "SELECT id
                 FROM branches
                 WHERE nama_cabang = ?
                 LIMIT 1"
            );

            $check->execute([$nama_cabang]);

            if ($check->fetch()) {

                $message = "Cabang tersebut sudah terdaftar.";
                $message_type = "error";

            } else {

                // Simpan cabang
                $stmt = $pdo->prepare(
                    "INSERT INTO branches (nama_cabang)
                     VALUES (?)"
                );

                $stmt->execute([$nama_cabang]);

                $message = "Cabang berhasil ditambahkan.";
                $message_type = "success";
            }

        } catch (PDOException $e) {

            // Jangan tampilkan detail database ke user
            error_log(
                "Gagal menambahkan cabang: " . $e->getMessage()
            );

            $message = "Terjadi kesalahan saat menambahkan cabang.";
            $message_type = "error";
        }
    }
}


// ==========================
// AMBIL DATA CABANG
// ==========================

try {

    $stmt = $pdo->query(
        "SELECT id, nama_cabang, created_at
         FROM branches
         ORDER BY id ASC"
    );

    $branches = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "Gagal mengambil data cabang: " . $e->getMessage()
    );

    $branches = [];

    if ($message === "") {
        $message = "Data cabang tidak dapat dimuat.";
        $message_type = "error";
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

    <title>Manajemen Cabang</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #222;
        }

        .container {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            border: none;
            background: #2563eb;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f5f9;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 25px;
        }

        .user-info {
            background: #e0f2fe;
            color: #075985;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .user-info strong {
            display: block;
            margin-bottom: 3px;
        }

        @media (max-width: 600px) {

            .container {
                width: 94%;
                margin: 20px auto;
            }

            .card {
                padding: 18px;
            }

            table {
                font-size: 14px;
            }

            th,
            td {
                padding: 10px 8px;
            }

            /*
             * Supaya tabel tetap nyaman
             * di layar HP
             */
            .table-wrapper {
                overflow-x: auto;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Manajemen Cabang</h1>

    <p class="subtitle">
        Tambahkan dan lihat daftar cabang yang terdaftar.
    </p>


    <!-- INFORMASI USER -->

    <div class="user-info">

        <strong>
            Login sebagai:
            <?= htmlspecialchars($_SESSION["nama"]) ?>
        </strong>

        Cabang:
        <?= htmlspecialchars($_SESSION["nama_cabang"] ?? "Tidak diketahui") ?>

    </div>


    <!-- PESAN -->

    <?php if ($message !== ""): ?>

        <div class="message <?= htmlspecialchars($message_type) ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- FORM TAMBAH CABANG -->

    <div class="card">

        <h2>Tambah Cabang</h2>

        <form method="POST">

            <?= csrf_field() ?>

            <label for="nama_cabang">
                Nama Cabang
            </label>

            <input
                type="text"
                id="nama_cabang"
                name="nama_cabang"
                placeholder="Contoh: Medan Pancing"
                maxlength="100"
                autocomplete="off"
                required
            >

            <button type="submit">
                Tambah Cabang
            </button>

        </form>

    </div>


    <!-- DAFTAR CABANG -->

    <div class="card">

        <h2>Daftar Cabang</h2>

        <?php if (count($branches) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Nama Cabang</th>

                            <th>Dibuat</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($branches as $branch): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $branch["id"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $branch["nama_cabang"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $branch["created_at"]
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                Belum ada cabang yang terdaftar.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>