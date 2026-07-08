<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan | Bina Usaha Sauyunan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php $activePage = 'laporan-keuangan';
    include '../components/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Laporan Keuangan</h1>
                <p>Bina Usaha Sauyunan &mdash; Ringkasan saldo, pembelian, penjualan, dan laba rugi</p>
            </div>
        </div>

        <div class="uc-panel">
            <div class="uc-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000"
                    viewBox="0 0 256 256">
                    <path
                        d="M188,88a27.75,27.75,0,0,0-12,2.71V60a28,28,0,0,0-41.36-24.6A28,28,0,0,0,80,44v6.71A27.75,27.75,0,0,0,68,48,28,28,0,0,0,40,76v76a88,88,0,0,0,176,0V116A28,28,0,0,0,188,88Zm12,64a72,72,0,0,1-144,0V76a12,12,0,0,1,24,0v44a8,8,0,0,0,16,0V44a12,12,0,0,1,24,0v68a8,8,0,0,0,16,0V60a12,12,0,0,1,24,0v68.67A48.08,48.08,0,0,0,120,176a8,8,0,0,0,16,0,32,32,0,0,1,32-32,8,8,0,0,0,8-8V116a12,12,0,0,1,24,0Z">
                    </path>
                </svg>
            </div>
            <h2>Menu Ini Sedang Dalam Pengembangan</h2>
            <p class="uc-desc">
                Halaman Laporan Keuangan lagi kami siapin biar makin rapi dan enak dipakai.
                Sabar ya, ga lama lagi bisa dipakai kok.
            </p>
        </div>

        <footer class="report-footer">
            &mdash; Sistem Bina Usaha Sauyunan
        </footer>
    </div>

    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>