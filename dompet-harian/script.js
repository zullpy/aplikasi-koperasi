/**
Dompet Belanja Harian SPPG
script.js — Grouped by Date > Menu
Role purchase: tombol "Sudah Dibeli" & "Upload Nota" PER ITEM (per baris barang)
Role lain: tombol Edit/Hapus di menu card
Biaya Admin sekarang diinput PER BARANG (bukan per transaksi/header lagi).
*/

// ─── State ───────────────────────────────────────────────────────────────────
let allData = [];
let masterBarang = [];
let searchQuery = '';
let editingId = null;
let barangRowCount = 0;
const USER_ROLE = window.CURRENT_USER_ROLE || 'admin';
const IS_PURCHASE_ROLE = USER_ROLE === 'purchase' || USER_ROLE === 'purchase_stok';

// ─── Accordion State ─────────────────────────────────────────────────────────
const expandedMenuCards = new Set();

function toggleMenuAccordion(event, itemId) {
  if (event && event.target && event.target.closest('.menu-actions, .btn-action, .btn-bukti-tf, .menu-saldo-masuk, input, button, a')) {
    return;
  }
  const cardEl = document.getElementById(`menu-card-${itemId}`);
  const bodyEl = document.getElementById(`menu-accordion-body-${itemId}`);
  if (!cardEl || !bodyEl) return;

  const isExpanded = expandedMenuCards.has(itemId);
  if (isExpanded) {
    expandedMenuCards.delete(itemId);
    cardEl.classList.remove('is-expanded');
    bodyEl.style.display = 'none';
  } else {
    expandedMenuCards.add(itemId);
    cardEl.classList.add('is-expanded');
    bodyEl.style.display = 'block';
  }
}

