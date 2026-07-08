// ===== STATE =====
let data = [];
let nextId = 1;
let tmpBukti = null;
let tmpBuktiName = '';
let currentKeputusan = '';
let itemRowCount = 0;
let stokData = []; // Data stok dari tabel barang

// ===== HELPERS =====
function fmt(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}
function fmtDate(d) {
    if (!d) return '-';
    const p = d.split('-');
    const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return p[2] + ' ' + bulan[parseInt(p[1]) - 1] + ' ' + p[0];
}
function today() {
    return new Date().toISOString().slice(0, 10);
}

// ===== DEBUG HELPER =====
const DEBUG = true; // set false di production
function dbg(...args) { if (DEBUG) console.log('[Pengajuan]', ...args); }

// ===== EXTRACT ARRAY dari berbagai format response PHP =====
// Handles: {success,data}, {status,data}, {data}, langsung array, dsb.
function extractArray(json) {
    if (!json) return null;
    if (Array.isArray(json)) return json;                         // langsung array
    if (Array.isArray(json.data)) return json.data;               // {data:[...]}
    if (Array.isArray(json.items)) return json.items;             // {items:[...]}
    if (Array.isArray(json.result)) return json.result;           // {result:[...]}
    // cek semua key yang nilainya array
    for (const k of Object.keys(json)) {
        if (Array.isArray(json[k])) return json[k];
    }
    return null;
}

// ===== NORMALIZE ITEM baris pengajuan =====
function normalizeItem(it) {
    return {
        ...it,
        keterangan: it.keterangan || it.nama_barang || it.ket || it.name || it.barang || '',
        qty: Number(it.qty) || 0,
        harga: Number(it.harga) || Number(it.harga_satuan) || Number(it.price) || 0,
        subtotal: Number(it.subtotal) || 0,
        sisaStok: it.sisaStok ?? it.sisa_stok ?? it.stok ?? '',
        satuan: it.satuan || it.unit || '',
    };
}

// ===== NORMALIZE STOK ITEM =====
function normalizeStok(s) {
    return {
        ...s,
        nama_barang: s.nama_barang || s.nama || s.name || s.barang || '',
        stok_akhir: Number(s.stok_akhir ?? s.stok ?? s.qty ?? s.stock ?? 0),
        harga_beli: Number(s.harga_beli ?? s.harga ?? s.price ?? 0),
        satuan: s.satuan || s.unit || '',
    };
}

// ===== LOAD DATA DARI SERVER =====
async function loadData() {
    try {
        const res = await fetch('../database/get-pengajuan.php');
        const text = await res.text(); // baca sebagai text dulu untuk debug
        dbg('get-pengajuan raw response:', text.slice(0, 300));

        let json;
        try { json = JSON.parse(text); }
        catch (e) {
            console.error('get-pengajuan.php tidak return JSON valid:', e.message);
            console.error('Response:', text.slice(0, 500));
            render(); return;
        }

        dbg('get-pengajuan parsed:', json);
        const arr = extractArray(json);
        if (arr) {
            data = arr.map(row => {
                if (Array.isArray(row.items)) {
                    row.items = row.items.map(normalizeItem);
                }
                return row;
            });
            nextId = data.length ? Math.max(...data.map(x => Number(x.id) || 0)) + 1 : 1;
            dbg('Loaded', data.length, 'pengajuan rows');
        } else {
            console.warn('get-pengajuan.php: tidak ditemukan array dalam response. Full JSON:', json);
        }
    } catch (e) {
        console.error('Gagal fetch get-pengajuan.php:', e);
    }
    render();
}

// ===== LOAD DATA STOK DARI SERVER =====
async function loadStokData() {
    try {
        const res = await fetch('../database/get-stok.php');
        const text = await res.text();
        dbg('get-stok raw response:', text.slice(0, 300));

        let json;
        try { json = JSON.parse(text); }
        catch (e) {
            console.error('get-stok.php tidak return JSON valid:', e.message);
            console.error('Response:', text.slice(0, 500));
            return;
        }

        dbg('get-stok parsed:', json);
        const arr = extractArray(json);
        if (arr) {
            stokData = arr.map(normalizeStok);
            dbg('Loaded', stokData.length, 'stok items:', stokData.slice(0, 3));
        } else {
            console.warn('get-stok.php: tidak ditemukan array dalam response. Full JSON:', json);
        }
    } catch (e) {
        console.error('Gagal fetch get-stok.php:', e);
    }
}

// ===== TAMPILKAN TOAST SUGGEST STOK =====
function updateStokDatalist(filterText, inputElement) {
    // Hapus toast lama
    document.querySelectorAll('.stok-toast-list').forEach(t => t.remove());

    const filter = filterText.toLowerCase().trim();
    if (filter === '') return;

    if (!stokData.length) {
        dbg('updateStokDatalist: stokData kosong, dropdown tidak muncul');
        return;
    }

    const filtered = stokData.filter(s =>
        s.nama_barang.toLowerCase().includes(filter)
    ).slice(0, 7);

    if (filtered.length === 0) return;

    const toast = document.createElement('div');
    toast.className = 'stok-toast-list';

    toast.innerHTML = filtered.map(s => {
        let badgeClass = 'aman', badgeLabel = 'Aman';
        if (s.stok_akhir <= 0) { badgeClass = 'habis'; badgeLabel = 'Habis'; }
        else if (s.stok_akhir < 10) { badgeClass = 'rendah'; badgeLabel = 'Rendah'; }

        const safeNama = s.nama_barang.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

        return `
            <div class="stok-toast-item"
                 onmousedown="event.preventDefault(); selectStokItem('${safeNama}', '${inputElement.id}')">
                <span class="stok-toast-info">${s.nama_barang}</span>
                <div class="stok-toast-right">
                    <span class="stok-toast-qty">Sisa: <strong>${s.stok_akhir}</strong></span>
                    <span class="stok-badge ${badgeClass}">${badgeLabel}</span>
                </div>
            </div>
        `;
    }).join('');

    // *** FIX: append ke body dengan posisi fixed mengikuti input ***
    // Ini bypass overflow:hidden pada .items-table-wrap dan .modal
    const rect = inputElement.getBoundingClientRect();
    toast.style.position = 'fixed';
    toast.style.top = (rect.bottom + 3) + 'px';
    toast.style.left = rect.left + 'px';
    toast.style.width = rect.width + 'px';
    toast.style.zIndex = '100001';
    document.body.appendChild(toast);

    // Repositioning saat scroll (modal bisa di-scroll)
    const reposition = () => {
        const r = inputElement.getBoundingClientRect();
        toast.style.top = (r.bottom + 3) + 'px';
        toast.style.left = r.left + 'px';
    };
    document.addEventListener('scroll', reposition, { passive: true, once: true });
}

