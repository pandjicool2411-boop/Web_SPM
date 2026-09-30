<?php

require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/csrf.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Permintaan tidak valid.";
    } else {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = "Username dan password wajib diisi.";
        } else {

            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.branch_id,
                    u.nama,
                    u.username,
                    u.password_hash,
                    b.nama_cabang
                FROM users u
                INNER JOIN branches b 
                    ON b.id = u.branch_id
                WHERE u.username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_cabang'] = $user['nama_cabang'];

                header("Location: dashboard.php");
                exit;

            } else {
                $error = "Username atau password salah.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Sistem Scan Unit</title>

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

        .login-box {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
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

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 15px;
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

        .register {
            text-align: center;
            margin-top: 20px;
        }

        .register a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h1>Login</h1>

    <div class="subtitle">
        Sistem Scan Unit
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token()) ?>"
        >

        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Masukkan username"
            autocomplete="username"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Masukkan password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Masuk
        </button>

    </form>

    <div class="register">
        Belum punya akun?
        <a href="register.php">Daftar</a>
    </div>

</div>

</body>
</html>