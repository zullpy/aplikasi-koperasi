/* ─────────────────────────────────────────────
   approve.js — Approval Pengajuan Belanja
   Layout card: detail item sebagai rincian saja,
   approve/reject per pengajuan (per menu)
───────────────────────────────────────────── */

// ── STATE ──────────────────────────────────────
let allData = [];
let currentFilter = 'all';

// ID yang sedang di-reject (untuk modal)
let rejectTargetId = null;

// ── FORMAT HELPERS ──────────────────────────────
function formatRupiah(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
}

function statusLabel(s) {
    return { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', completed: 'Selesai' }[s] || s;
}

// ── FETCH ───────────────────────────────────────
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
        </div>`).join('');
}

// ── RENDER CARDS ────────────────────────────────
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
    const items = item.detail_items || [];

    // ── Item rows (rincian saja, tanpa aksi per item)
    const itemsHtml = items.length > 0
        ? items.map((it, idx) => buildItemRow(it, idx)).join('')
        : `<p style="color:var(--text-muted);font-size:13px;padding:8px 0">Tidak ada barang</p>`;

    // ── Catatan bendahara (jika ada)
    const catatan = item.catatan_bendahara ? `
        <div class="catatan-box">
            <i class="ph ph-note"></i>
            <span><strong>Catatan:</strong> ${item.catatan_bendahara}</span>
        </div>` : '';

    // ── Footer actions (hanya tampil jika pending)
    const footerActions = isPending ? `
        <div class="card-actions">
            <button class="btn btn-danger btn-sm" onclick="openRejectModal(${item.id})">
                <i class="ph ph-x-circle"></i> Tolak
            </button>
            <button class="btn btn-success btn-sm" onclick="approveCard(${item.id})">
                <i class="ph ph-check-circle"></i> Setujui
            </button>
        </div>` : '';

    const userName = item.created_by_name || ('User #' + item.created_by);

    return `
    <div class="pengajuan-card status-${item.status}" id="card-${item.id}">

        <div class="card-header">
            <div class="card-header-left">
                <div class="card-menu-name">${item.nama_menu}</div>
                <div class="card-meta">
                    <span class="card-meta-item">
                        <i class="ph ph-calendar"></i> ${formatDate(item.tanggal)}
                    </span>
                    <span class="card-meta-item">
                        <i class="ph ph-bowl-food"></i> ${item.jumlah_porsi || '-'} porsi
                    </span>
                    <span class="card-meta-item">
                        <i class="ph ph-user"></i> ${userName}
                    </span>
                    <span class="card-meta-item">
                        <i class="ph ph-package"></i> ${items.length} item
                    </span>
                </div>
            </div>
            <div class="card-header-right">
                <span class="status-badge status-${item.status}">
                    ${statusLabel(item.status)}
                </span>
            </div>
        </div>

        <div class="card-body">
            <div class="item-list">
                ${itemsHtml}
            </div>
            ${catatan}
        </div>

        <div class="card-footer">
            <div class="card-total">
                <span class="card-total-label">Total Belanja</span>
                <span class="card-total-amount">${formatRupiah(item.total_belanja)}</span>
            </div>
            ${footerActions}
        </div>

    </div>`;
}

// ── BUILD ITEM ROW (read-only, rincian saja) ────
function buildItemRow(it, idx) {
    const subtotal = (parseFloat(it.harga) || 0) * (parseInt(it.qty) || 0);
    return `
        <div class="item-row">
            <div class="item-num">${idx + 1}</div>
            <div class="item-info">
                <div class="item-name">${it.nama_barang}</div>
                <div class="item-meta">${it.qty} ${it.satuan} × ${formatRupiah(it.harga)}</div>
            </div>
            <div class="item-subtotal">${formatRupiah(subtotal)}</div>
        </div>`;
}

// ── APPROVE CARD ─────────────────────────────────
async function approveCard(id) {
    const res = await Swal.fire({
        title: 'Konfirmasi Persetujuan',
        html: 'Seluruh pengajuan ini akan <strong>disetujui</strong>. Lanjutkan?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#e2e8f0',
    });
    if (!res.isConfirmed) return;
    await updateStatus(id, 'approved', '');
}

// ── REJECT ───────────────────────────────────────
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

// ── UPDATE STATUS ────────────────────────────────
async function updateStatus(id, status, catatan) {
    try {
        const res = await fetch('../database/api-belanja.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status, catatan_bendahara: catatan })
        });
        const result = await res.json();
        if (result.success) {
            showToast(status === 'approved' ? 'Pengajuan disetujui' : 'Pengajuan ditolak', 'success');
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

// ── TOAST ────────────────────────────────────────
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

// ── FILTER TABS ──────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        renderCards();
    });
});

// ── CLOSE MODAL OUTSIDE CLICK ───────────────────
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.remove('active');
    });
});

// ── INIT ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', fetchData);