// ===== DATA =====
let data = [
    { id: 1, jenis: 'stok', tanggal: '2025-06-10', keterangan: 'Beli beras 50 kg', jumlah: 500000, qty: 50, nota: '', notaName: 'nota_beras.jpg', status: 'approved', saldo: 500000, bukti: '', buktiName: 'bukti_beras.jpg', catatan: 'Sudah cair', approvedAt: '2025-06-11', alasan: '' },
    { id: 2, jenis: 'stok', tanggal: '2025-06-10', keterangan: 'Beli minyak goreng', jumlah: 300000, qty: 20, nota: '', notaName: 'nota_minyak.jpg', status: 'pending', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: '' },
    { id: 3, jenis: 'lainlain', tanggal: '2025-06-10', keterangan: 'Beli printer kantor', jumlah: 1500000, qty: 1, nota: '', notaName: '', status: 'rejected', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: 'Anggaran tidak mencukupi' },
    { id: 4, jenis: 'lainlain', tanggal: '2025-06-12', keterangan: 'Beli lemari showcase', jumlah: 2000000, qty: 1, nota: '', notaName: '', status: 'pending', saldo: 0, bukti: '', buktiName: '', catatan: '', approvedAt: '', alasan: '' },
    { id: 5, jenis: 'stok', tanggal: '2025-06-15', keterangan: 'Beli gula pasir 30 kg', jumlah: 450000, qty: 30, nota: '', notaName: 'nota_gula.jpg', status: 'approved', saldo: 450000, bukti: '', buktiName: 'bukti_gula.jpg', catatan: '', approvedAt: '2025-06-15', alasan: '' },
];