// ─── Helpers ────────────────────────────────────────────────────────────────
function formatRupiah(num) {
  if (!num && num !== 0) return 'Rp 0';
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function parseRupiah(str) {
  return parseFloat(String(str).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
}

function formatQty(val) {
  const num = parseFloat(val);
  if (isNaN(num)) return '0';
  // Kalau bulat, tampilkan tanpa desimal. Kalau ada koma, tampilkan apa adanya (maks 2 desimal)
  return Number.isInteger(num)
    ? num.toLocaleString('id-ID')
    : num.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
}

// Qty boleh desimal, dan orang Indonesia sering ketik pakai koma (0,5)
// bukan titik (0.5) — terutama dari keyboard numerik HP. Normalisasi
// koma jadi titik sebelum di-parse supaya kedua format diterima.
function parseQty(val) {
  if (val === null || val === undefined) return 0;
  const normalized = String(val).trim().replace(',', '.');
  const num = parseFloat(normalized);
  return isNaN(num) ? 0 : num;
}

// Sanitasi input Qty secara real-time: hanya izinkan angka dan SATU
// pemisah desimal (titik ATAU koma), tanpa memaksa ganti simbol yang
// sedang diketik user (supaya kursor tidak loncat-loncat).
function onQtyInput(el, rowId) {
  let val = el.value.replace(/[^0-9.,]/g, '');
  const sepMatches = val.match(/[.,]/g) || [];
  if (sepMatches.length > 1) {
    const firstSep = sepMatches[0];
    const firstIdx = val.indexOf(firstSep);
    val = val.slice(0, firstIdx + 1) + val.slice(firstIdx + 1).replace(/[.,]/g, '');
  }
  el.value = val;
  updateRowSubtotal(rowId);
}

function onHargaInput(el, rowId) {
  const raw = String(el.value).replace(/\./g, '').replace(/[^\d]/g, '');
  const num = parseInt(raw) || 0;
  el.value = num ? num.toLocaleString('id-ID') : '';
  updateRowSubtotal(rowId);
}

function formatLiveCurrency(el) {
  const raw = String(el.value).replace(/\./g, '').replace(/[^\d]/g, '');
  const num = parseInt(raw) || 0;
  el.value = num ? num.toLocaleString('id-ID') : '';
}

// Format live Biaya Admin (Rp) PER BARANG. Sama seperti input harga
// barang, lalu langsung update Total Estimasi di footer modal.
function onItemAdminFeeInput(el, rowId) {
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
  if (status === 'pending' || status === 'approved') {
    return '';
  }
  const map = {
    rejected: { label: 'Ditolak', cls: 'badge-rejected' },
    completed: { label: 'Selesai', cls: 'badge-completed' },
  };
  const s = map[status];
  if (!s) return '';
  return `<span class="status-badge ${s.cls}">${s.label}</span>`;
}

// ─── Fetch Data dari Database ────────────────────────────────────────────────
async function fetchData() {
  try {
    const [resBelanja, resBarang] = await Promise.all([
      fetch('../database/api-belanja.php?action=list&_t=' + Date.now()),
      fetch('../database/api-belanja.php?action=list_barang&_t=' + Date.now())
    ]);
    const dataBelanja = await resBelanja.json();
    const dataBarang = await resBarang.json();
    if (dataBelanja.success) {
      allData = dataBelanja.data.map(item => ({
        ...item,
        id_pengajuan: item.id || item.id_pengajuan,
        total_harga: item.total_belanja || item.total_harga || 0,
        items: item.items || item.detail_items || [],
        ttd_map: {}
      }));

      const ids = allData.map(d => d.id).filter(Boolean).join(',');
      if (ids) {
        try {
          const resTtd = await fetch(`../database/api-belanja.php?action=get_ttd&ids=${ids}&_t=${Date.now()}`);
          const dataTtd = await resTtd.json();
          if (dataTtd.success && Array.isArray(dataTtd.data)) {
            const ttdMap = {};
            dataTtd.data.forEach(t => {
              if (!ttdMap[t.pengajuan_id]) ttdMap[t.pengajuan_id] = {};
              if (!ttdMap[t.pengajuan_id][t.role_penanda]) {
                ttdMap[t.pengajuan_id][t.role_penanda] = t;
              }
            });
            allData.forEach(item => {
              if (ttdMap[item.id]) item.ttd_map = ttdMap[item.id];
            });
          }
        } catch (e) {
          console.error('Gagal fetch TTD:', e);
        }
      }
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

  const isPurchase = IS_PURCHASE_ROLE;
  let html = '';

  Object.keys(grouped)
    .sort((a, b) => new Date(b) - new Date(a))
    .forEach(tanggal => {
      const items = grouped[tanggal];
      const totalHari = items.reduce((sum, it) => {
        const dItems = it.items || it.detail_items || [];
        const tItem = dItems.reduce((s, b) =>
          s + ((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0)) + (parseFloat(b.biaya_admin) || 0), 0);
        return sum + tItem;
      }, 0);

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
        const totalItem = detailItems.reduce((sum, b) =>
          sum + ((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0)) + (parseFloat(b.biaya_admin) || 0), 0);
        const totalBelumDibayar = detailItems.reduce((sum, b) =>
          (b.status_lunas !== 'lunas'
            ? sum + (((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0)) + (parseFloat(b.biaya_admin) || 0))
            : sum), 0);
        const isExpanded = expandedMenuCards.has(item.id);
        const status = item.status || 'pending';
        const uangMasuk = parseFloat(item.uang_masuk) || 0;

        // Tombol aksi di level MENU CARD
        const safeBuktiTF = btoa(unescape(encodeURIComponent(JSON.stringify(item.bukti_transfer || ''))));
        const saldoBtnHtml = (USER_ROLE !== 'purchase_stok' && USER_ROLE !== 'purchase') ? `
                  <button class="btn-action btn-action-saldo" data-bukti="${safeBuktiTF}" onclick="event.stopPropagation(); openInputSaldoModalFromBtn(this, ${item.id}, ${uangMasuk})" title="Input / Edit Uang Masuk Per Menu" style="background:#f0f9ff; color:#0284c7; border-color:#bae6fd;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="12" y1="1" x2="12" y2="23"/>
                      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    ${uangMasuk > 0 ? 'Edit Uang Masuk' : '+ Uang Masuk'}
                  </button>
        ` : '';

        if (USER_ROLE === 'admin') {
          menuActionsHtml = `
                  <button class="btn-action btn-action-edit" onclick="event.stopPropagation(); openEditModal(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                      <path d="M2 9.5L8.5 3l1.5 1.5L3.5 11H2V9.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                      <path d="M7.5 4l1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                    Edit
                  </button>
                  <button class="btn-action btn-action-delete" onclick="event.stopPropagation(); deleteItem(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                      <path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                      <path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Hapus
                  </button>
                  ${saldoBtnHtml}
                  <button class="btn-action btn-action-pdf" onclick="event.stopPropagation(); exportPDF(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    PDF
                  </button>
                `;
        } else if (USER_ROLE === 'bendahara' || USER_ROLE === 'ketua') {
          menuActionsHtml = `
                  ${saldoBtnHtml}
                  <button class="btn-action btn-action-pdf" onclick="event.stopPropagation(); exportPDF(${item.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    PDF
                  </button>
                `;
        } else {
          menuActionsHtml = '';
        }

        return `
                <div class="menu-card ${isExpanded ? 'is-expanded' : ''}" id="menu-card-${item.id}">
                  <!-- Header menu: nama menu, porsi, status, tombol -->
                  <div class="menu-card-header" onclick="toggleMenuAccordion(event, ${item.id})">
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
                        ${item.keterangan ? `
                        <span class="menu-keterangan" style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-info" style="vertical-align: middle;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                          </svg>
                          ${escHtml(item.keterangan)}
                        </span>
                        ` : ''}
                      </div>

                      <!-- Subinfo: Total Item & Total Uang Belum Dibayar -->
                      <div class="menu-card-subinfo">
                        <span class="menu-stat-badge menu-stat-items" title="Total jumlah item barang">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                          </svg>
                          Total Item: <strong>${detailItems.length} item</strong>
                        </span>
                        <span class="menu-stat-badge ${totalBelumDibayar > 0 ? 'menu-stat-unpaid' : 'menu-stat-paid'}" title="Total nominal item yang belum dibayar">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                          </svg>
                          Total Belum Dibayar: <strong>${formatRupiah(totalBelumDibayar)}</strong>
                        </span>
                      </div>

                      ${(() => {
            const uangMasuk = parseFloat(item.uang_masuk) || 0;
            const biayaAdmin = parseFloat(item.biaya_admin) || 0;

            // ✅ FIX: bukti_transfer sekarang bisa berisi LEBIH DARI 1 file
            // (disimpan sebagai JSON array, sama seperti nota_urls).
            const buktiTFRaw = item.bukti_transfer || null;
            let buktiTFUrls = [];
            if (buktiTFRaw) {
              if (Array.isArray(buktiTFRaw)) {
                buktiTFUrls = buktiTFRaw;
              } else {
                try {
                  const parsed = JSON.parse(buktiTFRaw);
                  buktiTFUrls = Array.isArray(parsed) ? parsed : [buktiTFRaw];
                } catch (e) {
                  buktiTFUrls = [buktiTFRaw];
                }
              }
            }
            buktiTFUrls = buktiTFUrls.filter(u => u);

            // Baris 2: Saldo Masuk + Biaya Admin
            let row2 = '';
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
              const safeBuktiTFBadge = btoa(unescape(encodeURIComponent(JSON.stringify(item.bukti_transfer || ''))));
              if (USER_ROLE !== 'purchase_stok' && USER_ROLE !== 'purchase') {
                row2 += `<span class="menu-saldo-masuk" style="cursor:pointer;" data-bukti="${safeBuktiTFBadge}" onclick="event.stopPropagation(); openInputSaldoModalFromBtn(this, ${item.id}, ${uangMasuk})" title="Klik untuk edit Uang Masuk">
                                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                  <path d="M6.5 1v11M3 4.5l3.5-3.5L10 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Saldo Masuk: <strong>${formatRupiah(uangMasuk)}</strong>
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:3px; opacity:0.75;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                              </span>${selisihHtml}`;
              } else {
                row2 += `<span class="menu-saldo-masuk">
                                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                  <path d="M6.5 1v11M3 4.5l3.5-3.5L10 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Saldo Masuk: <strong>${formatRupiah(uangMasuk)}</strong>
                              </span>${selisihHtml}`;
              }
            }
            if (biayaAdmin) {
              row2 += `<span class="menu-biaya-admin">
                              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <rect x="1.5" y="3" width="10" height="7.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M1.5 5.5h10" stroke="currentColor" stroke-width="1.3"/>
                              </svg>
                              Biaya Admin: <strong>${formatRupiah(biayaAdmin)}</strong>
                            </span>`;
            }

            // Baris 3: Bukti TF
            let row3 = '';
            if (buktiTFUrls.length > 0 && !isPurchase) {
              const safeBuktiTF = btoa(unescape(encodeURIComponent(JSON.stringify(buktiTFUrls))));
              row3 = `<button class="btn-bukti-tf" data-bukti-tf="${safeBuktiTF}" onclick="event.stopPropagation(); openBuktiTFFromBtn(this)" title="Lihat ${buktiTFUrls.length} Bukti Transfer">
                              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <rect x="1" y="2" width="11" height="9" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M1 9.5l3-3 2 2 1.5-1.5L12 9.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="4" cy="5.5" r="1" fill="currentColor"/>
                              </svg>
                              Bukti TF${buktiTFUrls.length > 1 ? ` (${buktiTFUrls.length})` : ''}
                            </button>`;
            }

            let result = '';
            if (row2) result += `<div class="menu-card-info menu-card-info-row2">${row2}</div>`;
            if (row3) result += `<div class="menu-card-info menu-card-info-row3">${row3}</div>`;
            return result;
          })()}
                    </div>
                    <div class="menu-card-right">
                      <div class="menu-total-wrapper">
                        <div class="menu-total">${formatRupiah(totalItem)}</div>
                        <div class="accordion-toggle-btn" title="${isExpanded ? 'Tutup Detail' : 'Buka Detail'}">
                          <svg class="chevron-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                          </svg>
                        </div>
                      </div>
                      ${menuActionsHtml ? `<div class="menu-actions" onclick="event.stopPropagation()">${menuActionsHtml}</div>` : ''}
                    </div>
                  </div>

                  <!-- ACCORDION BODY CONTAINER -->
                  <div class="menu-accordion-body" id="menu-accordion-body-${item.id}" style="${isExpanded ? 'display: block;' : 'display: none;'}">
                    <!-- Tabel rincian barang -->
                    <div class="menu-card-body">
                      <div class="menu-card-body-header">
                        <span>Rincian Barang</span>
                      </div>
                      ${detailItems.length > 0 ? `
                      <table class="rincian-table">
                        <thead>
                          <tr>
                            <th style="width:4%">No</th>
                            <th style="width:${isPurchase ? '22%' : (USER_ROLE === 'admin' ? '18%' : '30%')}">Nama Barang</th>
                            <th style="width:7%">Qty</th>
                            <th style="width:7%">Satuan</th>
                            <th style="width:11%">Estimasi Harga</th>
                            <th style="width:10%">Biaya Admin</th>
                            <th style="width:9%">Subtotal</th>
                            ${isPurchase ? '<th style="width:14%">Status</th>' : ''}
                            ${USER_ROLE === 'admin' ? '<th style="width:12%; text-align:center;">Status Pembayaran</th>' : ''}
                            <th style="width:${isPurchase ? '16%' : (USER_ROLE === 'admin' ? '11%' : '18%')}">Nota</th>
                            ${USER_ROLE === 'admin' ? '<th style="width:11%; text-align:center;">Aksi</th>' : ''}
                          </tr>
                        </thead>
                        <tbody>
                          ${detailItems.map((b, i) => {
            const itemId = b.id || b.id_detail;
            const statusBeli = b.status_beli || 'belum';
            const isBought = statusBeli === 'sudah';
            const statusLunas = b.status_lunas || 'belum';
            const isLunas = statusLunas === 'lunas';

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
                : (USER_ROLE === 'purchase_stok'
                  ? `<span class="btn-item-bought btn-item-bought-pending" style="cursor: default; display: inline-flex; align-items: center; gap: 0.3rem;">
                                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                        <circle cx="6" cy="6" r="4.5" />
                                        <line x1="6" y1="3.5" x2="6" y2="6.5" />
                                        <circle cx="6" cy="8.5" r="0.5" fill="currentColor" />
                                      </svg>
                                      Belum Dibeli
                                    </span>`
                  : `<button class="btn-item-bought btn-item-bought-pending" onclick="markItemAsBought(${itemId})">
                                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                        <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                      </svg>
                                      Sudah Dibeli
                                    </button>`)
                }
                              </td>
                            ` : '';

            const adminLunasCell = USER_ROLE === 'admin' ? `
                              <td style="text-align:center;">
                                ${isLunas
                  ? `<button class="btn-item-lunas btn-item-lunas-done" onclick="confirmLunas(${itemId}, 'belum')" title="Klik untuk ubah ke belum dibayar">
                                        Sudah Dibayar
                                      </button>`
                  : `<button class="btn-item-lunas btn-item-lunas-pending" onclick="confirmLunas(${itemId}, 'lunas')" title="Klik untuk konfirmasi pembayaran">
                                        Belum Dibayar
                                      </button>`
                }
                              </td>
                            ` : '';

            // Kolom Aksi (khusus admin)
            const adminItemActionCell = USER_ROLE === 'admin' ? `
                              <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px; justify-content:center;">
                                  <button class="btn-item-action btn-item-edit" onclick="openEditItemModal(${itemId}, ${item.id})" title="Edit Barang">
                                    <svg width="14" height="14" viewBox="0 0 13 13" fill="none">
                                      <path d="M2 9.5L8.5 3l1.5 1.5L3.5 11H2V9.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                      <path d="M7.5 4l1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                    </svg>
                                  </button>
                                  <button class="btn-item-action btn-item-delete" onclick="deleteSingleItem(${itemId}, ${item.id})" title="Hapus Barang">
                                    <svg width="14" height="14" viewBox="0 0 13 13" fill="none">
                                      <path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                      <path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                  </button>
                                </div>
                              </td>
                            ` : '';

            return `
                              <tr>
                                <td>${i + 1}</td>
                                <td>${escHtml(b.nama_barang)}</td>
                                <td>${formatQty(b.qty || b.quantity || 0)}</td>
                                <td>${escHtml(b.satuan || '')}</td>
                                <td>${formatRupiah(b.harga || b.harga_satuan || 0)}</td>
                                <td>${formatRupiah(b.biaya_admin || 0)}</td>
                                <td class="subtotal-cell">${formatRupiah(((b.qty || b.quantity || 0) * (b.harga || b.harga_satuan || 0)) + (parseFloat(b.biaya_admin) || 0))}</td>
                                ${statusCell}
                                ${adminLunasCell}
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
                const uploadBtn = (((isPurchase && USER_ROLE !== 'purchase_stok') || USER_ROLE === 'admin'))
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
                                ${adminItemActionCell}
                              </tr>
                            `;
          }).join('')}
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="${isPurchase ? 6 : 6}" class="tfoot-label">Total Estimasi</td>
                            <td class="tfoot-total" colspan="${isPurchase ? 2 : (USER_ROLE === 'admin' ? 3 : 1)}">${formatRupiah(totalItem)}</td>
                          </tr>
                        </tfoot>
                      </table>
                    ` : `<p class="no-barang">Belum ada rincian barang.</p>`}
                  </div>
                  ${USER_ROLE === 'admin' ? `
                    <div class="card-add-barang-wrap">
                      <button class="btn-card-add-barang" onclick="openAddItemModal(${item.id})">
                        <span class="btn-card-add-barang-icon">
                          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                          </svg>
                        </span>
                        Tambah Barang
                      </button>
                    </div>
                  ` : ''}
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
  const result = await Swal.fire({
    title: 'Tandai Sudah Dibeli?',
    text: 'Barang ini akan ditandai sebagai sudah dibeli.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<svg width="14" height="14" viewBox="0 0 12 12" fill="none" style="vertical-align:middle;margin-right:4px"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Ya, Sudah Dibeli',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!result.isConfirmed) return;
  try {
    const res = await fetch('../database/api-belanja.php?action=update_item_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: detailId, status_beli: 'sudah' })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Barang ditandai sudah dibeli', 'success');
      fetchData();
    } else {
      showToast(data.message || 'Gagal update status', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Terjadi kesalahan saat update status', 'error');
  }
}

// ─── Confirm Lunas per item (KHUSUS ADMIN) ────────────────────────────────────
async function confirmLunas(detailId, statusLunas) {
  const isLunas = statusLunas === 'lunas';
  const swalResult = await Swal.fire({
    title: isLunas ? 'Konfirmasi Sudah Dibayar?' : 'Ubah ke Belum Dibayar?',
    text: isLunas
      ? 'Item ini akan ditandai sebagai sudah dibayar (lunas).'
      : 'Status pembayaran item ini akan diubah kembali ke belum dibayar.',
    icon: isLunas ? 'question' : 'warning',
    showCancelButton: true,
    confirmButtonColor: isLunas ? '#16a34a' : '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: isLunas ? '✓ Ya, Sudah Dibayar' : '✗ Ya, Belum Dibayar',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!swalResult.isConfirmed) return;
  try {
    const res = await fetch('../database/api-belanja.php?action=confirm_lunas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: detailId, status_lunas: statusLunas })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message || 'Status pembayaran diperbarui', 'success');
      fetchData();
    } else {
      showToast(result.message || 'Gagal update status pembayaran', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Terjadi kesalahan saat update status pembayaran', 'error');
  }
}

// ─── Approval Functions ───────────────────────────────────────────────────────
async function approveItem(id) {
  // Guard: role purchase tidak boleh approve
  if (IS_PURCHASE_ROLE) { console.warn('[RBAC] approveItem: role purchase tidak memiliki akses'); return; }
  const result = await Swal.fire({
    title: 'Setujui Pengajuan?',
    text: 'Pengajuan belanja ini akan disetujui.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '✓ Ya, Setujui',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!result.isConfirmed) return;
  await updateStatus(id, 'approved', '');
}

function openRejectModal(id) {
  // Guard: role purchase tidak boleh reject
  if (IS_PURCHASE_ROLE) { console.warn('[RBAC] openRejectModal: role purchase tidak memiliki akses'); return; }
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
  if (IS_PURCHASE_ROLE) { console.warn('[RBAC] confirmReject: role purchase tidak memiliki akses'); return; }
  const id = document.getElementById('rejectTargetId').value;
  const reason = document.getElementById('rejectionReason').value.trim();
  if (!reason) {
    Swal.fire({
      title: 'Alasan Kosong',
      text: 'Mohon isi alasan penolakan terlebih dahulu.',
      icon: 'warning',
      confirmButtonColor: '#f59e0b',
      confirmButtonText: 'OK',
      customClass: { popup: 'swal-kopdes' }
    });
    return;
  }
  await updateStatus(id, 'rejected', reason);
  closeRejectModal();
}

async function updateStatus(id, status, catatan) {
  // Guard: role purchase tidak boleh update status pengajuan
  if (IS_PURCHASE_ROLE) { console.warn('[RBAC] updateStatus: role purchase tidak memiliki akses'); return; }
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
  // Guard: hanya admin yang boleh tambah data
  if (USER_ROLE !== 'admin') { console.warn('[RBAC] openModal: selain admin tidak memiliki akses'); return; }
  editingId = null;
  resetForm();
  setTodayDate();
  addBarangRow();
  const sec = document.getElementById('daftarBarangSection');
  if (sec) sec.style.display = 'block';
  document.getElementById('modalTitle').textContent = 'Tambah Belanja Harian';
  document.getElementById('modalOverlay').classList.add('active');
}

function openEditModal(id) {
  // Guard: hanya admin yang boleh edit data
  if (USER_ROLE !== 'admin') { console.warn('[RBAC] openEditModal: selain admin tidak memiliki akses'); return; }
  const item = allData.find(d => d.id == id || d.id_pengajuan == id);
  if (!item) return;
  editingId = id;
  resetForm();
  document.getElementById('inputTanggal').value = item.tanggal;
  document.getElementById('inputPorsi').value = item.jumlah_porsi || '';
  document.getElementById('inputNamaMenu').value = item.nama_menu;
  const elKet = document.getElementById('inputKeterangan');
  if (elKet) elKet.value = item.keterangan || '';
  const elUangMasuk = document.getElementById('inputUangMasuk');
  if (elUangMasuk) elUangMasuk.value = item.uang_masuk ? Math.round(parseFloat(item.uang_masuk)).toLocaleString('id-ID') : '';

  const sec = document.getElementById('daftarBarangSection');
  if (sec) sec.style.display = 'none';

  document.getElementById('modalTitle').textContent = 'Edit Informasi Transaksi';
  document.getElementById('modalOverlay').classList.add('active');
}

function closeModal() {
  const modal = document.getElementById('modalOverlay');
  if (!modal) return;
  modal.classList.remove('active');
  editingId = null;
}

function resetForm() {
  ['inputTanggal', 'inputPorsi', 'inputNamaMenu', 'inputKeterangan', 'inputUangMasuk'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
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
// FIX: menerima `selectedName` sebagai fallback. Sebelumnya, kalau id_barang
// tidak ketemu di masterBarang (misalnya barang custom/manual yang diketik
// tangan, atau sudah dihapus dari estimasi_harga), nama barang jadi KOSONG
// saat modal Edit dibuka — akibatnya baris itu gagal lolos validasi dan
// hilang diam-diam saat disimpan (item "tidak kesimpen").
function createSearchableBarangDropdown(rowId, selectedId = null, selectedName = null) {
  const selectedBarang = selectedId
    ? masterBarang.find(b => b.id_barang == selectedId)
    : null;

  // Kalau ketemu di masterBarang, pakai data master (nama & harga terbaru).
  // Kalau tidak ketemu, tetap tampilkan nama asli yang tersimpan di data.
  const displayName = selectedBarang ? selectedBarang.nama_barang : (selectedName || '');
  const displayId = selectedBarang ? selectedBarang.id_barang : (selectedId || '');

  return `
    <div class="searchable-dropdown" data-row="${rowId}">
      <input
        type="hidden"
        class="barang-id"
        data-row="${rowId}"
        value="${displayId}"
      />
      <input
        type="text"
        class="form-input barang-search-input"
        data-row="${rowId}"
        placeholder="Cari nama barang..."
        value="${displayName ? escHtml(displayName) : ''}"
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
  // Simpan id_detail (id baris di tabel detail_item_belanja) kalau ini
  // baris hasil edit data lama. Dipakai saat SAVE supaya backend bisa
  // UPDATE baris yang sama alih-alih hapus+insert baris baru (yang akan
  // memutus link ke nota yang sudah diupload sebelumnya).
  row.dataset.idDetail = data?.id_detail ?? '';

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
        ${createSearchableBarangDropdown(rowId, data?.id_barang, data?.nama_barang)}
      </div>
      <div class="form-group">
        <label class="form-label">Qty</label>
        <input
          type="text"
          inputmode="decimal"
          class="form-input barang-quantity"
          data-row="${rowId}"
          value="${data?.quantity ?? ''}"
          placeholder="0"
          oninput="onQtyInput(this, ${rowId})"
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
    <div class="form-row-2" style="display:flex; gap:0.6rem; margin-top:0.6rem;">
      <div class="form-group" style="flex:1;">
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
      <div class="form-group" style="flex:1;">
        <label class="form-label">Biaya Admin (Opsional)</label>
        <div class="input-icon-wrapper">
          <span class="input-icon input-icon-text">Rp</span>
          <input
            type="text"
            class="form-input has-icon-text barang-biaya-admin"
            data-row="${rowId}"
            value="${data?.biaya_admin ? Number(data.biaya_admin).toLocaleString('id-ID') : ''}"
            placeholder="0"
            inputmode="numeric"
            oninput="onItemAdminFeeInput(this, ${rowId})"
          />
        </div>
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
  const quantity = parseQty(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value);
  const biayaAdminEl = document.querySelector(`.barang-biaya-admin[data-row="${rowId}"]`);
  const biayaAdmin = biayaAdminEl ? parseRupiah(biayaAdminEl.value) : 0;
  document.querySelector(`.row-subtotal-value[data-row="${rowId}"]`).textContent = formatRupiah((harga * quantity) + biayaAdmin);
  updateSubtotal();
}

function updateSubtotal() {
  let total = 0;
  document.querySelectorAll('.barang-row').forEach(row => {
    const rowId = row.dataset.rowId;
    const harga = parseRupiah(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value);
    const quantity = parseQty(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value);
    const biayaAdminEl = document.querySelector(`.barang-biaya-admin[data-row="${rowId}"]`);
    const biayaAdmin = biayaAdminEl ? parseRupiah(biayaAdminEl.value) : 0;
    total += (harga * quantity) + biayaAdmin;
  });
  // Catatan: Biaya Admin (per barang) ditambahkan ke Total Estimasi.
  document.getElementById('subtotalValue').textContent = formatRupiah(total);
}

function getBarangData() {
  const barangList = [];
  document.querySelectorAll('.barang-row').forEach(row => {
    const rowId = row.dataset.rowId;
    const idBarang = document.querySelector(`.barang-id[data-row="${rowId}"]`).value;
    const namaBarang = document.querySelector(`.barang-search-input[data-row="${rowId}"]`).value.trim();
    if (namaBarang) {  // cukup nama tidak kosong, id_barang boleh kosong (barang baru/manual)
      const biayaAdminEl = document.querySelector(`.barang-biaya-admin[data-row="${rowId}"]`);
      barangList.push({
        // id_detail: id baris lama di detail_item_belanja (kalau ada).
        // Dikirim ke backend supaya baris ini di-UPDATE, bukan
        // dihapus+dibuat ulang — supaya nota yang sudah diupload untuk
        // baris ini tidak kehilangan relasinya.
        id_detail: row.dataset.idDetail || null,
        id_barang: idBarang || null,
        nama_barang: namaBarang,
        harga: parseRupiah(document.querySelector(`.barang-harga[data-row="${rowId}"]`).value),
        quantity: parseQty(document.querySelector(`.barang-quantity[data-row="${rowId}"]`).value),
        satuan: document.querySelector(`.barang-satuan[data-row="${rowId}"]`).value,
        biaya_admin: biayaAdminEl ? parseRupiah(biayaAdminEl.value) : 0,
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
  // Guard: hanya admin yang boleh simpan/edit data belanja
  if (USER_ROLE !== 'admin') { console.warn('[RBAC] saveItem: selain admin tidak memiliki akses'); return; }

  // Jika editingId ada -> HANYA EDIT INFORMASI TRANSAKSI
  if (editingId) {
    const tanggal = document.getElementById('inputTanggal').value;
    const namaMenu = document.getElementById('inputNamaMenu').value.trim();

    ['errorTanggal', 'errorNamaMenu', 'errorBarang'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = '';
    });

    let valid = true;
    if (!tanggal) {
      document.getElementById('errorTanggal').textContent = 'Tanggal wajib diisi.';
      valid = false;
    }
    if (!namaMenu) {
      document.getElementById('errorNamaMenu').textContent = 'Nama menu wajib diisi.';
      valid = false;
    }
    if (!valid) return;

    const inputUangMasukEl = document.getElementById('inputUangMasuk');
    const inputUangMasuk = inputUangMasukEl ? parseRupiah(inputUangMasukEl.value) : 0;

    const payload = {
      id: editingId,
      tanggal: tanggal,
      nama_menu: namaMenu,
      jumlah_porsi: parseInt(document.getElementById('inputPorsi').value) || 0,
      keterangan: document.getElementById('inputKeterangan') ? document.getElementById('inputKeterangan').value.trim() : '',
      uang_masuk: inputUangMasuk
    };

    try {
      const res = await fetch('../database/api-belanja.php?action=save_header', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await res.json();
      if (!res.ok) throw new Error(result.message || 'Gagal menyimpan informasi transaksi');
      if (result.success) {
        showToast(result.message || 'Informasi transaksi berhasil diperbarui', 'success');
        closeModal();
        fetchData();
      } else {
        showToast(result.message || 'Gagal menyimpan informasi transaksi', 'error');
      }
    } catch (error) {
      console.error('Save header error:', error);
      showToast('Error: ' + error.message, 'error');
    }
    return;
  }

  // Tambah Belanja Baru
  if (!validate()) return;
  const barangList = getBarangData();
  const biayaAdmin = barangList.reduce((sum, b) => sum + (b.biaya_admin || 0), 0);
  const totalBelanja = barangList.reduce((sum, b) => sum + (b.harga * b.quantity) + (b.biaya_admin || 0), 0);
  const inputUangMasukEl = document.getElementById('inputUangMasuk');
  const inputUangMasuk = inputUangMasukEl ? parseRupiah(inputUangMasukEl.value) : 0;

  const payload = {
    id: null,
    tanggal: document.getElementById('inputTanggal').value,
    nama_menu: document.getElementById('inputNamaMenu').value.trim(),
    jumlah_porsi: parseInt(document.getElementById('inputPorsi').value) || 0,
    keterangan: document.getElementById('inputKeterangan') ? document.getElementById('inputKeterangan').value.trim() : '',
    biaya_admin: biayaAdmin,
    total_belanja: totalBelanja,
    uang_masuk: inputUangMasuk,
    status: 'approved',
    created_by: window.CURRENT_USER_ID || 1,
    items: barangList.map(b => ({
      id_detail: null,
      id_barang: b.id_barang,
      nama_barang: b.nama_barang,
      qty: b.quantity,
      satuan: b.satuan,
      harga: b.harga,
      biaya_admin: b.biaya_admin,
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

// ─── Single Item Modal Functions ──────────────────────────────────────────────
function openAddItemModal(pengajuanId) {
  if (USER_ROLE !== 'admin') return;
  document.getElementById('itemModalPengajuanId').value = pengajuanId;
  document.getElementById('itemModalDetailId').value = '';
  document.getElementById('itemModalBarangId').value = '';
  document.getElementById('itemModalSearchInput').value = '';
  document.getElementById('itemModalQty').value = '';
  document.getElementById('itemModalSatuan').value = '';
  document.getElementById('itemModalHarga').value = '';
  document.getElementById('itemModalBiayaAdmin').value = '';
  document.getElementById('itemModalSubtotalValue').textContent = 'Rp 0';
  document.getElementById('errorItemNamaBarang').textContent = '';
  document.getElementById('errorItemQty').textContent = '';
  document.getElementById('errorItemHarga').textContent = '';
  document.getElementById('itemModalTitle').textContent = 'Tambah Barang';
  setupItemModalDropdown();
  document.getElementById('itemModalOverlay').classList.add('active');
}

function openEditItemModal(detailId, pengajuanId) {
  if (USER_ROLE !== 'admin') return;
  const tx = allData.find(d => d.id == pengajuanId || d.id_pengajuan == pengajuanId);
  if (!tx) return;
  const detailItems = tx.items || tx.detail_items || [];
  const b = detailItems.find(it => (it.id || it.id_detail) == detailId);
  if (!b) return;

  document.getElementById('itemModalPengajuanId').value = pengajuanId;
  document.getElementById('itemModalDetailId').value = detailId;
  document.getElementById('itemModalBarangId').value = b.id_barang || '';
  document.getElementById('itemModalSearchInput').value = b.nama_barang || '';
  document.getElementById('itemModalQty').value = b.qty || b.quantity || '';
  document.getElementById('itemModalSatuan').value = b.satuan || '';
  document.getElementById('itemModalHarga').value = (b.harga || b.harga_satuan) ? Number(b.harga || b.harga_satuan).toLocaleString('id-ID') : '';
  document.getElementById('itemModalBiayaAdmin').value = b.biaya_admin ? Number(b.biaya_admin).toLocaleString('id-ID') : '';
  document.getElementById('errorItemNamaBarang').textContent = '';
  document.getElementById('errorItemQty').textContent = '';
  document.getElementById('errorItemHarga').textContent = '';
  document.getElementById('itemModalTitle').textContent = 'Edit Barang';
  calculateItemModalSubtotal();
  setupItemModalDropdown();
  document.getElementById('itemModalOverlay').classList.add('active');
}

function closeItemModal() {
  const overlay = document.getElementById('itemModalOverlay');
  if (overlay) overlay.classList.remove('active');
}

function setupItemModalDropdown() {
  const input = document.getElementById('itemModalSearchInput');
  const list = document.getElementById('itemModalDropdownList');
  if (!input || !list) return;

  const renderList = (q) => {
    const query = (q || '').toLowerCase().trim();
    const filtered = masterBarang.filter(b => !query || b.nama_barang.toLowerCase().includes(query));
    if (filtered.length === 0) {
      list.innerHTML = `<div style="padding:8px 12px; color:#94a3b8; font-size:13px;">Tidak ada hasil (akan disimpan sebagai barang baru)</div>`;
    } else {
      list.innerHTML = filtered.map(b => `
        <div class="dropdown-item" data-id="${b.id_barang}" data-name="${escHtml(b.nama_barang)}" data-harga="${b.harga_beli}" data-satuan="${escHtml(b.satuan)}">
          <div class="dropdown-item-name">${escHtml(b.nama_barang)}</div>
          <div class="dropdown-item-meta">${formatRupiah(b.harga_beli)} / ${escHtml(b.satuan)}</div>
        </div>
      `).join('');
    }

    list.querySelectorAll('.dropdown-item').forEach(el => {
      el.addEventListener('click', () => {
        document.getElementById('itemModalBarangId').value = el.dataset.id;
        input.value = el.dataset.name;
        document.getElementById('itemModalHarga').value = el.dataset.harga ? Number(el.dataset.harga).toLocaleString('id-ID') : '';
        document.getElementById('itemModalSatuan').value = el.dataset.satuan;
        list.classList.remove('active');
        calculateItemModalSubtotal();
      });
    });
  };

  input.onfocus = () => {
    renderList(input.value);
    list.classList.add('active');
  };
  input.oninput = () => {
    renderList(input.value);
    list.classList.add('active');
    document.getElementById('itemModalBarangId').value = '';
    calculateItemModalSubtotal();
  };

  const handleClickOutside = (e) => {
    const wrap = document.getElementById('itemModalDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
      list.classList.remove('active');
    }
  };
  document.removeEventListener('click', handleClickOutside);
  document.addEventListener('click', handleClickOutside);
}

function calculateItemModalSubtotal() {
  const qty = parseQty(document.getElementById('itemModalQty').value);
  const harga = parseRupiah(document.getElementById('itemModalHarga').value);
  const biayaAdmin = parseRupiah(document.getElementById('itemModalBiayaAdmin').value);
  const subtotal = (qty * harga) + biayaAdmin;
  document.getElementById('itemModalSubtotalValue').textContent = formatRupiah(subtotal);
}

async function saveSingleItem() {
  if (USER_ROLE !== 'admin') return;

  const pengajuanId = document.getElementById('itemModalPengajuanId').value;
  const detailId = document.getElementById('itemModalDetailId').value;
  const barangId = document.getElementById('itemModalBarangId').value;
  const namaBarang = document.getElementById('itemModalSearchInput').value.trim();
  const qty = parseQty(document.getElementById('itemModalQty').value);
  const satuan = document.getElementById('itemModalSatuan').value.trim();
  const harga = parseRupiah(document.getElementById('itemModalHarga').value);
  const biayaAdmin = parseRupiah(document.getElementById('itemModalBiayaAdmin').value);

  document.getElementById('errorItemNamaBarang').textContent = '';
  document.getElementById('errorItemQty').textContent = '';
  document.getElementById('errorItemHarga').textContent = '';

  let valid = true;
  if (!namaBarang) {
    document.getElementById('errorItemNamaBarang').textContent = 'Nama barang wajib diisi.';
    valid = false;
  }
  if (!qty || qty <= 0) {
    document.getElementById('errorItemQty').textContent = 'Qty harus lebih dari 0.';
    valid = false;
  }
  if (harga < 0) {
    document.getElementById('errorItemHarga').textContent = 'Harga tidak boleh negatif.';
    valid = false;
  }
  if (!valid) return;

  const payload = {
    pengajuan_id: pengajuanId,
    id_detail: detailId || null,
    id_barang: barangId || null,
    nama_barang: namaBarang,
    qty: qty,
    satuan: satuan,
    harga: harga,
    biaya_admin: biayaAdmin
  };

  try {
    const res = await fetch('../database/api-belanja.php?action=save_single_item', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (!res.ok) throw new Error(result.message || 'Gagal menyimpan barang');
    if (result.success) {
      showToast(result.message || 'Barang berhasil disimpan', 'success');
      closeItemModal();
      fetchData();
    } else {
      showToast(result.message || 'Gagal menyimpan barang', 'error');
    }
  } catch (err) {
    console.error('saveSingleItem error:', err);
    showToast('Error: ' + err.message, 'error');
  }
}

async function deleteSingleItem(detailId, pengajuanId) {
  if (USER_ROLE !== 'admin') return;
  const result = await Swal.fire({
    title: 'Hapus Barang?',
    text: 'Barang ini akan dihapus dari daftar belanja. Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '🗑 Ya, Hapus',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!result.isConfirmed) return;

  try {
    const res = await fetch('../database/api-belanja.php?action=delete_single_item', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_detail: detailId, pengajuan_id: pengajuanId })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Gagal menghapus barang');
    if (data.success) {
      showToast(data.message || 'Barang berhasil dihapus', 'success');
      fetchData();
    } else {
      showToast(data.message || 'Gagal menghapus barang', 'error');
    }
  } catch (err) {
    console.error('deleteSingleItem error:', err);
    showToast('Error: ' + err.message, 'error');
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

  const canDelete = USER_ROLE === 'admin' || (IS_PURCHASE_ROLE && USER_ROLE !== 'purchase_stok');

  if (!urls || urls.length === 0) {
    body.innerHTML = `<p class="nota-modal-empty">Tidak ada nota untuk barang ini.</p>`;
  } else {
    body.innerHTML = urls.map((url, i) => {
      const ext = url.split('.').pop().toLowerCase().split('?')[0];
      const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
      const isPdf = ext === 'pdf';
      const label = `Nota ${i + 1}`;

      const actionHtml = `
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px;">
          ${canDelete ? `
            <button type="button" class="btn-delete-nota" onclick="deleteNota('${escHtml(url)}')" style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; background:#fef2f2; color:#ef4444; border:1px solid #fee2e2; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition: all 0.15s ease;">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                <path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Hapus Nota
            </button>
          ` : ''}
        </div>
      `;

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
          <img src="${escHtml(url)}" alt="${label}" class="nota-preview-img"/>
          ${actionHtml}
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
          ${actionHtml}
        </div>`;
      } else {
        return `<div class="nota-preview-item nota-preview-file">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <path d="M7 3h13L26 10v19H6V3h1z" stroke="#2563a8" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M19 3v8h7" stroke="#2563a8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>${label}</span>
          ${actionHtml}
        </div>`;
      }
    }).join('');
  }

  overlay.classList.add('active');
}

function closeNotaModal() {
  document.getElementById('notaModalOverlay').classList.remove('active');
}

async function deleteNota(filePath) {
  const result = await Swal.fire({
    title: 'Hapus Nota?',
    text: 'File nota fisik akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '🗑 Ya, Hapus Permanen',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!result.isConfirmed) return;
  try {
    const res = await fetch('../database/api-belanja.php?action=delete_nota', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file_path: filePath })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Gagal menghapus nota');
    if (data.success) {
      showToast(data.message || 'Nota berhasil dihapus', 'success');
      closeNotaModal();
      fetchData();
    } else {
      showToast(data.message || 'Gagal menghapus nota', 'error');
    }
  } catch (err) {
    console.error('deleteNota error:', err);
    showToast('Error: ' + err.message, 'error');
  }
}

// ─── Bukti Transfer Preview ──────────────────────────────────────────────────
// Baca array URL yang dikirim lewat data-attribute (dienkode base64,
// sama seperti pola openNotaModalFromBtn) supaya aman dari karakter
// aneh/quote yang bisa merusak atribut onclick.
function openBuktiTFFromBtn(btn) {
  const urls = JSON.parse(decodeURIComponent(escape(atob(btn.dataset.buktiTf))));
  openBuktiTF(urls);
}

// ✅ FIX: sekarang menerima ARRAY url (bisa lebih dari 1 bukti transfer),
// bukan cuma 1 string seperti sebelumnya. Kalau ada kode lama yang masih
// manggil openBuktiTF('namafile.jpg') langsung, tetap didukung (dibungkus
// jadi array 1 elemen) supaya tidak patah.
function openBuktiTF(urls) {
  // Guard: role purchase tidak boleh melihat bukti transfer
  if (IS_PURCHASE_ROLE) { console.warn('[RBAC] openBuktiTF: role purchase tidak memiliki akses'); return; }
  if (!Array.isArray(urls)) urls = urls ? [urls] : [];

  const overlay = document.getElementById('notaModalOverlay');
  const title = document.getElementById('notaModalTitle');
  const body = document.getElementById('notaModalBody');
  title.textContent = 'Bukti Transfer' + (urls.length > 1 ? ` (${urls.length})` : '');

  if (urls.length === 0) {
    body.innerHTML = `<p class="nota-modal-empty">Tidak ada bukti transfer.</p>`;
    overlay.classList.add('active');
    return;
  }

  body.innerHTML = urls.map((rawUrl, i) => {
    let url = rawUrl;
    // Tambah prefix path jika hanya nama file
    if (url && !url.startsWith('http') && !url.startsWith('/') && !url.startsWith('../')) {
      url = '../uploads/bukti_transfer/' + url;
    }

    const isImg = /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(url);
    const isPdf = /\.pdf(\?|$)/i.test(url);
    const label = urls.length > 1 ? `Bukti Transfer ${i + 1}` : 'Bukti Transfer';

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
        <img src="${escHtml(url)}" class="nota-preview-img" alt="${label}"/>
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

  overlay.classList.add('active');
}

// ─── Modal Upload Nota PER ITEM ──────────────────────────────────────────────
let uploadNotaFiles = []; // queue file yang dipilih
let uploadNotaCurrentDetail = null;
let uploadNotaCurrentPengajuan = null;

function openUploadNotaForItem(detailId, pengajuanId) {
  if (USER_ROLE === 'purchase_stok') return;
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
  // Guard: hanya admin yang boleh hapus data
  if (USER_ROLE !== 'admin') { console.warn('[RBAC] deleteItem: selain admin tidak memiliki akses'); return; }
  const result = await Swal.fire({
    title: 'Hapus Data Belanja?',
    text: 'Seluruh data belanja beserta rincian barang akan dihapus. Tindakan ini tidak dapat dibatalkan!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '🗑 Ya, Hapus Semua',
    cancelButtonText: 'Batal',
    customClass: { popup: 'swal-kopdes' }
  });
  if (!result.isConfirmed) return;
  try {
    const res = await fetch('../database/api-belanja.php?action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) { showToast('Data dihapus', 'success'); fetchData(); }
    else showToast('Gagal menghapus data', 'error');
  } catch (error) {
    console.error('Delete error:', error);
    showToast('Terjadi kesalahan saat menghapus', 'error');
  }
}

// ─── Toast (SweetAlert2 mixin) ────────────────────────────────────────────────
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2800,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  }
});

let toastTimer = null;
function showToast(msg, type = '') {
  const iconMap = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
  Toast.fire({
    icon: iconMap[type] || 'info',
    title: msg
  });
}

function exportPDF(id) {
  const url = `cetak-laporan-sppg.php?id=${id}`;
  window.open(url, '_blank');
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

  bindIfExists('btnIncludeB3', 'click', () => {
    const input = document.getElementById('inputKeterangan');
    if (input) {
      if (input.value.includes('sudah termasuk B3')) {
        // Toggle off: remove it and clean up delimiters if any
        input.value = input.value.replace('sudah termasuk B3', '').replace(/^\s*-\s*|\s*-\s*$/, '').trim();
      } else {
        // Toggle on: append it
        input.value = (input.value ? input.value + ' - ' : '') + 'sudah termasuk B3';
      }
    }
  });

  // Direct Input Saldo modal events
  bindIfExists('btnCloseSaldoModal', 'click', closeInputSaldoModal);
  bindIfExists('btnCancelSaldo', 'click', closeInputSaldoModal);
  bindIfExists('btnSaveSaldo', 'click', saveDirectSaldo);
  bindIfExists('inputSaldoModalOverlay', 'click', (e) => {
    if (e.target === document.getElementById('inputSaldoModalOverlay')) closeInputSaldoModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeModal();
      closeRejectModal();
      closeNotaModal();
      closeUploadNotaModal();
      closeInputSaldoModal();
    }
  });

  const inputUangMasuk = document.getElementById('inputUangMasuk');
  if (inputUangMasuk) {
    inputUangMasuk.addEventListener('input', () => formatLiveCurrency(inputUangMasuk));
  }
  const inputSaldoDirect = document.getElementById('inputSaldoDirect');
  if (inputSaldoDirect) {
    inputSaldoDirect.addEventListener('input', () => formatLiveCurrency(inputSaldoDirect));
  }

  initSearch();
});

let saldoTargetId = null;
let _currentBuktiList = [];

function openInputSaldoModalFromBtn(btn, id, currentSaldo) {
  let rawBukti = null;
  if (btn && btn.getAttribute('data-bukti')) {
    try {
      rawBukti = JSON.parse(decodeURIComponent(escape(atob(btn.getAttribute('data-bukti')))));
    } catch(e) {
      rawBukti = btn.getAttribute('data-bukti');
    }
  }
  openInputSaldoModal(id, currentSaldo, rawBukti);
}

function openInputSaldoModal(id, currentSaldo, rawBuktiTF = null) {
  saldoTargetId = id;
  const input = document.getElementById('inputSaldoDirect');
  if (input) input.value = currentSaldo ? Math.round(parseFloat(currentSaldo)).toLocaleString('id-ID') : '';
  
  const titleEl = document.getElementById('modalSaldoTitle');
  if (titleEl) {
    titleEl.textContent = (currentSaldo && parseFloat(currentSaldo) > 0) ? 'Edit Uang Masuk' : 'Input Uang Masuk';
  }

  // Parse existing bukti transfer
  _currentBuktiList = [];
  if (rawBuktiTF) {
    if (Array.isArray(rawBuktiTF)) {
      _currentBuktiList = [...rawBuktiTF];
    } else {
      try {
        const parsed = typeof rawBuktiTF === 'string' ? JSON.parse(rawBuktiTF) : rawBuktiTF;
        _currentBuktiList = Array.isArray(parsed) ? [...parsed] : [rawBuktiTF];
      } catch (e) {
        _currentBuktiList = [rawBuktiTF];
      }
    }
  }
  _currentBuktiList = _currentBuktiList.filter(u => u && typeof u === 'string');

  renderExistingBuktiPreview();

  // Reset new file input & preview
  const fileInput = document.getElementById('inputBuktiTFDirect');
  if (fileInput) fileInput.value = '';
  const preview = document.getElementById('directBuktiPreviewList');
  if (preview) preview.innerHTML = '';

  const modal = document.getElementById('inputSaldoModalOverlay');
  if (modal) modal.classList.add('active');
  if (input) {
    setTimeout(() => { input.focus(); input.select(); }, 100);
  }
}

function renderExistingBuktiPreview() {
  const section = document.getElementById('existingBuktiSection');
  const container = document.getElementById('existingBuktiList');
  if (!section || !container) return;

  if (_currentBuktiList.length === 0) {
    section.style.display = 'none';
    container.innerHTML = '';
    return;
  }

  section.style.display = 'block';
  container.innerHTML = '';

  _currentBuktiList.forEach((filename, index) => {
    const itemTag = document.createElement('div');
    itemTag.style.cssText = 'display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; color:#334155; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:500; border:1px solid #cbd5e1; max-width:100%; position:relative;';

    const ext = filename.split('.').pop().toLowerCase();
    const filePath = '../uploads/bukti_transfer/' + encodeURIComponent(filename);

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
      const imgLink = document.createElement('a');
      imgLink.href = filePath;
      imgLink.target = '_blank';
      imgLink.title = 'Lihat Foto Full';
      const img = document.createElement('img');
      img.src = filePath;
      img.style.cssText = 'width:24px; height:24px; object-fit:cover; border-radius:4px;';
      imgLink.appendChild(img);
      itemTag.appendChild(imgLink);
    } else {
      const icon = document.createElement('span');
      icon.textContent = '📄';
      itemTag.appendChild(icon);
    }

    const textLink = document.createElement('a');
    textLink.href = filePath;
    textLink.target = '_blank';
    textLink.style.cssText = 'color:#0284c7; text-decoration:none; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;';
    textLink.textContent = filename;
    itemTag.appendChild(textLink);

    const btnDelete = document.createElement('button');
    btnDelete.type = 'button';
    btnDelete.style.cssText = 'background:none; border:none; color:#ef4444; font-size:14px; font-weight:700; cursor:pointer; padding:0 2px; margin-left:4px; line-height:1;';
    btnDelete.innerHTML = '&times;';
    btnDelete.title = 'Hapus foto ini';
    btnDelete.onclick = (e) => {
      e.stopPropagation();
      removeExistingBuktiItem(index);
    };
    itemTag.appendChild(btnDelete);

    container.appendChild(itemTag);
  });
}

function removeExistingBuktiItem(index) {
  _currentBuktiList.splice(index, 1);
  renderExistingBuktiPreview();
}

function closeInputSaldoModal() {
  const modal = document.getElementById('inputSaldoModalOverlay');
  if (modal) modal.classList.remove('active');
  saldoTargetId = null;
  _currentBuktiList = [];
  const fileInput = document.getElementById('inputBuktiTFDirect');
  if (fileInput) fileInput.value = '';
  const preview = document.getElementById('directBuktiPreviewList');
  if (preview) preview.innerHTML = '';
}

function onDirectBuktiTFSelected(input) {
  const preview = document.getElementById('directBuktiPreviewList');
  if (!preview) return;
  preview.innerHTML = '';

  if (!input.files || input.files.length === 0) return;

  const countBadge = document.createElement('div');
  countBadge.style.cssText = 'width:100%; font-size:12px; font-weight:600; color:#0284c7; margin-bottom:4px;';
  countBadge.textContent = `${input.files.length} file bukti transfer baru dipilih:`;
  preview.appendChild(countBadge);

  Array.from(input.files).forEach((file) => {
    const itemTag = document.createElement('div');
    itemTag.style.cssText = 'display:inline-flex; align-items:center; gap:6px; background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:500; border:1px solid #bae6fd; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;';

    if (file.type.startsWith('image/')) {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.style.cssText = 'width:20px; height:20px; object-fit:cover; border-radius:3px;';
      itemTag.appendChild(img);
    } else {
      const icon = document.createElement('span');
      icon.textContent = '📄';
      itemTag.appendChild(icon);
    }

    const textNode = document.createElement('span');
    textNode.textContent = file.name;
    itemTag.appendChild(textNode);

    preview.appendChild(itemTag);
  });
}

async function saveDirectSaldo() {
  if (!saldoTargetId) return;
  const input = document.getElementById('inputSaldoDirect');
  const uangMasuk = input ? parseRupiah(input.value) : 0;

  const btn = document.getElementById('btnSaveSaldo');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
  }

  try {
    const formData = new FormData();
    formData.append('id', saldoTargetId);
    formData.append('uang_masuk', uangMasuk);
    formData.append('existing_bukti', JSON.stringify(_currentBuktiList));

    const fileInput = document.getElementById('inputBuktiTFDirect');
    if (fileInput && fileInput.files.length > 0) {
      for (let i = 0; i < fileInput.files.length; i++) {
        formData.append('bukti_transfer[]', fileInput.files[i]);
      }
    }

    const res = await fetch('../database/api-belanja.php?action=update_saldo', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();
    if (result.success) {
      showToast('Uang masuk & bukti transfer berhasil disimpan', 'success');
      closeInputSaldoModal();
      fetchData();
    } else {
      showToast(result.message || 'Gagal menyimpan uang masuk', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Terjadi kesalahan saat menyimpan uang masuk', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg> Simpan Uang Masuk`;
    }
  }
}
// ═══════════════════════════════════════════════════════════════
//  SIGNATURE / TANDA TANGAN DIGITAL — Pembuat (Saepul Misbah)
// ═══════════════════════════════════════════════════════════════

let _sigPengajuanId = null;
let _sigCanvas      = null;
let _sigCtx         = null;
let _sigDrawing     = false;
let _sigHasStroke   = false;

function openSignatureModal(pengajuanId) {
  if (!['admin', 'purchase_stok'].includes(window.CURRENT_USER_ROLE || '')) {
    showToast('Anda tidak memiliki akses untuk tanda tangan', 'error');
    return;
  }
  _sigPengajuanId = pengajuanId;
  _sigHasStroke   = false;

  const targetItem = allData.find(it => (it.id || it.id_pengajuan) == pengajuanId);
  const existingTtd = (targetItem && targetItem.ttd_map)
    ? (targetItem.ttd_map.purchase || targetItem.ttd_map.admin)
    : null;

  const overlay = document.getElementById('signatureModalOverlay');
  if (!overlay) return;
  overlay.classList.add('active');

  requestAnimationFrame(() => {
    _sigCanvas = document.getElementById('signatureCanvas');
    if (!_sigCanvas) return;
    _sigCtx = _sigCanvas.getContext('2d');

    const rect = _sigCanvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    _sigCanvas.width  = rect.width  * dpr;
    _sigCanvas.height = rect.height * dpr;
    _sigCtx.scale(dpr, dpr);
    _sigCtx.strokeStyle = '#1e293b';
    _sigCtx.lineWidth   = 2.5;
    _sigCtx.lineCap     = 'round';
    _sigCtx.lineJoin    = 'round';
    _sigCtx.clearRect(0, 0, rect.width, rect.height);

    if (existingTtd && existingTtd.signature_data) {
      const img = new Image();
      img.onload = () => {
        _sigCtx.drawImage(img, 0, 0, rect.width, rect.height);
        _sigHasStroke = true;
      };
      img.src = existingTtd.signature_data;
    }

    _sigCanvas.onmousedown  = _sigStart;
    _sigCanvas.onmousemove  = _sigMove;
    _sigCanvas.onmouseup    = _sigEnd;
    _sigCanvas.onmouseleave = _sigEnd;
    _sigCanvas.ontouchstart = (e) => { e.preventDefault(); _sigStart(e.touches[0]); };
    _sigCanvas.ontouchmove  = (e) => { e.preventDefault(); _sigMove(e.touches[0]); };
    _sigCanvas.ontouchend   = _sigEnd;
  });
}

function closeSignatureModal() {
  const overlay = document.getElementById('signatureModalOverlay');
  if (overlay) overlay.classList.remove('active');
  _sigPengajuanId = null;
  _sigHasStroke   = false;
}

function clearSignatureCanvas() {
  if (!_sigCanvas || !_sigCtx) return;
  const rect = _sigCanvas.getBoundingClientRect();
  const dpr = window.devicePixelRatio || 1;
  _sigCanvas.width  = rect.width  * dpr;
  _sigCanvas.height = rect.height * dpr;
  _sigCtx.scale(dpr, dpr);
  _sigCtx.strokeStyle = '#1e293b';
  _sigCtx.lineWidth   = 2.5;
  _sigCtx.lineCap     = 'round';
  _sigCtx.lineJoin    = 'round';
  _sigCtx.clearRect(0, 0, rect.width, rect.height);
  _sigHasStroke = false;
}

function _sigStart(e) {
  const r = _sigCanvas.getBoundingClientRect();
  _sigDrawing = true;
  _sigCtx.beginPath();
  _sigCtx.moveTo(e.clientX - r.left, e.clientY - r.top);
}

function _sigMove(e) {
  if (!_sigDrawing) return;
  const r = _sigCanvas.getBoundingClientRect();
  _sigCtx.lineTo(e.clientX - r.left, e.clientY - r.top);
  _sigCtx.stroke();
  _sigCtx.beginPath();
  _sigCtx.moveTo(e.clientX - r.left, e.clientY - r.top);
  _sigHasStroke = true;
}

function _sigEnd() {
  _sigDrawing = false;
  if (_sigCtx) _sigCtx.beginPath();
}

async function saveSignature() {
  if (!_sigHasStroke) {
    showToast('Silakan buat tanda tangan terlebih dahulu', 'error');
    return;
  }
  if (!_sigPengajuanId) {
    showToast('ID pengajuan tidak ditemukan', 'error');
    return;
  }

  const btn = document.getElementById('btnSaveSignature');
  if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan...'; }

  try {
    const dataUrl = _sigCanvas.toDataURL('image/png');
    const res = await fetch('../database/api-belanja.php?action=save_ttd', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pengajuan_id:   _sigPengajuanId,
        role_penanda:   'purchase',
        signature_data: dataUrl,
        nama:           'Saepul Misbah',
        user_id:        0
      })
    });
    const result = await res.json();
    if (result.success) {
      // Update memory lokal langsung
      const targetItem = allData.find(it => (it.id || it.id_pengajuan) == _sigPengajuanId);
      if (targetItem) {
        if (!targetItem.ttd_map) targetItem.ttd_map = {};
        targetItem.ttd_map.purchase = {
          pengajuan_id: _sigPengajuanId,
          role_penanda: 'purchase',
          signature_data: dataUrl,
          timestamp: new Date().toISOString()
        };
      }
      showToast('Tanda tangan berhasil disimpan! ✓', 'success');
      closeSignatureModal();
      fetchData();
    } else {
      showToast(result.message || 'Gagal menyimpan tanda tangan', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Terjadi kesalahan saat menyimpan tanda tangan', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Simpan TTD`;
    }
  }
}
