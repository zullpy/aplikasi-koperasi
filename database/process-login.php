<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

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

            $_SESSION['success'] = 'Login berhasil';
            header("Location: ../selection-page/index.php");
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
