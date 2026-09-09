<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';
require_once __DIR__ . '/ip_helper.php';

// Cek apakah form benar-benar di-submit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Username dan password harus diisi';
        header("Location: ../");
        exit;
    }

    $query = "SELECT * FROM akun WHERE username=?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        // Cek password
        if ($password === $user['password']) {
            $_SESSION['id']       = $user['id'];
            $_SESSION['username'] = $user['username'];

            // PERBAIKAN: Ambil role dari database, JANGAN di-hardcode!
            // Pastikan di tabel database Anda ada kolom bernama 'role' (isi: admin/bendahara/purchase)
            $_SESSION['role']     = $user['role'];
            $_SESSION['last_activity_time'] = time();

            // Catat aktivitas login pengguna
            $ipAddress = function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '');
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $updateTrack = "UPDATE akun SET last_login = NOW(), last_activity = NOW(), is_online = 1, ip_address = ?, user_agent = ? WHERE id = ?";
            if ($stmtTrack = mysqli_prepare($koneksi, $updateTrack)) {
                mysqli_stmt_bind_param($stmtTrack, "ssi", $ipAddress, $userAgent, $user['id']);
                mysqli_stmt_execute($stmtTrack);
                mysqli_stmt_close($stmtTrack);
            }

            $_SESSION['success'] = 'Login berhasil';
            // Redirect based on role
            if ($_SESSION['role'] === 'admin') {
                header("Location: ../selection-page/index.php");
            } elseif (in_array($_SESSION['role'], ['bendahara', 'ketua'])) {
                header("Location: ../transaksi-pembelian-food/index.php");
            } elseif (in_array($_SESSION['role'], ['purchase', 'purchase_stok'])) {
                header("Location: ../dompet-harian/index.php");
            } else {
                // Fallback to selection page for unknown roles
                header("Location: ../selection-page/index.php");
            }
            exit;
        } else {
            $_SESSION['error'] = 'Password salah';
            header("Location: ../");
            exit;
        }
    } else {
        $_SESSION['error'] = 'Username tidak ditemukan';
        header("Location: ../");
        exit;
    }
} else {
    // Jika ada yang coba akses file ini langsung tanpa submit form
    header("Location: ../");
    exit;
}
