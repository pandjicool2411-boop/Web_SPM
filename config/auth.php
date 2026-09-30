<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function user_branch_id()
{
    return $_SESSION['branch_id'] ?? null;
}

function user_name()
{
    return $_SESSION['nama'] ?? '';
}

function user_branch_name()
{
    return $_SESSION['nama_cabang'] ?? '';
}