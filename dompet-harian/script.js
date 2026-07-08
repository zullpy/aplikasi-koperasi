/**
Dompet Belanja Harian SPPG
script.js — Grouped by Date > Menu
Role purchase: tombol "Sudah Dibeli" & "Upload Nota" PER ITEM (per baris barang)
Role lain: tombol Edit/Hapus di menu card
*/

// ─── State ───────────────────────────────────────────────────────────────────
let allData = [];
let masterBarang = [];
let searchQuery = '';
let editingId = null;
let barangRowCount = 0;
const USER_ROLE = window.CURRENT_USER_ROLE || 'admin';

// ─── Helpers ────────────────────────────────────────────────────────────────
function formatRupiah(num) {
  if (!num && num !== 0) return 'Rp 0';
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function parseRupiah(str) {
  return parseFloat(String(str).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
}

function onHargaInput(el, rowId) {
  const raw = String(el.value).replace(/\./g, '').replace(/[^\d]/g, '');
  const num = parseInt(raw) || 0;
  el.value = num ? num.toLocaleString('id-ID') : '';
  updateRowSubtotal(rowId);
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

  const isPurchase = USER_ROLE === 'purchase';
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

        // Tombol aksi di level MENU CARD (hanya untuk non-purchase)
        let menuActionsHtml = '';
        if (!isPurchase) {
          menuActionsHtml = `
                  <button class="btn-action btn-action-edit" onclick="openEditModal(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                      <path d="M2 9.5L8.5 3l1.5 1.5L3.5 11H2V9.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                      <path d="M7.5 4l1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                    Edit
                  </button>
                  <button class="btn-action btn-action-delete" onclick="deleteItem(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                      <path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                      <path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Hapus
                  </button>
                `;
        }

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
                        ${status === 'approved' || status === 'completed' ? (() => {
            const uangMasuk = parseFloat(item.uang_masuk) || 0;
            const buktiTF = item.bukti_transfer || null;
            let html = '';
            if (uangMasuk) {
              const selisih = uangMasuk - totalItem;
              let selisihHtml = '';
              if (selisih > 0) {
                selisihHtml = `<span class="menu-selisih menu-selisih-lebih">Kembalian <strong>${formatRupiah(selisih)}</strong></span>`;
              } else if (selisih < 0) {
                selisihHtml = `<span class="menu-selisih menu-selisih-kurang">Kurang <strong>${formatRupiah(Math.abs(selisih))}</strong></span>`;
              } else {
                selisihHtml = `<span class="menu-selisih menu-selisih-lunas">✓ Pas</span>`;
              }
              html += `<span class="menu-saldo-masuk">
                              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <path d="M6.5 1v11M3 4.5l3.5-3.5L10 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>
                              Saldo Masuk: <strong>${formatRupiah(uangMasuk)}</strong>
                            </span>${selisihHtml}`;
            }
            if (buktiTF && !isPurchase) {
              html += `<button class="btn-bukti-tf" onclick="openBuktiTF('${escHtml(buktiTF)}')" title="Lihat Bukti Transfer">
                              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <rect x="1" y="2" width="11" height="9" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M1 9.5l3-3 2 2 1.5-1.5L12 9.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="4" cy="5.5" r="1" fill="currentColor"/>
                              </svg>
                              Bukti TF
                            </button>`;
            }
            return html;
          })() : ''}
                      </div>
                    </div>
                    <div class="menu-card-right">
                      <div class="menu-total">${formatRupiah(totalItem)}</div>
                      ${menuActionsHtml ? `<div class="menu-actions">${menuActionsHtml}</div>` : ''}
                    </div>
                  </div>

                  <!-- Tabel rincian barang -->
                  <div class="menu-card-body">
                    ${detailItems.length > 0 ? `
                      <table class="rincian-table">
                        <thead>
                          <tr>
                            <th style="width:4%">No</th>
                            <th style="width:${isPurchase ? '25%' : '35%'}">Nama Barang</th>
                            <th style="width:8%">Qty</th>
                            <th style="width:8%">Satuan</th>
                            <th style="width:12%">Estimasi Harga </th>
                            <th style="width:10%">Subtotal</th>
                            ${isPurchase ? '<th style="width:15%">Status</th>' : ''}
                            <th style="width:${isPurchase ? '18%' : '20%'}">Nota</th>
                          </tr>
                        </thead>
                        <tbody>
                          ${detailItems.map((b, i) => {
            const itemId = b.id || b.id_detail;
            const statusBeli = b.status_beli || 'belum';
            const isBought = statusBeli === 'sudah';

            // Kolom status (khusus purchase)
            const statusCell = isPurchase ? `
                              <td class="item-status-cell">
                                ${isBought
                ? `<span class="btn-item-bought btn-item-bought-done">
                                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                        <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                      </svg>
                                      Sudah Dibeli
                                    </span>`
                : `<button class="btn-item-bought btn-item-bought-pending" onclick="markItemAsBought(${itemId})">
                                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                        <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                      </svg>
                                      Sudah Dibeli
                                    </button>`
              }
                              </td>
                            ` : '';

            return `
                              <tr>
                                <td>${i + 1}</td>
                                <td>${escHtml(b.nama_barang)}</td>
                                <td>${b.qty || b.quantity || 0}</td>
                                <td>${escHtml(b.satuan || '')}</td>
                                <td>${formatRupiah(b.harga || b.harga_satuan || 0)}</td>
                                <td class="subtotal-cell">${formatRupiah((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0))}</td>
                                ${statusCell}
                                <td class="nota-cell">
                                  ${(() => {
                const urls = b.nota_urls
                  ? (Array.isArray(b.nota_urls) ? b.nota_urls : JSON.parse(b.nota_urls || '[]'))
                  : (b.nota_url ? [b.nota_url] : []);
                const safeUrls = btoa(unescape(encodeURIComponent(JSON.stringify(urls))));
                const viewBtn = urls.length > 0
                  ? `<button class="btn-nota-icon btn-nota-view-icon" data-nota-urls="${safeUrls}" data-nota-nama="${escHtml(b.nama_barang)}" onclick="openNotaModalFromBtn(this)" title="Lihat ${urls.length} nota">
                                          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <rect x="1" y="2.5" width="12" height="9" rx="1.2" stroke="currentColor" stroke-width="1.4"/>
                                            <circle cx="5" cy="6.5" r="1.2" fill="currentColor"/>
                                            <path d="M1 11l3.5-3.5 2 2 2-2L13 11" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                          </svg>
                                          <span class="nota-count-badge">${urls.length}</span>
                                        </button>`
                  : `<span class="nota-empty-label">—</span>`;
                const uploadBtn = isPurchase
                  ? `<button class="btn-nota-icon btn-nota-upload-icon" title="Upload nota" onclick="openUploadNotaForItem(${itemId}, ${item.id})">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                          <path d="M7 10V4M4 7l3-3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                          <path d="M2 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                      </button>`
                  : '';
                return `<div class="nota-action-group">${viewBtn}${uploadBtn}</div>`;
              })()}
                                </td>
                              </tr>
                            `;
          }).join('')}
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="${isPurchase ? 5 : 5}" class="tfoot-label">Total Estimasi  </td>
                            <td class="tfoot-total" colspan="${isPurchase ? 2 : 1}">${formatRupiah(totalItem)}</td>
                          </tr>
                        </tfoot>
                      </table>
                    ` : `<p class="no-barang">Belum ada rincian barang.</p>`}
                  </div>
                </div>
              `;
      }).join('')}
          </div>
        </div>
      `;
    });

  container.innerHTML = html;
}

