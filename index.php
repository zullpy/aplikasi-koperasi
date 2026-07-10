<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css"
    />
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css"
    />
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-card" action="">
    <!-- KIRI -->
    <div class="left">
        <h1>Selamat datang di Teras<br>
        <span class="kang">KANG dan SISTA !</span></h1>
        <p class="teknologi">Teknologi report account system</p>
        <h4>Kelola Administrasi Neraca Guna & <br>Sistem Informasi Stok dan Transaksi</h4>
        <span class="panjang">Administrasi pencatatan dan akuntabilitas setiap transaksi. Mulai dari pembelian, penjualan, inventori stok barang, dan laporan keuangan </span>
        <img src="assets/logo.png" alt="logo" class="illustration">
        <p class="slogan"> 
            <span class="text-yellow">
                Bersama Membangun Usaha, 
            </span>
            <span class="text-blue">
                Bersatu Meraih Sejahtera
            </span>
        </p>
    </div>

    <!-- KANAN -->
    <div class="right">
        <img src="assets/logo.png" alt="Logo" class="mobile-logo">
        <h2>Login</h2>
        <form action="database/process-login.php" method="post">
            <label>Username</label>
            <input type="text" name="username" id="input-identifier" required>
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="input-password" required>
                <i class="ph ph-eye" id="togglePassword"></i>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="alamat">
            <span>jl. cicarulang</span>
            <span>@KOPERASI BINA USAHA SAUYUNAN</span>
        </div>
    </div>
    </div>

<script src="script.js"></script>
</body>
</html>

<?php
session_start();

if(isset($_SESSION['error'])) {
?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Gagal',
    text: '<?= $_SESSION['error']; ?>',
    customClass:{
        popup: 'myswal'
    }
});
</script>
<?php
unset($_SESSION['error']);
}
?>