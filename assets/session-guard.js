/**
 * Kopdes Client Session Guard
 * - Tutup Tab / Keluar Browser -> Otomatis Logout (via sessionStorage)
 * - Auto-logout jika tidak ada aktivitas selama 30 menit
 */
(function () {
    const path = window.location.pathname;
    const isSubfolder = path.includes('/transaksi-') ||
                        path.includes('/laporan-') ||
                        path.includes('/penjualan-') ||
                        path.includes('/daftar-') ||
                        path.includes('/data-') ||
                        path.includes('/dompet-') ||
                        path.includes('/stok-') ||
                        path.includes('/profit-') ||
                        path.includes('/rekap-') ||
                        path.includes('/pengajuan-') ||
                        path.includes('/selection-page/') ||
                        path.includes('/profile/');
    const logoutUrl = isSubfolder ? '../database/logout.php' : 'database/logout.php';

    const IDLE_TIMEOUT_MS = 30 * 60 * 1000; // 30 menit

    // 1. Cek sessionStorage (Jika tab baru dibuka tanpa sesi aktif, otomatis logout)
    if (!sessionStorage.getItem('kopdes_session_active')) {
        window.location.replace(logoutUrl + '?reason=tab_closed');
        return;
    }

    // 2. Cek apakah ada jeda idle lebih dari 30 menit saat tab kembali dibuka
    const lastActivity = parseInt(sessionStorage.getItem('kopdes_last_activity') || '0', 10);
    if (lastActivity > 0 && (Date.now() - lastActivity > IDLE_TIMEOUT_MS)) {
        sessionStorage.removeItem('kopdes_session_active');
        sessionStorage.removeItem('kopdes_last_activity');
        window.location.replace(logoutUrl + '?reason=idle_timeout');
        return;
    }

    sessionStorage.setItem('kopdes_last_activity', Date.now().toString());

    // 3. Timer Idle 30 menit otomatis
    let idleTimer;
    function resetIdleTimer() {
        sessionStorage.setItem('kopdes_last_activity', Date.now().toString());
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            sessionStorage.removeItem('kopdes_session_active');
            sessionStorage.removeItem('kopdes_last_activity');
            alert('Sesi Anda telah berakhir otomatis karena tidak ada aktivitas selama 30 menit.');
            window.location.replace(logoutUrl + '?reason=idle_timeout');
        }, IDLE_TIMEOUT_MS);
    }

    const events = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
    events.forEach(evt => {
        window.addEventListener(evt, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();
})();
