// ===================== LAPORAN KOPERASI - SCRIPT =====================
// File ini dimuat SETELAH script.js (pakai fungsi formatRibuan & angkaBersih
// yang sudah ada di sana).

/* ─── Detail Toggle ─────────────────────────────────────────────────── */
function toggleDetail(id, btn) {
    const row = document.getElementById(id);
    const isOpen = row.classList.contains('open');
    row.classList.toggle('open', !isOpen);
    btn.style.background = !isOpen ? 'var(--navy)' : '';
    btn.style.color = !isOpen ? '#fff' : '';
    btn.style.borderColor = !isOpen ? 'var(--navy)' : '';
}

/* ─── Modal Helpers ─────────────────────────────────────────────────── */
function bukaModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}

function tutupModalById(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'modalTtd') resetModalTtd();
    // Jangan buka scroll body kalau masih ada modal lain yang kebuka
    // (misal: modal zoom gambar ditutup, tapi modal galeri nota di belakangnya masih terbuka).
    const masihAdaYangTerbuka = document.querySelector('.modal-overlay.open');
    document.body.style.overflow = masihAdaYangTerbuka ? 'hidden' : '';
}

function tutupModal(id, e) {
    if (e.target === document.getElementById(id)) tutupModalById(id);
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
    }
});

/* ─── Modal Gambar (nota / bukti transfer) ───────────────────────────── */
function bukaGambar(src, judul) {
    document.getElementById('gambarBesar').src = src;
    document.getElementById('titleGambar').textContent = judul || 'Lihat Gambar';
    bukaModal('modalGambar');
}

/* ─── Modal Tambah Saldo ──────────────────────────────────────────────── */
function bukaTambahSaldo(id, namaPengajuan) {
    document.getElementById('inputIdPengajuanSaldo').value = id;
    document.getElementById('inputNamaPengajuanSaldo').value = namaPengajuan;
    bukaModal('modalSaldo');
}

/* ─── Modal Tambah Barang ─────────────────────────────────────────────── */
function bukaTambahBarang(pengajuanId) {
    document.getElementById('tambahBarangPengajuanId').value = pengajuanId;
    document.getElementById('tambahBarangQty').value = '';
    document.getElementById('tambahBarangHarga').value = '';
    document.getElementById('tambahBarangSubtotal').value = 'Rp 0';

    resetNotaContext('tambahBarang');
    bukaModal('modalTambahBarang');
}

/* ─── Modal Upload Nota (untuk barang yang sudah ada) ─────────────────── */
function bukaUploadNota(itemId, namaBarang) {
    document.getElementById('uploadNotaItemId').value = itemId;
    document.getElementById('uploadNotaNama').value = namaBarang;

    resetNotaContext('uploadNota');
    bukaModal('modalUploadNota');
}

function validasiUploadNota() {
    if (notaFileLists.uploadNota.length === 0) {
        if (window.Swal) {
            Swal.fire('Belum ada file', 'Pilih atau ambil foto nota dulu sebelum simpan.', 'warning');
        } else {
            alert('Pilih atau ambil foto nota dulu sebelum simpan.');
        }
        return false;
    }
    return true;
}

/* ─── Upload Nota: bisa lebih dari 1 file, dari file manager ATAU kamera ─── *
 * Dipakai di 2 tempat: modal "Tambah Barang" (context: tambahBarang) dan
 * modal "Upload Nota" untuk barang yang sudah ada (context: uploadNota).
 * Kedua sumber (tombol "Pilih File" & tombol "Ambil Foto") memicu input file
 * masing-masing (tanpa atribut "name", jadi tidak ikut ke-submit langsung).
 * Setiap file baru yang dipilih di-akumulasi ke notaFileLists[context], lalu
 * disinkron ke satu input tersembunyi per-context yang benar-benar dikirim
 * ke server via DataTransfer. Ini memungkinkan:
 * - Gabung file dari galeri + kamera dalam satu submit
 * - Ambil foto kamera berkali-kali (tiap jepretan ditambahkan, bukan menimpa)
 * - Hapus satu per satu sebelum submit
 */
const notaContextConfig = {
    tambahBarang: { submitInputId: 'tambahBarangNotaSubmit', previewListId: 'tambahBarangNotaPreview' },
    uploadNota: { submitInputId: 'uploadNotaSubmit', previewListId: 'uploadNotaPreview' }
};
const notaFileLists = { tambahBarang: [], uploadNota: [] };

