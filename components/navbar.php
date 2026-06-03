<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<head>
    <link rel="stylesheet" href="../style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<header class="header">
        <div class="header-container">
            <a href="../index.php" class="brand">
                <div class="logo-wrapper">
                    <img src="<?= $base_url ?>/assets/logo.jpeg" alt="Logo" class="logo">
                </div>
                <div class="brand-name">
                    <span class="brand-title">Koperasi</span>
                    <span class="brand-subtitle">Bina Usaha Sauyunan</span>
                </div>
            </a>

            <nav class="nav-menu" id="nav-menu">
                <ul class="nav-list">
                    <li class="nav-item <?= $activePage == 'dashboard' ? 'active' : '' ?>">
                        <a href="../index.php" class="nav-link">Profil Koperasi</a>
                    </li>
                    <li class="nav-item <?= $activePage == 'transaksi-pembelian' ? 'active' : '' ?>">
                        <a href="../transaksi-pembelian/index.php" class="nav-link">Transaksi Pembelian</a>
                    </li>
                    <li class="nav-item <?= $activePage == 'transaksi-penjualan' ? 'active' : '' ?>">
                        <a href="../transaksi-penjualan/index.php" class="nav-link">Transaksi Penjualan</a>
                    </li>
                    <li class="nav-item <?= $activePage == 'daftar-harga-barang' ? 'active' : '' ?>">
                        <a href="../daftar-harga-barang/index.php" class="nav-link">Daftar Harga Barang</a>
                    </li>
                    <li class="nav-item <?= $activePage == 'laporan-keuangan' ? 'active' : '' ?>">
                        <a href="../laporan-keuangan/index.php" class="nav-link">Laporan Keuangan</a>
                    </li>
                </ul>
            </nav>

            <button class="hamburger" id="hamburger-btn" aria-label="Toggle Menu" aria-expanded="false">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </header>