// ===== SELECT ITEM DARI TOAST =====
function selectStokItem(namaBarang, inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    // Ambil rid dari id input: "ket_row_X" → "row_X"
    const rid = inputId.replace('ket_', '');
    const tr = document.getElementById(rid);

    input.value = namaBarang;

    // Cari data stok exact match
    const found = stokData.find(s => s.nama_barang === namaBarang);
    if (found && tr) {
        const hargaInput = tr.querySelector('.row-harga');
        if (hargaInput) {
            hargaInput.value = Number(found.harga_beli).toLocaleString('id-ID');
        }
        const sisaInput = tr.querySelector('.row-sisa-stok');
        if (sisaInput) sisaInput.value = found.stok_akhir;

        const satuanInput = tr.querySelector('.row-satuan');
        if (satuanInput) satuanInput.value = found.satuan || '';

        tr.dataset.stokMatched = found.nama_barang;
        recalcRow(rid);
    }

    // Hapus semua toast
    document.querySelectorAll('.stok-toast-list').forEach(t => t.remove());

    // Fokus ke kolom qty supaya flow lebih enak
    if (tr) {
        const qtyInput = tr.querySelector('.row-qty');
        if (qtyInput) qtyInput.focus();
    }
}

// ===== RENDER — GROUP PER TANGGAL =====
// ===== ACTION DROPDOWN (kebab menu) =====
function closeActionMenu() {
    const m = document.getElementById('globalActionMenu');
    if (m) m.remove();
    document.removeEventListener('click', onDocClickCloseActionMenu);
    window.removeEventListener('scroll', closeActionMenu, true);
    window.removeEventListener('resize', closeActionMenu);
}

function onDocClickCloseActionMenu(e) {
    const m = document.getElementById('globalActionMenu');
    if (m && !m.contains(e.target) && !e.target.closest('.action-menu-btn')) {
        closeActionMenu();
    }
}

function toggleActionMenu(e, id) {
    e.stopPropagation();
    const already = document.getElementById('globalActionMenu');
    const wasOpenForSameRow = already && String(already.dataset.id) === String(id);
    closeActionMenu();
    if (wasOpenForSameRow) return;

    const btn = e.currentTarget;
    const rect = btn.getBoundingClientRect();

    const menu = document.createElement('div');
    menu.id = 'globalActionMenu';
    menu.className = 'action-dropdown';
    menu.dataset.id = id;
    const isAdmin = SESSION_ROLE === 'admin';
    menu.innerHTML = `
        ${isAdmin ? `<button class="action-dropdown-item" onclick="closeActionMenu();openEdit(${id})"><i class="ti ti-edit"></i> Edit</button>` : ''}
        ${isAdmin ? `<button class="action-dropdown-item" onclick="closeActionMenu();exportPDF(${id})"><i class="ti ti-file-type-pdf" style="color:#c2410c"></i> Ekspor PDF</button>` : ''}
        ${isAdmin ? `<div class="action-dropdown-divider"></div>
        <button class="action-dropdown-item danger" onclick="closeActionMenu();deleteItem(${id})"><i class="ti ti-trash"></i> Hapus</button>` : ''}
    `;
    document.body.appendChild(menu);

    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;
    let left = rect.right - menuWidth;
    if (left < 8) left = 8;
    let top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - 8) {
        top = rect.top - menuHeight - 6;
    }
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    menu.classList.add('open');

    setTimeout(() => {
        document.addEventListener('click', onDocClickCloseActionMenu);
        window.addEventListener('scroll', closeActionMenu, true);
        window.addEventListener('resize', closeActionMenu);
    }, 0);
}

function render() {
    const from = document.getElementById('filterFrom').value;
    const to = document.getElementById('filterTo').value;
    let filtered = data.slice();
    if (from) filtered = filtered.filter(i => i.tanggal >= from);
    if (to) filtered = filtered.filter(i => i.tanggal <= to);
    const el = document.getElementById('list');

    if (!filtered.length) {
        el.innerHTML = '<div class="empty"><i class="ti ti-inbox" style="font-size:32px;display:block;margin-bottom:8px"></i>Tidak ada data pengajuan</div>';
        return;
    }

    filtered.sort((a, b) => b.tanggal.localeCompare(a.tanggal) || b.id - a.id);

    const grouped = {};
    filtered.forEach(i => {
        if (!grouped[i.tanggal]) grouped[i.tanggal] = [];
        grouped[i.tanggal].push(i);
    });

    let html = '';
    Object.keys(grouped)
        .sort((a, b) => b.localeCompare(a))
        .forEach(tgl => {
            const items = grouped[tgl];
            const totalHari = items.reduce((s, i) => s + (parseFloat(i.jumlah) || 0), 0);
            let noUrut = 1;
            const rows = items.map(i => renderRow(i)).join('');

            html += `
            <div class="date-group">
                <div class="date-group-header">
                    <span class="date-label"><i class="ti ti-calendar" aria-hidden="true"></i> ${fmtDate(tgl)}</span>
                    <span class="date-total">${items.length} pengajuan &nbsp;·&nbsp; Total: ${fmt(totalHari)}</span>
                </div>
                <div class="table-wrap">
                    <table class="main-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center">#</th>
                                <th>Tujuan / Keterangan</th>
                                <th style="width:50px;text-align:center">Item</th>
                                <th style="width:140px;text-align:right">Total Diajukan</th>
                                <th style="width:80px;text-align:center">Status</th>
                                <th style="width:120px;text-align:center">Saldo Cair</th>
                                <th style="width:160px;text-align:center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`;
        });

    el.innerHTML = html;
}

