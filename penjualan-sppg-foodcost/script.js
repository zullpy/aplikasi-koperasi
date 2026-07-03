/* =========================================================
   PENJUALAN SPPG FOODCOST — SCRIPT
   Interaksi UI: collapse kartu, loading filter, scroll-to-top
========================================================= */

document.addEventListener('DOMContentLoaded', function () {
    initCardCollapse();
    initFilterLoading();
    initResetButton();
    initScrollTop();
    initPaymentModal();
    initToast();
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

/**
 * Mengubah string angka jadi format ribuan Indonesia, misal "150000" -> "150.000"
 */
function formatRibuan(angka) {
    var bersih = String(angka).replace(/[^0-9]/g, '');
    if (bersih === '') return '';
    return bersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Modal "Tambah Pembayaran": dibuka lewat tombol per kartu transaksi,
 * mengisi No. Transaksi & Sisa Tagihan, serta menampilkan/menyembunyikan
 * field upload bukti transfer sesuai metode pembayaran yang dipilih.
 */
function initPaymentModal() {
    var overlay = document.getElementById('fcPayModalOverlay');
    var closeBtn = document.getElementById('fcPayModalClose');
    var cancelBtn = document.getElementById('fcPayCancel');
    var idField = document.getElementById('fcPayIdPengambilan');
    var noTrxField = document.getElementById('fcPayNoTrx');
    var sisaField = document.getElementById('fcPaySisa');
    var jumlahInput = document.getElementById('fcPayJumlah');
    var hintField = document.getElementById('fcPayHint');
    var buktiWrap = document.getElementById('fcPayBuktiWrap');
    var buktiInput = document.getElementById('fcPayBukti');
    var form = document.getElementById('fcPayForm');
    var submitBtn = document.getElementById('fcPaySubmit');
    var metodeRadios = document.querySelectorAll('input[name="metode_pembayaran"]');

    if (!overlay || !form) return;

    var sisaAktif = 0;

    function bukaModal(data) {
        idField.value = data.id;
        noTrxField.textContent = data.no;
        sisaField.textContent = data.sisaFormat;
        sisaAktif = parseInt(data.sisa, 10) || 0;
        jumlahInput.value = '';
        hintField.textContent = '';
        hintField.classList.remove('is-error');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { jumlahInput.focus(); }, 50);
    }

    function tutupModal() {
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    // tombol "+ Tambah Pembayaran" di tiap kartu
    document.querySelectorAll('.fc-btn-bayar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            bukaModal({
                id: btn.getAttribute('data-id'),
                no: btn.getAttribute('data-no'),
                sisa: btn.getAttribute('data-sisa'),
                sisaFormat: btn.getAttribute('data-sisa-format')
            });
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', tutupModal);
    if (cancelBtn) cancelBtn.addEventListener('click', tutupModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) tutupModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) tutupModal();
    });

    // tampilkan field upload bukti transfer hanya saat metode = transfer
    function toggleBuktiWrap() {
        var terpilih = document.querySelector('input[name="metode_pembayaran"]:checked');
        var isTransfer = terpilih && terpilih.value === 'transfer';
        buktiWrap.style.display = isTransfer ? 'block' : 'none';
        if (buktiInput) buktiInput.required = isTransfer;
    }
    metodeRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleBuktiWrap);
    });
    toggleBuktiWrap();

    // format ribuan otomatis saat mengetik jumlah pembayaran
    if (jumlahInput) {
        jumlahInput.addEventListener('input', function () {
            var caretDariKanan = jumlahInput.value.length - jumlahInput.selectionStart;
            jumlahInput.value = formatRibuan(jumlahInput.value);
            var posisiBaru = jumlahInput.value.length - caretDariKanan;
            jumlahInput.setSelectionRange(posisiBaru, posisiBaru);

            var angka = parseInt(jumlahInput.value.replace(/\./g, ''), 10) || 0;
            if (angka > sisaAktif && sisaAktif > 0) {
                hintField.textContent = 'Jumlah melebihi sisa tagihan (' + formatRibuan(sisaAktif) + ').';
                hintField.classList.add('is-error');
            } else {
                hintField.textContent = '';
                hintField.classList.remove('is-error');
            }
        });
    }

    form.addEventListener('submit', function () {
        if (submitBtn) {
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
        }
    });

    // jika modal terbuka otomatis karena ada error validasi dari server,
    // pastikan tampilan field upload sesuai metode yang tadi dipilih
    if (overlay.classList.contains('is-open')) {
        document.body.style.overflow = 'hidden';
        toggleBuktiWrap();
    }
}

/**
 * Notifikasi kecil "Pembayaran berhasil disimpan" yang hilang otomatis.
 */
function initToast() {
    var toast = document.getElementById('fcToastSukses');
    if (!toast) return;

    setTimeout(function () {
        toast.classList.add('is-hide');
        setTimeout(function () { toast.remove(); }, 400);
    }, 3200);
}