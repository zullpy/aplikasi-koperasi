const PAGE_SIZE = 8;
let currentPage = 1;
let data = [];

/* ───────── Ambil data dari database ───────── */
async function loadData() {
  try {
    const res = await fetch('../database/get-stok-barang.php');
    const json = await res.json();
    if (json.status === 'success') {
      data = json.data;
    } else {
      console.error('Gagal ambil data:', json.message);
      data = [];
    }
  } catch (e) {
    console.error('Fetch error:', e);
    data = [];
  }
  draw();
}

/* ───────── Status stok ───────── */
function getStatus(akhir) {
  if (akhir <= 0)  return { cls: 'st-habis', label: 'Habis' };
  if (akhir <= 10) return { cls: 'st-low',   label: 'Stok rendah' };
  return { cls: 'st-aman', label: 'Aman' };
}

function getStatusKey(akhir) {
  if (akhir <= 0)  return 'habis';
  if (akhir <= 10) return 'low';
  return 'aman';
}

/* ───────── Filter ───────── */
function filtered() {
  const q  = document.getElementById('search-input').value.toLowerCase();
  const sf = document.getElementById('filter-status').value;
  return data.filter(d => {
    const matchQ = !q  || d.nama.toLowerCase().includes(q);
    const matchS = !sf || getStatusKey(d.akhir) === sf;
    return matchQ && matchS;
  });
}

/* ───────── Summary cards ───────── */
function updateCards(rows) {
  document.getElementById('sum-awal').textContent   = rows.reduce((a, d) => a + d.awal,   0).toLocaleString('id-ID');
  document.getElementById('sum-masuk').textContent  = rows.reduce((a, d) => a + d.masuk,  0).toLocaleString('id-ID');
  document.getElementById('sum-keluar').textContent = rows.reduce((a, d) => a + d.keluar, 0).toLocaleString('id-ID');
  document.getElementById('sum-akhir').textContent  = rows.reduce((a, d) => a + d.akhir,  0).toLocaleString('id-ID');
}

/* ───────── Render ───────── */
function renderTable() {
  currentPage = 1;
  draw();
}

function draw() {
  const rows  = filtered();
  updateCards(rows);
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (currentPage > pages) currentPage = pages;
  const slice = rows.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  document.getElementById('item-count').textContent = total + ' barang';
  const from = total === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1;
  const to   = Math.min(currentPage * PAGE_SIZE, total);
  document.getElementById('page-info').textContent = `Menampilkan ${from}–${to} dari ${total}`;

  const tbody = document.getElementById('tb-body');

  if (total === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:#64748b;">Tidak ada data barang</td></tr>`;
  } else {
    tbody.innerHTML = slice.map((d, i) => {
      const st     = getStatus(d.akhir);
      const rowNum = (currentPage - 1) * PAGE_SIZE + i + 1;
      const jenisTag = d.masuk > 0 && d.keluar === 0
        ? `<span class="sb-jenis jenis-masuk">Masuk</span>`
        : d.keluar > 0 && d.masuk === 0
          ? `<span class="sb-jenis jenis-keluar">Keluar</span>`
          : d.masuk > 0 && d.keluar > 0
            ? `<span class="sb-jenis jenis-masuk">Masuk</span> <span class="sb-jenis jenis-keluar">Keluar</span>`
            : `<span class="sb-jenis jenis-none">-</span>`;

      return `<tr>
        <td><span class="sb-no">${rowNum}</span></td>
        <td>
          <div class="sb-nama">${d.nama}</div>
          <div class="sb-satuan">${d.satuan}</div>
          <div class="sb-mutasi-tag">${jenisTag}</div>
        </td>
        <td class="center"><span class="num-awal">${d.awal.toLocaleString('id-ID')}</span></td>
        <td class="center"><span class="num-masuk">+${d.masuk.toLocaleString('id-ID')}</span></td>
        <td class="center"><span class="num-keluar">−${d.keluar.toLocaleString('id-ID')}</span></td>
        <td class="center"><span class="num-akhir">${d.akhir.toLocaleString('id-ID')}</span></td>
        <td class="center"><span class="sb-status ${st.cls}">${st.label}</span></td>
      </tr>`;
    }).join('');
  }

  /* Pagination — max 5 tombol */
  const pg = document.getElementById('pagination');
  const MAX_BTN = 5;
  let html = '';

  if (currentPage > 1)
    html += `<button onclick="goPage(${currentPage - 1})"><i class="ti ti-chevron-left" style="font-size:12px" aria-hidden="true"></i></button>`;

  let startPage = Math.max(1, currentPage - Math.floor(MAX_BTN / 2));
  let endPage   = startPage + MAX_BTN - 1;
  if (endPage > pages) { endPage = pages; startPage = Math.max(1, endPage - MAX_BTN + 1); }

  for (let p = startPage; p <= endPage; p++)
    html += `<button class="${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;

  if (currentPage < pages)
    html += `<button onclick="goPage(${currentPage + 1})"><i class="ti ti-chevron-right" style="font-size:12px" aria-hidden="true"></i></button>`;

  pg.innerHTML = html;
}

function goPage(p)  { currentPage = p; draw(); }
function resetFilter() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-status').value = '';
  renderTable();
}

/* ───────── Init ───────── */
loadData();