let nextId = 10;
let tmpNota = null;
let tmpNotaName = '';
let tmpBukti = null;
let tmpBuktiName = '';
let currentKeputusan = '';

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
          <th style="width:55px;text-align:center">Qty</th>
          <th style="width:120px">Jumlah diajukan</th>
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

    const notaInfo = i.jenis === 'stok' && i.notaName
        ? `<br><span style="font-size:11px;color:var(--text-muted)"><i class="ti ti-file"></i> ${i.notaName}</span>`
        : '';

    return `<tr>
    <td>${jenisBadge}</td>
    <td>${i.keterangan}${notaInfo}</td>
    <td style="text-align:center">${i.qty || '-'}</td>
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

// ===== MODAL TAMBAH / EDIT =====
function openAdd() {
    document.getElementById('editId').value = '';
    document.getElementById('modalAddTitle').textContent = 'Tambah Pengajuan';
    document.getElementById('fJenis').value = 'stok';
    document.getElementById('fTanggal').value = today();
    document.getElementById('fKeterangan').value = '';
    document.getElementById('fJumlah').value = '';
    document.getElementById('fQty').value = '';
    document.getElementById('fNota').value = '';
    document.getElementById('notaLabel').textContent = 'Klik untuk upload nota';
    document.getElementById('notaPreview').style.display = 'none';
    document.getElementById('btnSimpanLagi').style.display = 'inline-flex';
    tmpNota = null;
    tmpNotaName = '';
    toggleJenis();
    document.getElementById('modalAdd').style.display = 'flex';
}

function openEdit(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    document.getElementById('editId').value = id;
    document.getElementById('modalAddTitle').textContent = 'Edit Pengajuan';
    document.getElementById('fJenis').value = i.jenis;
    document.getElementById('fTanggal').value = i.tanggal;
    document.getElementById('fKeterangan').value = i.keterangan;
    document.getElementById('fJumlah').value = i.jumlah;
    document.getElementById('fQty').value = i.qty;
    document.getElementById('notaLabel').textContent = i.notaName || 'Klik untuk upload nota';
    document.getElementById('notaPreview').style.display = 'none';
    document.getElementById('btnSimpanLagi').style.display = 'none';
    tmpNota = i.nota || null;
    tmpNotaName = i.notaName || '';
    toggleJenis();
    document.getElementById('modalAdd').style.display = 'flex';
}

function toggleJenis() {
    const j = document.getElementById('fJenis').value;
    document.getElementById('notaWrap').style.display = j === 'stok' ? 'block' : 'none';
}

function previewNota() {
    const f = document.getElementById('fNota').files[0];
    if (!f) return;
    tmpNotaName = f.name;
    document.getElementById('notaLabel').textContent = f.name;
    const r = new FileReader();
    r.onload = e => {
        tmpNota = e.target.result;
        if (f.type.startsWith('image/')) {
            const img = document.getElementById('notaPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        }
    };
    r.readAsDataURL(f);
}

function saveItem(tambahLagi = false) {
    const eid = document.getElementById('editId').value;
    const jenis = document.getElementById('fJenis').value;
    const tgl = document.getElementById('fTanggal').value;
    const ket = document.getElementById('fKeterangan').value.trim();
    const jml = parseFloat(document.getElementById('fJumlah').value) || 0;
    const qty = parseFloat(document.getElementById('fQty').value) || 0;

    if (!tgl || !ket || !jml) {
        alert('Tanggal, keterangan, dan jumlah wajib diisi.');
        return;
    }

    if (eid) {
        const item = data.find(x => x.id === parseInt(eid));
        if (item) Object.assign(item, {
            jenis, tanggal: tgl, keterangan: ket, jumlah: jml, qty,
            nota: tmpNota || item.nota,
            notaName: tmpNotaName || item.notaName,
        });
        closeModal('modalAdd');
    } else {
        data.push({
            id: nextId++, jenis, tanggal: tgl, keterangan: ket, jumlah: jml, qty,
            nota: tmpNota || '', notaName: tmpNotaName || '',
            status: 'pending', saldo: 0, bukti: '', buktiName: '',
            catatan: '', approvedAt: '', alasan: '',
        });
        if (tambahLagi) {
            // Reset form tapi modal tetap terbuka, pertahankan tanggal & jenis
            const keepTgl = tgl;
            const keepJenis = jenis;
            document.getElementById('fKeterangan').value = '';
            document.getElementById('fJumlah').value = '';
            document.getElementById('fQty').value = '';
            document.getElementById('fNota').value = '';
            document.getElementById('notaLabel').textContent = 'Klik untuk upload nota';
            document.getElementById('notaPreview').style.display = 'none';
            document.getElementById('fTanggal').value = keepTgl;
            document.getElementById('fJenis').value = keepJenis;
            toggleJenis();
            tmpNota = null;
            tmpNotaName = '';
            // Flash feedback
            const btn = document.getElementById('btnSimpanLagi');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="ti ti-check"></i> Tersimpan!';
            btn.style.color = 'var(--text-success)';
            setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1200);
        } else {
            closeModal('modalAdd');
        }
    }
    render();
}

// ===== DELETE =====
function deleteItem(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;
    const msg = i.jenis === 'stok'
        ? `Hapus pengajuan "${i.keterangan}" beserta notanya?`
        : `Hapus pengajuan "${i.keterangan}"?`;
    if (!confirm(msg)) return;
    data = data.filter(x => x.id !== id);
    render();
}

// ===== MODAL APPROVAL =====
function openApproval(id) {
    const i = data.find(x => x.id === id);
    if (!i) return;

    document.getElementById('approvalId').value = id;
    document.getElementById('approvalDesc').textContent = i.keterangan;
    document.getElementById('approvalJumlah').textContent = fmt(i.jumlah);
    document.getElementById('aSaldo').value = i.saldo || '';
    document.getElementById('aAlasan').value = i.alasan || '';
    document.getElementById('aCatatan').value = i.catatan || '';
    tmpBukti = i.bukti || null;
    tmpBuktiName = i.buktiName || '';

    // reset tombol keputusan
    currentKeputusan = '';
    document.getElementById('btnSetuju').className = 'keputusan-btn';
    document.getElementById('btnTolak').className = 'keputusan-btn';
    document.getElementById('approvedFields').className = 'approval-fields';
    document.getElementById('rejectedFields').className = 'approval-fields';
    document.getElementById('btnSimpanApproval').style.display = 'none';

    // pre-select jika sudah ada status
    if (i.status !== 'pending') pilihKeputusan(i.status);

    // bukti state
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
    const setuju = document.getElementById('btnSetuju');
    const tolak = document.getElementById('btnTolak');
    const appF = document.getElementById('approvedFields');
    const rejF = document.getElementById('rejectedFields');
    const simpan = document.getElementById('btnSimpanApproval');

    setuju.className = 'keputusan-btn' + (v === 'approved' ? ' selected-approve' : '');
    tolak.className = 'keputusan-btn' + (v === 'rejected' ? ' selected-reject' : '');
    appF.className = 'approval-fields' + (v === 'approved' ? ' open' : '');
    rejF.className = 'approval-fields' + (v === 'rejected' ? ' open' : '');
    simpan.style.display = 'inline-flex';
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
    if (!currentKeputusan) {
        alert('Pilih keputusan terlebih dahulu.');
        return;
    }
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