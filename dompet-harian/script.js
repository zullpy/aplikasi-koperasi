/**
 * Dompet Belanja Harian SPPG
 * script.js — Grouped by Date > Menu, dengan tabel rincian barang & tombol approval
 */

// ─── State ───────────────────────────────────────────────────────────────────
let allData = [];
let masterBarang = [];
let searchQuery = '';
let editingId = null;
let barangRowCount = 0;

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatRupiah(num) {
    if (!num && num !== 0) return 'Rp 0';
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function formatDateFull(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function statusBadge(status) {
    const map = {
        pending: { label: 'Pending', cls: 'badge-pending' },
        approved: { label: 'Disetujui', cls: 'badge-approved' },
        rejected: { label: 'Ditolak', cls: 'badge-rejected' },
        completed: { label: 'Selesai', cls: 'badge-completed' },
    };
    const s = map[status] || { label: status, cls: 'badge-pending' };
    return `<span class="status-badge ${s.cls}">${s.label}</span>`;
}

// ─── Fetch Data dari Database ────────────────────────────────────────────────
async function fetchData() {
    try {
        const [resBelanja, resBarang] = await Promise.all([
            fetch('../database/api-belanja.php?action=list'),
            fetch('../database/api-belanja.php?action=list_barang')
        ]);
        const dataBelanja = await resBelanja.json();
        const dataBarang = await resBarang.json();

        if (dataBelanja.success) {
            allData = dataBelanja.data.map(item => ({
                ...item,
                id_pengajuan: item.id || item.id_pengajuan,
                total_harga: item.total_belanja || item.total_harga || 0,
                items: item.items || item.detail_items || []
            }));
        }

        if (dataBarang.success) masterBarang = dataBarang.data;

        renderTable();
    } catch (error) {
        console.error('Gagal fetch data:', error);
        showToast('Gagal memuat data dari server', 'error');
    }
}

// ─── Render Table (Grouped by Date → per Menu) ───────────────────────────────
function renderTable() {
    const container = document.getElementById('tableContainer');
    const emptyState = document.getElementById('emptyState');
    const searchInfo = document.getElementById('searchInfo');

    const q = searchQuery.trim().toLowerCase();
    const filtered = q
        ? allData.filter(item => item.nama_menu.toLowerCase().includes(q))
        : allData;

    emptyState.style.display = filtered.length === 0 ? 'flex' : 'none';
    searchInfo.textContent = q && filtered.length > 0
        ? `Menampilkan ${filtered.length} hasil untuk "${q}"`
        : '';

    if (filtered.length === 0) {
        container.innerHTML = '';
        return;
    }

    // Group by tanggal
    const grouped = {};
    filtered.forEach(item => {
        if (!grouped[item.tanggal]) grouped[item.tanggal] = [];
        grouped[item.tanggal].push(item);
    });

    let html = '';
    Object.keys(grouped)
        .sort((a, b) => new Date(b) - new Date(a))
        .forEach(tanggal => {
            const items = grouped[tanggal];
            const totalHari = items.reduce((sum, it) =>
                sum + (parseFloat(it.total_belanja || it.total_harga) || 0), 0);

            html += `
<div class="date-group">
  <div class="date-group-header">
    <div class="date-group-title">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
        <rect x="2" y="3" width="14" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
        <path d="M2 7h14M5 1.5v3M13 1.5v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      ${formatDateFull(tanggal)}
    </div>
    <div class="date-group-total">
      <span>Total Hari:</span>
      <strong>${formatRupiah(totalHari)}</strong>
      <span class="date-group-count">(${items.length} menu)</span>
    </div>
  </div>

  <div class="menu-group-list">
    ${items.map(item => {
                const detailItems = item.items || item.detail_items || [];
                const totalItem = parseFloat(item.total_belanja || item.total_harga) || 0;
                const status = item.status || 'pending';
                const isPending = status === 'pending';

                return `
    <div class="menu-card">
      <!-- Header menu: nama menu, porsi, status, tombol -->
      <div class="menu-card-header">
        <div class="menu-card-meta">
          <div class="menu-card-title">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M3 5h10l-1.2 7H4.2L3 5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
              <path d="M6 5V4a2 2 0 0 1 4 0v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <strong>${escHtml(item.nama_menu)}</strong>
          </div>
          <div class="menu-card-info">
            <span class="menu-porsi">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="5" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                <path d="M2 12c0-2.5 2.2-4 5-4s5 1.5 5 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
              ${escHtml(item.jumlah_porsi || '-')} porsi
            </span>
            ${statusBadge(status)}
          </div>
        </div>
        <div class="menu-card-right">
          <div class="menu-total">${formatRupiah(totalItem)}</div>
          <div class="menu-actions">
            <button class="btn-action btn-action-edit" onclick="openEditModal(${item.id})">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 9.5L8.5 3l1.5 1.5L3.5 11H2V9.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 4l1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              Edit
            </button>
            <button class="btn-action btn-action-delete" onclick="deleteItem(${item.id})">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Hapus
            </button>
            ${isPending ? `
            <button class="btn-action btn-action-approve" onclick="approveItem(${item.id})">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 6.5l3 3 6-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Setujui
            </button>
            <button class="btn-action btn-action-reject" onclick="openRejectModal(${item.id})">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M3 3l7 7M10 3l-7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
              Tolak
            </button>
            ` : ''}
          </div>
        </div>
      </div>

      <!-- Tabel rincian barang -->
      <div class="menu-card-body">
        ${detailItems.length > 0 ? `
        <table class="rincian-table">
          <thead>
            <tr>
              <th style="width:5%">No</th>
              <th style="width:35%">Nama Barang</th>
              <th style="width:10%">Qty</th>
              <th style="width:10%">Satuan</th>
              <th style="width:15%">Harga Satuan</th>
              <th style="width:10%">Subtotal</th>
              <th style="width:15%">Nota</th>
            </tr>
          </thead>
          <tbody>
            ${detailItems.map((b, i) => `
            <tr>
              <td>${i + 1}</td>
              <td>${escHtml(b.nama_barang)}</td>
              <td>${b.qty || b.quantity || 0}</td>
              <td>${escHtml(b.satuan || '')}</td>
              <td>${formatRupiah(b.harga || b.harga_satuan || 0)}</td>
              <td class="subtotal-cell">${formatRupiah((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0))}</td>
              <td class="nota-cell">
                ${(() => {
                        const urls = b.nota_urls
                            ? (Array.isArray(b.nota_urls) ? b.nota_urls : JSON.parse(b.nota_urls || '[]'))
                            : (b.nota_url ? [b.nota_url] : []);
                        const urlsJson = escHtml(JSON.stringify(urls));
                        const namaBarang = escHtml(b.nama_barang);
                        const safeUrls = btoa(unescape(encodeURIComponent(JSON.stringify(urls))));
                        const viewBtn = urls.length > 0
                            ? `<button class="btn-nota-icon btn-nota-view-icon" data-nota-urls="${safeUrls}" data-nota-nama="${escHtml(b.nama_barang)}" onclick="openNotaModalFromBtn(this)" title="Lihat ${urls.length} nota">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="2.5" width="12" height="9" rx="1.2" stroke="currentColor" stroke-width="1.4"/><circle cx="5" cy="6.5" r="1.2" fill="currentColor"/><path d="M1 11l3.5-3.5 2 2 2-2L13 11" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nota-count-badge">${urls.length}</span>
                      </button>`
                            : `<span class="nota-empty-label">—</span>`;
                        const uploadBtn = `<label class="btn-nota-icon btn-nota-upload-icon" title="Upload nota">
                      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 10V4M4 7l3-3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      <input type="file" accept="image/*,.pdf" multiple style="display:none" onchange="uploadNota(this, ${b.id}, ${item.id})"/>
                    </label>`;
                        return `<div class="nota-action-group">${viewBtn}${uploadBtn}</div>`;
                    })()}
              </td>
            </tr>
            `).join('')}
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" class="tfoot-label">Total Belanja</td>
              <td class="tfoot-total">${formatRupiah(totalItem)}</td>
            </tr>
          </tfoot>
        </table>
        ` : `<p class="no-barang">Belum ada rincian barang.</p>`}
      </div>
    </div>`;
            }).join('')}
  </div>
</div>`;
        });

    container.innerHTML = html;
}

// ─── Approval Functions ───────────────────────────────────────────────────────
async function approveItem(id) {
    if (!confirm('Setujui pengajuan ini?')) return;
    await updateStatus(id, 'approved', '');
}

function openRejectModal(id) {
    document.getElementById('rejectTargetId').value = id;
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    document.getElementById('rejectionReason').value = '';
}

async function confirmReject() {
    const id = document.getElementById('rejectTargetId').value;
    const reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) {
        alert('Mohon isi alasan penolakan.');
        return;
    }
    await updateStatus(id, 'rejected', reason);
    closeRejectModal();
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
            showToast(status === 'approved' ? 'Pengajuan disetujui!' : 'Pengajuan ditolak.', 'success');
            fetchData();
        } else {
            showToast(result.message || 'Gagal update status', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Terjadi kesalahan saat update status', 'error');
    }
}

// ─── Modal Functions ─────────────────────────────────────────────────────────
function openModal() {
    editingId = null;
    resetForm();
    setTodayDate();
    addBarangRow();
    document.getElementById('modalTitle').textContent = 'Tambah Belanja Harian';
    document.getElementById('modalOverlay').classList.add('active');
}

function openEditModal(id) {
    const item = allData.find(d => d.id == id || d.id_pengajuan == id);
    if (!item) return;

    editingId = id;
    resetForm();

    document.getElementById('inputTanggal').value = item.tanggal;
    document.getElementById('inputPorsi').value = item.jumlah_porsi || '';
    document.getElementById('inputNamaMenu').value = item.nama_menu;

    const detailItems = item.items || item.detail_items || [];
    if (detailItems.length > 0) {
        detailItems.forEach(b => {
            addBarangRow({
                id_barang: b.id_barang,
                nama_barang: b.nama_barang,
                harga: b.harga || b.harga_satuan,
                quantity: b.qty || b.quantity,
                satuan: b.satuan
            });
        });
    } else {
        addBarangRow();
    }

    updateSubtotal();
    document.getElementById('modalTitle').textContent = 'Edit Belanja Harian';
    document.getElementById('modalOverlay').classList.add('active');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
    editingId = null;
}

function resetForm() {
    ['inputTanggal', 'inputPorsi', 'inputNamaMenu'].forEach(id => {
        document.getElementById(id).value = '';
    });
    ['errorTanggal', 'errorNamaMenu', 'errorBarang'].forEach(id => {
        document.getElementById(id).textContent = '';
    });
    document.getElementById('barangList').innerHTML = '';
    document.getElementById('subtotalValue').textContent = 'Rp 0';
    barangRowCount = 0;
}

function setTodayDate() {
    document.getElementById('inputTanggal').value = new Date().toISOString().split('T')[0];
}

// ─── Searchable Barang Dropdown ───────────────────────────────────────────────
function createSearchableBarangDropdown(rowId, selectedId = null) {
    const selectedBarang = selectedId
        ? masterBarang.find(b => b.id_barang == selectedId)
        : null;

    return `
    <div class="searchable-dropdown" data-row="${rowId}">
      <input
        type="hidden"
        class="barang-id"
        data-row="${rowId}"
        value="${selectedBarang ? selectedBarang.id_barang : ''}"
      />
      <input
        type="text"
        class="form-input barang-search-input"
        data-row="${rowId}"
        placeholder="Cari nama barang..."
        value="${selectedBarang ? escHtml(selectedBarang.nama_barang) : ''}"
        autocomplete="off"
      />
      <div class="searchable-dropdown-list" data-row="${rowId}">
        ${masterBarang.map(b => `
          <div
            class="dropdown-item"
            data-id="${b.id_barang}"
            data-name="${escHtml(b.nama_barang)}"
            data-harga="${b.harga_beli}"
            data-satuan="${escHtml(b.satuan)}"
            data-row="${rowId}"
          >
            <div class="dropdown-item-name">${escHtml(b.nama_barang)}</div>
            <div class="dropdown-item-meta">${formatRupiah(b.harga_beli)} / ${escHtml(b.satuan)}</div>
          </div>
        `).join('')}
      </div>
    </div>`;
}

// ─── Add Barang Row ───────────────────────────────────────────────────────────
function addBarangRow(data = null) {
    barangRowCount++;
    const rowId = barangRowCount;
    const list = document.getElementById('barangList');

    const row = document.createElement('div');
    row.className = 'barang-row';
    row.dataset.rowId = rowId;

    row.innerHTML = `
    <div class="barang-row-header">
      <span class="barang-row-number">Barang #${rowId}</span>
      <button type="button" class="btn-remove-row" onclick="removeBarangRow(${rowId})" title="Hapus baris">
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M2 2L10 10M10 2L2 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <div class="form-row-3">
      <div class="form-group">
        <label class="form-label">Nama Barang</label>
        ${createSearchableBarangDropdown(rowId, data?.id_barang)}
      </div>
      <div class="form-group">
        <label class="form-label">Qty</label>
        <input
          type="number"
          class="form-input barang-quantity"
          data-row="${rowId}"
          value="${data?.quantity ?? ''}"
          placeholder="0"
          min="0"
          oninput="updateRowSubtotal(${rowId})"
        />
      </div>
      <div class="form-group">
        <label class="form-label">Satuan</label>
        <input
          type="text"
          class="form-input barang-satuan"
          data-row="${rowId}"
          value="${data?.satuan ? escHtml(data.satuan) : ''}"
          placeholder="kg / pcs / ltr"
        />
      </div>
    </div>
    <div class="form-group" style="margin-top:0.6rem">
      <label class="form-label">Harga Satuan</label>
      <div class="input-icon-wrapper">
        <span class="input-icon input-icon-text">Rp</span>
        <input
          type="number"
          class="form-input has-icon-text barang-harga"
          data-row="${rowId}"
          value="${data?.harga ?? ''}"
          placeholder="0"
          min="0"
          oninput="updateRowSubtotal(${rowId})"
        />
      </div>
    </div>
    <div class="barang-row-subtotal">
      <span>Subtotal:</span>
      <span class="row-subtotal-value" data-row="${rowId}">Rp 0</span>
    </div>
    <div class="nota-upload-row">
      <label class="form-label">
        Nota / Struk
        <span class="form-label-optional">(opsional, jpg/png/pdf, maks 5MB/file)</span>
      </label>
      <div class="nota-upload-wrapper">
        <label class="nota-file-label" data-row="${rowId}">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M7 10V4M4 7l3-3 3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          Pilih File Nota
          <input type="file" accept="image/*,.pdf" multiple class="barang-nota-file" data-row="${rowId}" style="display:none"/>
        </label>
      </div>
      <div class="nota-chips-preview" data-row="${rowId}"></div>
    </div>`;

    list.appendChild(row);

    // Attach dropdown events
    const searchInput = row.querySelector('.barang-search-input');
    const dropdownList = row.querySelector('.searchable-dropdown-list');

    searchInput.addEventListener('focus', () => {
        filterDropdown(rowId, searchInput.value);
        dropdownList.classList.add('active');
    });

    searchInput.addEventListener('input', () => {
        filterDropdown(rowId, searchInput.value);
        dropdownList.classList.add('active');
        row.querySelector(`.barang-id[data-row="${rowId}"]`).value = '';
    });

    document.addEventListener('click', (e) => {
        if (!row.querySelector(`.searchable-dropdown[data-row="${rowId}"]`).contains(e.target)) {
            dropdownList.classList.remove('active');
        }
    });

    dropdownList.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            row.querySelector(`.barang-id[data-row="${rowId}"]`).value = item.dataset.id;
            searchInput.value = item.dataset.name;
            row.querySelector(`.barang-harga[data-row="${rowId}"]`).value = item.dataset.harga;
            row.querySelector(`.barang-satuan[data-row="${rowId}"]`).value = item.dataset.satuan;
            dropdownList.classList.remove('active');
            updateRowSubtotal(rowId);
        });
    });

    if (data?.harga && data?.quantity) updateRowSubtotal(rowId);

    // Nota multi-file: chip preview dengan tombol hapus per file
    const notaInput = row.querySelector(`.barang-nota-file[data-row="${rowId}"]`);
    const chipsPreview = row.querySelector(`.nota-chips-preview[data-row="${rowId}"]`);
    // Kumpulan File objects yang dipilih (dikumulatif)
    let selectedFiles = [];

    function renderNotaChips() {
        if (!chipsPreview) return;
        chipsPreview.innerHTML = selectedFiles.map((f, i) => `
          <div class="nota-chip">
            <svg width="11" height="11" viewBox="0 0 11 11" fill="none"><path d="M1 2.5h9M4 2.5V1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M4.5 5v3M6.5 5v3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M2 2.5l.6 6.3a.5.5 0 0 0 .5.45h3.8a.5.5 0 0 0 .5-.45L8 2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="nota-chip-name">${escHtml(f.name)}</span>
            <button type="button" class="nota-chip-remove" onclick="removeNotaFile(${rowId}, ${i})" title="Hapus">
              <svg width="9" height="9" viewBox="0 0 9 9" fill="none"><path d="M1.5 1.5l6 6M7.5 1.5l-6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            </button>
          </div>`).join('');
        // Simpan ke dataset untuk diakses getBarangData
        row._notaFiles = selectedFiles;
    }

    // Expose fungsi hapus per chip ke global
    row._removeNotaFile = (idx) => {
        selectedFiles.splice(idx, 1);
        renderNotaChips();
    };

    if (notaInput) {
        notaInput.addEventListener('change', () => {
            const newFiles = Array.from(notaInput.files);
            const maxMb = 5;
            const valid = newFiles.filter(f => {
                if (f.size > maxMb * 1024 * 1024) {
                    showToast(`"${f.name}" melebihi ${maxMb}MB, dilewati`, 'error');
                    return false;
                }
                return true;
            });
            // Cegah duplikat nama
            valid.forEach(f => {
                if (!selectedFiles.find(sf => sf.name === f.name && sf.size === f.size))
                    selectedFiles.push(f);
            });
            notaInput.value = ''; // Reset supaya bisa pilih file sama lagi
            renderNotaChips();
        });
    }

    renumberRows();
}

