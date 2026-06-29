/* ─────────────────────────────────────────────
approve.js — Approval Pengajuan Belanja
+ Fitur: Sisa uang otomatis nambah ke menu berikutnya
───────────────────────────────────────────── */

// ── STATE ─────────────────────────────────────
let allData = [];
let currentFilter = 'all';
let rejectTargetId = null;
let approveTargetId = null;
let approveTargetTotal = 0;
let sisaUangSebelumnya = 0; // 💰 Sisa uang dari menu sebelumnya

// State tanda tangan lokal
let signatures = {};

// TTD modal state
let ttdTargetId = null;
let ttdTargetRole = null;
let ttdCanvas = null;
let ttdCtx = null;
let isDrawing = false;
let lastX = 0, lastY = 0;

// Role labels
const ROLES = {
    bendahara: { label: 'Bendahara Koperasi', nama: 'Nancy Febi Yolla' },
    ketua: { label: 'Ketua Koperasi', nama: 'Yudi Hendrian' },
};

const ROLE_DB_MAP = {
    bendahara: 'bendahara',
    ketua: 'ketua',
};

// ─ FORMAT HELPERS ──────────────────────────────
function formatRupiah(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function parseRupiah(str) {
    return parseFloat(String(str).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
}

function onUangMasukInput(el) {
    const raw = String(el.value).replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    el.value = num ? num.toLocaleString('id-ID') : '';
    hitungSisa();
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'long', year: 'numeric'
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function statusLabel(s) {
    return { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', completed: 'Selesai' }[s] || s;
}

// ── LOAD / SAVE SIGNATURES ─────────────────────
function loadSigs() {
    try {
        const raw = localStorage.getItem('kbus_signatures');
        if (raw) signatures = JSON.parse(raw);
    } catch (e) { signatures = {}; }
}

function saveSigsLocal() {
    try { localStorage.setItem('kbus_signatures', JSON.stringify(signatures)); } catch (e) { }
}

function getSig(pengajuanId, role) {
    return (signatures[pengajuanId] || {})[role] || null;
}

function setSigLocal(pengajuanId, role, dataUrl, savedToDB = false) {
    if (!signatures[pengajuanId]) signatures[pengajuanId] = {};
    signatures[pengajuanId][role] = {
        dataUrl,
        timestamp: new Date().toISOString(),
        nama: ROLES[role].nama,
        savedToDB,
    };
    saveSigsLocal();
}

function removeSigLocal(pengajuanId, role) {
    if (signatures[pengajuanId]) delete signatures[pengajuanId][role];
    saveSigsLocal();
}

// ── HITUNG SISA UANG DARI MENU SEBELUMNYA ───────
/**
 * Mencari menu approved sebelumnya (berdasarkan tanggal & urutan)
 * dan mengembalikan sisa uangnya
 */
// ── HITUNG SISA UANG DARI MENU SEBELUMNYA ───────
/**
 * Mencari menu approved sebelumnya berdasarkan urutan tanggal & id
 * (bukan mencari posisi menu saat ini di daftar approved)
 */
function hitungSisaUangMenuSebelumnya(currentId) {
    const currentItem = allData.find(d => d.id == currentId);
    if (!currentItem) return 0;

    const currentTanggal = currentItem.tanggal;
    const currentIdNum = parseInt(currentItem.id);

    // Cari semua menu yang URUTANNYA sebelum menu saat ini
    // (tanggal lebih lama, ATAU tanggal sama tapi id lebih kecil)
    const menuSebelumnya = allData
        .filter(d => {
            if (d.id == currentId) return false; // skip diri sendiri
            if (d.tanggal < currentTanggal) return true;
            if (d.tanggal === currentTanggal && parseInt(d.id) < currentIdNum) return true;
            return false;
        })
        // Filter hanya yang approved/completed dan punya uang_masuk
        .filter(d =>
            (d.status === 'approved' || d.status === 'completed') &&
            parseFloat(d.uang_masuk) > 0
        )
        // Urutkan: yang paling baru (terakhir) di atas
        .sort((a, b) => {
            if (a.tanggal !== b.tanggal) return new Date(b.tanggal) - new Date(a.tanggal);
            return parseInt(b.id) - parseInt(a.id);
        });

    // Ambil menu approved paling terakhir (yang paling dekat dengan menu saat ini)
    if (menuSebelumnya.length === 0) return 0;

    const lastApproved = menuSebelumnya[0];
    const uangMasuk = parseFloat(lastApproved.uang_masuk) || 0;
    const totalBelanja = parseFloat(lastApproved.total_belanja) || 0;
    const sisa = uangMasuk - totalBelanja;

    console.log('💰 Sisa dari menu sebelumnya:', {
        menu: lastApproved.nama_menu,
        uangMasuk,
        totalBelanja,
        sisa
    });

    return sisa > 0 ? sisa : 0;
}

/**
 * Cari nama menu approved sebelumnya (untuk display info)
 */
function cariMenuSebelumnya(currentId) {
    const currentItem = allData.find(d => d.id == currentId);
    if (!currentItem) return null;

    const currentTanggal = currentItem.tanggal;
    const currentIdNum = parseInt(currentItem.id);

    const menuSebelumnya = allData
        .filter(d => {
            if (d.id == currentId) return false;
            if (d.tanggal < currentTanggal) return true;
            if (d.tanggal === currentTanggal && parseInt(d.id) < currentIdNum) return true;
            return false;
        })
        .filter(d =>
            (d.status === 'approved' || d.status === 'completed') &&
            parseFloat(d.uang_masuk) > 0
        )
        .sort((a, b) => {
            if (a.tanggal !== b.tanggal) return new Date(b.tanggal) - new Date(a.tanggal);
            return parseInt(b.id) - parseInt(a.id);
        });

    if (menuSebelumnya.length === 0) return null;
    return menuSebelumnya[0].nama_menu;
}

// ── FETCH DATA ────────────────────────────────
async function fetchData() {
    showSkeletons();
    try {
        const res = await fetch('../database/api-belanja.php?action=list');
        const result = await res.json();
        if (result.success) {
            allData = result.data;
            allData.forEach(d => {
                if (!d.detail_items && d.items) d.detail_items = d.items;
                if (!d.detail_items) d.detail_items = [];
            });
            await loadTtdFromDB();
            updateStats();
            updateTabCounts();
            renderCards();
        } else {
            showToast(result.message || 'Gagal memuat data', 'error');
            document.getElementById('cardsGrid').innerHTML = '';
        }
    } catch (err) {
        console.error(err);
        showToast('Gagal memuat data dari server', 'error');
        document.getElementById('cardsGrid').innerHTML = '';
    }
}

async function loadTtdFromDB() {
    try {
        const ids = allData.map(d => d.id);
        if (!ids.length) return;
        const res = await fetch('../database/api-belanja.php?action=get_ttd&ids=' + ids.join(','));
        const result = await res.json();
        if (result.success && result.data) {
            result.data.forEach(row => {
                const matchKey = Object.keys(ROLE_DB_MAP).find(k => ROLE_DB_MAP[k] === row.role_penanda);
                if (!matchKey) return;
                const pid = row.pengajuan_id;
                if (!signatures[pid]) signatures[pid] = {};
                if (!signatures[pid][matchKey] || !signatures[pid][matchKey].savedToDB) {
                    signatures[pid][matchKey] = {
                        dataUrl: row.signature_data,
                        timestamp: row.timestamp,
                        nama: row.nama || ROLES[matchKey].nama,
                        savedToDB: true,
                    };
                }
            });
            saveSigsLocal();
        }
    } catch (e) {
        console.warn('Gagal muat TTD dari DB:', e);
    }
}

// ── STATS & TAB COUNTS ──────────────────────────
function updateStats() {
    const pending = allData.filter(d => d.status === 'pending').length;
    const approved = allData.filter(d => d.status === 'approved').length;
    const rejected = allData.filter(d => d.status === 'rejected').length;
    const total = allData.reduce((s, d) => s + (parseFloat(d.total_belanja) || 0), 0);

    document.getElementById('countPending').textContent = pending;
    document.getElementById('countApproved').textContent = approved;
    document.getElementById('countRejected').textContent = rejected;
    document.getElementById('totalAmount').textContent = formatRupiah(total);
}

function updateTabCounts() {
    const counts = {
        all: allData.length,
        pending: allData.filter(d => d.status === 'pending').length,
        approved: allData.filter(d => d.status === 'approved').length,
        rejected: allData.filter(d => d.status === 'rejected').length,
    };
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const el = btn.querySelector('.tab-count');
        if (el && counts[btn.dataset.filter] !== undefined)
            el.textContent = counts[btn.dataset.filter];
    });
}

// ── SKELETON ────────────────────────────────────
function showSkeletons(n = 4) {
    const grid = document.getElementById('cardsGrid');
    document.getElementById('emptyState').style.display = 'none';
    grid.innerHTML = Array.from({ length: n }).map(() => `
        <div class="skeleton-card">
            <div class="skeleton skeleton-line w-60"></div>
            <div class="skeleton skeleton-line w-40" style="margin-bottom:16px"></div>
            <div class="skeleton skeleton-line w-80"></div>
            <div class="skeleton skeleton-line w-80"></div>
            <div class="skeleton skeleton-line w-60" style="margin-top:14px"></div>
        </div>
    `).join('');
}

// ─ RENDER CARDS ────────────────────────────────
function renderCards() {
    const grid = document.getElementById('cardsGrid');
    const empty = document.getElementById('emptyState');
    const filtered = currentFilter === 'all'
        ? allData
        : allData.filter(d => d.status === currentFilter);

    if (filtered.length === 0) {
        grid.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    grid.innerHTML = filtered.map(item => buildCard(item)).join('');
}

function buildCard(item) {
    const isPending = item.status === 'pending';
    const isApproved = item.status === 'approved';
    const items = item.detail_items || [];

    const itemsHtml = items.length > 0
        ? items.map((it, idx) => buildItemRow(it, idx)).join('')
        : `<p style="color:var(--text-muted);font-size:13px;padding:8px 0">Tidak ada barang</p>`;

    let saldoHtml = '';
    if (isApproved && (item.uang_masuk || item.sisa_uang)) {
        const buktiHtml = item.bukti_transfer
            ? `<a href="../uploads/bukti_transfer/${item.bukti_transfer}" target="_blank" class="saldo-chip bukti">
                <i class="ph ph-image"></i> Lihat Bukti Transfer
            </a>`
            : '';
    }

    const catatan = item.catatan_bendahara ? `
        <div class="catatan-box">
            <i class="ph ph-note"></i>
            <span><strong>Catatan:</strong> ${item.catatan_bendahara}</span>
        </div>` : '';

    const footerActions = isPending ? `
        <div class="card-actions">
            <button class="btn btn-danger btn-sm" onclick="openRejectModal(${item.id})">
                <i class="ph ph-x-circle"></i> Tolak
            </button>
            <button class="btn btn-success btn-sm" onclick="openApproveModal(${item.id}, ${parseFloat(item.total_belanja) || 0})">
                <i class="ph ph-check-circle"></i> Setujui
            </button>
        </div>` : '';

    const ttdHtml = buildTtdSection(item);
    const userName = item.created_by_name || ('User #' + item.created_by);

    return `
    <div class="pengajuan-card status-${item.status}" id="card-${item.id}">
        <div class="card-header">
            <div class="card-header-left">
                <div class="card-menu-name">${item.nama_menu}</div>
                <div class="card-meta">
                    <span class="card-meta-item"><i class="ph ph-calendar"></i> ${formatDate(item.tanggal)}</span>
                    <span class="card-meta-item"><i class="ph ph-bowl-food"></i> ${item.jumlah_porsi || '-'} porsi</span>
                    <span class="card-meta-item"><i class="ph ph-user"></i> ${userName}</span>
                    <span class="card-meta-item"><i class="ph ph-package"></i> ${items.length} item</span>
                </div>
            </div>
            <div class="card-header-right">
                <span class="status-badge status-${item.status}">${statusLabel(item.status)}</span>
            </div>
        </div>

        <div class="card-body">
            <div class="item-list">${itemsHtml}</div>
            ${saldoHtml}
            ${catatan}
        </div>

        ${ttdHtml}

        <div class="card-footer">
            <div class="card-total">
                <span class="card-total-label">Total Belanja</span>
                <span class="card-total-amount">${formatRupiah(item.total_belanja)}</span>
            </div>
            <div class="card-footer-right">
                ${footerActions}
                ${isApproved ? `
                <label class="btn ${item.bukti_transfer ? 'btn-ghost' : 'btn-outline-secondary'} btn-sm" title="${item.bukti_transfer ? 'Ganti Bukti Transfer' : 'Upload Bukti Transfer'}" style="cursor:pointer;margin:0;">
                    <i class="ph ph-${item.bukti_transfer ? 'arrows-clockwise' : 'upload-simple'}"></i> ${item.bukti_transfer ? 'Ganti BT' : 'Bukti TF'}
                    <input type="file" accept="image/*,.pdf" style="display:none"
                        onchange="uploadBuktiTransfer(event, ${item.id})">
                </label>
                ${item.bukti_transfer ? `
                <a href="../uploads/bukti_transfer/${item.bukti_transfer}" target="_blank"
                    class="btn btn-outline-primary btn-sm" title="Lihat bukti transfer">
                    <i class="ph ph-image"></i> Lihat Bukti Transfer
                </a>` : ''}` : ''}
                <button class="btn btn-outline-primary btn-sm" onclick="exportPDF(${item.id})">
                    <i class="ph ph-file-pdf"></i> Ekspor PDF
                </button>
            </div>
        </div>
    </div>`;
}

function buildItemRow(it, idx) {
    const subtotal = (parseFloat(it.harga) || 0) * (parseInt(it.qty) || 0);
    return `<div class="item-row">
        <div class="item-num">${idx + 1}</div>
        <div class="item-info">
            <div class="item-name">${it.nama_barang}</div>
            <div class="item-meta">${it.qty} ${it.satuan} × ${formatRupiah(it.harga)}</div>
        </div>
        <div class="item-subtotal">${formatRupiah(subtotal)}</div>
    </div>`;
}

function buildTtdSection(item) {
    const roleKeys = Object.keys(ROLES);
    const cols = roleKeys.map(role => {
        const sig = getSig(item.id, role);
        const info = ROLES[role];

        const imgOrEmpty = sig
            ? `<div class="ttd-img-wrap">
                <img src="${sig.dataUrl}" alt="TTD ${info.label}" class="ttd-img">
                <div class="ttd-timestamp">${formatDateTime(sig.timestamp)}</div>
            </div>`
            : `<div class="ttd-empty">
                <i class="ph ph-pen-nib"></i>
                <span>Belum ditandatangani</span>
            </div>`;

        const savedBadge = sig && sig.savedToDB
            ? `<div class="ttd-saved-badge"><i class="ph ph-cloud-check"></i> Tersimpan</div>`
            : (sig ? `<div class="ttd-saved-badge" style="background:var(--warning-bg);color:var(--warning);border-color:var(--warning-border)"><i class="ph ph-warning"></i> Lokal</div>` : '');

        const btnLabel = sig ? 'Ubah' : 'Tanda Tangan';
        const btnClass = sig ? 'btn-ghost' : 'btn-primary';

        return `
        <div class="ttd-col">
            <div class="ttd-role-label">${info.label}</div>
            <div class="ttd-box">${imgOrEmpty}</div>
            <div class="ttd-name-label">${info.nama}</div>
            ${savedBadge}
            <div class="ttd-actions">
                <button class="btn ${btnClass} btn-xs" onclick="openTtdModal(${item.id}, '${role}')">
                    <i class="ph ph-pen-nib"></i> ${btnLabel}
                </button>
                ${sig ? `<button class="btn btn-ghost btn-xs" onclick="hapusTtd(${item.id}, '${role}')">
                    <i class="ph ph-trash"></i>
                </button>` : ''}
            </div>
        </div>`;
    });

    return `
    <div class="ttd-section">
        <div class="ttd-section-label">
            <i class="ph ph-pen-nib"></i> Tanda Tangan
        </div>
        <div class="ttd-grid">${cols.join('')}</div>
    </div>`;
}

// ══════════════════════════════════════════════════
// MODAL: APPROVE (saldo masuk + bukti transfer)
// + FITUR: SISA UANG OTOMATIS DARI MENU SEBELUMNYA
// ═════════════════════════════════════════════════
function openApproveModal(id, totalBelanja) {
    approveTargetId = id;
    approveTargetTotal = totalBelanja;

    // 💰 HITUNG SISA UANG DARI MENU SEBELUMNYA
    sisaUangSebelumnya = hitungSisaUangMenuSebelumnya(id);

    // Reset form
    document.getElementById('inputUangMasuk').value = '';
    document.getElementById('inputBuktiTransfer').value = '';
    removeFile(null, true);

    // Tampilkan info sisa uang sebelumnya
    const sisaInfoEl = document.getElementById('approveSisaSebelumnya');
    const sisaValueEl = document.getElementById('approveSisaSebelumnyaValue');
    const sisaRow = document.getElementById('approveSisaSebelumnyaRow');

    if (sisaUangSebelumnya > 0) {
        // Cari nama menu sebelumnya untuk info
        const prevMenu = cariMenuSebelumnya(id);
        sisaInfoEl.textContent = prevMenu
            ? `Sisa dari "${prevMenu}" akan otomatis ditambahkan`
            : 'Sisa dari menu sebelumnya akan otomatis ditambahkan';
        sisaValueEl.textContent = formatRupiah(sisaUangSebelumnya);
        sisaRow.style.display = 'flex';
    } else {
        sisaRow.style.display = 'none';
    }

    // Reset info sisa
    document.getElementById('approveInfoSisaRow').style.display = 'none';
    document.getElementById('approveTotalMasukRow').style.display = 'none';

    // Tampilkan total belanja
    const item = allData.find(d => d.id == id);
    document.getElementById('approveModalSubtitle').textContent =
        item ? `Menu: ${item.nama_menu}` : 'Isi saldo masuk dan bukti transfer sebelum menyetujui';
    document.getElementById('approveInfoTotal').textContent = formatRupiah(totalBelanja);

    // Enable tombol
    const btn = document.getElementById('btnKonfirmasiSetujui');
    btn.classList.remove('btn-loading');
    btn.disabled = false;

    document.getElementById('approveModal').classList.add('active');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('active');
    approveTargetId = null;
    approveTargetTotal = 0;
    sisaUangSebelumnya = 0;
}

/**
 * Hitung sisa uang saat user mengetik di input uang masuk
 * Total Masuk = Input User + Sisa Menu Sebelumnya
 */
function hitungSisa() {
    const inputUser = parseRupiah(document.getElementById('inputUangMasuk').value);

    // 💰 TOTAL MASUK = INPUT USER + SISA SEBELUMNYA
    const totalMasuk = inputUser + sisaUangSebelumnya;
    const sisa = totalMasuk - approveTargetTotal;

    const sisaRow = document.getElementById('approveInfoSisaRow');
    const sisaEl = document.getElementById('approveInfoSisa');
    const totalMasukEl = document.getElementById('approveTotalMasuk');
    const totalMasukRow = document.getElementById('approveTotalMasukRow');

    if (inputUser > 0 || sisaUangSebelumnya > 0) {
        sisaRow.style.display = 'flex';
        sisaEl.textContent = formatRupiah(sisa);
        sisaEl.className = 'approve-info-value sisa-value' + (sisa < 0 ? ' kurang' : '');

        // Tampilkan breakdown total masuk jika ada sisa sebelumnya
        if (sisaUangSebelumnya > 0) {
            totalMasukRow.style.display = 'flex';
            totalMasukEl.innerHTML = `
                <span style="font-size:0.75rem;color:var(--text-muted);">${formatRupiah(inputUser)}</span>
                <span style="color:var(--text-muted);margin:0 4px;">+</span>
                <span style="font-size:0.75rem;color:var(--success);">${formatRupiah(sisaUangSebelumnya)}</span>
                <span style="color:var(--text-muted);margin:0 4px;">=</span>
                <strong style="color:var(--primary);">${formatRupiah(totalMasuk)}</strong>
            `;
        } else {
            totalMasukRow.style.display = 'none';
        }
    } else {
        sisaRow.style.display = 'none';
        totalMasukRow.style.display = 'none';
    }
}

// ── PREVIEW FILE UPLOAD ──────────────────────────
function previewFile(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast('File terlalu besar, maksimal 5 MB', 'error');
        input.value = '';
        return;
    }

    const uploadArea = document.getElementById('uploadArea');
    const placeholder = document.getElementById('uploadPlaceholder');
    const preview = document.getElementById('uploadPreview');
    const previewImg = document.getElementById('previewImg');
    const previewName = document.getElementById('previewName');

    previewName.textContent = file.name;
    uploadArea.classList.add('has-file');
    placeholder.style.display = 'none';
    preview.style.display = 'flex';

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { previewImg.src = e.target.result; previewImg.style.display = 'block'; };
        reader.readAsDataURL(file);
    } else {
        previewImg.src = '';
        previewImg.style.display = 'none';
    }
}

function removeFile(e, silent = false) {
    if (e) e.stopPropagation();
    const uploadArea = document.getElementById('uploadArea');
    const placeholder = document.getElementById('uploadPlaceholder');
    const preview = document.getElementById('uploadPreview');
    const previewImg = document.getElementById('previewImg');

    document.getElementById('inputBuktiTransfer').value = '';
    uploadArea.classList.remove('has-file');
    placeholder.style.display = 'flex';
    preview.style.display = 'none';
    previewImg.src = '';
}

// ─ SUBMIT APPROVE ───────────────────────────────
async function submitApprove() {
    const inputUser = parseRupiah(document.getElementById('inputUangMasuk').value);
    const fileInput = document.getElementById('inputBuktiTransfer');
    const file = fileInput.files[0];

    if (!inputUser && sisaUangSebelumnya <= 0) {
        showToast('Mohon isi saldo / uang masuk', 'error');
        return;
    }
    // 💰 TOTAL MASUK = INPUT USER + SISA SEBELUMNYA
    const uangMasukTotal = inputUser + sisaUangSebelumnya;

    const btn = document.getElementById('btnKonfirmasiSetujui');
    btn.classList.add('btn-loading');
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('id', approveTargetId);
        formData.append('uang_masuk', uangMasukTotal); // Kirim total yang sudah ditambah sisa
        if (file) formData.append('bukti_transfer', file); // opsional

        const res = await fetch('../database/api-belanja.php?action=approve', {
            method: 'POST',
            body: formData,
        });
        const result = await res.json();

        if (result.success) {
            const pesan = sisaUangSebelumnya > 0
                ? `Pengajuan disetujui! (+${formatRupiah(sisaUangSebelumnya)} sisa menu sebelumnya)`
                : 'Pengajuan berhasil disetujui!';
            showToast(pesan, 'success');
            closeApproveModal();
            fetchData();
        } else {
            showToast(result.message || 'Gagal menyetujui pengajuan', 'error');
            btn.classList.remove('btn-loading');
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        showToast('Terjadi kesalahan saat menyetujui', 'error');
        btn.classList.remove('btn-loading');
        btn.disabled = false;
    }
}

// ═════════════════════════════════════════════════
// MODAL: REJECT
// ═════════════════════════════════════════════════
function openRejectModal(id) {
    rejectTargetId = id;
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    rejectTargetId = null;
}

async function confirmReject() {
    const reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) { showToast('Mohon isi alasan penolakan', 'error'); return; }
    await updateStatus(rejectTargetId, 'rejected', reason);
}

