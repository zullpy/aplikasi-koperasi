// ===== DATA =====
// Struktur baru: setiap pengajuan punya array `items` [{keterangan, qty, harga, subtotal}]
// jumlah = total semua subtotal
let data = [
    {
        id: 1, jenis: 'stok', tanggal: '2025-06-10',
        items: [
            { keterangan: 'Beli beras', qty: 50, harga: 8000, subtotal: 400000 },
            { keterangan: 'Beli garam', qty: 10, harga: 10000, subtotal: 100000 },
        ],
        jumlah: 500000,
        status: 'approved', saldo: 500000, bukti: '', buktiName: 'bukti_beras.jpg', catatan: 'Sudah cair', approvedAt: '2025-06-11', alasan: ''
    },
    {
        id: 2, jenis: 'stok', tanggal: '2025-06-10',
        items: [
            { keterangan: 'Beli minyak goreng', qty: 20, harga: 15000, subtotal: 300000 },
        ],
        jumlah: 300000,
        status: 'pending', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: ''
    },
    {
        id: 3, jenis: 'lainlain', tanggal: '2025-06-10',
        items: [
            { keterangan: 'Beli printer kantor', qty: 1, harga: 1500000, subtotal: 1500000 },
        ],
        jumlah: 1500000,
        status: 'rejected', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: 'Anggaran tidak mencukupi'
    },
    {
        id: 4, jenis: 'lainlain', tanggal: '2025-06-12',
        items: [
            { keterangan: 'Beli lemari showcase', qty: 1, harga: 2000000, subtotal: 2000000 },
        ],
        jumlah: 2000000,
        status: 'pending', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: ''
    },
    {
        id: 5, jenis: 'stok', tanggal: '2025-06-15',
        items: [
            { keterangan: 'Beli gula pasir', qty: 30, harga: 15000, subtotal: 450000 },
        ],
        jumlah: 450000,
        status: 'approved', saldo: 450000, bukti: '', buktiName: 'bukti_gula.jpg', catatan: '', approvedAt: '2025-06-15', alasan: ''
    },
];

let nextId = 10;
let tmpBukti = null;
let tmpBuktiName = '';
let currentKeputusan = '';
let itemRowCount = 0;