const JENIS_META = {
    stok: { label: 'Stok', icon: 'ti-package', cls: 'badge-stok' },
    peralatan: { label: 'Peralatan', icon: 'ti-tools', cls: 'badge-peralatan' },
    operasional: { label: 'Operasional', icon: 'ti-settings', cls: 'badge-operasional' },
    lainlain: { label: 'Lain-lain', icon: 'ti-dots', cls: 'badge-peralatan' },
};

function renderRow(i, no = 1) {
    let statusBadge = '';
    if (i.status === 'pending') statusBadge = '<span class="badge pending"><i class="ti ti-clock" style="font-size:10px"></i> Pending</span>';
    else if (i.status === 'approved') statusBadge = '<span class="badge approved"><i class="ti ti-check" style="font-size:10px"></i> Disetujui</span>';
    else statusBadge = '<span class="badge rejected"><i class="ti ti-x" style="font-size:10px"></i> Ditolak</span>';

    const meta = JENIS_META[i.jenis] || JENIS_META['lainlain'];
    const jenisBadge = `<span class="badge ${meta.cls}"><i class="ti ${meta.icon}" style="font-size:10px"></i> ${meta.label}</span>`;

    const tujuan = i.tujuan ? `<span style="font-weight:500">${i.tujuan}</span>` : '';
    let subKet = '';
    if (i.jenis !== 'operasional' && i.items && i.items.length) {
        const firstItem = i.items[0].keterangan;
        const extraCount = i.items.length > 1 ? `<span class="more-badge" onclick="openDetail(${i.id})">+${i.items.length - 1}</span>` : '';
        subKet = `<br><span class="ket-link" style="color:var(--text-muted);font-size:12px" onclick="openDetail(${i.id})">${firstItem}</span>${extraCount}`;
    }
    const ketEl = tujuan ? `${tujuan}${subKet}` : `<span class="ket-link" onclick="openDetail(${i.id})">${i.items && i.items.length ? i.items[0].keterangan : '-'}</span>`;

    const itemCount = i.jenis === 'operasional'
        ? '<span style="color:var(--text-muted);font-size:12px">—</span>'
        : (i.items ? i.items.length : 1);

    let saldoCell = '<span style="color:var(--text-muted);font-size:12px">—</span>';
    if (i.status === 'approved' && i.saldo > 0) {
        const buktiBtn = i.buktiName
            ? `<span class="bukti-chip" onclick="viewBuktiSingle(${i.id})" title="Lihat bukti TF"><i class="ti ti-file-check"></i></span>`
            : `<span class="no-bukti-chip" onclick="viewBuktiSingle(${i.id})" title="Bukti belum ada"><i class="ti ti-alert-triangle"></i></span>`;
        saldoCell = `<span style="color:var(--text-success);font-size:12px;font-weight:500">${fmt(i.saldo)}</span> ${buktiBtn}`;
    } else if (i.status === 'rejected') {
        saldoCell = `<span style="color:var(--text-danger);font-size:11px" title="${i.alasan || ''}">Ditolak</span>`;
    }

    return `<tr>
        <td style="text-align:center;color:var(--text-muted);font-size:12px">${no}</td>
        <td>${jenisBadge} ${ketEl}</td>
        <td style="text-align:center">${itemCount}</td>
        <td style="text-align:right;font-weight:500">${fmt(i.jumlah)}</td>
        <td style="text-align:center">${statusBadge}</td>
        <td style="text-align:center">${saldoCell}</td>
        <td>
            <div class="actions" style="justify-content:center">
                ${(['ketua','bendahara'].includes(SESSION_ROLE)) ? `<button class="btn sm success-btn" onclick="openApproval(${i.id})" title="Approval"><i class="ti ti-shield-check" aria-hidden="true"></i> Approval</button>` : ''}
                <button class="btn sm" style="background:#f3e8ff;color:#7c3aed;border-color:#d8b4fe;" onclick="openSignature(${i.id})" title="Tanda Tangan"><i class="ti ti-pencil" aria-hidden="true"></i> TTD</button>
                ${SESSION_ROLE === 'admin' ? `<button class="btn sm icon-only action-menu-btn" onclick="toggleActionMenu(event, ${i.id})" title="Aksi lainnya"><i class="ti ti-dots-vertical" aria-hidden="true"></i></button>` : ''}
            </div>
        </td>
    </tr>`;
}

// ===== FILTER =====
function clearFilter() {
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
    render();
}

