/* =========================================================
   PENJUALAN SPPG FOODCOST — SCRIPT
   Interaksi UI: collapse kartu, loading filter, scroll-to-top
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
    initCardCollapse();
    initFilterLoading();
    initResetButton();
    initScrollTop();
});

/**
 * Klik header kartu transaksi untuk membuka/menutup tabel detail.
 * Membantu saat data transaksi sangat banyak agar halaman tetap ringkas.
 */
function initCardCollapse() {
    var heads = document.querySelectorAll('.fc-card-head[data-toggle="collapse"]');

    heads.forEach(function (head) {
        head.addEventListener('click', function () {
            var card = head.closest('.fc-card');
            if (!card) return;
            card.classList.toggle('is-collapsed');
        });

        // aksesibilitas: bisa dibuka dengan keyboard (Enter / Space)
        head.setAttribute('tabindex', '0');
        head.setAttribute('role', 'button');
        head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                head.click();
            }
        });
    });
}

/**
 * Menampilkan spinner pada tombol "Terapkan Filter" saat form disubmit,
 * supaya pengguna tahu proses sedang berjalan (form ini reload halaman).
 */
function initFilterLoading() {
    var form = document.getElementById('fcFilterForm');
    var submitBtn = document.getElementById('fcSubmitFilter');

    if (!form || !submitBtn) return;

    form.addEventListener('submit', function () {
        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
    });
}

/**
 * Tombol reset akan mengosongkan semua field filter lalu submit ulang form,
 * sehingga kembali menampilkan seluruh data.
 */
function initResetButton() {
    var resetBtn = document.getElementById('fcResetFilter');
    var form = document.getElementById('fcFilterForm');

    if (!resetBtn || !form) return;

    resetBtn.addEventListener('click', function () {
        form.querySelectorAll('input').forEach(function (input) {
            input.value = '';
        });
        form.submit();
    });
}

/**
 * Tombol "kembali ke atas" muncul setelah pengguna scroll cukup jauh,
 * berguna karena daftar transaksi bisa sangat panjang.
 */
function initScrollTop() {
    var btn = document.getElementById('fcScrollTop');
    if (!btn) return;

    var toggleVisibility = function () {
        if (window.scrollY > 400) {
            btn.classList.add('is-visible');
        } else {
            btn.classList.remove('is-visible');
        }
    };

    window.addEventListener('scroll', toggleVisibility, { passive: true });
    toggleVisibility();

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}