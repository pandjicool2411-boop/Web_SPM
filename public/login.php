<?php

require_once "../config/database.php";
require_once "../config/csrf.php";

// ============================================================
// CACHE CONTROL
// ============================================================

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header("Pragma: no-cache");

// ============================================================
// VARIABLE
// ============================================================

$message = "";
$message_type = "";

$username = "";

// ============================================================
// PROSES LOGIN
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ========================================================
    // CEK CSRF
    // ========================================================

    verify_csrf();

    // ========================================================
    // AMBIL INPUT
    // ========================================================

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    // ========================================================
    // VALIDASI INPUT
    // ========================================================

    if ($username === "" || $password === "") {

        $message = "Username dan password wajib diisi.";
        $message_type = "error";

    } elseif (strlen($username) < 3) {

        $message = "Username minimal 3 karakter.";
        $message_type = "error";

    } elseif (strlen($username) > 50) {

        $message = "Username maksimal 50 karakter.";
        $message_type = "error";

    } else {

        try {

            // =================================================
            // AMBIL DATA USER
            // =================================================

            $stmt = $pdo->prepare(
                "SELECT
                    users.id,
                    users.nama,
                    users.no_hp,
                    users.username,
                    users.password_hash,
                    users.branch_id,
                    branches.nama_cabang
                 FROM users
                 INNER JOIN branches
                    ON users.branch_id = branches.id
                 WHERE users.username = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $username
            ]);

            $user = $stmt->fetch();

            // =================================================
            // VERIFIKASI USERNAME + PASSWORD
            // =================================================

            if (
                $user &&
                isset($user["password_hash"]) &&
                password_verify(
                    $password,
                    $user["password_hash"]
                )
            ) {

                // =============================================
                // REGENERATE SESSION ID
                // =============================================

                session_regenerate_id(true);

                // =============================================
                // SIMPAN DATA USER KE SESSION
                // =============================================

                $_SESSION["user_id"] =
                    (int) $user["id"];

                $_SESSION["nama"] =
                    $user["nama"];

                $_SESSION["username"] =
                    $user["username"];

                $_SESSION["branch_id"] =
                    (int) $user["branch_id"];

                $_SESSION["nama_cabang"] =
                    $user["nama_cabang"];

                // =============================================
                // BUAT CSRF TOKEN BARU
                // =============================================

                $_SESSION["csrf_token"] =
                    bin2hex(random_bytes(32));

                // =============================================
                // REDIRECT DASHBOARD
                // =============================================

                header("Location: dashboard.php");
                exit;

            } else {

                // =============================================
                // LOGIN GAGAL
                // =============================================
                //
                // Jangan membedakan:
                // - username tidak ditemukan
                // - password salah
                //
                // Tujuannya mengurangi username enumeration.

                $message =
                    "Username atau password salah.";

                $message_type = "error";
            }

        } catch (PDOException $e) {

            // =================================================
            // LOG ERROR DATABASE
            // =================================================

            error_log(
                "login.php database error: " .
                $e->getMessage()
            );

            // Jangan tampilkan detail database
            // kepada user.

            $message =
                "Terjadi kesalahan pada sistem. Silakan coba lagi.";

            $message_type = "error";
        }
    }
}

