<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<head>
    <link rel="stylesheet" href="../components/style.css">
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
            <ul class="nav-list">

                <!-- GROUP: Transaksi (dropdown desktop, flat mobile) -->
                <li class="nav-item <?= in_array($activePage, ['transaksi-pembelian', 'transaksi-penjualan']) ? 'active' : '' ?>" id="dd-transaksi">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-transaksi">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                        Transaksi
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li class="dropdown-group-label">Transaksi</li>
                        <li>
                            <a href="../transaksi-pembelian-food/index.php"
                               class="dropdown-item <?= $activePage == 'transaksi-pembelian' ? 'active' : '' ?>"
                               role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                Pembelian
                            </a>
                        </li>
                        <li>
                            <a href="../transaksi-penjualan-food/index.php"
                               class="dropdown-item <?= $activePage == 'transaksi-penjualan' ? 'active' : '' ?>"
                               role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                Penjualan
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- GROUP: Data Master (dropdown desktop, flat mobile) -->
                <li class="nav-item <?= in_array($activePage, ['data-pelanggan', 'data-supplier', 'stok-barang']) ? 'active' : '' ?>" id="dd-datamaster">
                    <a href="#" class="nav-link dd-trigger" aria-haspopup="true" aria-expanded="false" data-target="dd-datamaster">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Data Master
                        <span class="dd-arrow">▾</span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li class="dropdown-group-label">Data Master</li>
                        <li>
                            <a href="../data-pelanggan/index.php"
                               class="dropdown-item <?= $activePage == 'daftar-pelanggan' ? 'active' : '' ?>"
                               role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                Data Pelanggan
                            </a>
                        </li>
                        <li>
                            <a href="../data-supplier/index.php"
                               class="dropdown-item <?= $activePage == 'daftar-supplier' ? 'active' : '' ?>"
                               role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                Data Supplier
                            </a>
                        </li>
                        <li>
                            <a href="../stok-barang-food/index.php"
                               class="dropdown-item <?= $activePage == 'stok-barang' ? 'active' : '' ?>"
                               role="menuitem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                Stok Barang
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Daftar Harga Barang -->
                <li class="nav-item <?= $activePage == 'daftar-harga-barang' ? 'active' : '' ?>">
                    <a href="../daftar-harga-barang-food/index.php" class="nav-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        Daftar Harga Barang
                    </a>
                </li>

                <!-- Laporan Keuangan -->
                <li class="nav-item <?= $activePage == 'laporan-keuangan' ? 'active' : '' ?>">
                    <a href="../laporan-keuangan-food/index.php" class="nav-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Laporan Keuangan
                    </a>
                </li>

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
(function () {
    const hamburger = document.getElementById('hamburger-btn');
    const navMenu   = document.getElementById('nav-menu');
    const overlay   = document.getElementById('nav-overlay');
    const header    = document.getElementById('main-header');

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
    }, { passive: true });

    /* --- Dropdown (desktop & mobile) --- */
    const triggers = document.querySelectorAll('.dd-trigger');
    const isMobile = () => window.innerWidth <= 788;

    triggers.forEach(trigger => {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const parentLi = document.getElementById(this.dataset.target);
            const isOpen   = parentLi.classList.contains('dropdown-open');

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
            }
        });
    });

    /* Close dropdown when clicking outside (desktop) */
    document.addEventListener('click', function (e) {
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
})();
</script>