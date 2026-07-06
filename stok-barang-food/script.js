const PAGE_SIZE = 10;
let currentPage = 1;
let data = [];

function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

function fmtQty(n) {
  n = n || 0;
  // Kalau bulat, tampilkan tanpa desimal. Kalau ada pecahan, tampilkan max 2 desimal.
  return Number.isInteger(n) ? n.toLocaleString('id-ID') : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

async function loadData() {
  try {
    const res = await fetch('../database/get-stok-barang.php');
    const json = await res.json();
    data = (json.status === 'success') ? json.data : [];
  } catch (e) { console.error(e); data = []; }
  draw();
}

// Ambil total stok (dalam satuan eceran / satuan terkecil) untuk satu gudang
function stokOf(g) {
  if (!g) return 0;
  return g.stok_eceran || 0;
}

// Title Case buat label satuan, cth: "dus" -> "Dus", "pcs" -> "Pcs"
function ucSatuan(s) {
  if (!s) return '';
  return s.toString().trim().toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}

// Format stok jadi gabungan satuan besar + sisa satuan eceran.
// Contoh: isi_per_satuan = 24, stok_eceran = 47  ->  "1 Dus 23 Pcs"
// Kalau barang tidak punya isi_per_satuan (satuan tunggal), tampil polos: "47 Kg"
function fmtStok(d, g) {
  const stokEceran = stokOf(g);
  if (stokEceran <= 0) return null; // ditangani di caller (tampilkan 0)

  const isi = d.isi_per_satuan;
  if (!isi || isi <= 0) {
    return `${fmtQty(stokEceran)} ${ucSatuan(d.satuan_eceran || d.satuan)}`;
  }

  const besar = Math.floor(stokEceran / isi);
  let sisa = stokEceran - (besar * isi);
  sisa = Math.round(sisa * 100) / 100;

  const parts = [];
  if (besar > 0) parts.push(`${fmtQty(besar)} ${ucSatuan(d.satuan)}`);
  if (sisa > 0) parts.push(`${fmtQty(sisa)} ${ucSatuan(d.satuan_eceran)}`);
  if (parts.length === 0) return null;
  return parts.join(' ');
}

function totalQtyOf(d) {
  return d.total_qty_eceran || 0;
}

function totalNilaiOf(d) {
  return d.total_nilai_eceran || 0;
}

function getStatus(totalQty) {
  if (totalQty <= 0) return { cls: 'st-habis', label: 'Habis' };
  if (totalQty <= 10) return { cls: 'st-rendah', label: 'Rendah' };
  if (totalQty <= 30) return { cls: 'st-menipis', label: 'Menipis' };
  return { cls: 'st-aman', label: 'Aman' };
}

function filtered() {
  const q = document.getElementById('search-input').value.toLowerCase();
  const gf = document.getElementById('filter-gudang').value;
  const sf = document.getElementById('filter-status').value;
  return data.filter(d => {
    const matchQ = !q || d.nama.toLowerCase().includes(q);
    let matchG = true;
    if (gf === 'pusat') matchG = stokOf(d.pusat) > 0;
    if (gf === 'sodong') matchG = stokOf(d.sodong) > 0;
    if (gf === 'sariwangi') matchG = stokOf(d.sariwangi) > 0;
    if (gf === 'manonjaya') matchG = stokOf(d.manonjaya) > 0;
    if (gf === 'habis') matchG = totalQtyOf(d) <= 0;
    let matchS = true;
    if (sf) matchS = getStatus(totalQtyOf(d)).cls === sf;
    return matchQ && matchG && matchS;
  });
}

function updateCards(rows) {
  let pusat = 0, cabang = 0, totalQty = 0;
  rows.forEach(d => {
    const harga = d.harga_eceran;
    pusat += stokOf(d.pusat) * harga;
    cabang += (stokOf(d.sodong) + stokOf(d.sariwangi) + stokOf(d.manonjaya)) * harga;
    totalQty += totalQtyOf(d);
  });
  document.getElementById('sum-pusat').textContent = rupiah(pusat);
  document.getElementById('sub-pusat').textContent = rows.filter(d => stokOf(d.pusat) > 0).length + ' item aktif';
  document.getElementById('sum-cabang').textContent = rupiah(cabang);
  document.getElementById('sum-total').textContent = rupiah(pusat + cabang);
  document.getElementById('sub-total').textContent = rows.length + ' barang · ' + fmtQty(totalQty) + ' unit';
}

function cellGudang(d, g, cls) {
  const label = fmtStok(d, g);
  if (!label) return `<td class="center"><span class="num-zero">0</span></td>`;
  return `<td class="center"><span class="num-stok ${cls}">${label}</span></td>`;
}

function renderTable() { currentPage = 1; draw(); }

function draw() {
  const rows = filtered();
  updateCards(rows);
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (currentPage > pages) currentPage = pages;
  const slice = rows.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  document.getElementById('item-count').textContent = total + ' barang';
  const from = total === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1;
  const to = Math.min(currentPage * PAGE_SIZE, total);
  document.getElementById('page-info').textContent = `Menampilkan ${from}–${to} dari ${total}`;

  const tbody = document.getElementById('tb-body');
  if (total === 0) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:#64748b;">Tidak ada data barang</td></tr>`;
  } else {
    tbody.innerHTML = slice.map((d, i) => {
      const tQty = totalQtyOf(d);
      const st = getStatus(tQty);
      const no = (currentPage - 1) * PAGE_SIZE + i + 1;
      return `
            <tr>
                <td><span class="sb-no">${no}</span></td>
                <td>
                    <div class="sb-nama">${d.nama}</div>
                    <div class="sb-satuan">${ucSatuan(d.satuan_eceran)} · <span class="sb-status ${st.cls}">${st.label}</span></div>
                </td>
                <td class="center"><span class="num-harga">${rupiah(d.harga_eceran)}</span></td>
                ${cellGudang(d, d.pusat, 'c-pusat')}
                ${cellGudang(d, d.sodong, 'c-sodong')}
                ${cellGudang(d, d.sariwangi, 'c-sariwangi')}
                ${cellGudang(d, d.manonjaya, 'c-manonjaya')}
                <td class="center"><span class="num-total-qty">${fmtQty(tQty)} ${ucSatuan(d.satuan_eceran)}</span></td>
                <td class="center"><span class="num-total-nilai">${rupiah(totalNilaiOf(d))}</span></td>
            </tr>`;
    }).join('');
  }

  // Pagination
  const pg = document.getElementById('pagination');
  const MAX_BTN = 5;
  let html = '';
  if (currentPage > 1) html += `<button onclick="goPage(${currentPage - 1})"><i class="ti ti-chevron-left" style="font-size:12px"></i></button>`;
  let s = Math.max(1, currentPage - Math.floor(MAX_BTN / 2));
  let e = s + MAX_BTN - 1;
  if (e > pages) { e = pages; s = Math.max(1, e - MAX_BTN + 1); }
  for (let p = s; p <= e; p++)
    html += `<button class="${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
  if (currentPage < pages) html += `<button onclick="goPage(${currentPage + 1})"><i class="ti ti-chevron-right" style="font-size:12px"></i></button>`;
  pg.innerHTML = html;
}

function goPage(p) { currentPage = p; draw(); }
function resetFilter() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-gudang').value = '';
  document.getElementById('filter-status').value = '';
  renderTable();
}

loadData();