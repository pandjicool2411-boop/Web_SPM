<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Permintaan tidak valid.";
    } else {

        $nama = trim($_POST['nama'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $branch_id = (int) ($_POST['branch_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */

        if (
            $nama === '' ||
            $no_hp === '' ||
            $branch_id <= 0 ||
            $username === '' ||
            $password === '' ||
            $konfirmasi_password === ''
        ) {

            $error = "Semua data wajib diisi.";

        } elseif ($password !== $konfirmasi_password) {

            $error = "Konfirmasi password tidak sama.";

        } elseif (strlen($password) < 6) {

            $error = "Password minimal 6 karakter.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Cek cabang
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id, nama_cabang
                FROM branches
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$branch_id]);

            $branch = $stmt->fetch();

            if (!$branch) {

                $error = "Cabang tidak valid.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Cek username
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ?
                    LIMIT 1
                ");

                $stmt->execute([$username]);

                if ($stmt->fetch()) {

                    $error = "Username sudah digunakan.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Buat akun
                    |--------------------------------------------------------------------------
                    */

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            branch_id,
                            nama,
                            no_hp,
                            username,
                            password_hash
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $branch_id,
                        $nama,
                        $no_hp,
                        $username,
                        $password_hash
                    ]);

                    $success = "Akun berhasil dibuat. Silakan login.";

                    $_POST = [];
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil daftar cabang
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, nama_cabang
    FROM branches
    ORDER BY id ASC
");

$branches = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daftar - Sistem Scan Unit</title>

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
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-box {
            width: 100%;
            max-width: 460px;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 15px;
            background: white;
        }

        button {
            width: 100%;
            border: 0;
            padding: 13px;
            border-radius: 10px;
            background: #111827;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            opacity: .9;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .login {
            text-align: center;
            margin-top: 20px;
        }

        .login a {
            color: #2563eb;
            text-decoration: none;
        }

    </style>

</head>

<body>

<div class="register-box">

    <h1>Daftar Akun</h1>

    <div class="subtitle">
        Sistem Scan Unit
    </div>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token()) ?>"
        >


        <label>Nama</label>

        <input
            type="text"
            name="nama"
            placeholder="Nama lengkap"
            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
            required
        >


        <label>No. HP</label>

        <input
            type="tel"
            name="no_hp"
            placeholder="08xxxxxxxxxx"
            value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
            required
        >


        <label>Cabang</label>

        <select name="branch_id" required>

            <option value="">
                -- Pilih Cabang --
            </option>

            <?php foreach ($branches as $branch): ?>

                <option
                    value="<?= $branch['id'] ?>"
                    <?= (
                        isset($_POST['branch_id']) &&
                        (int) $_POST['branch_id'] === (int) $branch['id']
                    ) ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($branch['nama_cabang']) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Minimal 6 karakter"
            autocomplete="new-password"
            required
        >


        <label>Konfirmasi Password</label>

        <input
            type="password"
            name="konfirmasi_password"
            placeholder="Ulangi password"
            autocomplete="new-password"
            required
        >


        <button type="submit">
            Daftar
        </button>

    </form>


    <div class="login">

        Sudah punya akun?

        <a href="login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>