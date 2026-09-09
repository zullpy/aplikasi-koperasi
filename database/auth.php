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

// Track waktu aktivitas terakhir akun (dithrottle tiap 20 detik agar performa tetap kencang)
$currentTime = time();
if (!isset($_SESSION['last_activity_update']) || ($currentTime - $_SESSION['last_activity_update']) >= 20) {
    $_SESSION['last_activity_update'] = $currentTime;
    if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
        require_once __DIR__ . '/koneksi.php';
    }
    require_once __DIR__ . '/ip_helper.php';
    if (isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
        initAppTimezone($koneksi);
        $userId = (int)$_SESSION['id'];
        $ip = function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmtAct = @$koneksi->prepare("UPDATE akun SET last_activity = NOW(), is_online = 1, ip_address = ?, user_agent = ? WHERE id = ?");
        if ($stmtAct) {
            $stmtAct->bind_param("ssi", $ip, $ua, $userId);
            $stmtAct->execute();
            $stmtAct->close();
        }
    }
}