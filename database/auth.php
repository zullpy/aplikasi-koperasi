<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CEK LOGIN: Jika belum login, lempar ke halaman login
if (!isset($_SESSION['role'])) {
    header("Location: ../index.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// 2. FUNGSI RBAC: Cek apakah role user ada di dalam daftar role yang diizinkan
function require_role($allowed_roles)
{
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Jika tidak punya akses, lempar kembali ke selection-page dengan pesan error
        header("Location: ../transaksi-pembelian-food/index.php?error=akses_ditolak");
        exit;
    }
}
