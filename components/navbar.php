<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base_url = '';
$userRole = $_SESSION['role'] ?? null;
$isPurchase = ($userRole === 'purchase' || $userRole === 'purchase_stok');
$isBendaharaOrKetua = in_array($userRole, ['bendahara', 'ketua']);
?>

<head>
    <link rel="stylesheet" href="../components/style.css?v=1.1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<!-- Overlay for mobile drawer -->
<div class="nav-overlay" id="nav-overlay"></div>

<header class="header" id="main-header">
    <div class="header-container">

        <!-- Brand -->
        <a href="<?= $base_url ?>/index.php" class="brand">
            <div class="logo-wrapper">
                <img src="../assets/logo.png" alt="Logo" class="logo">
            </div>
            <div class="brand-name">
                <span class="brand-title">Koperasi</span>
                <span class="brand-subtitle">Bina Usaha Sauyunan</span>
            </div>
        </a>

        <!-- Nav -->
        <nav class="nav-menu" id="nav-menu" aria-label="Menu utama">
            <!-- Fitur Pencarian Menu/Halaman (Khusus Mobile) -->
            <div class="nav-search-container">
                <div class="nav-search-input-wrapper">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="search-icon">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="nav-search-input" placeholder="Cari halaman...." autocomplete="off">
                    <button type="button" id="nav-search-clear" style="display: none;">&times;</button>
                </div>
            </div>

            <ul class="nav-list">

                <?php if (!$isPurchase): ?>
                <!-- GROUP: Transaksi (dropdown desktop, flat mobile) -->
                <li class="nav-item <?= in_array($activePage, ['transaksi-pembelian', 'transaksi-penjualan', 'penjualan-sppg-foodcost', 'penjualan-sppg-addcost', 'transaksi-penjualan-umum']) ? 'active' : '' ?>" id="dd-transaksi">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-transaksi">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="9" cy="20" r="1" />
                            <circle cx="18" cy="20" r="1" />
                            <path d="M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H7" />
                        </svg>
                        Transaksi
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">

                        <!-- Pembelian -->
                        <li class="dropdown-group-label">Pembelian</li>
                        <li>
                            <a href="../transaksi-pembelian-food/index.php"
                                class="dropdown-item <?= $activePage == 'transaksi-pembelian' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1" />
                                    <circle cx="20" cy="21" r="1" />
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                                </svg>
                                Pembelian
                            </a>
                        </li>

                        <!-- Penjualan -->
                        <li class="dropdown-group-label" style="margin-top:6px;">Penjualan</li>
                        <!-- <li>
                            <a href="../transaksi-penjualan-food/index.php"
                                class="dropdown-item <?= $activePage == 'transaksi-penjualan-umum' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                                Penjualan Umum
                            </a>
                        </li> -->

                        <!-- SPPG nested dropdown -->
                        <li class="dropdown-item-nested <?= in_array($activePage, ['penjualan-sppg-foodcost', 'penjualan-sppg-addcost']) ? 'active' : '' ?>" id="dd-sppg">
                            <a href="#" class="dropdown-item dd-sppg-trigger">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="1" y="3" width="15" height="13" rx="1" />
                                    <path d="M16 8h4l3 5v3h-7V8z" />
                                    <circle cx="5.5" cy="18.5" r="2.5" />
                                    <circle cx="18.5" cy="18.5" r="2.5" />
                                </svg>
                                Penjualan SPPG
                                <span class="dd-arrow-nested">▸</span>
                            </a>
                            <ul class="dropdown-menu-nested" role="menu">
                                <li>
                                    <a href="../penjualan-sppg-foodcost/index.php"
                                        class="dropdown-item <?= $activePage == 'penjualan-sppg-foodcost' ? 'active' : '' ?>"
                                        role="menuitem">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M3 2h18v20H3z" />
                                            <path d="M7 6h10M7 10h10M7 14h6" />
                                        </svg>
                                        Food Cost
                                    </a>
                                </li>
                                <li>
                                    <a href="../penjualan-sppg-addcost/index.php"
                                        class="dropdown-item <?= $activePage == 'penjualan-sppg-addcost' ? 'active' : '' ?>"
                                        role="menuitem">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="16" />
                                            <line x1="8" y1="12" x2="16" y2="12" />
                                        </svg>
                                        Add Cost
                                    </a>
                                </li>
                            </ul>
                        </li>

                    </ul>
                </li>
                <?php endif; ?>

                <?php if (!$isPurchase): ?>
                    <?php if ($isBendaharaOrKetua): ?>
                        <!-- Direct link to Stok Barang for Bendahara and Ketua -->
                        <li class="nav-item <?= $activePage == 'stok-barang' ? 'active' : '' ?>">
                            <a href="../stok-barang-food/index.php" class="nav-link">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                </svg>
                                Stok Barang
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- GROUP: Data Master (dropdown desktop, flat mobile) -->
                        <li class="nav-item <?= in_array($activePage, ['data-pelanggan', 'data-supplier', 'stok-barang']) ? 'active' : '' ?>" id="dd-datamaster">
                            <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-datamaster">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <ellipse cx="12" cy="5" rx="9" ry="3" />
                                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                                </svg>
                                Data Master
                                <span class="dd-arrow">▾</span>
                            </a>
                            <ul class="dropdown-menu" role="menu">
                                <li class="dropdown-group-label">Data Master</li>
                                <li>
                                    <a href="../data-pelanggan/index.php"
                                        class="dropdown-item <?= $activePage == 'daftar-pelanggan' ? 'active' : '' ?>"
                                        role="menuitem">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                        </svg>
                                        Data Pelanggan
                                    </a>
                                </li>
                                <li>
                                    <a href="../data-supplier/index.php"
                                        class="dropdown-item <?= $activePage == 'daftar-supplier' ? 'active' : '' ?>"
                                        role="menuitem">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="1" y="3" width="15" height="13" rx="1" />
                                            <path d="M16 8h4l3 5v3h-7V8z" />
                                            <circle cx="5.5" cy="18.5" r="2.5" />
                                            <circle cx="18.5" cy="18.5" r="2.5" />
                                        </svg>
                                        Data Supplier
                                    </a>
                                </li>
                                <li>
                                    <a href="../stok-barang-food/index.php"
                                        class="dropdown-item <?= $activePage == 'stok-barang' ? 'active' : '' ?>"
                                        role="menuitem">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        </svg>
                                        Stok Barang
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$isPurchase): ?>
                <!-- GROUP: Daftar (dropdown) -->
                <li class="nav-item <?= in_array($activePage, ['daftar-harga-barang', 'daftar-aset-koperasi']) ? 'active' : '' ?>" id="dd-daftar">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-daftar">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="8" y1="6" x2="21" y2="6" />
                            <line x1="8" y1="12" x2="21" y2="12" />
                            <line x1="8" y1="18" x2="21" y2="18" />
                            <line x1="3" y1="6" x2="3.01" y2="6" />
                            <line x1="3" y1="12" x2="3.01" y2="12" />
                            <line x1="3" y1="18" x2="3.01" y2="18" />
                        </svg>
                        Daftar
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li class="dropdown-group-label">Daftar</li>
                        <li>
                            <a href="../daftar-harga-barang-food/index.php"
                                class="dropdown-item <?= $activePage == 'daftar-harga-barang' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                                    <line x1="7" y1="7" x2="7.01" y2="7" />
                                </svg>
                                Daftar Harga Barang
                            </a>
                        </li>
                        <li>
                            <a href="../daftar-aset/index.php"
                                class="dropdown-item <?= $activePage == 'daftar-aset-koperasi' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="7" width="20" height="14" rx="2" />
                                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                    <line x1="12" y1="12" x2="12" y2="16" />
                                    <line x1="10" y1="14" x2="14" y2="14" />
                                </svg>
                                Daftar Aset Koperasi
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- GROUP: Pengajuan -->
                <?php if ($isPurchase): ?>
                <li class="nav-item <?= $activePage == 'dompet-belanja-harian' ? 'active' : '' ?>">
                    <a href="../dompet-harian/index.php" class="nav-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7" />
                            <path d="M16 2v4M8 2v4" />
                            <circle cx="18" cy="18" r="4" />
                            <path d="M18 16v2l1 1" />
                        </svg>
                        Dompet Belanja Harian SPPG
                    </a>
                </li>
                <?php if ($userRole === 'purchase_stok'): ?>
                <li class="nav-item <?= $activePage == 'stok-barang' ? 'active' : '' ?>">
                    <a href="../stok-barang-food/index.php" class="nav-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true" style="margin-right: 6px; display: inline-block; vertical-align: middle;">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                        Stok Barang
                    </a>
                </li>
                <li class="nav-item <?= $activePage == 'daftar-harga-barang' ? 'active' : '' ?>">
                    <a href="../daftar-harga-barang-food/index.php" class="nav-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true" style="margin-right: 6px; display: inline-block; vertical-align: middle;">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                            <line x1="7" y1="7" x2="7.01" y2="7" />
                        </svg>
                        Daftar Harga Barang
                    </a>
                </li>
                <?php endif; ?>
                <?php else: ?>
                <li class="nav-item <?= in_array($activePage, ['dompet-belanja-harian', 'pengajuan-koperasi']) ? 'active' : '' ?>" id="dd-pengajuan">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-pengajuan">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" y1="18" x2="12" y2="12" />
                            <line x1="9" y1="15" x2="15" y2="15" />
                        </svg>
                        Pengajuan
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li class="dropdown-group-label">Pengajuan</li>
                        <li>
                            <a href="../dompet-harian/index.php"
                                class="dropdown-item <?= $activePage == 'dompet-belanja-harian' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7" />
                                    <path d="M16 2v4M8 2v4" />
                                    <circle cx="18" cy="18" r="4" />
                                    <path d="M18 16v2l1 1" />
                                </svg>
                                Dompet Belanja Harian SPPG
                            </a>
                        </li>
                        <li>
                            <a href="../pengajuan-koperasi/index.php"
                                class="dropdown-item <?= $activePage == 'pengajuan-koperasi' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="3" width="20" height="14" rx="2" />
                                    <path d="M8 21h8M12 17v4" />
                                    <path d="M9 8h6M9 11h4" />
                                </svg>
                                Pengajuan Anggaran Koperasi
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>


                <?php if (!$isPurchase): ?>
                <!-- GROUP: Laporan (dropdown) -->
                <li class="nav-item <?= in_array($activePage, ['laporan-sppg', 'laporan-koperasi']) ? 'active' : '' ?>" id="dd-laporan">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-laporan">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10 9 9 9 8 9" />
                        </svg>
                        Laporan Belanja
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li class="dropdown-group-label">Laporan</li>
                        <li>
                            <a href="../laporan-sppg/index.php"
                                class="dropdown-item <?= $activePage == 'laporan-sppg' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="1" y="3" width="15" height="13" rx="1" />
                                    <path d="M16 8h4l3 5v3h-7V8z" />
                                    <circle cx="5.5" cy="18.5" r="2.5" />
                                    <circle cx="18.5" cy="18.5" r="2.5" />
                                </svg>
                                Laporan Belanja SPPG
                            </a>
                        </li>
                        <li>
                            <a href="../laporan-koperasi/index.php"
                                class="dropdown-item <?= $activePage == 'laporan-koperasi' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                                Laporan Belanja Koperasi
                            </a>
                        </li>
                        
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (!$isPurchase): ?>
                <!-- GROUP: Laporan Keuangan (dropdown desktop, flat mobile) -->
                <li class="nav-item <?= in_array($activePage, ['kas-koperasi', 'rekap-kpm','omset-sppg', 'rekap-hutang-piutang', 'laporan-barang']) ? 'active' : '' ?>" id="dd-laporan-keuangan">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-laporan-keuangan">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="18" y1="20" x2="18" y2="10" />
                            <line x1="12" y1="20" x2="12" y2="4" />
                            <line x1="6" y1="20" x2="6" y2="14" />
                        </svg>
                        Laporan Keuangan
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-right" role="menu">
                        <li class="dropdown-group-label">Laporan Keuangan</li>
                        <li>
                            <a href="../kas-koperasi/index.php"
                                class="dropdown-item <?= $activePage == 'kas-koperasi' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="6" width="20" height="12" rx="2" />
                                    <circle cx="12" cy="12" r="2" />
                                    <path d="M6 6V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                                </svg>
                                Kas Koperasi
                            </a>
                        </li>
                        <li>
                            <a href="../omset-sppg/index.php"
                                class="dropdown-item <?= $activePage == 'omset-sppg' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 256 256"><path d="M232,208a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V48a8,8,0,0,1,16,0V156.69l50.34-50.35a8,8,0,0,1,11.32,0L128,132.69,180.69,80H160a8,8,0,0,1,0-16h40a8,8,0,0,1,8,8v40a8,8,0,0,1-16,0V91.31l-58.34,58.35a8,8,0,0,1-11.32,0L96,123.31l-56,56V200H224A8,8,0,0,1,232,208Z"></path></svg>
                                Data KPM
                            </a>
                        </li>
                        <li>
                            <a href="../rekap-hutang-piutang/index.php"
                                class="dropdown-item <?= $activePage == 'rekap-hutang-piutang' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                Rekap Hutang Piutang
                            </a>
                        </li>
                        <li>
                            <a href="../laporan-barang/index.php"
                                class="dropdown-item <?= $activePage == 'laporan-barang' ? 'active' : '' ?>"
                                role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21 16V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2z" />
                                    <polyline points="3 10 21 10" />
                                </svg>
                                Laporan Barang
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Hamburger -->
        <button class="hamburger" id="hamburger-btn" aria-label="Toggle Menu" aria-expanded="false">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

    </div>