function filterDropdown(rowId, query) {
    const list = document.querySelector(`.searchable-dropdown-list[data-row="${rowId}"]`);
    const items = list.querySelectorAll('.dropdown-item');
    const q = query.toLowerCase().trim();
    items.forEach(item => {
        item.style.display = (!q || item.dataset.name.toLowerCase().includes(q)) ? '' : 'none';
    });
}

function removeBarangRow(rowId) {
    const row = document.querySelector(`.barang-row[data-row-id="${rowId}"]`);
    if (row) { row.remove(); updateSubtotal(); renumberRows(); }
}

function renumberRows() {
    const rows = document.querySelectorAll('.barang-row');
    rows.forEach((row, index) => {
        const numSpan = row.querySelector('.barang-row-number');
        if (numSpan) numSpan.textContent = `Barang #${index + 1}`;
        const removeBtn = row.querySelector('.btn-remove-row');
        if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'flex' : 'none';
    });
}

function updateRowSubtotal(rowId) {
    const harga = parseFloat(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value) || 0;
    const quantity = parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0;
    document.querySelector(`.row-subtotal-value[data-row="${rowId}"]`).textContent = formatRupiah(harga * quantity);
    updateSubtotal();
}

function updateSubtotal() {
    let total = 0;
    document.querySelectorAll('.barang-row').forEach(row => {
        const rowId = row.dataset.rowId;
        const harga = parseFloat(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value) || 0;
        const quantity = parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0;
        total += harga * quantity;
    });
    document.getElementById('subtotalValue').textContent = formatRupiah(total);
}