// ===== FORMAT HARGA =====
function parseHarga(str) {
    return parseFloat(String(str).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
}
function onHargaInput(el, rid) {
    const raw = el.value.replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    el.value = num ? num.toLocaleString('id-ID') : '';
    recalcRow(rid);
}

// ===== ITEM ROWS =====
function addItemRow(ket = '', qty = '', harga = '', sisaStok = '', satuan = '') {
    itemRowCount++;
    const rid = 'row_' + itemRowCount;
    const tbody = document.getElementById('itemRows');
    const tr = document.createElement('tr');
    tr.id = rid;
    tr.dataset.rid = rid;
    tr.dataset.stokMatched = '';
    const hargaFormatted = harga ? Number(harga).toLocaleString('id-ID') : '';

    const cfg = MODAL_JENIS_CFG[currentJenis] || MODAL_JENIS_CFG['stok'];
    const placeholder = cfg.useStokLookup ? 'Ketik nama barang...' : 'Nama peralatan...';

    tr.innerHTML = `
        <td>
            <button class="btn sm danger icon-only" onclick="removeItemRow('${rid}')" title="Hapus baris">
                <i class="ti ti-trash" aria-hidden="true"></i>
            </button>
        </td>
        <td style="position:relative">
            <input type="text" class="row-ket" id="ket_${rid}"
                placeholder="${placeholder}" value="${ket}" autocomplete="off"
                oninput="onKetInput(this,'${rid}')"
                onblur="onKetBlur(this)"
                style="width:100%">
        </td>
        <td>
            <input type="text" class="row-sisa-stok" readonly value="${sisaStok}"
               placeholder="-" style="width:100%;background:var(--surface-1,#f9fafb);color:var(--text-secondary,#555);cursor:default;text-align:center;">
        </td>
        <td>
            <input type="text" class="row-satuan" readonly value="${satuan}"
               placeholder="-" style="width:100%;background:var(--surface-1,#f9fafb);color:var(--text-secondary,#555);cursor:default;text-align:center;">
        </td>
        <td><input type="number" class="row-qty" placeholder="0" value="${qty}" min="0" oninput="recalcRow('${rid}')" style="width:100%"></td>
        <td><input type="text" class="row-harga" placeholder="0" value="${hargaFormatted}" inputmode="numeric" oninput="onHargaInput(this,'${rid}')" style="width:100%"></td>
        <td class="subtotal-cell" style="text-align:right;font-weight:500;white-space:nowrap">Rp 0</td>
    `;
    tbody.appendChild(tr);

    // Sembunyikan kolom sisa stok & satuan untuk peralatan
    if (cfg.hideStokCols) {
        tr.querySelector('.row-sisa-stok').closest('td').style.display = 'none';
        tr.querySelector('.row-satuan').closest('td').style.display = 'none';
    }

    recalcRow(rid);
    if (!ket) {
        tr.querySelector('.row-ket').focus();
    }
}

// Blur handler — delay agar onmousedown di toast sempat jalan dulu
function onKetBlur(el) {
    setTimeout(() => {
        document.querySelectorAll('.stok-toast-list').forEach(t => t.remove());
    }, 200);
}

function removeItemRow(rid) {
    const tr = document.getElementById(rid);
    if (!tr) return;
    const rows = document.getElementById('itemRows').querySelectorAll('tr');
    if (rows.length <= 1) {
        tr.querySelector('.row-ket').value = '';
        tr.querySelector('.row-qty').value = '';
        tr.querySelector('.row-harga').value = '';
        tr.querySelector('.row-sisa-stok').value = '';
        recalcRow(rid);
        return;
    }
    tr.remove();
    recalcTotal();
}

function recalcRow(rid) {
    const tr = document.getElementById(rid);
    if (!tr) return;
    const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
    const harga = parseHarga(tr.querySelector('.row-harga').value);
    const sub = qty * harga;
    tr.querySelector('.subtotal-cell').textContent = fmt(sub);
    recalcTotal();
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#itemRows tr').forEach(tr => {
        const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
        const harga = parseHarga(tr.querySelector('.row-harga').value);
        total += qty * harga;
    });
    document.getElementById('grandTotal').textContent = fmt(total);
}

function getItemsFromRows() {
    const items = [];
    document.querySelectorAll('#itemRows tr').forEach(tr => {
        const ket = tr.querySelector('.row-ket').value.trim();
        const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
        const harga = parseHarga(tr.querySelector('.row-harga').value);
        const sisaStok = tr.querySelector('.row-sisa-stok').value;
        const satuan = tr.querySelector('.row-satuan')?.value || '';
        const subtotal = qty * harga;
        if (ket || qty || harga) {
            items.push({ keterangan: ket, qty, harga, subtotal, sisaStok, satuan });
        }
    });
    return items;
}

function clearItemRows() {
    document.getElementById('itemRows').innerHTML = '';
    itemRowCount = 0;
}

// ===== LOOKUP STOK (Auto-fill harga & sisa stok) =====
function onKetInput(el, rid) {
    recalcRow(rid);
    const nama = el.value.trim();
    const tr = document.getElementById(rid);
    if (!tr) return;

    const cfg = MODAL_JENIS_CFG[currentJenis] || MODAL_JENIS_CFG['stok'];

    // Jenis peralatan: tidak pakai autocomplete & tidak lookup stok
    if (!cfg.useStokLookup) {
        document.querySelectorAll('.stok-toast-list').forEach(t => t.remove());
        return;
    }

    // Tampilkan toast suggest (hanya untuk stok)
    updateStokDatalist(nama, el);

    if (!nama) {
        tr.querySelector('.row-sisa-stok').value = '';
        tr.dataset.stokMatched = '';
        return;
    }

    // Exact match → auto-fill harga & sisa stok
    const found = stokData.find(s => s.nama_barang.toLowerCase() === nama.toLowerCase());
    if (found && tr.dataset.stokMatched !== found.nama_barang) {
        tr.dataset.stokMatched = found.nama_barang;

        const hargaInput = tr.querySelector('.row-harga');
        hargaInput.value = Number(found.harga_beli).toLocaleString('id-ID');

        tr.querySelector('.row-sisa-stok').value = found.stok_akhir;

        const satuanInput = tr.querySelector('.row-satuan');
        if (satuanInput) satuanInput.value = found.satuan || '';

        recalcRow(rid);
    } else if (!found) {
        tr.dataset.stokMatched = '';
        tr.querySelector('.row-sisa-stok').value = '';
        const satuanInput = tr.querySelector('.row-satuan');
        if (satuanInput) satuanInput.value = '';
    }
}

