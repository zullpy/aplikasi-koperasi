const PAGE_SIZE = 10;
let currentPage = 1;
let data = [];

function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

async function loadData() {
  try {
    const res = await fetch('../database/get-stok-barang.php');
    const json = await res.json();
    data = (json.status === 'success') ? json.data : [];
  } catch (e) { console.error(e); data = []; }
  draw();
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
    if (gf === 'pusat') matchG = d.pusat.stok > 0;
    if (gf === 'sodong') matchG = d.sodong.stok > 0;
    if (gf === 'sariwangi') matchG = d.sariwangi.stok > 0;
    if (gf === 'manonjaya') matchG = d.manonjaya.stok > 0;
    if (gf === 'habis') matchG = d.total_qty <= 0;
    let matchS = true;
    if (sf) matchS = getStatus(d.total_qty).cls === sf;
    return matchQ && matchG && matchS;
  });
}

function updateCards(rows) {
  let pusat = 0, cabang = 0, totalQty = 0;
  rows.forEach(d => {
    pusat += d.pusat.stok * d.harga_beli;
    cabang += (d.sodong.stok + d.sariwangi.stok + d.manonjaya.stok) * d.harga_beli;
    totalQty += d.total_qty;
  });
  document.getElementById('sum-pusat').textContent = rupiah(pusat);
  document.getElementById('sub-pusat').textContent = rows.filter(d => d.pusat.stok > 0).length + ' item aktif';
  document.getElementById('sum-cabang').textContent = rupiah(cabang);
  document.getElementById('sum-total').textContent = rupiah(pusat + cabang);
  document.getElementById('sub-total').textContent = rows.length + ' barang · ' + totalQty.toLocaleString('id-ID') + ' unit';
}

function cellGudang(g, cls) {
  if (!g || g.stok <= 0) return `<td class="center"><span class="num-zero">0</span></td>`;
  return `<td class="center"><span class="num-stok ${cls}">${g.stok.toLocaleString('id-ID')}</span></td>`;
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
      const st = getStatus(d.total_qty);
      const no = (currentPage - 1) * PAGE_SIZE + i + 1;
      return `
            <tr>
                <td><span class="sb-no">${no}</span></td>
                <td>
                    <div class="sb-nama">${d.nama}</div>
                    <div class="sb-satuan">${d.satuan} · <span class="sb-status ${st.cls}">${st.label}</span></div>
                </td>
                <td class="center"><span class="num-harga">${rupiah(d.harga_beli)}</span></td>
                ${cellGudang(d.pusat, 'c-pusat')}
                ${cellGudang(d.sodong, 'c-sodong')}
                ${cellGudang(d.sariwangi, 'c-sariwangi')}
                ${cellGudang(d.manonjaya, 'c-manonjaya')}
                <td class="center"><span class="num-total-qty">${d.total_qty.toLocaleString('id-ID')}</span></td>
                <td class="center"><span class="num-total-nilai">${rupiah(d.total_nilai)}</span></td>
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