// ─── Mark Item as Bought (per item detail) ───────────────────────────────────
async function markItemAsBought(detailId) {
  if (!confirm('Tandai barang ini sudah dibeli?')) return;
  try {
    const res = await fetch('../database/api-belanja.php?action=update_item_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: detailId, status_beli: 'sudah' })
    });
    const result = await res.json();
    if (result.success) {
      showToast('Barang ditandai sudah dibeli', 'success');
      fetchData();
    } else {
      showToast(result.message || 'Gagal update status', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Terjadi kesalahan saat update status', 'error');
  }
}

// ─── Approval Functions ───────────────────────────────────────────────────────
async function approveItem(id) {
  // Guard: role purchase tidak boleh approve
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] approveItem: role purchase tidak memiliki akses'); return; }
  if (!confirm('Setujui pengajuan ini?')) return;
  await updateStatus(id, 'approved', '');
}

function openRejectModal(id) {
  // Guard: role purchase tidak boleh reject
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] openRejectModal: role purchase tidak memiliki akses'); return; }
  document.getElementById('rejectTargetId').value = id;
  document.getElementById('rejectionReason').value = '';
  document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
  const modal = document.getElementById('rejectModal');
  if (!modal) return;
  modal.classList.remove('active');
  const reason = document.getElementById('rejectionReason');
  if (reason) reason.value = '';
}