// ===== SETUP MODAL SESUAI JENIS =====
const MODAL_JENIS_CFG = {
    stok: {
        title: 'Tambah Pengajuan Stok',
        icon: 'ti-package',
        labelTujuan: 'Tujuan Pembelian',
        placeholderTujuan: 'Contoh: Pembelian sembako minggu ini...',
        thNama: 'Nama Barang',
        labelTambah: 'Tambah Barang',
        hideStokCols: false,  // tampilkan sisa stok & satuan
        useStokLookup: true,  // aktifkan autocomplete dari tabel barang
    },
    peralatan: {
        title: 'Tambah Pengajuan Peralatan',
        icon: 'ti-tools',
        labelTujuan: 'Tujuan Pembelian',
        placeholderTujuan: 'Contoh: Pembelian peralatan dapur...',
        thNama: 'Nama Peralatan',
        labelTambah: 'Tambah Peralatan',
        hideStokCols: true,   // sembunyikan sisa stok & satuan
        useStokLookup: false, // jangan lookup dari tabel barang
    },
    operasional: {
        title: 'Tambah Pengajuan Operasional',
        icon: 'ti-settings',
        labelTujuan: 'Tujuan / Keperluan',
        placeholderTujuan: 'Contoh: Servis AC ruang kantor...',
        thNama: '',
        labelTambah: '',
        hideStokCols: true,
        useStokLookup: false,
    },
};

// Simpan jenis aktif supaya bisa dicek di onKetInput
let currentJenis = 'stok';

function applyModalJenis(jenis) {
    currentJenis = jenis;
    const cfg = MODAL_JENIS_CFG[jenis] || MODAL_JENIS_CFG['stok'];
    document.getElementById('fJenis').value = jenis;
    document.getElementById('modalAddIcon').className = `ti ${cfg.icon}`;
    document.getElementById('modalAddTitle').textContent = cfg.title;
    document.getElementById('labelTujuan').textContent = cfg.labelTujuan;
    document.getElementById('fTujuan').placeholder = cfg.placeholderTujuan;
    const isOps = jenis === 'operasional';
    document.getElementById('sectionItems').style.display = isOps ? 'none' : 'block';
    document.getElementById('sectionOperasional').style.display = isOps ? 'block' : 'none';

    if (!isOps) {
        document.getElementById('thNamaBarang').textContent = cfg.thNama;
        document.getElementById('labelTambahItem').textContent = cfg.labelTambah;

        // Show/hide kolom Sisa Stok (index 2) dan Satuan (index 3) di header
        const ths = document.querySelectorAll('.items-table thead th');
        if (ths.length >= 4) {
            ths[2].style.display = cfg.hideStokCols ? 'none' : '';  // Sisa Stok
            ths[3].style.display = cfg.hideStokCols ? 'none' : '';  // Satuan
        }
    }
}

// ===== ANGGARAN INPUT (operasional) =====
function onAnggaranInput(el) {
    const raw = el.value.replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    el.value = num ? num.toLocaleString('id-ID') : '';
    document.getElementById('grandTotalOps').textContent = fmt(num);
}


// ===== SALDO INPUT (approval) =====
function onSaldoInput(el) {
    const raw = el.value.replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    el.value = num ? num.toLocaleString('id-ID') : '';
}

// ===== MODAL TAMBAH / EDIT =====
function openAdd(jenis = 'stok') {
    document.getElementById('editId').value = '';
    document.getElementById('fTanggal').value = today();
    document.getElementById('fTujuan').value = '';
    document.getElementById('fAnggaran').value = '';
    document.getElementById('fKeterangan').value = '';
    document.getElementById('grandTotalOps').textContent = fmt(0);
    clearItemRows();
    applyModalJenis(jenis);
    if (jenis !== 'operasional') {
        addItemRow();
        recalcTotal();
    }
    document.getElementById('modalAdd').style.display = 'flex';
}

function openEdit(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const jenis = i.jenis || 'stok';
    document.getElementById('editId').value = id;
    document.getElementById('fTanggal').value = i.tanggal;
    document.getElementById('fTujuan').value = i.tujuan || '';

    applyModalJenis(jenis);

    const cfg = MODAL_JENIS_CFG[jenis] || MODAL_JENIS_CFG['stok'];
    document.getElementById('modalAddTitle').textContent = cfg.title.replace('Tambah', 'Edit');

    if (jenis === 'operasional') {
        const angNum = i.jumlah || 0;
        document.getElementById('fAnggaran').value = angNum ? Number(angNum).toLocaleString('id-ID') : '';
        document.getElementById('fKeterangan').value = i.keterangan || '';
        document.getElementById('grandTotalOps').textContent = fmt(angNum);
    } else {
        clearItemRows();
        if (i.items && i.items.length) {
            i.items.forEach(it => addItemRow(it.keterangan, it.qty, it.harga, it.sisaStok || '', it.satuan || ''));
        } else {
            addItemRow('', 1, i.jumlah || 0, '');
        }
        recalcTotal();
    }

    document.getElementById('modalAdd').style.display = 'flex';
}

