// ===================== LAPORAN SPPG - SCRIPT =====================

function setSaldoForm(id, namaMenu) {
    document.getElementById('idPengajuan').value = id;
    document.getElementById('namaMenuSaldo').value = namaMenu;
}

function tampilkanGambar(src) {
    document.getElementById('gambarBesar').src = src;
}

// ===================== FORMAT RIBUAN (INPUT RUPIAH) =====================

/**
 * Ambil hanya digit angka dari sebuah string, buang semua karakter lain
 * (titik, huruf, spasi, dll).
 */
function angkaBersih(str) {
    return (str || '').toString().replace(/[^\d]/g, '');
}

/**
 * Dipasang lewat oninput pada input bertipe text yang berisi nominal rupiah.
 * Otomatis menambahkan titik ribuan sambil user mengetik, contoh: 200000 -> 200.000
 */
function formatRibuan(input) {
    const raw = angkaBersih(input.value);
    if (raw === '') {
        input.value = '';
        return;
    }
    input.value = Number(raw).toLocaleString('id-ID');
}

// ===================== TANDA TANGAN DIGITAL (CANVAS) =====================

let ttdCtx = null;
let ttdMenggambar = false;
let ttdSudahMenggambar = false;

/**
 * Siapkan ulang canvas setiap kali modal tanda tangan dibuka.
 * Canvas di-scale sesuai ukuran tampilan (device pixel ratio) agar tetap tajam,
 * dan seluruh event mouse/touch/pointer di-pasang di sini.
 */
function initCanvasTTD() {
    const canvas = document.getElementById('canvasTTD');
    if (!canvas) return;

    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;

    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;

    ttdCtx = canvas.getContext('2d');
    ttdCtx.scale(ratio, ratio);
    ttdCtx.lineWidth = 2.2;
    ttdCtx.lineCap = 'round';
    ttdCtx.lineJoin = 'round';
    ttdCtx.strokeStyle = '#0F172A';

    ttdMenggambar = false;
    ttdSudahMenggambar = false;
    togglePlaceholderTTD(true);

    // Lepas listener lama (jika ada) supaya tidak dobel saat modal dibuka berkali-kali
    canvas.onpointerdown = mulaiGambarTTD;
    canvas.onpointermove = gambarTTD;
    canvas.onpointerup = selesaiGambarTTD;
    canvas.onpointerleave = selesaiGambarTTD;
    canvas.style.touchAction = 'none'; // supaya scroll halaman tidak ikut kepencet saat menggambar
}

function posisiRelatif(canvas, e) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

function mulaiGambarTTD(e) {
    if (!ttdCtx) return;
    ttdMenggambar = true;
    togglePlaceholderTTD(false);
    const canvas = e.target;
    const pos = posisiRelatif(canvas, e);
    ttdCtx.beginPath();
    ttdCtx.moveTo(pos.x, pos.y);
}

function gambarTTD(e) {
    if (!ttdMenggambar || !ttdCtx) return;
    const canvas = e.target;
    const pos = posisiRelatif(canvas, e);
    ttdCtx.lineTo(pos.x, pos.y);
    ttdCtx.stroke();
    ttdSudahMenggambar = true;
}

function selesaiGambarTTD() {
    ttdMenggambar = false;
}

function togglePlaceholderTTD(tampil) {
    const el = document.getElementById('signaturePlaceholder');
    if (el) el.style.display = tampil ? 'flex' : 'none';
}

/**
 * Bersihkan canvas tanda tangan (dipanggil dari tombol "Hapus" di modal).
 */
function hapusTandaTangan() {
    const canvas = document.getElementById('canvasTTD');
    if (canvas && ttdCtx) {
        ttdCtx.clearRect(0, 0, canvas.width, canvas.height);
    }
    ttdSudahMenggambar = false;
    togglePlaceholderTTD(true);
    const hidden = document.getElementById('inputSignatureData');
    if (hidden) hidden.value = '';
}

// ===================== GALERI NOTA (MULTI FOTO PER ITEM) =====================
function bukaNota(notas, namaBarang) {
    document.getElementById('titleNotaGaleri').textContent = 'Nota — ' + namaBarang;
    const grid = document.getElementById('notaGaleriGrid');
    grid.innerHTML = '';

    if (!notas || notas.length === 0) {
        grid.innerHTML = '<div class="nota-gallery-empty">Tidak ada nota.</div>';
        bukaModal('modalNotaGaleri');
        return;
    }

    notas.forEach(function(nota) {
        if (!nota.file_path) return;
        const ext = nota.file_path.split('.').pop().toLowerCase();
        const item = document.createElement('div');
        item.className = 'nota-gallery-item';

        if (ext === 'pdf') {
            item.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
            item.onclick = function() {
                bukaGambar(nota.file_path, 'Nota Belanja', true);
            };
        } else {
            const img = document.createElement('img');
            img.src = nota.file_path;
            img.alt = 'nota';
            img.onerror = function() {
                item.innerHTML = '<span style="color:var(--minus);font-size:10px;text-align:center;padding:4px">File tidak ditemukan</span>';
            };
            item.appendChild(img);
            item.onclick = function() {
                bukaGambar(nota.file_path, 'Nota Belanja', false);
            };
        }
        grid.appendChild(item);
    });

    bukaModal('modalNotaGaleri');
}