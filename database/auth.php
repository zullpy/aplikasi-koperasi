<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // matikan display error di production, log saja

// Deteksi apakah koneksi HTTPS atau tidak
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

// Hanya set cookie params & start session KALAU sesi belum aktif.
// Ini mencegah warning/kegagalan set params saat ada file lain yang
// sudah keburu manggil session_start() sebelum auth.php di-include.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',       // kosongkan, biar otomatis ikut host yang diakses (domain ATAU IP)
        'secure'   => $isHttps, // true hanya kalau memang HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
} elseif (session_status() === PHP_SESSION_ACTIVE) {
    // Sesi sudah aktif duluan oleh file lain (cookie params default PHP dipakai).
    // Log supaya kelihatan file mana yang harus diperbaiki agar tidak
    // manggil session_start() sendiri sebelum auth.php.
    error_log('auth.php: session sudah aktif sebelum auth.php dijalankan. Cek file pemanggil di ' . ($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
}

if (!isset($_SESSION['id'])) {
    header("Location: ../");
    exit;
}