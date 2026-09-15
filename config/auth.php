<?php

/*
|--------------------------------------------------------------------------
| SESSION & AUTHENTICATION
|--------------------------------------------------------------------------
| File ini memastikan:
| - Session dimulai dengan konfigurasi keamanan
| - User wajib login
| - Session harus memiliki data penting
| - Session cookie lebih aman
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SESSION CONFIGURATION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    /*
    | Mencegah penggunaan session ID yang tidak valid.
    */
    ini_set("session.use_strict_mode", "1");

    /*
    | Session ID tidak bisa dibaca melalui JavaScript.
    | Membantu mengurangi risiko pencurian session melalui XSS.
    */
    ini_set("session.cookie_httponly", "1");

    /*
    | Saat development menggunakan HTTP localhost/XAMPP.
    |
    | NANTI ketika website sudah HTTPS:
    | ubah menjadi:
    | ini_set("session.cookie_secure", "1");
    */
    ini_set("session.cookie_secure", "0");

    /*
    | Membatasi pengiriman cookie pada request lintas situs.
    */
    ini_set("session.cookie_samesite", "Lax");

    /*
    | Session hanya menggunakan cookie.
    | Session ID tidak ditempelkan ke URL.
    */
    ini_set("session.use_only_cookies", "1");

    session_start();
}

/*
|--------------------------------------------------------------------------
| CEK USER SUDAH LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: ../public/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CEK SESSION LENGKAP
|--------------------------------------------------------------------------
|
| Session minimal harus memiliki:
| - user_id
| - branch_id
| - nama
| - username
|
*/

$required_session_data = [
    "user_id",
    "branch_id",
    "nama",
    "username"
];

foreach ($required_session_data as $session_key) {

    if (
        !isset($_SESSION[$session_key]) ||
        $_SESSION[$session_key] === ""
    ) {

        /*
        | Hapus seluruh session jika session
        | tidak lengkap / tidak valid.
        */
        $_SESSION = [];

        /*
        | Hapus session cookie.
        */
        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                [
                    "expires" => time() - 42000,
                    "path" => $params["path"],
                    "domain" => $params["domain"],
                    "secure" => $params["secure"],
                    "httponly" => $params["httponly"],
                    "samesite" => "Lax"
                ]
            );
        }

        /*
        | Hancurkan session.
        */
        session_destroy();

        /*
        | Kembali ke login.
        */
        header("Location: ../public/login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| NORMALISASI DATA SESSION
|--------------------------------------------------------------------------
|
| Pastikan ID user dan branch berbentuk integer.
|
*/

$_SESSION["user_id"] = (int) $_SESSION["user_id"];
$_SESSION["branch_id"] = (int) $_SESSION["branch_id"];