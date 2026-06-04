<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$username = $_POST['username'];
$password = $_POST['password']; 

$query = "SELECT * FROM akun WHERE username=?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user) {
    // Cek password

    if ($password === $user['password']) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'admin';

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


?>