async function confirmReject() {
  // Guard: role purchase tidak boleh reject
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] confirmReject: role purchase tidak memiliki akses'); return; }
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
  // Guard: role purchase tidak boleh update status pengajuan
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] updateStatus: role purchase tidak memiliki akses'); return; }
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
  // Guard: role purchase tidak boleh tambah data
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] openModal: role purchase tidak memiliki akses'); return; }
  editingId = null;
  resetForm();
  setTodayDate();
  addBarangRow();
  document.getElementById('modalTitle').textContent = 'Tambah Belanja Harian';
  document.getElementById('modalOverlay').classList.add('active');
}

function openEditModal(id) {
  // Guard: role purchase tidak boleh edit data
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] openEditModal: role purchase tidak memiliki akses'); return; }
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
  const modal = document.getElementById('modalOverlay');
  if (!modal) return;
  modal.classList.remove('active');
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
    </div>
  `;
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
      <label class="form-label">Estimasi Harga</label>
      <div class="input-icon-wrapper">
        <span class="input-icon input-icon-text">Rp</span>
        <input
          type="text"
          class="form-input has-icon-text barang-harga"
          data-row="${rowId}"
          value="${data?.harga ? Number(data.harga).toLocaleString('id-ID') : ''}"
          placeholder="0"
          inputmode="numeric"
          oninput="onHargaInput(this, ${rowId})"
        />
      </div>
    </div>
    <div class="barang-row-subtotal">
      <span>Subtotal:</span>
      <span class="row-subtotal-value" data-row="${rowId}">Rp 0</span>
    </div>
  `;

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
      const hargaNum = parseFloat(item.dataset.harga) || 0;
      row.querySelector(`.barang-harga[data-row="${rowId}"]`).value = hargaNum ? hargaNum.toLocaleString('id-ID') : '';
      row.querySelector(`.barang-satuan[data-row="${rowId}"]`).value = item.dataset.satuan;
      dropdownList.classList.remove('active');
      updateRowSubtotal(rowId);
    });
  });

  if (data?.harga && data?.quantity) updateRowSubtotal(rowId);
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
  const harga = parseRupiah(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value);
  const quantity = parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0;
  document.querySelector(`.row-subtotal-value[data-row="${rowId}"]`).textContent = formatRupiah(harga * quantity);
  updateSubtotal();
}

function updateSubtotal() {
  let total = 0;
  document.querySelectorAll('.barang-row').forEach(row => {
    const rowId = row.dataset.rowId;
    const harga = parseRupiah(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value);
    const quantity = parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0;
    total += harga * quantity;
  });
  document.getElementById('subtotalValue').textContent = formatRupiah(total);
}

function getBarangData() {
  const barangList = [];
  document.querySelectorAll('.barang-row').forEach(row => {
    const rowId = row.dataset.rowId;
    const idBarang = document.querySelector(`.barang-id[data-row="${rowId}"]`).value;
    const namaBarang = document.querySelector(`.barang-search-input[data-row="${rowId}"]`).value.trim();
    if (namaBarang) {  // cukup nama tidak kosong, id_barang boleh kosong (barang baru/manual)
      barangList.push({
        id_barang: idBarang || null,
        nama_barang: namaBarang,
        harga: parseRupiah(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value),
        quantity: parseFloat(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value) || 0,
        satuan: document.querySelector(`.barang-satuan[data-row="${rowId}"]`).value,
      });
    }
  });
  return barangList;
}