function resetNotaContext(context) {
    const cfg = notaContextConfig[context];
    if (!cfg) return;

    notaFileLists[context] = [];
    syncNotaSubmitInput(context);
    renderNotaPreview(context);

    // Reset input picker file & kamera (biar kalau modal dibuka lagi, ga kebawa pilihan lama)
    document.querySelectorAll('input[type="file"][onchange*="\'' + context + '\'"]').forEach(function (el) {
        el.value = '';
    });
}

function handleNotaFilesAdded(context, fileList) {
    const cfg = notaContextConfig[context];
    if (!cfg) return;

    const files = Array.from(fileList || []);
    const maxSize = 5 * 1024 * 1024; // 5MB, samakan dengan batas di server

    files.forEach(function (file) {
        const namaOk = /\.(jpe?g|png|pdf)$/i.test(file.name) || file.type.startsWith('image/');
        if (!namaOk) {
            if (window.Swal) {
                Swal.fire('Format tidak didukung', file.name + ' harus JPG, PNG, atau PDF.', 'warning');
            }
            return;
        }
        if (file.size > maxSize) {
            if (window.Swal) {
                Swal.fire('Ukuran terlalu besar', file.name + ' melebihi 5MB.', 'warning');
            }
            return;
        }
        notaFileLists[context].push(file);
    });

    syncNotaSubmitInput(context);
    renderNotaPreview(context);
}

/**
 * Sinkronkan notaFileLists[context] ke input file tersembunyi yang benar-benar
 * ikut ter-submit.
 */
function syncNotaSubmitInput(context) {
    const cfg = notaContextConfig[context];
    if (!cfg) return;

    const submitInput = document.getElementById(cfg.submitInputId);
    if (!submitInput) return;

    const dt = new DataTransfer();
    notaFileLists[context].forEach(function (file) {
        dt.items.add(file);
    });
    submitInput.files = dt.files;
}

/**
 * Render ulang daftar chip preview nota (thumbnail untuk gambar, badge "PDF"
 * untuk dokumen), lengkap dengan tombol hapus per file.
 */
function renderNotaPreview(context) {
    const cfg = notaContextConfig[context];
    if (!cfg) return;

    const preview = document.getElementById(cfg.previewListId);
    if (!preview) return;
    preview.innerHTML = '';

    notaFileLists[context].forEach(function (file, idx) {
        const chip = document.createElement('div');
        chip.className = 'nota-preview-item';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            img.onload = function () { URL.revokeObjectURL(img.src); };
            chip.appendChild(img);
        } else {
            const icon = document.createElement('div');
            icon.className = 'nota-preview-item__icon';
            icon.textContent = 'PDF';
            chip.appendChild(icon);
        }

        const label = document.createElement('span');
        label.className = 'nota-preview-item__name';
        label.textContent = file.name;
        chip.appendChild(label);

        const hapusBtn = document.createElement('button');
        hapusBtn.type = 'button';
        hapusBtn.className = 'nota-preview-item__remove';
        hapusBtn.innerHTML = '&times;';
        hapusBtn.title = 'Hapus file ini';
        hapusBtn.onclick = function () { removeNotaFile(context, idx); };
        chip.appendChild(hapusBtn);

        preview.appendChild(chip);
    });
}

function removeNotaFile(context, idx) {
    notaFileLists[context].splice(idx, 1);
    syncNotaSubmitInput(context);
    renderNotaPreview(context);
}

/* ─── Modal Galeri Nota (full preview, mendukung lebih dari 1 file) ───── */
/**
 * notas: array nama file (relatif ke folder ../uploads/)
 * judul: judul yang ditampilkan di header modal
 */