// ===== HELPERS =====
function fmt(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function fmtDate(d) {
    if (!d) return '-';
    const p = d.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

// ===== GROUPING & RENDER =====
function grouped(items) {
    const m = {};
    items.forEach(i => {
        if (!m[i.tanggal]) m[i.tanggal] = [];
        m[i.tanggal].push(i);
    });
    return Object.keys(m)
        .sort((a, b) => b.localeCompare(a))
        .map(k => ({ tanggal: k, items: m[k] }));
}

function render() {
    const from = document.getElementById('filterFrom').value;
    const to = document.getElementById('filterTo').value;
    let filtered = data;
    if (from) filtered = filtered.filter(i => i.tanggal >= from);
    if (to) filtered = filtered.filter(i => i.tanggal <= to);
    const groups = grouped(filtered);
    const el = document.getElementById('list');
    if (!groups.length) {
        el.innerHTML = '<div class="empty"><i class="ti ti-inbox" style="font-size:32px;display:block;margin-bottom:8px"></i>Tidak ada data pengajuan</div>';
        return;
    }
    el.innerHTML = groups.map(g => renderGroup(g)).join('');
}

function renderGroup(g) {
    const hasSaldo = g.items.some(i => i.status === 'approved' && i.saldo > 0);
    const totalSaldo = g.items.filter(i => i.status === 'approved').reduce((s, i) => s + i.saldo, 0);
    const buktiItems = g.items.filter(i => i.status === 'approved' && i.saldo > 0);
    const hasBukti = buktiItems.some(i => i.buktiName);
    const buktiBtn = hasSaldo
        ? (hasBukti
            ? `<span class="bukti-chip" onclick="viewBuktiGroup('${g.tanggal}')"><i class="ti ti-file-check" aria-hidden="true"></i> Lihat Bukti TF</span>`
            : `<span class="no-bukti-chip" onclick="viewBuktiGroup('${g.tanggal}')"><i class="ti ti-alert-triangle" aria-hidden="true"></i> Bukti TF belum ada</span>`)
        : '';
    const saldoBadge = hasSaldo ? `<span class="saldo-badge">${fmt(totalSaldo)}</span>` : '';
    const rows = g.items.map(i => renderRow(i)).join('');

    return `<div class="group-block">
    <div class="group-header">
      <div class="group-header-left">
        <i class="ti ti-calendar" style="color:var(--text-secondary)" aria-hidden="true"></i>
        <span class="group-date">${fmtDate(g.tanggal)}</span>
        ${saldoBadge}${buktiBtn}
      </div>
      <span style="font-size:12px;color:var(--text-muted)">${g.items.length} pengajuan</span>
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:80px">Jenis</th>
          <th>Keterangan</th>
          <th style="width:70px;text-align:center">Item</th>
          <th style="width:130px">Total diajukan</th>
          <th style="width:85px">Status</th>
          <th style="width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  </div>`;
}

function renderRow(i) {
    const jenisBadge = `<span class="badge ${i.jenis === 'stok' ? 'stok' : 'lainlain'}">${i.jenis === 'stok' ? 'Stok' : 'Lain-lain'}</span>`;

    let statusBadge = '';
    if (i.status === 'pending') statusBadge = '<span class="badge pending"><i class="ti ti-clock" style="font-size:10px"></i> Pending</span>';
    else if (i.status === 'approved') statusBadge = '<span class="badge approved"><i class="ti ti-check" style="font-size:10px"></i> Disetujui</span>';
    else statusBadge = '<span class="badge rejected"><i class="ti ti-x" style="font-size:10px"></i> Ditolak</span>';

    // Ringkasan keterangan: tampilkan item pertama + sisanya
    const firstItem = i.items && i.items.length ? i.items[0].keterangan : '-';
    const extraCount = i.items && i.items.length > 1 ? `<span class="more-badge">+${i.items.length - 1} lainnya</span>` : '';
    const ketEl = `<span class="ket-link" onclick="openDetail(${i.id})">${firstItem}</span>${extraCount}`;

    return `<tr>
    <td>${jenisBadge}</td>
    <td>${ketEl}</td>
    <td style="text-align:center">${i.items ? i.items.length : 1}</td>
    <td>${fmt(i.jumlah)}</td>
    <td>${statusBadge}</td>
    <td><div class="actions">
      <button class="btn sm success-btn" onclick="openApproval(${i.id})" title="Approval"><i class="ti ti-shield-check" aria-hidden="true"></i> Approval</button>
      <button class="btn sm" onclick="openEdit(${i.id})" title="Edit"><i class="ti ti-edit" aria-hidden="true"></i></button>
      <button class="btn sm danger" onclick="deleteItem(${i.id})" title="Hapus"><i class="ti ti-trash" aria-hidden="true"></i></button>
    </div></td>
  </tr>`;
}

// ===== FILTER =====
function clearFilter() {
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
    render();
}

// ===== FORMAT HARGA (titik ribuan saat mengetik) =====
function parseHarga(str) {
    // Hapus semua titik, ambil angka murni
    return parseFloat(String(str).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
}

function formatHarga(str) {
    const num = parseHarga(str);
    if (!num) return '';
    return num.toLocaleString('id-ID'); // hasilkan format 1.500.000
}

function onHargaInput(el, rid) {
    const raw = el.value.replace(/\./g, '').replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    // Simpan posisi kursor agar tidak loncat
    const formatted = num ? num.toLocaleString('id-ID') : '';
    el.value = formatted;
    recalcRow(rid);
}

// ===== ITEM ROWS (multi-item form) =====
function addItemRow(ket = '', qty = '', harga = '') {
    itemRowCount++;
    const rid = 'row_' + itemRowCount;
    const tbody = document.getElementById('itemRows');
    const tr = document.createElement('tr');
    tr.id = rid;
    tr.dataset.rid = rid;

    const hargaFormatted = harga ? Number(harga).toLocaleString('id-ID') : '';

    tr.innerHTML = `
        <td>
            <button class="btn sm danger icon-only" onclick="removeItemRow('${rid}')" title="Hapus baris">
                <i class="ti ti-trash" aria-hidden="true"></i>
            </button>
        </td>
        <td><input type="text" class="row-ket" placeholder="Keterangan item" value="${ket}" oninput="recalcRow('${rid}')" style="width:100%"></td>
        <td><input type="number" class="row-qty" placeholder="0" value="${qty}" min="0" oninput="recalcRow('${rid}')" style="width:100%"></td>
        <td><input type="text" class="row-harga" placeholder="0" value="${hargaFormatted}" inputmode="numeric" oninput="onHargaInput(this,'${rid}')" style="width:100%"></td>
        <td class="subtotal-cell" style="text-align:right;font-weight:500;white-space:nowrap">Rp 0</td>
    `;
    tbody.appendChild(tr);
    recalcRow(rid);
    tr.querySelector('.row-ket').focus();
}

function removeItemRow(rid) {
    const tr = document.getElementById(rid);
    if (!tr) return;
    const rows = document.getElementById('itemRows').querySelectorAll('tr');
    if (rows.length <= 1) {
        tr.querySelector('.row-ket').value = '';
        tr.querySelector('.row-qty').value = '';
        tr.querySelector('.row-harga').value = '';
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
        const subtotal = qty * harga;
        if (ket || qty || harga) {
            items.push({ keterangan: ket, qty, harga, subtotal });
        }
    });
    return items;
}

function clearItemRows() {
    document.getElementById('itemRows').innerHTML = '';
    itemRowCount = 0;
}

// ===== MODAL TAMBAH / EDIT =====
function openAdd() {
    document.getElementById('editId').value = '';
    document.getElementById('modalAddTitle').textContent = 'Tambah Pengajuan';
    document.getElementById('fJenis').value = 'stok';
    document.getElementById('fTanggal').value = today();
    clearItemRows();
    addItemRow();
    recalcTotal();
    document.getElementById('modalAdd').style.display = 'flex';
}

function openEdit(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    document.getElementById('editId').value = id;
    document.getElementById('modalAddTitle').textContent = 'Edit Pengajuan';
    document.getElementById('fJenis').value = i.jenis;
    document.getElementById('fTanggal').value = i.tanggal;
    clearItemRows();
    if (i.items && i.items.length) {
        i.items.forEach(it => addItemRow(it.keterangan, it.qty, it.harga));
    } else {
        // Legacy: satu item dari keterangan lama
        addItemRow(i.keterangan || '', 1, i.jumlah || 0);
    }
    recalcTotal();
    document.getElementById('modalAdd').style.display = 'flex';
}

function saveItem() {
    const eid = document.getElementById('editId').value;
    const jenis = document.getElementById('fJenis').value;
    const tgl = document.getElementById('fTanggal').value;

    if (!tgl) {
        alert('Tanggal wajib diisi.');
        return;
    }

    const items = getItemsFromRows();
    if (!items.length || items.every(it => !it.keterangan)) {
        alert('Minimal satu keterangan item wajib diisi.');
        return;
    }

    const jumlah = items.reduce((s, it) => s + it.subtotal, 0);

    if (eid) {
        const item = data.find(x => x.id === parseInt(eid));
        if (item) Object.assign(item, { jenis, tanggal: tgl, items, jumlah });
    } else {
        data.push({
            id: nextId++, jenis, tanggal: tgl, items, jumlah,
            status: 'pending', saldo: 0, bukti: '', buktiName: '',
            catatan: '', approvedAt: '', alasan: '',
        });
    }
    closeModal('modalAdd');
    render();
}

// ===== DELETE =====
function deleteItem(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const label = i.items && i.items.length ? i.items[0].keterangan : 'pengajuan ini';
    if (!confirm(`Hapus pengajuan "${label}"?`)) return;
    data = data.filter(x => x.id !== id);
    render();
}

// ===== MODAL DETAIL =====
function openDetail(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const rows = (i.items || []).map(it => `
        <tr>
            <td>${it.keterangan || '-'}</td>
            <td style="text-align:center">${it.qty}</td>
            <td style="text-align:right">${fmt(it.harga)}</td>
            <td style="text-align:right;font-weight:500">${fmt(it.subtotal)}</td>
        </tr>
    `).join('');

    document.getElementById('detailContent').innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:left;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border)">Keterangan</th>
                    <th style="text-align:center;padding:6px 8px;font-size:12px;color:var(--text-secondary);border-bottom:0.5px solid var(--border);width:50px">Qty</th>
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

// ===== MODAL APPROVAL =====
function openApproval(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;

    document.getElementById('approvalId').value = id;
    // Keterangan ringkas untuk info box
    const descItems = (i.items || []).map(it => it.keterangan).filter(Boolean);
    document.getElementById('approvalDesc').textContent = descItems.length > 1
        ? descItems[0] + ' (+' + (descItems.length - 1) + ' item lainnya)'
        : (descItems[0] || '-');
    document.getElementById('approvalJumlah').textContent = fmt(i.jumlah);
    document.getElementById('aSaldo').value = i.saldo || '';
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
            img.src = i.bukti;
            img.style.display = 'block';
        } else {
            img.style.display = 'none';
        }
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

function previewBukti() {
    const f = document.getElementById('aBuktiFile').files[0];
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
        img.src = e.target.result;
        img.style.display = 'block';
    };
    r.readAsDataURL(f);
}

function saveApproval() {
    const id = parseInt(document.getElementById('approvalId').value);
    const i = data.find(x => x.id === id);
    if (!i) return;
    if (!currentKeputusan) { alert('Pilih keputusan terlebih dahulu.'); return; }
    i.status = currentKeputusan;
    if (currentKeputusan === 'approved') {
        i.saldo = parseFloat(document.getElementById('aSaldo').value) || 0;
        i.bukti = tmpBukti || i.bukti || '';
        i.buktiName = tmpBuktiName || i.buktiName || '';
        i.catatan = document.getElementById('aCatatan').value;
        i.approvedAt = today();
        i.alasan = '';
    } else {
        i.alasan = document.getElementById('aAlasan').value;
        i.saldo = 0;
        i.bukti = '';
        i.buktiName = '';
    }
    closeModal('modalApproval');
    render();
}

// ===== MODAL LIHAT BUKTI TF =====
function viewBuktiGroup(tanggal) {
    const items = data.filter(i => i.tanggal === tanggal && i.status === 'approved' && i.saldo > 0);
    if (!items.length) return;
    const totalSaldo = items.reduce((s, i) => s + i.saldo, 0);
    const buktiItem = items.find(i => i.buktiName) || items[0];
    document.getElementById('viewSaldo').textContent = fmt(totalSaldo);
    document.getElementById('viewTgl').textContent = fmtDate(buktiItem.approvedAt || tanggal);
    const img = document.getElementById('viewBuktiImg');
    const note = document.getElementById('viewBuktiNote');
    if (buktiItem.bukti && buktiItem.bukti.startsWith('data:image')) {
        img.src = buktiItem.bukti;
        img.style.display = 'block';
        note.textContent = buktiItem.buktiName;
    } else if (buktiItem.buktiName) {
        img.style.display = 'none';
        note.textContent = 'File: ' + buktiItem.buktiName;
    } else {
        img.style.display = 'none';
        note.textContent = 'Belum ada bukti transfer diupload.';
    }
    const cat = buktiItem.catatan;
    if (cat) {
        document.getElementById('viewCatatan').style.display = 'block';
        document.getElementById('viewCatatanText').textContent = cat;
    } else {
        document.getElementById('viewCatatan').style.display = 'none';
    }
    document.getElementById('modalBukti').style.display = 'flex';
}

// ===== MODAL HELPERS =====
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function closeOnBg(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
}

// ===== INIT =====
render();