// ============================================================
// HELPER ESCAPE
// ============================================================

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

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>Login - Sistem Scan Unit</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;

            padding: 24px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff 0%,
                    #f8fafc 55%,
                    #eef2ff 100%
                );

            color: #0f172a;
        }

        .container {
            width: 100%;
            max-width: 420px;
        }

        .card {
            background: #ffffff;

            border: 1px solid #e2e8f0;
            border-radius: 22px;

            padding: 32px;

            box-shadow:
                0 24px 60px rgba(
                    15,
                    23,
                    42,
                    0.10
                );
        }

        .brand {
            width: 54px;
            height: 54px;

            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 16px;

            background: #2563eb;
            color: #ffffff;

            font-size: 17px;
            font-weight: 800;

            letter-spacing: -0.5px;

            box-shadow:
                0 8px 20px rgba(
                    37,
                    99,
                    235,
                    0.22
                );
        }

        h1 {
            margin: 0;

            text-align: center;

            font-size: 27px;
            line-height: 1.2;

            letter-spacing: -0.7px;
        }

        .subtitle {
            margin: 9px 0 27px;

            text-align: center;

            color: #64748b;

            font-size: 14px;
            line-height: 1.6;
        }

        .message {
            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 12px;

            font-size: 14px;
            line-height: 1.5;
        }

        .message.error {
            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: #334155;

            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;
            height: 47px;

            padding: 0 14px;

            border: 1px solid #cbd5e1;
            border-radius: 11px;

            background: #ffffff;
            color: #0f172a;

            font-family: inherit;
            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(
                    37,
                    99,
                    235,
                    0.12
                );
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 86px;
        }

        .toggle-password {
            position: absolute;

            top: 50%;
            right: 8px;

            transform: translateY(-50%);

            height: 34px;

            padding: 0 9px;

            border: none;
            border-radius: 8px;

            background: transparent;

            color: #64748b;

            font-family: inherit;
            font-size: 12px;
            font-weight: 700;

            cursor: pointer;
        }

        .toggle-password:hover {
            color: #2563eb;

            background: #f8fafc;
        }

        .button {
            width: 100%;
            height: 48px;

            margin-top: 5px;

            border: none;
            border-radius: 11px;

            background: #2563eb;
            color: #ffffff;

            font-family: inherit;
            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease,
                opacity 0.2s ease;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .button:active {
            transform: translateY(1px);
        }

        .button:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .register {
            margin-top: 22px;

            text-align: center;

            color: #64748b;

            font-size: 14px;
        }

        .register a {
            color: #2563eb;

            font-weight: 700;

            text-decoration: none;
        }

        .register a:hover {
            text-decoration: underline;
        }

        @media (max-width: 520px) {

            body {
                padding: 16px;

                align-items: flex-start;
            }

            .container {
                margin-top: 18px;
            }

            .card {
                padding: 25px 20px;

                border-radius: 18px;
            }

            h1 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="brand">
            QR
        </div>

        <h1>
            Selamat Datang
        </h1>

        <p class="subtitle">
            Masuk ke Sistem Scan Unit
        </p>

        <?php if ($message !== ""): ?>

            <div
                class="message <?= e($message_type) ?>"
                role="alert"
                aria-live="polite"
            >
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            id="loginForm"
            autocomplete="on"
        >

            <?= csrf_field(); ?>

            <!-- USERNAME -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($username) ?>"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    minlength="3"
                    maxlength="50"
                    required
                    autofocus
                >

            </div>

            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Tampilkan password"
                    >
                        Lihat
                    </button>

                </div>

            </div>

            <!-- SUBMIT -->

            <button
                type="submit"
                class="button"
                id="loginButton"
            >
                Login
            </button>

        </form>

        <div class="register">

            Belum punya akun?

            <a href="register.php">
                Register
            </a>

        </div>

    </div>

</div>

<script>

    // =========================================================
    // SHOW / HIDE PASSWORD
    // =========================================================

    const passwordInput =
        document.getElementById("password");

    const togglePassword =
        document.getElementById("togglePassword");

    if (passwordInput && togglePassword) {

        togglePassword.addEventListener(
            "click",
            function () {

                const isPassword =
                    passwordInput.type === "password";

                passwordInput.type =
                    isPassword
                        ? "text"
                        : "password";

                togglePassword.textContent =
                    isPassword
                        ? "Sembunyikan"
                        : "Lihat";

                togglePassword.setAttribute(
                    "aria-label",
                    isPassword
                        ? "Sembunyikan password"
                        : "Tampilkan password"
                );

            }
        );

    }

    // =========================================================
    // PREVENT DOUBLE SUBMIT
    // =========================================================

    const loginForm =
        document.getElementById("loginForm");

    const loginButton =
        document.getElementById("loginButton");

    if (loginForm && loginButton) {

        loginForm.addEventListener(
            "submit",
            function () {

                if (!loginForm.checkValidity()) {
                    return;
                }

                loginButton.disabled = true;

                loginButton.textContent =
                    "Memproses...";

            }
        );

    }

</script>

</body>

</html>