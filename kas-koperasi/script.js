// ============================================================
// KAS KOPERASI — modal, format rupiah, AJAX simpan/hapus
// ============================================================

const modal        = document.getElementById('modalTransaksi');
const formTransaksi = document.getElementById('formTransaksi');
const fId           = document.getElementById('fId');
const fTanggal      = document.getElementById('fTanggal');
const fKeterangan   = document.getElementById('fKeterangan');
const fNominal      = document.getElementById('fNominal');
const modalTitle    = document.getElementById('modalTitle');
const formError     = document.getElementById('formError');
const btnSimpan     = document.getElementById('btnSimpan');

/* ---------------- Format angka ribuan saat mengetik ---------------- */
if (fNominal) {
    fNominal.addEventListener('input', () => {
        const angka = fNominal.value.replace(/\D/g, '');
        fNominal.value = angka ? new Intl.NumberFormat('id-ID').format(angka) : '';
    });
}

/* ---------------- Buka modal: tambah ---------------- */
function bukaModalTambah() {
    formTransaksi.reset();
    fId.value = '';
    modalTitle.textContent = 'Tambah Transaksi';
    document.querySelector('input[name="jenis"][value="masuk"]').checked = true;

    const today = new Date().toISOString().slice(0, 10);
    fTanggal.value = today;

    sembunyikanError();
    modal.style.display = 'flex';
}

/* ---------------- Buka modal: edit (ambil data dari atribut baris tabel) ---------------- */
function bukaModalEdit(btn) {
    const tr = btn.closest('tr');
    const { id, tanggal, keterangan, jenis, nominal } = tr.dataset;

    formTransaksi.reset();
    fId.value = id;
    modalTitle.textContent = 'Edit Transaksi';
    document.querySelector(`input[name="jenis"][value="${jenis}"]`).checked = true;
    fTanggal.value = tanggal;
    fKeterangan.value = keterangan;
    fNominal.value = new Intl.NumberFormat('id-ID').format(Math.round(parseFloat(nominal)));

    sembunyikanError();
    modal.style.display = 'flex';
}

/* ---------------- Tutup modal ---------------- */
function tutupModal() {
    modal.style.display = 'none';
}

// Klik di luar kotak modal untuk menutup
if (modal) {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) tutupModal();
    });
}

// Tombol Escape untuk menutup modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
        tutupModal();
    }
});

function tampilkanError(pesan) {
    formError.textContent = pesan;
    formError.style.display = 'block';
}

function sembunyikanError() {
    formError.style.display = 'none';
    formError.textContent = '';
}

/* ---------------- Submit form (tambah / edit) ---------------- */
if (formTransaksi) {
    formTransaksi.addEventListener('submit', async (e) => {
        e.preventDefault();
        sembunyikanError();

        const formData = new FormData(formTransaksi);
        // Kirim nominal dalam bentuk angka murni (buang titik pemisah ribuan)
        formData.set('nominal', fNominal.value.replace(/\D/g, ''));

        btnSimpan.disabled = true;
        const teksAsli = btnSimpan.textContent;
        btnSimpan.textContent = 'Menyimpan...';

        try {
            const res = await fetch('../database/simpan-kas.php', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                tampilkanToast(data.message, 'success');
                tutupModal();
                setTimeout(() => window.location.reload(), 500);
            } else {
                tampilkanError(data.message || 'Gagal menyimpan transaksi.');
            }
        } catch (err) {
            tampilkanError('Terjadi kesalahan koneksi. Coba lagi.');
        } finally {
            btnSimpan.disabled = false;
            btnSimpan.textContent = teksAsli;
        }
    });
}

/* ---------------- Hapus transaksi ---------------- */
async function hapusTransaksi(id) {
    if (!confirm('Yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.set('id', id);

        const res = await fetch('../database/hapus-kas.php', {
            method: 'POST',
            body: formData,
        });
        const data = await res.json();

        if (data.success) {
            tampilkanToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            tampilkanToast(data.message || 'Gagal menghapus transaksi.', 'error');
        }
    } catch (err) {
        tampilkanToast('Terjadi kesalahan koneksi. Coba lagi.', 'error');
    }
}

/* ---------------- Ganti tahun (reset bulan + rentang) ---------------- */
function gantiTahun(tahun) {
    const url = new URL(window.location.href);
    url.searchParams.set('tahun', tahun);
    url.searchParams.set('bulan', '0');
    url.searchParams.delete('dari');
    url.searchParams.delete('sampai');
    window.location.href = url.toString();
}

/* ---------------- Ganti bulan (pertahankan tahun, hapus rentang) ---------------- */
function gantiBulan(bulan) {
    const url = new URL(window.location.href);
    url.searchParams.set('bulan', bulan);
    url.searchParams.delete('dari');
    url.searchParams.delete('sampai');
    window.location.href = url.toString();
}

/* ---------------- Terapkan rentang tanggal ---------------- */
function terapkanRentang() {
    const dari   = document.getElementById('inputDari').value;
    const sampai = document.getElementById('inputSampai').value;
    if (!dari || !sampai) {
        alert('Isi kedua tanggal terlebih dahulu.');
        return;
    }
    if (dari > sampai) {
        alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('dari', dari);
    url.searchParams.set('sampai', sampai);
    url.searchParams.delete('bulan');
    window.location.href = url.toString();
}

/* ---------------- Reset rentang (kembali ke filter tahun) ---------------- */
function resetRentang() {
    const url = new URL(window.location.href);
    url.searchParams.delete('dari');
    url.searchParams.delete('sampai');
    window.location.href = url.toString();
}


/* ---------------- Toast notifikasi sederhana ---------------- */
function tampilkanToast(pesan, tipe = 'success') {
    let toast = document.getElementById('toastNotif');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastNotif';
        document.body.appendChild(toast);
    }
    toast.className = `toast ${tipe}`;
    toast.textContent = pesan;

    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => toast.classList.remove('show'), 2500);
}