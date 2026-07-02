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