// Fungsi global untuk hapus chip nota per baris
function removeNotaFile(rowId, idx) {
    const row = document.querySelector(`.barang-row[data-row-id="${rowId}"]`);
    if (row && row._removeNotaFile) row._removeNotaFile(idx);
}

function getBarangData() {
    const barangList = [];
    document.querySelectorAll('.barang-row').forEach(row => {
        const rowId = row.dataset.rowId;
        const idBarang = document.querySelector(`.barang-id[data-row="${rowId}"]`).value;
        const namaBarang = document.querySelector(`.barang-search-input[data-row="${rowId}"]`).value.trim();
        if (idBarang && namaBarang) {
            barangList.push({
                id_barang: idBarang,
                nama_barang: namaBarang,
                harga: parseFloat(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value) || 0,
                quantity: parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0,
                satuan: document.querySelector(`.barang-satuan[data-row="${rowId}"]`).value,
                nota_files: row._notaFiles || []
            });
        }
    });
    return barangList;
}

// ─── Validation ──────────────────────────────────────────────────────────────
function validate() {
    let valid = true;
    ['errorTanggal', 'errorNamaMenu', 'errorBarang'].forEach(id => {
        document.getElementById(id).textContent = '';
    });

    if (!document.getElementById('inputTanggal').value) {
        document.getElementById('errorTanggal').textContent = 'Tanggal wajib diisi.';
        valid = false;
    }
    if (!document.getElementById('inputNamaMenu').value.trim()) {
        document.getElementById('errorNamaMenu').textContent = 'Nama menu wajib diisi.';
        valid = false;
    }
    if (getBarangData().length === 0) {
        document.getElementById('errorBarang').textContent = 'Tambahkan minimal 1 barang.';
        valid = false;
    }
    return valid;
}