// ─── Validation ─────────────────────────────────────────────────────────────
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
  // Guard: role purchase tidak boleh simpan/edit data belanja
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] saveItem: role purchase tidak memiliki akses'); return; }
  if (!validate()) return;
  const barangList = getBarangData();
  const totalBelanja = barangList.reduce((sum, b) => sum + (b.harga * b.quantity), 0);

  // ✅ AMBIL DATA LAMA SAAT EDIT (Status + Saldo Masuk)
  const currentItem = allData.find(d => d.id == editingId || d.id_pengajuan == editingId);
  const currentStatus = currentItem ? currentItem.status : 'pending';
  const currentUangMasuk = currentItem ? (parseFloat(currentItem.uang_masuk) || 0) : 0;

  const payload = {
    id: editingId,
    tanggal: document.getElementById('inputTanggal').value,
    nama_menu: document.getElementById('inputNamaMenu').value.trim(),
    jumlah_porsi: parseInt(document.getElementById('inputPorsi').value) || 0,
    total_belanja: totalBelanja,
    uang_masuk: currentUangMasuk, // ✅ KIRIM KEMBALI SALDO MASUK AGAR TIDAK HILANG
    status: currentStatus,        // ✅ STATUS TETAP TERJAGA
    created_by: window.CURRENT_USER_ID || 1,
    items: barangList.map(b => ({
      id_barang: b.id_barang,
      nama_barang: b.nama_barang,
      qty: b.quantity,
      satuan: b.satuan,
      harga: b.harga,
    }))
  };

  try {
    const res = await fetch('../database/api-belanja.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
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
        return `<div class="nota-preview-item">
          <div class="nota-preview-label">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <rect x="1" y="2" width="12" height="10" rx="1.2" stroke="currentColor" stroke-width="1.4"/>
              <circle cx="4.5" cy="6" r="1.2" fill="currentColor"/>
              <path d="M1 12l4-4 2.5 2.5 2-2L13 12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            ${label}
          </div>
          <img src="${escHtml(url)}" alt="${label}" class="nota-preview-img" onclick="window.open('${escHtml(url)}','_blank')"/>
          <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
              <path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Buka di Tab Baru
          </a>
        </div>`;
      } else if (isPdf) {
        return `<div class="nota-preview-item">
          <div class="nota-preview-label">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M3 1h5.5L12 4.5V13H2V1h1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
              <path d="M8 1v4h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            ${label} (PDF)
          </div>
          <div class="nota-preview-pdf-wrap">
            <iframe src="${escHtml(url)}" class="nota-preview-pdf" title="${label}"></iframe>
          </div>
          <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
              <path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Buka di Tab Baru
          </a>
        </div>`;
      } else {
        return `<div class="nota-preview-item nota-preview-file">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <path d="M7 3h13L26 10v19H6V3h1z" stroke="#2563a8" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M19 3v8h7" stroke="#2563a8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>${label}</span>
          <a href="${escHtml(url)}" target="_blank" class="nota-preview-open">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
              <path d="M2 11L11 2M11 2H6M11 2V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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

// ─── Bukti Transfer Preview ──────────────────────────────────────────────────
function openBuktiTF(url) {
  // Guard: role purchase tidak boleh melihat bukti transfer
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] openBuktiTF: role purchase tidak memiliki akses'); return; }
  const overlay = document.getElementById('notaModalOverlay');
  const title = document.getElementById('notaModalTitle');
  const body = document.getElementById('notaModalBody');
  title.textContent = 'Bukti Transfer';

  // Tambah prefix path jika hanya nama file
  if (url && !url.startsWith('http') && !url.startsWith('/') && !url.startsWith('../')) {
    url = '../uploads/bukti_transfer/' + url;
  }

  const isImg = /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(url);
  const isPdf = /\.pdf(\?|$)/i.test(url);

  if (isImg) {
    body.innerHTML = `<div class="nota-preview-wrap"><img src="${escHtml(url)}" class="nota-preview-img" alt="Bukti Transfer"/></div>`;
  } else if (isPdf) {
    body.innerHTML = `<div class="nota-preview-wrap"><iframe src="${escHtml(url)}" class="nota-preview-iframe" title="Bukti Transfer"></iframe></div>`;
  } else {
    body.innerHTML = `<div class="nota-preview-wrap nota-preview-link">
      <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
        <path d="M6 4h16l8 8v20H6V4z" stroke="#2563a8" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M22 4v9h10" stroke="#2563a8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <a href="${escHtml(url)}" target="_blank" rel="noopener" class="btn-save" style="margin-top:1rem;display:inline-flex;gap:0.4rem;align-items:center;">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <path d="M2 12L12 2M12 2H6M12 2V7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Buka File
      </a>
    </div>`;
  }

  overlay.classList.add('active');
}

// ─── Modal Upload Nota PER ITEM ──────────────────────────────────────────────
let uploadNotaFiles = []; // queue file yang dipilih
let uploadNotaCurrentDetail = null;
let uploadNotaCurrentPengajuan = null;

function openUploadNotaForItem(detailId, pengajuanId) {
  uploadNotaFiles = [];
  uploadNotaCurrentDetail = detailId;
  uploadNotaCurrentPengajuan = pengajuanId;

  // Cari nama barang dari data
  let namaBarang = 'Barang';
  for (const pengajuan of allData) {
    if (pengajuan.id == pengajuanId || pengajuan.id_pengajuan == pengajuanId) {
      const items = pengajuan.items || pengajuan.detail_items || [];
      const found = items.find(b => (b.id || b.id_detail) == detailId);
      if (found) { namaBarang = found.nama_barang; break; }
    }
  }

  const overlay = document.getElementById('uploadNotaModalOverlay');
  const title = document.getElementById('uploadNotaModalTitle');
  const body = document.getElementById('uploadNotaModalBody');
  title.textContent = 'Upload Nota';

  body.innerHTML = `
    <div class="upload-nota-info-bar">
      <div class="upload-nota-info-icon">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
          <path d="M3 5h12l-1.5 9h-9L3 5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          <path d="M6 5V4a3 3 0 0 1 6 0v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <div class="upload-nota-info-label">Nota untuk</div>
        <div class="upload-nota-info-name">${escHtml(namaBarang)}</div>
      </div>
    </div>

    <div class="upload-nota-source-row">
      <label class="upload-nota-source-btn upload-nota-source-camera">
        <input type="file" id="uploadNotaCameraInput" accept="image/*" capture="environment"/>
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
          <path d="M2 7.5C2 6.4 2.9 5.5 4 5.5h1.2l1.1-2h5.4l1.1 2H18c1.1 0 2 .9 2 2V17c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V7.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
          <circle cx="11" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
        </svg>
        <span>Foto Langsung</span>
      </label>
      <label class="upload-nota-source-btn upload-nota-source-file">
        <input type="file" id="uploadNotaFileInput" accept="image/*,.pdf" multiple/>
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
          <path d="M4 4h8l4 4v10H4V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
          <path d="M12 4v4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M8 14h6M8 11h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span>Pilih Galeri / File</span>
      </label>
    </div>
    <div class="upload-nota-hint">JPG, PNG, PDF · Maks 5 MB/file</div>

    <label class="upload-nota-dropzone" id="uploadNotaDropzone" style="display:none">
      <input type="file" id="uploadNotaFileInputDesktop" accept="image/*,.pdf" multiple/>
      <div class="upload-nota-dropzone-icon">
        <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
          <path d="M13 17V7M9 11l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M4 20h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="upload-nota-dropzone-title">Seret & lepas file di sini</div>
      <div class="upload-nota-dropzone-subtitle">atau klik untuk memilih file<br><span style="font-size:0.72rem;opacity:0.75">JPG, PNG, GIF, WebP, PDF · Maks 5 MB/file</span></div>
      <div class="upload-nota-dropzone-btn">
        <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
          <path d="M6.5 9V3M3.5 6l3-3 3 3" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M1 11h11" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
        Pilih File
      </div>
    </label>

    <div class="upload-nota-queue" id="uploadNotaQueue"></div>

    <div class="upload-nota-progress-wrap" id="uploadNotaProgressWrap">
      <div class="upload-nota-progress-label" id="uploadNotaProgressLabel">Mengunggah...</div>
      <div class="upload-nota-progress-bar">
        <div class="upload-nota-progress-fill" id="uploadNotaProgressFill"></div>
      </div>
    </div>

    <div class="upload-nota-status" id="uploadNotaStatusMsg"></div>
  `;

  // Wire up events
  const fileInput = document.getElementById('uploadNotaFileInput');
  const cameraInput = document.getElementById('uploadNotaCameraInput');
  const dropzone = document.getElementById('uploadNotaDropzone');
  const desktopInput = document.getElementById('uploadNotaFileInputDesktop');

  const isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);

  // Mobile: tampilkan tombol kamera+galeri; Desktop: tampilkan dropzone
  if (!isMobile) {
    document.querySelector('.upload-nota-source-row').style.display = 'none';
    document.querySelector('.upload-nota-hint').style.display = 'none';
    dropzone.style.display = '';
    desktopInput.addEventListener('change', () => handleUploadNotaFiles(desktopInput.files));
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', e => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
      handleUploadNotaFiles(e.dataTransfer.files);
    });
  }

  fileInput.addEventListener('change', () => handleUploadNotaFiles(fileInput.files));
  cameraInput.addEventListener('change', () => handleUploadNotaFiles(cameraInput.files));

  // Wire submit button
  document.getElementById('btnSubmitUploadNota').onclick = () => doUploadNota(detailId, pengajuanId);

  overlay.classList.add('active');
  syncUploadNotaQueue();
}

function handleUploadNotaFiles(fileList) {
  const maxMb = 5;
  const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

  Array.from(fileList).forEach(f => {
    if (!allowed.includes(f.type)) { showToast(`"${f.name}" — format tidak didukung`, 'error'); return; }
    if (f.size > maxMb * 1024 * 1024) { showToast(`"${f.name}" terlalu besar (maks ${maxMb}MB)`, 'error'); return; }
    // Hindari duplikat
    if (!uploadNotaFiles.find(x => x.name === f.name && x.size === f.size)) {
      uploadNotaFiles.push(f);
    }
  });

  // Reset input supaya bisa pilih file yang sama lagi
  document.getElementById('uploadNotaFileInput').value = '';
  syncUploadNotaQueue();
}

function syncUploadNotaQueue() {
  const queue = document.getElementById('uploadNotaQueue');
  const submitBtn = document.getElementById('btnSubmitUploadNota');

  if (!queue) return;

  if (uploadNotaFiles.length === 0) {
    queue.innerHTML = '';
    if (submitBtn) submitBtn.disabled = true;
    return;
  }

  if (submitBtn) submitBtn.disabled = false;

  queue.innerHTML = uploadNotaFiles.map((f, i) => {
    const isImg = f.type.startsWith('image/');
    const isPdf = f.type === 'application/pdf';
    const sizeTxt = f.size > 1024 * 1024
      ? (f.size / 1024 / 1024).toFixed(1) + ' MB'
      : Math.round(f.size / 1024) + ' KB';

    const thumbHtml = isImg
      ? `<img class="upload-nota-queue-thumb" id="thumb_${i}" alt="${escHtml(f.name)}"/>`
      : `<div class="upload-nota-queue-thumb-pdf">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M4 1h7.5L15 4.5V17H3V1h1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
            <path d="M11 1v4.5h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="5" y="13.5" font-size="4" fill="currentColor" font-family="sans-serif" font-weight="700">PDF</text>
          </svg>
        </div>`;

    return `
      <div class="upload-nota-queue-item" id="qitem_${i}">
        ${thumbHtml}
        <div class="upload-nota-queue-meta">
          <div class="upload-nota-queue-name">${escHtml(f.name)}</div>
          <div class="upload-nota-queue-size">${sizeTxt}</div>
        </div>
        <button class="upload-nota-queue-remove" onclick="removeUploadNotaFile(${i})" title="Hapus">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
            <path d="M2 2L10 10M10 2L2 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    `;
  }).join('');

  // Load thumbnail for images
  uploadNotaFiles.forEach((f, i) => {
    if (f.type.startsWith('image/')) {
      const img = document.getElementById('thumb_' + i);
      if (img) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; };
        reader.readAsDataURL(f);
      }
    }
  });
}

