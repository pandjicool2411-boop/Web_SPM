<?php

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Generate CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Ambil Token
|--------------------------------------------------------------------------
*/

function csrf_token()
{
    return $_SESSION["csrf_token"];
}

/*
|--------------------------------------------------------------------------
| Hidden Input
|--------------------------------------------------------------------------
*/

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") .
        '">';
}

/*
|--------------------------------------------------------------------------
| Verifikasi Token
|--------------------------------------------------------------------------
*/

function verify_csrf()
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return;
    }

    $token = $_POST["csrf_token"] ?? "";

    if (
        empty($token) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        http_response_code(403);
        die("403 Forbidden - CSRF token tidak valid.");
    }
}