// ─── Save ─────────────────────────────────────────────────────────────────────
async function saveItem() {
    if (!validate()) return;

    const barangList = getBarangData();
    const totalBelanja = barangList.reduce((sum, b) => sum + (b.harga * b.quantity), 0);

    const payload = {
        id: editingId,
        tanggal: document.getElementById('inputTanggal').value,
        nama_menu: document.getElementById('inputNamaMenu').value.trim(),
        jumlah_porsi: parseInt(document.getElementById('inputPorsi').value) || 0,
        total_belanja: totalBelanja,
        status: 'pending',
        created_by: window.CURRENT_USER_ID || 1,
        items: barangList.map(b => ({
            id_barang: b.id_barang,
            nama_barang: b.nama_barang,
            qty: b.quantity,
            satuan: b.satuan,
            harga: b.harga,
        }))
    };

    // Cek apakah ada file nota yang perlu diupload
    const hasNota = barangList.some(b => b.nota_files && b.nota_files.length > 0);

    try {
        let res, result;

        if (hasNota) {
            // Kirim sebagai FormData agar file bisa disertakan
            const fd = new FormData();
            fd.append('data', JSON.stringify(payload));
            barangList.forEach((b, i) => {
                (b.nota_files || []).forEach(f => fd.append(`nota_${i}[]`, f));
            });
            res = await fetch('../database/api-belanja.php?action=save', {
                method: 'POST',
                body: fd
            });
        } else {
            res = await fetch('../database/api-belanja.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }

        result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Gagal menyimpan data');

        if (result.success) {
            showToast(result.message || 'Data berhasil disimpan', 'success');
            closeModal();
            fetchData();
        } else {
            showToast(result.message || 'Gagal menyimpan data', 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showToast('Error: ' + error.message, 'error');
    }
}

function openNotaModalFromBtn(btn) {
    const urls = JSON.parse(decodeURIComponent(escape(atob(btn.dataset.notaUrls))));
    const nama = btn.dataset.notaNama;
    openNotaModal(urls, nama);
}

// ─── Modal Preview Nota ──────────────────────────────────────────────────────
function openNotaModal(urls, namaBarang) {
    const overlay = document.getElementById('notaModalOverlay');
    const title = document.getElementById('notaModalTitle');
    const body = document.getElementById('notaModalBody');

    title.textContent = 'Nota — ' + namaBarang;

    if (!urls || urls.length === 0) {
        body.innerHTML = `<p class="nota-modal-empty">Tidak ada nota untuk barang ini.</p>`;
    } else {
        body.innerHTML = urls.map((url, i) => {
            const ext = url.split('.').pop().toLowerCase().split('?')[0];
            const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
            const isPdf = ext === 'pdf';
            const label = `Nota ${i + 1}`;
            if (isImg) {
                return `
                <div class="nota-preview-item">
                  <div class="nota-preview-label">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="2" width="12" height="10" rx="1.2" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="6" r="1.2" fill="currentColor"/><path d="M1 12l4-4 2.5 2.5 2-2L13 12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    ${label}
                  </div>
                  <img src="${escHtml(url)}" alt="${label}" class="nota-preview-img" onclick="window.open('${escHtml(url)}','_blank')"/>
                  <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Buka di Tab Baru
                  </a>
                </div>`;
            } else if (isPdf) {
                return `
                <div class="nota-preview-item">
                  <div class="nota-preview-label">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M3 1h5.5L12 4.5V13H2V1h1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M8 1v4h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    ${label} (PDF)
                  </div>
                  <div class="nota-preview-pdf-wrap">
                    <iframe src="${escHtml(url)}" class="nota-preview-pdf" title="${label}"></iframe>
                  </div>
                  <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Buka di Tab Baru
                  </a>
                </div>`;
            } else {
                return `
                <div class="nota-preview-item nota-preview-file">
                  <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M7 3h13L26 10v19H6V3h1z" stroke="#2563a8" stroke-width="1.6" stroke-linejoin="round"/><path d="M19 3v8h7" stroke="#2563a8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  <span>${label}</span>
                  <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Unduh / Buka
                  </a>
                </div>`;
            }
        }).join('');
    }

    overlay.classList.add('active');
}

function closeNotaModal() {
    document.getElementById('notaModalOverlay').classList.remove('active');
}

// ─── Upload Nota (multi-file) ─────────────────────────────────────────────────
async function uploadNota(input, itemId, pengajuanId) {
    const files = Array.from(input.files);
    if (!files.length) return;

    const maxMb = 5;
    const oversized = files.find(f => f.size > maxMb * 1024 * 1024);
    if (oversized) {
        showToast(`"${oversized.name}" terlalu besar (maks ${maxMb}MB per file)`, 'error');
        return;
    }

    // Tampilkan status loading di label
    const labelEl = input.closest('.btn-nota-upload');
    const origContent = labelEl ? labelEl.innerHTML : '';
    if (labelEl) labelEl.innerHTML = `<span class="nota-uploading">Mengunggah ${files.length} file…</span>`;

    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    formData.append('item_id', itemId);
    formData.append('pengajuan_id', pengajuanId);

    try {
        const res = await fetch('../database/api-belanja.php?action=upload_nota', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast(`${files.length} nota berhasil diunggah`, 'success');
            fetchData();
        } else {
            showToast(result.message || 'Gagal mengunggah nota', 'error');
            if (labelEl) labelEl.innerHTML = origContent;
        }
    } catch (err) {
        console.error(err);
        showToast('Terjadi kesalahan saat mengunggah nota', 'error');
        if (labelEl) labelEl.innerHTML = origContent;
    }
}


async function deleteItem(id) {
    if (!confirm('Hapus data belanja ini?')) return;
    try {
        const res = await fetch('../database/api-belanja.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.success) { showToast('Data dihapus', 'success'); fetchData(); }
        else showToast('Gagal menghapus data', 'error');
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Terjadi kesalahan saat menghapus', 'error');
    }
}

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg, type = '') {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast show ' + type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.className = 'toast'; }, 2800);
}

// ─── Search ───────────────────────────────────────────────────────────────────
function initSearch() {
    const input = document.getElementById('searchInput');
    const clearBtn = document.getElementById('searchClear');

    input.addEventListener('input', () => {
        searchQuery = input.value;
        clearBtn.classList.toggle('visible', searchQuery.length > 0);
        renderTable();
    });

    clearBtn.addEventListener('click', () => {
        input.value = '';
        searchQuery = '';
        clearBtn.classList.remove('visible');
        renderTable();
        input.focus();
    });
}

// ─── Boot ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchData();

    document.getElementById('btnOpenModal').addEventListener('click', openModal);
    document.getElementById('btnCloseModal').addEventListener('click', closeModal);
    document.getElementById('btnCancel').addEventListener('click', closeModal);
    document.getElementById('btnSave').addEventListener('click', saveItem);

    document.getElementById('btnAddBarangRow').addEventListener('click', () => {
        addBarangRow();
        renumberRows();
    });

    document.getElementById('modalOverlay').addEventListener('click', (e) => {
        if (e.target === document.getElementById('modalOverlay')) closeModal();
    });

    // Reject modal events
    document.getElementById('btnCancelReject').addEventListener('click', closeRejectModal);
    document.getElementById('btnConfirmReject').addEventListener('click', confirmReject);
    document.getElementById('rejectModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('rejectModal')) closeRejectModal();
    });

    // Nota preview modal
    document.getElementById('btnCloseNotaModal').addEventListener('click', closeNotaModal);
    document.getElementById('notaModalOverlay').addEventListener('click', (e) => {
        if (e.target === document.getElementById('notaModalOverlay')) closeNotaModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeModal(); closeRejectModal(); closeNotaModal(); }
    });

    initSearch();
});