function removeUploadNotaFile(idx) {
  uploadNotaFiles.splice(idx, 1);
  syncUploadNotaQueue();
}

async function doUploadNota(detailId, pengajuanId) {
  if (!uploadNotaFiles.length) return;

  const submitBtn = document.getElementById('btnSubmitUploadNota');
  const progressWrap = document.getElementById('uploadNotaProgressWrap');
  const progressFill = document.getElementById('uploadNotaProgressFill');
  const progressLabel = document.getElementById('uploadNotaProgressLabel');
  const statusMsg = document.getElementById('uploadNotaStatusMsg');

  submitBtn.disabled = true;
  if (progressWrap) { progressWrap.classList.add('visible'); }
  if (progressFill) { progressFill.style.width = '30%'; }
  if (progressLabel) { progressLabel.textContent = `Mengunggah ${uploadNotaFiles.length} file...`; }
  if (statusMsg) { statusMsg.className = 'upload-nota-status'; }

  const formData = new FormData();
  uploadNotaFiles.forEach(f => formData.append('files[]', f));
  formData.append('item_id', detailId);
  formData.append('pengajuan_id', pengajuanId);

  try {
    if (progressFill) progressFill.style.width = '65%';
    const res = await fetch('../database/api-belanja.php?action=upload_nota', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (progressFill) progressFill.style.width = '100%';

    if (result.success) {
      if (statusMsg) {
        statusMsg.textContent = `✓ ${uploadNotaFiles.length} nota berhasil diunggah`;
        statusMsg.className = 'upload-nota-status visible success';
      }
      showToast(`${uploadNotaFiles.length} nota berhasil diunggah`, 'success');
      uploadNotaFiles = [];
      setTimeout(() => { fetchData(); closeUploadNotaModal(); }, 800);
    } else {
      if (statusMsg) {
        statusMsg.textContent = `✗ ${result.message || 'Gagal mengunggah nota'}`;
        statusMsg.className = 'upload-nota-status visible error';
      }
      if (progressWrap) progressWrap.classList.remove('visible');
      submitBtn.disabled = false;
      showToast(result.message || 'Gagal mengunggah nota', 'error');
    }
  } catch (err) {
    console.error(err);
    if (statusMsg) {
      statusMsg.textContent = '✗ Terjadi kesalahan saat mengunggah';
      statusMsg.className = 'upload-nota-status visible error';
    }
    if (progressWrap) progressWrap.classList.remove('visible');
    submitBtn.disabled = false;
    showToast('Terjadi kesalahan saat mengunggah nota', 'error');
  }
}

function closeUploadNotaModal() {
  uploadNotaFiles = [];
  document.getElementById('uploadNotaModalOverlay').classList.remove('active');
}

async function deleteItem(id) {
  // Guard: role purchase tidak boleh hapus data
  if (USER_ROLE === 'purchase') { console.warn('[RBAC] deleteItem: role purchase tidak memiliki akses'); return; }
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
// Helper: bind event listener hanya kalau elemennya ada di DOM.
// Penting untuk role 'purchase', karena beberapa tombol (btnOpenModal, dll)
// sengaja tidak dirender oleh index.php untuk role ini.
function bindIfExists(id, event, handler) {
  const el = document.getElementById(id);
  if (el) el.addEventListener(event, handler);
}

document.addEventListener('DOMContentLoaded', () => {
  fetchData();

  // Tombol-tombol ini hanya ada untuk role selain 'purchase'
  bindIfExists('btnOpenModal', 'click', openModal);
  bindIfExists('btnCloseModal', 'click', closeModal);
  bindIfExists('btnCancel', 'click', closeModal);
  bindIfExists('btnSave', 'click', saveItem);
  bindIfExists('btnAddBarangRow', 'click', () => {
    addBarangRow();
    renumberRows();
  });
  bindIfExists('modalOverlay', 'click', (e) => {
    if (e.target === document.getElementById('modalOverlay')) closeModal();
  });

  // Reject modal events (bukan untuk purchase)
  bindIfExists('btnCancelReject', 'click', closeRejectModal);
  bindIfExists('btnConfirmReject', 'click', confirmReject);
  bindIfExists('rejectModal', 'click', (e) => {
    if (e.target === document.getElementById('rejectModal')) closeRejectModal();
  });

  // Nota preview modal (dipakai semua role, termasuk purchase)
  bindIfExists('btnCloseNotaModal', 'click', closeNotaModal);
  bindIfExists('notaModalOverlay', 'click', (e) => {
    if (e.target === document.getElementById('notaModalOverlay')) closeNotaModal();
  });

  // Upload nota modal (dipakai purchase)
  bindIfExists('btnCloseUploadNotaModal', 'click', closeUploadNotaModal);
  bindIfExists('uploadNotaModalOverlay', 'click', (e) => {
    if (e.target === document.getElementById('uploadNotaModalOverlay')) closeUploadNotaModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeModal(); closeRejectModal(); closeNotaModal(); closeUploadNotaModal(); }
  });

  initSearch();
});