</header>

<script>
    (function() {
        const hamburger = document.getElementById('hamburger-btn');
        const navMenu = document.getElementById('nav-menu');
        const overlay = document.getElementById('nav-overlay');
        const header = document.getElementById('main-header');

        /* --- Hamburger / drawer --- */
        function openMenu() {
            navMenu.classList.add('active');
            overlay.classList.add('active');
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            navMenu.classList.remove('active');
            overlay.classList.remove('active');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        hamburger.addEventListener('click', () => {
            navMenu.classList.contains('active') ? closeMenu() : openMenu();
        });

        overlay.addEventListener('click', closeMenu);

        /* --- Scroll header shadow --- */
        window.addEventListener('scroll', () => {
            header.classList.toggle('scroll-active', window.scrollY > 10);
        }, {
            passive: true
        });

        /* --- Dropdown (desktop & mobile) --- */
        const triggers = document.querySelectorAll('.dd-trigger');
        const isMobile = () => window.innerWidth <= 788;

        /* Ingat state nested SPPG (pinned = user sudah klik buka) */
        let sppgPinned = false;

        triggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const parentLi = document.getElementById(this.dataset.target);
                const isOpen = parentLi.classList.contains('dropdown-open');

                /* Close all dropdowns first */
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('dropdown-open');
                    const a = item.querySelector('.dd-trigger');
                    if (a) a.setAttribute('aria-expanded', 'false');

                    /* On mobile also hide dropdown-menu */
                    if (isMobile()) {
                        const ddMenu = item.querySelector('.dropdown-menu');
                        if (ddMenu) ddMenu.style.display = '';
                    }
                });

                if (!isOpen) {
                    parentLi.classList.add('dropdown-open');
                    this.setAttribute('aria-expanded', 'true');

                    /* On mobile: show dropdown-menu inline */
                    if (isMobile()) {
                        const ddMenu = parentLi.querySelector('.dropdown-menu');
                        if (ddMenu) ddMenu.style.display = 'block';
                    }

                    /* Jika dropdown Transaksi dibuka, restore state nested SPPG */
                    if (parentLi.id === 'dd-transaksi') {
                        const sppgItem = document.getElementById('dd-sppg');
                        if (sppgItem && (sppgPinned || sppgItem.classList.contains('active'))) {
                            sppgItem.classList.add('nested-open');
                        }
                    }
                }
            });
        });

        /* Close dropdown when clicking outside (desktop) */
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item')) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('dropdown-open');
                    const a = item.querySelector('.dd-trigger');
                    if (a) a.setAttribute('aria-expanded', 'false');
                });
            }
        });

        /* Close drawer on resize to desktop */
        window.addEventListener('resize', () => {
            if (window.innerWidth > 788) closeMenu();
        });

        /* --- Nested dropdown SPPG --- */
        const sppgTriggers = document.querySelectorAll('.dd-sppg-trigger');
        sppgTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parentLi = this.closest('.dropdown-item-nested');
                const isOpen = parentLi.classList.contains('nested-open');
                parentLi.classList.toggle('nested-open', !isOpen);
                /* Simpan state — ingat apakah user sudah klik buka */
                sppgPinned = !isOpen;
            });
        });

        /* Desktop: open nested on hover — DIHAPUS, pakai click-to-toggle saja */

        /* Simpan state sppgPinned jika halaman aktif di sub-menu SPPG */
        const ddSppg = document.getElementById('dd-sppg');
        if (ddSppg && ddSppg.classList.contains('active')) {
            sppgPinned = true;
        }

        /* --- Fitur Pencarian Halaman/Menu (Mobile Only) --- */
        const searchInput = document.getElementById('nav-search-input');
        const searchClear = document.getElementById('nav-search-clear');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length > 0) {
                    searchClear.style.display = 'block';
                } else {
                    searchClear.style.display = 'none';
                }
                
                filterMenus(query);
            });
            
            searchClear.addEventListener('click', function() {
                searchInput.value = '';
                this.style.display = 'none';
                filterMenus('');
                searchInput.focus();
            });
        }
        
        function filterMenus(query) {
            const listItems = document.querySelectorAll('.nav-list > li');
            
            listItems.forEach(item => {
                const link = item.querySelector('.nav-link');
                const isDropdownTrigger = link && link.classList.contains('dd-trigger');
                
                if (!isDropdownTrigger) {
                    if (link) {
                        const text = link.textContent.toLowerCase();
                        if (text.includes(query)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                    return;
                }
                
                const ddMenu = item.querySelector('.dropdown-menu');
                if (!ddMenu) return;
                
                const groupLabels = ddMenu.querySelectorAll('.dropdown-group-label');
                const subItems = ddMenu.querySelectorAll('li:not(.dropdown-item-nested)');
                const nestedSppg = ddMenu.querySelector('.dropdown-item-nested');
                
                let matchesInDropdown = 0;
                
                // Cek item sub-menu normal
                subItems.forEach(sub => {
                    const subLink = sub.querySelector('a');
                    if (subLink) {
                        const text = subLink.textContent.toLowerCase();
                        if (text.includes(query)) {
                            sub.style.display = '';
                            matchesInDropdown++;
                        } else {
                            sub.style.display = 'none';
                        }
                    }
                });
                
                // Cek item sub-menu nested SPPG
                if (nestedSppg) {
                    const nestedTrigger = nestedSppg.querySelector('.dd-sppg-trigger');
                    const nestedListItems = nestedSppg.querySelectorAll('.dropdown-menu-nested li');
                    let matchesInNested = 0;
                    
                    nestedListItems.forEach(nestedLi => {
                        const nestedLink = nestedLi.querySelector('a');
                        if (nestedLink) {
                            const text = nestedLink.textContent.toLowerCase();
                            if (text.includes(query)) {
                                nestedLi.style.display = '';
                                matchesInNested++;
                                matchesInDropdown++;
                            } else {
                                nestedLi.style.display = 'none';
                            }
                        }
                    });
                    
                    if (matchesInNested > 0 || (nestedTrigger && nestedTrigger.textContent.toLowerCase().includes(query))) {
                        nestedSppg.style.display = '';
                        nestedSppg.classList.add('nested-open');
                    } else {
                        nestedSppg.style.display = 'none';
                        nestedSppg.classList.remove('nested-open');
                    }
                }
                
                const triggerText = link ? link.textContent.toLowerCase() : '';
                if (matchesInDropdown > 0 || triggerText.includes(query)) {
                    item.style.display = '';
                    item.classList.add('dropdown-open');
                    ddMenu.style.display = 'block';
                    
                    groupLabels.forEach(label => {
                        label.style.display = '';
                    });
                } else {
                    item.style.display = 'none';
                    item.classList.remove('dropdown-open');
                    ddMenu.style.display = '';
                }
            });
            
            // Reset state if query is empty
            if (query === '') {
                document.querySelectorAll('.nav-list > li').forEach(item => {
                    item.style.display = '';
                    item.classList.remove('dropdown-open');
                    const ddMenu = item.querySelector('.dropdown-menu');
                    if (ddMenu) ddMenu.style.display = '';
                });
                document.querySelectorAll('.dropdown-menu li').forEach(sub => {
                    sub.style.display = '';
                });
                document.querySelectorAll('.dropdown-group-label').forEach(label => {
                    label.style.display = '';
                });
                const sppg = document.getElementById('dd-sppg');
                if (sppg) {
                    sppg.style.display = '';
                    sppg.classList.remove('nested-open');
                    if (sppg.classList.contains('active') || sppgPinned) {
                        sppg.classList.add('nested-open');
                    }
                }
            }
        }

    })();
</script>