async function updateStatus(id, status, catatan) {
    try {
        const res = await fetch('../database/api-belanja.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status, catatan_bendahara: catatan })
        });
        const result = await res.json();
        if (result.success) {
            showToast(status === 'rejected' ? 'Pengajuan ditolak' : 'Status diperbarui', 'success');
            closeRejectModal();
            fetchData();
        } else {
            showToast(result.message || 'Gagal update status', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Terjadi kesalahan', 'error');
    }
}

// ══════════════════════════════════════════════════
// TANDA TANGAN MODAL
// ═════════════════════════════════════════════════
function openTtdModal(pengajuanId, role) {
    ttdTargetId = pengajuanId;
    ttdTargetRole = role;
    document.getElementById('ttdModalSubtitle').innerHTML =
        `Tanda tangan sebagai <strong>${ROLES[role].label}</strong> — ${ROLES[role].nama}`;

    ttdCanvas = document.getElementById('ttdCanvas');
    ttdCtx = ttdCanvas.getContext('2d');
    clearCanvas();

    const existing = getSig(pengajuanId, role);
    if (existing) {
        const img = new Image();
        img.onload = () => ttdCtx.drawImage(img, 0, 0);
        img.src = existing.dataUrl;
    }

    const btn = document.getElementById('btnSimpanTtd');
    btn.classList.remove('btn-loading');
    btn.disabled = false;

    bindCanvasEvents();
    document.getElementById('ttdModal').classList.add('active');
}

function closeTtdModal() {
    document.getElementById('ttdModal').classList.remove('active');
    unbindCanvasEvents();
    ttdTargetId = null;
    ttdTargetRole = null;
}

function clearCanvas() {
    if (!ttdCtx) return;
    ttdCtx.clearRect(0, 0, ttdCanvas.width, ttdCanvas.height);
    ttdCtx.save();
    ttdCtx.strokeStyle = '#d5e3f5';
    ttdCtx.lineWidth = 1;
    ttdCtx.setLineDash([4, 4]);
    ttdCtx.beginPath();
    ttdCtx.moveTo(20, 160);
    ttdCtx.lineTo(ttdCanvas.width - 20, 160);
    ttdCtx.stroke();
    ttdCtx.restore();
}

function getPos(canvas, e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
}

function onStart(e) {
    e.preventDefault(); isDrawing = true;
    const pos = getPos(ttdCanvas, e);
    lastX = pos.x; lastY = pos.y;
    ttdCtx.beginPath(); ttdCtx.moveTo(lastX, lastY);
}

function onMove(e) {
    if (!isDrawing) return; e.preventDefault();
    const pos = getPos(ttdCanvas, e);
    ttdCtx.lineWidth = 2; ttdCtx.lineCap = 'round'; ttdCtx.lineJoin = 'round';
    ttdCtx.strokeStyle = '#1a2b4a';
    ttdCtx.lineTo(pos.x, pos.y); ttdCtx.stroke();
    ttdCtx.beginPath(); ttdCtx.moveTo(pos.x, pos.y);
    lastX = pos.x; lastY = pos.y;
}

function onEnd() { isDrawing = false; }

function bindCanvasEvents() {
    ttdCanvas.addEventListener('mousedown', onStart);
    ttdCanvas.addEventListener('mousemove', onMove);
    ttdCanvas.addEventListener('mouseup', onEnd);
    ttdCanvas.addEventListener('mouseleave', onEnd);
    ttdCanvas.addEventListener('touchstart', onStart, { passive: false });
    ttdCanvas.addEventListener('touchmove', onMove, { passive: false });
    ttdCanvas.addEventListener('touchend', onEnd);
}

function unbindCanvasEvents() {
    if (!ttdCanvas) return;
    ttdCanvas.removeEventListener('mousedown', onStart);
    ttdCanvas.removeEventListener('mousemove', onMove);
    ttdCanvas.removeEventListener('mouseup', onEnd);
    ttdCanvas.removeEventListener('mouseleave', onEnd);
    ttdCanvas.removeEventListener('touchstart', onStart);
    ttdCanvas.removeEventListener('touchmove', onMove);
    ttdCanvas.removeEventListener('touchend', onEnd);
}

async function saveTtd() {
    const imgData = ttdCtx.getImageData(0, 0, ttdCanvas.width, 155);
    const hasDrawing = Array.from(imgData.data).some((v, i) => i % 4 === 3 && v > 10);
    if (!hasDrawing) {
        showToast('Silakan gambar tanda tangan terlebih dahulu', 'error');
        return;
    }

    const dataUrl = ttdCanvas.toDataURL('image/png');
    const btn = document.getElementById('btnSimpanTtd');
    btn.classList.add('btn-loading');
    btn.disabled = true;

    try {
        const res = await fetch('../database/api-belanja.php?action=save_ttd', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pengajuan_id: ttdTargetId,
                role_penanda: ROLE_DB_MAP[ttdTargetRole],
                signature_data: dataUrl,
            })
        });
        const result = await res.json();

        if (result.success) {
            setSigLocal(ttdTargetId, ttdTargetRole, dataUrl, true);
            showToast(`Tanda tangan ${ROLES[ttdTargetRole].label} tersimpan ke database`, 'success');
            closeTtdModal();
            renderCards();
        } else {
            setSigLocal(ttdTargetId, ttdTargetRole, dataUrl, false);
            showToast('Tersimpan lokal — DB: ' + (result.message || 'gagal'), 'info');
            closeTtdModal();
            renderCards();
        }
    } catch (err) {
        console.error(err);
        setSigLocal(ttdTargetId, ttdTargetRole, dataUrl, false);
        showToast('Tersimpan lokal (server tidak terjangkau)', 'info');
        closeTtdModal();
        renderCards();
    }
}