function bukaGaleriNota(notas, judul) {
    const body = document.getElementById('galeriNotaBody');
    const title = document.getElementById('titleGaleriNota');
    if (title) title.textContent = judul || 'Nota Belanja';
    if (!body) return;

    body.innerHTML = '';

    if (!notas || notas.length === 0) {
        body.innerHTML = '<div style="text-align:center;color:var(--muted);padding:20px">Belum ada nota.</div>';
        bukaModal('modalGaleriNota');
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'nota-galeri-grid';

    notas.forEach(function (nota, idx) {
        const path = '../uploads/' + nota;
        const isPdf = /\.pdf(\?.*)?$/i.test(nota);
        const item = document.createElement('div');
        item.className = 'nota-galeri-item';

        if (isPdf) {
            item.innerHTML =
                '<a href="' + path + '" target="_blank" rel="noopener" class="nota-galeri-pdf">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
                '<polyline points="14 2 14 8 20 8"/>' +
                '</svg>' +
                '<span>Nota ' + (idx + 1) + ' (PDF) — buka di tab baru</span>' +
                '</a>';
        } else {
            const img = document.createElement('img');
            img.src = path;
            img.alt = 'Nota ' + (idx + 1);
            img.loading = 'lazy';
            img.title = 'Klik untuk perbesar';
            img.onclick = function () {
                bukaGambar(path, (judul || 'Nota Belanja') + ' — ' + (idx + 1));
            };
            img.onerror = function () {
                item.innerHTML = '<span style="color:var(--minus);font-size:11px">File tidak ditemukan</span>';
            };
            item.appendChild(img);
        }

        grid.appendChild(item);
    });

    body.appendChild(grid);
    bukaModal('modalGaleriNota');
}

function hitungSubtotalTambah() {
    const qty = parseFloat(document.getElementById('tambahBarangQty').value) || 0;
    const hargaInput = document.getElementById('tambahBarangHarga').value;
    const harga = parseFloat(angkaBersih(hargaInput)) || 0;
    const subtotal = qty * harga;
    document.getElementById('tambahBarangSubtotal').value =
        'Rp ' + subtotal.toLocaleString('id-ID');
}

/* ─── Modal Edit Harga (unified: item barang ATAU nominal pengajuan) ──── */
/**
 * targetType: 'item'       -> edit harga_satuan di detail_pengajuan
 *             'pengajuan'  -> edit jumlah langsung di pengajuan_anggaran (jenis operasional)
 */
function bukaEditHarga(targetType, id, nama, nilaiSaatIni) {
    const form = document.getElementById('formEditHarga');
    const aksiInput = document.getElementById('editHargaAksi');
    const itemIdInput = document.getElementById('editHargaItemId');
    const pengajuanIdInput = document.getElementById('editHargaPengajuanId');
    const labelNama = document.getElementById('editHargaLabelNama');
    const labelInput = document.getElementById('editHargaLabelInput');
    const hint = document.getElementById('editHargaHint');
    const inputNilai = document.getElementById('editHargaInput');

    document.getElementById('editHargaNama').value = nama;
    inputNilai.value = Math.round(nilaiSaatIni).toLocaleString('id-ID');
    inputNilai.name = 'harga_baru';

    itemIdInput.value = '';
    pengajuanIdInput.value = '';

    if (targetType === 'item') {
        aksiInput.value = 'edit_harga_item';
        itemIdInput.value = id;
        labelNama.textContent = 'Nama Barang';
        labelInput.textContent = 'Harga Baru (Rp)';
        hint.textContent = 'Subtotal akan dihitung ulang otomatis (qty × harga).';
        inputNilai.name = 'harga_baru';
        document.getElementById('titleEditHarga').textContent = 'Edit Harga Barang';
    } else {
        aksiInput.value = 'edit_jumlah_pengajuan';
        pengajuanIdInput.value = id;
        labelNama.textContent = 'Tujuan Pengajuan';
        labelInput.textContent = 'Nominal Baru (Rp)';
        hint.textContent = 'Nominal pengajuan operasional akan diperbarui langsung.';
        inputNilai.name = 'jumlah_baru';
        document.getElementById('titleEditHarga').textContent = 'Edit Nominal Pengajuan';
    }

    bukaModal('modalEditHarga');
}

/* ─── Modal Tanda Tangan (TTD) ─────────────────────────────────────────
 * Canvas & event drawing (initCanvasTTD, mulaiGambarTTD, gambarTTD,
 * selesaiGambarTTD, hapusTandaTangan, ttdSudahMenggambar) sudah ada di
 * script.js dan dipakai ulang di sini — file ini cuma mengatur alur
 * buka/tutup modal + mode "lihat" vs "gambar" + submit ke server.
 *
 * Alur:
 * - Kalau role yang login BELUM tanda tangan pengajuan ini -> langsung
 *   mode GAMBAR (canvas kosong).
 * - Kalau SUDAH pernah -> mode LIHAT (tampilkan gambar tanda tangan
 *   tersimpan). User bisa pilih "Ganti Tanda Tangan" (pindah ke mode
 *   gambar, canvas kosong, lalu simpan akan menimpa yang lama) atau
 *   "Hapus Tanda Tangan" (hapus dari server, balik ke status kosong).
 * ──────────────────────────────────────────────────────────────────── */

/**
 * ttdExisting: null kalau belum pernah tanda tangan, atau
 * { signature_path, signed_by, signed_at } kalau sudah.
 */
function bukaTtdKoperasi(pengajuanId, namaPengajuan, roleLabel, ttdExisting) {
    document.getElementById('ttdPengajuanId').value = pengajuanId;
    document.getElementById('ttdNamaPengajuan').value = namaPengajuan;
    document.getElementById('ttdRoleLabel').value = roleLabel;
    document.getElementById('ttdSignatureData').value = '';
    document.getElementById('ttdAksiInput').value = 'simpan_ttd';

    if (ttdExisting && ttdExisting.signature_path) {
        tampilkanTtdViewMode(ttdExisting);
    } else {
        tampilkanTtdDrawMode();
    }

    bukaModal('modalTtd');
}

function tampilkanTtdViewMode(ttdExisting) {
    document.getElementById('ttdViewMode').style.display = 'block';
    document.getElementById('ttdDrawMode').style.display = 'none';
    document.getElementById('ttdSubmitBtn').style.display = 'none';

    document.getElementById('ttdExistingImg').src = '../uploads/' + ttdExisting.signature_path;

    let tglTampil = ttdExisting.signed_at;
    try {
        const tgl = new Date(ttdExisting.signed_at.replace(' ', 'T'));
        if (!isNaN(tgl.getTime())) tglTampil = tgl.toLocaleString('id-ID');
    } catch (e) { /* biarkan tampilkan mentah kalau parsing gagal */ }

    document.getElementById('ttdInfoText').textContent =
        'Ditandatangani oleh ' + ttdExisting.signed_by + ' pada ' + tglTampil;
}

function tampilkanTtdDrawMode() {
    document.getElementById('ttdViewMode').style.display = 'none';
    document.getElementById('ttdDrawMode').style.display = 'block';
    document.getElementById('ttdSubmitBtn').style.display = '';

    // initCanvasTTD butuh canvas sudah kelihatan (punya ukuran) supaya
    // getBoundingClientRect() akurat -> tunggu 1 frame + sedikit delay
    // setelah modal/div-nya ditampilkan.
    requestAnimationFrame(function () {
        setTimeout(initCanvasTTD, 30);
    });
}

/** Tombol "Ganti Tanda Tangan" di mode lihat -> pindah ke canvas kosong. */
function gantiTtdKoperasi() {
    tampilkanTtdDrawMode();
}

/** Tombol "Hapus Tanda Tangan" di mode lihat -> submit form dengan aksi hapus_ttd. */
function hapusTtdKoperasiClick() {
    const konfirmasi = window.Swal
        ? Swal.fire({
            title: 'Hapus tanda tangan?',
            text: 'Tanda tangan ini akan dihapus permanen dan perlu ditandatangani ulang.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) kirimHapusTtd(); })
        : (confirm('Hapus tanda tangan ini? Perlu ditandatangani ulang setelahnya.') ? kirimHapusTtd() : null);
}

function kirimHapusTtd() {
    document.getElementById('ttdAksiInput').value = 'hapus_ttd';
    document.getElementById('formTtd').submit();
}

/** Validasi sebelum submit form (onsubmit di <form id="formTtd">). */
function submitTtdKoperasi(e) {
    const aksi = document.getElementById('ttdAksiInput').value;

    // Aksi hapus tidak perlu validasi canvas, langsung lanjut submit.
    if (aksi === 'hapus_ttd') return true;

    const drawModeAktif = document.getElementById('ttdDrawMode').style.display !== 'none';
    if (!drawModeAktif) {
        // Harusnya tidak mungkin (tombol submit disembunyikan di mode lihat),
        // tapi dijaga di sini juga.
        return false;
    }

    if (!ttdSudahMenggambar) {
        if (window.Swal) {
            Swal.fire('Belum menandatangani', 'Silakan gambar tanda tangan terlebih dahulu di dalam kotak.', 'warning');
        } else {
            alert('Silakan gambar tanda tangan terlebih dahulu di dalam kotak.');
        }
        return false;
    }

    const canvas = document.getElementById('canvasTTD');
    document.getElementById('ttdSignatureData').value = canvas.toDataURL('image/png');
    return true;
}

function resetModalTtd() {
    const aksiInput = document.getElementById('ttdAksiInput');
    if (aksiInput) aksiInput.value = 'simpan_ttd';

    const sigInput = document.getElementById('ttdSignatureData');
    if (sigInput) sigInput.value = '';

    const submitBtn = document.getElementById('ttdSubmitBtn');
    if (submitBtn) submitBtn.style.display = '';

    const viewMode = document.getElementById('ttdViewMode');
    if (viewMode) viewMode.style.display = 'none';

    const drawMode = document.getElementById('ttdDrawMode');
    if (drawMode) drawMode.style.display = 'none';
}