async function saveItem() {
    const eid = document.getElementById('editId').value;
    const jenis = document.getElementById('fJenis').value;
    const tgl = document.getElementById('fTanggal').value;
    const tujuan = document.getElementById('fTujuan').value.trim();
    if (!tgl) { showToast('Tanggal wajib diisi.', 'error'); return; }
    if (!tujuan) { showToast('Tujuan wajib diisi.', 'error'); return; }

    let items = [];
    let jumlah = 0;
    let keterangan = '';

    if (jenis === 'operasional') {
        jumlah = parseHarga(document.getElementById('fAnggaran').value);
        if (!jumlah) { showToast('Anggaran yang diajukan wajib diisi.', 'error'); return; }
        keterangan = document.getElementById('fKeterangan').value.trim();
    } else {
        items = getItemsFromRows();
        if (!items.length || items.every(it => !it.keterangan)) {
            showToast('Minimal satu item wajib diisi.', 'error');
            return;
        }
        jumlah = items.reduce((s, it) => s + it.subtotal, 0);
    }

    const payload = {
        id: eid ? parseInt(eid) : null,
        jenis, tanggal: tgl, tujuan, items, jumlah, keterangan,
    };

    try {
        const res = await fetch('../database/add-pengajuan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        // Baca sebagai text dulu agar bisa debug jika bukan JSON
        const text = await res.text();
        dbg('add-pengajuan response:', text.slice(0, 300));

        let json = {};
        try { json = JSON.parse(text); } catch (e) {
            // PHP mungkin return bukan JSON (misal echo "ok" atau ada warning PHP)
            // Kalau HTTP status OK, anggap berhasil
            if (res.ok) {
                json = { success: true, id: null };
            } else {
                throw new Error('Server error: ' + text.slice(0, 100));
            }
        }

        // Handle berbagai format: {success}, {status:"ok"}, {error}, HTTP status
        const isSuccess = json.success === true
            || json.success === 1
            || json.status === 'ok'
            || json.status === 'success'
            || (res.ok && json.error == null && json.message == null);

        if (!isSuccess) {
            throw new Error(json.message || json.error || 'Gagal simpan');
        }

        // Gunakan id dari server, atau generate lokal jika tidak ada
        const newId = json.id ? Number(json.id) : nextId++;

        if (eid) {
            const item = data.find(x => x.id === parseInt(eid));
            if (item) Object.assign(item, { jenis, tanggal: tgl, tujuan, items, jumlah });
        } else {
            data.push({
                id: newId, jenis, tanggal: tgl, tujuan, items, jumlah,
                status: 'pending', saldo: 0, bukti: '', buktiName: '',
                catatan: '', approvedAt: '', alasan: '',
            });
        }

        closeModal('modalAdd');
        render();
        showToast(eid ? 'Pengajuan berhasil diperbarui.' : 'Pengajuan berhasil disimpan.', 'success');
    } catch (e) {
        console.error('saveItem error:', e);
        showToast('Error: ' + e.message, 'error');
    }
}

// ===== DELETE =====
async function deleteItem(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const label = i.tujuan || (i.items && i.items.length ? i.items[0].keterangan : 'pengajuan ini');
    if (!confirm(`Hapus pengajuan "${label}"?`)) return;
    try {
        const res = await fetch('../database/add-pengajuan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Gagal hapus');
        data = data.filter(x => x.id !== id);
        render();
        showToast('Pengajuan dihapus.', 'success');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

// ===== MODAL DETAIL =====
function openDetail(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const rows = (i.items || []).map(it => `<tr>
        <td>${it.keterangan || '-'}</td>
        <td style="text-align:center">${it.qty}${it.satuan ? ' <span style="color:var(--text-muted);font-size:11px">' + it.satuan + '</span>' : ''}</td>
        <td style="text-align:right">${fmt(it.harga)}</td>
        <td style="text-align:right;font-weight:500">${fmt(it.subtotal)}</td>
    </tr>`).join('');
    document.getElementById('detailContent').innerHTML = `
        ${i.tujuan ? `<p style="margin-bottom:10px;font-size:13px;color:var(--text-secondary)">Tujuan: <strong style="color:var(--text-primary)">${i.tujuan}</strong></p>` : ''}
        ${i.keterangan ? `<p style="margin-bottom:10px;font-size:13px;color:var(--text-secondary)">Keterangan: <strong style="color:var(--text-primary)">${i.keterangan}</strong></p>` : ''}
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:left;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border)">Keterangan</th>
                    <th style="text-align:center;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border);width:70px">Qty</th>
                    <th style="text-align:right;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border);width:120px">Harga</th>
                    <th style="text-align:right;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border);width:120px">Subtotal</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        <div class="total-bar" style="margin-top:0;border-radius:0 0 8px 8px">
            <span class="total-label">Total</span>
            <span class="total-value">${fmt(i.jumlah)}</span>
        </div>
    `;
    document.getElementById('modalDetail').style.display = 'flex';
}

// ===== EKSPOR PDF PER TUJUAN =====
function exportPDF(id) {
    window.open('cetak-ajuan.php?id=' + encodeURIComponent(id), '_blank');
}

// ===== MODAL APPROVAL =====
function openApproval(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    document.getElementById('approvalId').value = id;
    const descItems = (i.items || []).map(it => it.keterangan).filter(Boolean);
    document.getElementById('approvalDesc').textContent = i.tujuan
        ? i.tujuan + (descItems.length ? ` (${descItems.length} item)` : '')
        : (descItems[0] || '-') + (descItems.length > 1 ? ` (+${descItems.length - 1} lainnya)` : '');
    document.getElementById('approvalJumlah').textContent = fmt(i.jumlah);
    document.getElementById('aSaldo').value = i.saldo ? Number(i.saldo).toLocaleString('id-ID') : '';
    document.getElementById('aAlasan').value = i.alasan || '';
    document.getElementById('aCatatan').value = i.catatan || '';
    tmpBukti = i.bukti || null;
    tmpBuktiName = i.buktiName || '';

    currentKeputusan = '';
    document.getElementById('btnSetuju').className = 'keputusan-btn';
    document.getElementById('btnTolak').className = 'keputusan-btn';
    document.getElementById('approvedFields').className = 'approval-fields';
    document.getElementById('rejectedFields').className = 'approval-fields';
    document.getElementById('btnSimpanApproval').style.display = 'none';

    if (i.status !== 'pending') pilihKeputusan(i.status);

    const buktiExist = document.getElementById('aBuktiExist');
    const buktiEmpty = document.getElementById('aBuktiEmpty');
    if (i.buktiName) {
        buktiExist.style.display = 'block';
        buktiEmpty.style.display = 'none';
        document.getElementById('aBuktiNote').textContent = i.buktiName;
        const img = document.getElementById('aBuktiImg');
        if (i.bukti && i.bukti.startsWith('data:image')) {
            img.src = i.bukti; img.style.display = 'block';
        } else if (i.bukti) {
            img.src = resolveBuktiUrl(i.bukti); img.style.display = 'block';
        } else { img.style.display = 'none'; }
    } else {
        buktiExist.style.display = 'none';
        buktiEmpty.style.display = 'block';
    }

    document.getElementById('buktiLabel').textContent = 'Klik untuk upload bukti transfer';
    document.getElementById('modalApproval').style.display = 'flex';
}

function pilihKeputusan(v) {
    currentKeputusan = v;
    document.getElementById('btnSetuju').className = 'keputusan-btn' + (v === 'approved' ? ' selected-approve' : '');
    document.getElementById('btnTolak').className = 'keputusan-btn' + (v === 'rejected' ? ' selected-reject' : '');
    document.getElementById('approvedFields').className = 'approval-fields' + (v === 'approved' ? ' open' : '');
    document.getElementById('rejectedFields').className = 'approval-fields' + (v === 'rejected' ? ' open' : '');
    document.getElementById('btnSimpanApproval').style.display = 'inline-flex';
}

function previewBukti(inputEl) {
    const f = inputEl.files[0];
    if (!f) return;
    tmpBuktiName = f.name;
    document.getElementById('buktiLabel').textContent = f.name;
    const r = new FileReader();
    r.onload = e => {
        tmpBukti = e.target.result;
        document.getElementById('aBuktiExist').style.display = 'block';
        document.getElementById('aBuktiEmpty').style.display = 'none';
        document.getElementById('aBuktiNote').textContent = f.name;
        const img = document.getElementById('aBuktiImg');
        img.src = e.target.result; img.style.display = 'block';
    };
    r.readAsDataURL(f);
}

// Bukti bisa berupa data URL base64 (baru dipilih user, belum tersimpan)
// atau path relatif dari server (sudah tersimpan, misal "bukti_approval_koperasi/xxx.jpg").
function resolveBuktiUrl(bukti) {
    if (!bukti) return '';
    if (bukti.startsWith('data:image')) return bukti;
    return '../uploads/' + bukti;
}

async function saveApproval() {
    const id = parseInt(document.getElementById('approvalId').value);
    const i = data.find(x => x.id === id);
    if (!i) return;
    if (!currentKeputusan) { showToast('Pilih keputusan terlebih dahulu.', 'error'); return; }

    const payload = { action: 'approval', id, status: currentKeputusan };
    if (currentKeputusan === 'approved') {
        payload.saldo = parseHarga(document.getElementById('aSaldo').value);
        payload.catatan = document.getElementById('aCatatan').value;
        // Bukti transfer wajib diikutkan di payload — sebelumnya tmpBukti
        // cuma dipakai buat update tampilan lokal, jadi server ga pernah nerima datanya.
        payload.bukti = tmpBukti || '';
        payload.buktiName = tmpBuktiName || '';
    } else {
        payload.alasan = document.getElementById('aAlasan').value;
    }

    try {
        const res = await fetch('../database/add-pengajuan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (!result.success) {
            showToast(result.message || 'Gagal menyimpan keputusan.', 'error');
            return;
        }

        // Sinkronkan state lokal HANYA setelah backend konfirmasi sukses
        i.status = currentKeputusan;
        if (currentKeputusan === 'approved') {
            i.saldo = payload.saldo;
            i.bukti = tmpBukti || i.bukti || '';
            i.buktiName = tmpBuktiName || i.buktiName || '';
            i.catatan = payload.catatan;
            i.approvedAt = today();
            i.alasan = '';
        } else {
            i.alasan = payload.alasan;
            i.saldo = 0; i.bukti = ''; i.buktiName = '';
        }

        closeModal('modalApproval');
        render();
        showToast('Keputusan berhasil disimpan.');
    } catch (err) {
        showToast('Terjadi kesalahan saat menyimpan keputusan.', 'error');
    }
}

// ===== MODAL LIHAT BUKTI TF =====
function viewBuktiSingle(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    document.getElementById('viewSaldo').textContent = fmt(i.saldo || 0);
    document.getElementById('viewTgl').textContent = fmtDate(i.approvedAt || i.tanggal);
    const img = document.getElementById('viewBuktiImg');
    const note = document.getElementById('viewBuktiNote');
    if (i.bukti) {
        img.src = resolveBuktiUrl(i.bukti); img.style.display = 'block';
        note.textContent = i.buktiName || '';
    } else if (i.buktiName) {
        img.style.display = 'none';
        note.textContent = 'File: ' + i.buktiName;
    } else {
        img.style.display = 'none';
        note.textContent = 'Belum ada bukti transfer diupload.';
    }
    const cat = i.catatan;
    if (cat) {
        document.getElementById('viewCatatan').style.display = 'block';
        document.getElementById('viewCatatanText').textContent = cat;
    } else {
        document.getElementById('viewCatatan').style.display = 'none';
    }
    document.getElementById('modalBukti').style.display = 'flex';
}

// ===== TOAST =====
function showToast(msg, type = 'success') {
    // Remove any existing toasts first to avoid stacking
    document.querySelectorAll('.app-toast').forEach(t => t.remove());

    const el = document.createElement('div');
    el.className = `app-toast app-toast--${type}`;
    el.innerHTML = `<i class="ti ${type === 'success' ? 'ti-circle-check' : 'ti-circle-x'}"></i> ${msg}`;

    // Append langsung ke <body> — jangan ke elemen lain yang punya stacking context
    document.body.appendChild(el);

    // Paksa reflow agar animasi jalan
    el.getBoundingClientRect();

    // Auto-remove setelah 3s dengan fade-out
    setTimeout(() => {
        el.style.transition = 'opacity 0.3s ease';
        el.style.opacity = '0';
        setTimeout(() => { if (el.parentNode) el.remove(); }, 300);
    }, 2700);
}

// ===== MODAL HELPERS =====
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function closeOnBg(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function () {
    loadData();
    loadStokData();
});

// ===== FITUR TANDA TANGAN DIGITAL (TAMBAHAN BARU) =====
let currentSigPengajuanId = null;
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let signatureDataList = [];

function openSignature(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    currentSigPengajuanId = id;

    document.getElementById('sigPengajuanTitle').textContent = i.tujuan || (i.items && i.items[0] ? i.items[0].keterangan : '-');
    document.getElementById('sigPengajuanTotal').textContent = fmt(i.jumlah);
    document.getElementById('sigCanvasArea').style.display = 'none';
    document.getElementById('sigStatusList').innerHTML = '<i>Memuat data tanda tangan...</i>';

    // Auto-set role sesuai login, lalu langsung buka canvas
    const select = document.getElementById('sigRole');
    const roleMap = {
        'ketua': 'ketua',
        'bendahara': 'bendahara',
        'admin': 'admin',
    };

    if (SESSION_ROLE && roleMap[SESSION_ROLE]) {
        select.value = roleMap[SESSION_ROLE];
        // Sembunyikan semua option, tampilkan hanya yang sesuai role
        Array.from(select.options).forEach(opt => {
            if (opt.value === '' || opt.value === roleMap[SESSION_ROLE]) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    } else {
        select.value = '';
    }

    fetchSignatures(id).then(() => {
        if (select.value) prepareSignatureCanvas();
    });

    document.getElementById('modalSignature').style.display = 'flex';
}

function prepareSignatureCanvas() {
    const role = document.getElementById('sigRole').value;
    const canvasArea = document.getElementById('sigCanvasArea');
    if (!role) {
        canvasArea.style.display = 'none';
        return;
    }
    canvasArea.style.display = 'block';

    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');

    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;

    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Ambil elemen tombol
    const btnArea = document.getElementById('sigBtnArea');

    const existing = signatureDataList.find(s => s.role_penanda === role);

    if (existing && existing.signature_data) {
        // Gambar TTD yang sudah ada
        const img = new Image();
        img.onload = () => { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); };
        img.src = existing.signature_data;

        // Kunci canvas — hapus semua event
        canvas.onmousedown = null;
        canvas.onmousemove = null;
        canvas.onmouseup = null;
        canvas.onmouseout = null;
        canvas.ontouchstart = null;
        canvas.ontouchmove = null;
        canvas.ontouchend = null;
        canvas.style.cursor = 'not-allowed';

        // Ganti tombol
        btnArea.innerHTML = `
            <span style="font-size:12px;color:green"><i class="ti ti-circle-check"></i> Tanda tangan sudah tersimpan</span>
            <button class="btn sm" onclick="resetSignatureCanvas()"><i class="ti ti-refresh"></i> Ulangi TTD</button>
        `;
    } else {
        // Canvas kosong, aktifkan drawing
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.cursor = 'crosshair';

        btnArea.innerHTML = `
            <button class="btn sm danger" onclick="clearSignatureCanvas()"><i class="ti ti-trash"></i> Hapus TTD</button>
            <button class="btn sm primary" onclick="saveSignature()"><i class="ti ti-check"></i> Simpan Tanda Tangan</button>
        `;

        canvas.onmousedown = (e) => { isDrawing = true;[lastX, lastY] = [e.offsetX, e.offsetY]; };
        canvas.onmousemove = (e) => {
            if (!isDrawing) return;
            ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke();
            [lastX, lastY] = [e.offsetX, e.offsetY];
        };
        canvas.onmouseup = () => isDrawing = false;
        canvas.onmouseout = () => isDrawing = false;

        canvas.ontouchstart = (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            isDrawing = true;
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
        };
        canvas.ontouchmove = (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
            lastX = x; lastY = y;
        };
        canvas.ontouchend = () => isDrawing = false;
    }
}

function resetSignatureCanvas() {
    const role = document.getElementById('sigRole').value;
    signatureDataList = signatureDataList.filter(s => s.role_penanda !== role);
    prepareSignatureCanvas();
}

function clearSignatureCanvas() {
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

async function saveSignature() {
    const role = document.getElementById('sigRole').value;
    if (!role) { showToast('Pilih penandatangan terlebih dahulu.', 'error'); return; }

    const canvas = document.getElementById('signatureCanvas');
    const blank = document.createElement('canvas');
    blank.width = canvas.width; blank.height = canvas.height;
    if (canvas.toDataURL() === blank.toDataURL()) {
        showToast('Tanda tangan masih kosong.', 'error'); return;
    }

    const signatureDataUrl = canvas.toDataURL('image/png');

    try {
        const res = await fetch('../database/save-signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pengajuan_id: currentSigPengajuanId,
                role_penanda: role,
                signature_data: signatureDataUrl
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Tanda tangan berhasil disimpan.', 'success');
            fetchSignatures(currentSigPengajuanId).then(() => {
                prepareSignatureCanvas();
            });
        } else {
            showToast(json.message || 'Gagal menyimpan tanda tangan.', 'error');
        }
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function fetchSignatures(pengajuanId) {
    try {
        const res = await fetch(`../database/get-signatures.php?pengajuan_id=${pengajuanId}`);
        const json = await res.json();
        const arr = extractArray(json) || [];
        signatureDataList = arr;

        const statusList = document.getElementById('sigStatusList');
        if (arr.length === 0) {
            statusList.innerHTML = '<i>Belum ada tanda tangan.</i>';
            return;
        }

        let html = '';
        arr.forEach(s => {
            let roleName = s.role_penanda;
            if (s.role_penanda === 'ketua') roleName = 'Yudi Hendrian (Ketua)';
            if (s.role_penanda === 'bendahara') roleName = 'Nancy Febi Yolla (Bendahara)';
            if (s.role_penanda === 'admin') roleName = 'Evin Yentiana (Admin)';

            html += `<span class="sig-badge ${s.role_penanda} done">
                <i class="ti ti-check"></i> ${roleName}
            </span>`;
        });
        statusList.innerHTML = html;
    } catch (e) {
        document.getElementById('sigStatusList').innerHTML = '<i>Gagal memuat data.</i>';
    }
}