<?php
require_once '../database/auth.php';

// Pastikan hanya role admin yang bisa mengakses halaman ini
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../selection-page/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Akun & Status Aktif | Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1e3a5f">
</head>
<body>

    <?php 
    $activePage = 'data-akun'; 
    include '../components/navbar.php'; 
    ?>

    <!-- PAGE HEADER -->
    <header class="page-header">
        <div class="page-header-left">
            <div class="header-icon">
                <i class="ti ti-user-shield"></i>
            </div>
            <div>
                <div class="header-title">
                    Data Akun & Aktivitas
                    <span class="admin-tag">Khusus Admin</span>
                </div>
                <div class="header-subtitle">
                    Pantau akun yang sedang online dan riwayat waktu terakhir aktif
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-refresh" id="btn-refresh" onclick="loadAccountData(true)">
                <i class="ti ti-refresh" id="refresh-icon"></i>
                <span>Segarkan</span>
            </button>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="ti ti-users"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Akun Terdaftar</span>
                    <span class="stat-value" id="stat-total">-</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="ti ti-wifi"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Sedang Aktif (Online)</span>
                    <span class="stat-value" id="stat-online" style="color: #10b981;">-</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gray">
                    <i class="ti ti-clock"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Tidak Aktif (Offline)</span>
                    <span class="stat-value" id="stat-offline" style="color: #64748b;">-</span>
                </div>
            </div>
        </div>

        <!-- TOOLBAR & FILTERS -->
        <div class="toolbar">
            <div class="search-box">
                <i class="ti ti-search"></i>
                <input type="text" id="search-input" placeholder="Cari akun atau role..." autocomplete="off">
            </div>

            <div class="filter-group">
                <button class="filter-btn active" data-filter="all" onclick="setFilter('all', this)">Semua</button>
                <button class="filter-btn" data-filter="online" onclick="setFilter('online', this)">Sedang Aktif</button>
                <button class="filter-btn" data-filter="offline" onclick="setFilter('offline', this)">Offline</button>
            </div>

            <div class="live-badge">
                <span class="pulse-dot"></span>
                <span>Live Update</span>
            </div>
        </div>

        <!-- ACCOUNTS GRID -->
        <div class="accounts-grid" id="accounts-grid">
            <div class="empty-state">
                <i class="ti ti-loader spinning"></i>
                <p>Memuat data status akun...</p>
            </div>
        </div>

    </main>

    <script>
        let accountsData = [];
        let revealedPasswords = {};
        let currentFilter = 'all';
        let searchQuery = '';
        let isFetching = false;

        const roleLabels = {
            'admin': 'Admin',
            'bendahara': 'Bendahara',
            'ketua': 'Ketua',
            'purchase': 'Purchase',
            'purchase_stok': 'Purchase Stok'
        };

        function getRoleClass(role) {
            return 'role-' + (role || 'default').replace(/\s+/g, '_').toLowerCase();
        }

        async function loadAccountData(isManual = false) {
            if (isFetching) return;
            isFetching = true;

            const refreshIcon = document.getElementById('refresh-icon');
            if (refreshIcon) refreshIcon.classList.add('spinning');

            try {
                const response = await fetch('api-get-akun.php?t=' + Date.now());
                if (!response.ok) {
                    throw new Error('Gagal mengambil data dari server');
                }
                const result = await response.json();

                if (result.status === 'success') {
                    accountsData = result.data || [];

                    // Update ringkasan statistik
                    document.getElementById('stat-total').textContent = result.summary.total;
                    document.getElementById('stat-online').textContent = result.summary.online;
                    document.getElementById('stat-offline').textContent = result.summary.offline;

                    renderAccounts();
                }
            } catch (err) {
                console.error('Error fetching account data:', err);
                if (isManual) {
                    alert('Gagal memuat status akun. Silakan periksa koneksi.');
                }
            } finally {
                isFetching = false;
                if (refreshIcon) {
                    setTimeout(() => refreshIcon.classList.remove('spinning'), 400);
                }
            }
        }

        function renderAccounts() {
            const grid = document.getElementById('accounts-grid');
            if (!grid) return;

            let filtered = accountsData.filter(acc => {
                // Filter status / role
                if (currentFilter === 'online' && !acc.is_online) return false;
                if (currentFilter === 'offline' && acc.is_online) return false;
                if (['admin', 'bendahara', 'ketua', 'purchase', 'purchase_stok'].includes(currentFilter)) {
                    if (acc.role !== currentFilter) return false;
                }

                // Search query
                if (searchQuery) {
                    const u = (acc.username || '').toLowerCase();
                    const r = (roleLabels[acc.role] || acc.role || '').toLowerCase();
                    const ip = (acc.ip_address || '').toLowerCase();
                    const dev = (acc.device || '').toLowerCase();
                    if (!u.includes(searchQuery) && !r.includes(searchQuery) && !ip.includes(searchQuery) && !dev.includes(searchQuery)) {
                        return false;
                    }
                }
                return true;
            });

            if (filtered.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="ti ti-user-off"></i>
                        <p style="font-weight:600; font-size:15px; margin-bottom:4px;">Tidak ada akun ditemukan</p>
                        <p style="font-size:13px;">Coba gunakan kata kunci pencarian atau filter lain.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            filtered.forEach(acc => {
                const initial = (acc.username || 'U').charAt(0).toUpperCase();
                const roleClass = getRoleClass(acc.role);
                const roleLabel = roleLabels[acc.role] || acc.role;
                const isOnline = acc.is_online;
                const cardClass = `account-card ${isOnline ? 'is-online' : ''} ${acc.is_current_user ? 'is-current' : ''}`;
                const hasRevealedPwd = !!revealedPasswords[acc.id];

                html += `
                    <div class="${cardClass}">
                        <div class="card-top">
                            <div class="user-profile">
                                <div class="avatar">
                                    ${initial}
                                    <span class="${isOnline ? 'avatar-online-dot' : 'avatar-offline-dot'}"></span>
                                </div>
                                <div class="user-meta">
                                    <div class="username-row">
                                        <span class="username">${escapeHtml(acc.username)}</span>
                                        ${acc.is_current_user ? '<span class="badge-you">Anda</span>' : ''}
                                    </div>
                                    <span class="role-badge ${roleClass}">
                                        <i class="ti ti-shield-check" style="font-size:11px; vertical-align:middle;"></i>
                                        ${escapeHtml(roleLabel)}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <span class="status-pill ${isOnline ? 'online' : 'offline'}">
                                    ${isOnline ? '<span class="pulse-dot"></span>' : '<i class="ti ti-point" style="font-size:16px;"></i>'}
                                    ${escapeHtml(acc.status_label)}
                                </span>
                            </div>
                        </div>

                        <div class="card-details">
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="ti ti-key"></i>
                                    Password
                                </span>
                                <div class="password-box" id="pwd-box-${acc.id}">
                                    ${hasRevealedPwd 
                                        ? `<span class="plain-pwd">${escapeHtml(revealedPasswords[acc.id])}</span>
                                           <button type="button" class="btn-copy-pwd" onclick="copyPassword(${acc.id})" title="Salin password"><i class="ti ti-copy"></i></button>
                                           <button type="button" class="btn-show-pwd" onclick="hidePassword(${acc.id})" title="Sembunyikan"><i class="ti ti-eye-off"></i></button>`
                                        : `<span class="masked-pwd">••••••••</span>
                                           <button type="button" class="btn-show-pwd" onclick="askAdminPassword(${acc.id}, '${escapeHtml(acc.username)}')" title="Lihat password">
                                               <i class="ti ti-eye"></i>
                                               <span>Lihat</span>
                                           </button>`
                                    }
                                </div>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="ti ti-activity"></i>
                                    Terakhir Aktif
                                </span>
                                <span class="detail-value ${isOnline ? 'highlight' : ''}" title="${escapeHtml(acc.last_activity_full)}">
                                    ${escapeHtml(acc.last_activity_formatted)}
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="ti ti-login"></i>
                                    Terakhir Login
                                </span>
                                <span class="detail-value" title="${escapeHtml(acc.last_login_formatted)}">
                                    ${escapeHtml(acc.last_login_formatted)}
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="ti ti-device-laptop"></i>
                                    Perangkat
                                </span>
                                <span class="detail-value">${escapeHtml(acc.device)}</span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="ti ti-network"></i>
                                    IP Address
                                </span>
                                <span class="detail-value">${escapeHtml(acc.ip_address)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        async function askAdminPassword(accountId, username) {
            const { value: adminPass } = await Swal.fire({
                title: 'Konfirmasi Keamanan',
                html: `
                    <div style="text-align: left; font-size: 13.5px; color: #475569; margin-bottom: 14px;">
                        Untuk melihat password akun <b>"${escapeHtml(username)}"</b>, silakan masukkan <b>password Admin Anda</b>:
                    </div>
                `,
                input: 'password',
                inputPlaceholder: 'Password Admin Anda...',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off',
                    autocomplete: 'current-password'
                },
                showCancelButton: true,
                confirmButtonText: 'Verifikasi & Buka',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#2563a8',
                showLoaderOnConfirm: true,
                preConfirm: async (adminPass) => {
                    if (!adminPass) {
                        Swal.showValidationMessage('Password admin wajib diisi!');
                        return false;
                    }
                    try {
                        const res = await fetch('api-reveal-password.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                admin_password: adminPass,
                                account_id: accountId
                            })
                        });
                        const data = await res.json();
                        if (data.status !== 'success') {
                            throw new Error(data.message || 'Verifikasi gagal');
                        }
                        return data;
                    } catch (err) {
                        Swal.showValidationMessage(err.message || 'Terjadi kesalahan sistem');
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            });

            if (adminPass && adminPass.password) {
                revealedPasswords[accountId] = adminPass.password;
                renderAccounts();
                Swal.fire({
                    icon: 'success',
                    title: 'Password Terbuka',
                    text: `Password akun "${username}" berhasil ditampilkan.`,
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }

        function hidePassword(accountId) {
            delete revealedPasswords[accountId];
            renderAccounts();
        }

        function copyPassword(accountId) {
            const pwd = revealedPasswords[accountId];
            if (!pwd) return;
            navigator.clipboard.writeText(pwd).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Tersalin!',
                    text: 'Password berhasil disalin ke clipboard.',
                    timer: 1200,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }).catch(() => {
                alert('Gagal menyalin password');
            });
        }

        function setFilter(filter, el) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');
            renderAccounts();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Event listener search
        document.getElementById('search-input').addEventListener('input', function(e) {
            searchQuery = e.target.value.toLowerCase().trim();
            renderAccounts();
        });

        // Load data pertama kali
        loadAccountData();

        // Auto-refresh data tiap 10 detik
        setInterval(() => {
            loadAccountData(false);
        }, 10000);
    </script>
</body>
</html>
