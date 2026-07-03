<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // matikan display error di production, log saja

// Deteksi apakah koneksi HTTPS atau tidak
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

// Set parameter cookie session SEBELUM session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',       // kosongkan, biar otomatis ikut host yang diakses (domain ATAU IP)
    'secure'   => $isHttps, // true hanya kalau memang HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../");
    exit;
}
