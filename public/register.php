<?php

require_once "../config/database.php";
require_once "../config/csrf.php";

$message = "";
$message_type = "";

$nama = "";
$no_hp = "";
$username = "";
$branch_id = "";

// ============================================================
// PROSES REGISTER
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ========================================================
    // CEK CSRF
    // ========================================================

    verify_csrf();

    // ========================================================
    // AMBIL INPUT
    // ========================================================

    $nama = trim($_POST["nama"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $branch_id = $_POST["branch_id"] ?? "";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    // ========================================================
    // VALIDASI FIELD WAJIB
    // ========================================================

    if (
        $nama === "" ||
        $no_hp === "" ||
        $branch_id === "" ||
        $username === "" ||
        $password === ""
    ) {

        $message = "Semua field wajib diisi.";
        $message_type = "error";

    } elseif (
        !ctype_digit((string) $branch_id) ||
        (int) $branch_id <= 0
    ) {

        $message = "Cabang tidak valid.";
        $message_type = "error";

    } elseif (strlen($nama) > 100) {

        $message = "Nama terlalu panjang.";
        $message_type = "error";

    } elseif (strlen($no_hp) > 20) {

        $message = "Nomor HP terlalu panjang.";
        $message_type = "error";

    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $no_hp)) {

        $message = "Format nomor HP tidak valid.";
        $message_type = "error";

    } elseif (strlen($username) > 50) {

        $message = "Username maksimal 50 karakter.";
        $message_type = "error";

    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {

        $message =
            "Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.";

        $message_type = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password minimal 6 karakter.";
        $message_type = "error";

    } else {

        $branch_id_int = (int) $branch_id;

        try {

            // ====================================================
            // CEK CABANG
            // ====================================================

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM branches
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $branch_id_int
            ]);

            $branch = $stmt->fetch();

            if (!$branch) {

                $message = "Cabang tidak ditemukan.";
                $message_type = "error";

            } else {

                // =================================================
                // CEK USERNAME
                // =================================================

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM users
                     WHERE username = ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $username
                ]);

                if ($stmt->fetch()) {

                    $message = "Username sudah digunakan.";
                    $message_type = "error";

                } else {

                    // =============================================
                    // HASH PASSWORD
                    // =============================================

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($password_hash === false) {

                        $message =
                            "Gagal mengamankan password.";

                        $message_type = "error";

                    } else {

                        // =========================================
                        // INSERT USER
                        // =========================================

                        $stmt = $pdo->prepare(
                            "INSERT INTO users
                            (
                                branch_id,
                                nama,
                                no_hp,
                                username,
                                password_hash
                            )
                            VALUES (?, ?, ?, ?, ?)"
                        );

                        $stmt->execute([
                            $branch_id_int,
                            $nama,
                            $no_hp,
                            $username,
                            $password_hash
                        ]);

                        // =========================================
                        // BERHASIL
                        // =========================================

                        $message = "Akun berhasil dibuat.";
                        $message_type = "success";

                        // Kosongkan form
                        $nama = "";
                        $no_hp = "";
                        $branch_id = "";
                        $username = "";
                    }
                }
            }

        } catch (PDOException $e) {

            // ====================================================
            // LOG ERROR
            // ====================================================

            error_log(
                "register.php database error: " .
                $e->getMessage()
            );

            // ====================================================
            // HANDLE DUPLICATE USERNAME
            // ====================================================

            if (
                isset($e->errorInfo[1]) &&
                (int) $e->errorInfo[1] === 1062
            ) {

                $message = "Username sudah digunakan.";

            } else {

                $message =
                    "Terjadi kesalahan pada sistem. Silakan coba lagi.";
            }

            $message_type = "error";
        }
    }
}

// ============================================================
// AMBIL DATA CABANG
// ============================================================

$branches = [];

try {

    $stmt = $pdo->query(
        "SELECT
            id,
            nama_cabang
         FROM branches
         ORDER BY nama_cabang ASC"
    );

    $branches = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        "register.php branch query error: " .
        $e->getMessage()
    );

    $branches = [];

    $message = "Data cabang tidak dapat dimuat.";
    $message_type = "error";
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

    <title>Register Akun - Sistem Scan Unit</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
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
                    #eff6ff,
                    #f8fafc
                );
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .container {
            width: 100%;
            max-width: 460px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 32px;
            box-shadow:
                0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .brand {
            width: 52px;
            height: 52px;
            border-radius: 15px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .subtitle {
            margin: 8px 0 28px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .message {
            padding: 13px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
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

        input,
        select {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border:
                1px solid #cbd5e1;
            border-radius: 11px;
            background: #ffffff;
            color: #0f172a;
            font-family: inherit;
            font-size: 14px;
            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        select {
            cursor: pointer;
        }

        .button {
            width: 100%;
            height: 48px;
            margin-top: 6px;
            border: none;
            border-radius: 11px;
            background: #2563eb;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition:
                background 0.2s,
                transform 0.1s;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .button:active {
            transform: translateY(1px);
        }

        .empty {
            padding: 14px;
            border-radius: 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 14px;
            line-height: 1.5;
        }

        .footer {
            margin-top: 22px;
            text-align: center;
        }

        .footer a {
            color: #2563eb;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .hint {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 12px;
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
                padding: 24px 20px;
                border-radius: 17px;
            }

            h1 {
                font-size: 23px;
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
            Buat Akun
        </h1>

        <p class="subtitle">
            Daftarkan akun untuk mengakses
            Sistem Scan Unit.
        </p>

        <?php if ($message !== ""): ?>

            <div
                class="message <?= htmlspecialchars(
                    $message_type,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>

        <?php if (count($branches) > 0): ?>

            <form method="POST" autocomplete="off">

                <?= csrf_field(); ?>

                <!-- NAMA -->

                <div class="form-group">

                    <label for="nama">
                        Nama
                    </label>

                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars(
                            $nama,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Masukkan nama"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >

                </div>

                <!-- NO HP -->

                <div class="form-group">

                    <label for="no_hp">
                        No. HP
                    </label>

                    <input
                        type="tel"
                        id="no_hp"
                        name="no_hp"
                        value="<?= htmlspecialchars(
                            $no_hp,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="081234567890"
                        maxlength="20"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    >

                </div>

                <!-- CABANG -->

                <div class="form-group">

                    <label for="branch_id">
                        Cabang
                    </label>

                    <select
                        id="branch_id"
                        name="branch_id"
                        required
                    >

                        <option value="">
                            Pilih cabang
                        </option>

                        <?php foreach ($branches as $branch): ?>

                            <option
                                value="<?= (int) $branch["id"] ?>"
                                <?= (string) $branch_id ===
                                    (string) $branch["id"]
                                    ? "selected"
                                    : "" ?>
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

                <!-- USERNAME -->

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Masukkan username"
                        maxlength="50"
                        autocomplete="username"
                        required
                    >

                    <div class="hint">
                        Gunakan huruf, angka, titik,
                        garis bawah, atau tanda hubung.
                    </div>

                </div>

                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimal 6 karakter"
                        minlength="6"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="button"
                >
                    Buat Akun
                </button>

            </form>

        <?php else: ?>

            <div class="empty">
                Belum ada cabang yang tersedia.
                Silakan tambahkan cabang terlebih dahulu.
            </div>

        <?php endif; ?>

        <div class="footer">

            <a href="login.php">
                ← Kembali ke Login
            </a>

        </div>

    </div>

</div>

</body>

</html>