function hapusTtd(pengajuanId, role) {
    removeSigLocal(pengajuanId, role);
    showToast(`Tanda tangan ${ROLES[role].label} dihapus dari tampilan`, 'info');
    renderCards();
}

// ══════════════════════════════════════════════════
// UPLOAD BUKTI TRANSFER (dari card langsung)
// ═════════════════════════════════════════════════
async function uploadBuktiTransfer(event, id) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast('File terlalu besar, maksimal 5 MB', 'error');
        return;
    }

    showToast('Mengupload bukti transfer...', 'info');

    const formData = new FormData();
    formData.append('id', id);
    formData.append('bukti_transfer', file);

    try {
        const res = await fetch('../database/api-belanja.php?action=upload_bukti', {
            method: 'POST',
            body: formData,
        });
        const result = await res.json();
        if (result.success) {
            showToast('Bukti transfer berhasil diupload!', 'success');
            fetchData();
        } else {
            showToast(result.message || 'Gagal upload bukti transfer', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Terjadi kesalahan saat upload', 'error');
    }
}

// ══════════════════════════════════════════════════
// EKSPOR PDF - BUKA FILE TERPISAH
// ═════════════════════════════════════════════════
function exportPDF(id) {
    const url = `cetak-laporan-sppg.php?id=${id}`;
    window.open(url, '_blank');
}

// ── TOAST ─────────────────────────────────────────
function showToast(msg, type = 'info') {
    let wrapper = document.querySelector('.toast-wrapper');
    if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.className = 'toast-wrapper';
        document.body.appendChild(wrapper);
    }
    const icons = { success: 'ph-check-circle', error: 'ph-x-circle', info: 'ph-info' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="ph ${icons[type] || icons.info}" style="font-size:16px;flex-shrink:0"></i>${msg}`;
    wrapper.appendChild(toast);
    setTimeout(() => {
        toast.style.cssText += 'opacity:0;transform:translateX(16px);transition:all .3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ── FILTER TABS ───────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        renderCards();
    });
});

// ── CLOSE MODAL OUTSIDE CLICK ────────────────────
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.classList.remove('active');
            if (modal.id === 'ttdModal') unbindCanvasEvents();
        }
    });
});

// ─ DRAG & DROP UPLOAD ────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const uploadArea = document.getElementById('uploadArea');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', e => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--primary)';
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '';
        });
        uploadArea.addEventListener('drop', e => {
            e.preventDefault();
            uploadArea.style.borderColor = '';
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                const input = document.getElementById('inputBuktiTransfer');
                input.files = dt.files;
                previewFile(input);
            }
        });
    }
});

// ─ INIT ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadSigs();
    fetchData();
});