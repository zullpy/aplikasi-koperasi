<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../');
    exit;
}
$activePage = 'stok-barang';
include '../components/navbar.php'; ?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Stok Multi Gudang | Bina Usaha Sauyunan</title>
  <meta name="description" content="Dashboard stok 4 gudang: Pusat, Sodong, Sariwangi, Manonjaya">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>

<body>
  <div class="sb-wrap">

    <!-- HEADER -->
    <div class="sb-header">
      <div class="sb-header-left">
        <h1><i class="ti ti-buildings"></i> Stok Multi Gudang</h1>
        <p>Bina Usaha Sauyunan · Pusat, Sodong, Sariwangi, Manonjaya</p>
      </div>
      <span class="sb-badge">Live · <?= date('d M Y') ?></span>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="sb-cards">
      <div class="sb-card ic-pusat">
        <div class="sb-card-icon"><i class="ti ti-building-store"></i></div>
        <div class="sb-card-label">Gudang Pusat</div>
        <div class="sb-card-val" id="sum-pusat">Rp 0</div>
        <div class="sb-card-sub" id="sub-pusat">0 item aktif</div>
        <div class="sb-card-bar"></div>
      </div>
      <div class="sb-card ic-cabang">
        <div class="sb-card-icon"><i class="ti ti-building-warehouse"></i></div>
        <div class="sb-card-label">Gudang Cabang</div>
        <div class="sb-card-val" id="sum-cabang">Rp 0</div>
        <div class="sb-card-sub">Sodong + Sariwangi + Manonjaya</div>
        <div class="sb-card-bar"></div>
      </div>
      <div class="sb-card ic-total">
        <div class="sb-card-icon"><i class="ti ti-coin-taka"></i></div>
        <div class="sb-card-label">Total Nilai Barang</div>
        <div class="sb-card-val" id="sum-total">Rp 0</div>
        <div class="sb-card-sub" id="sub-total">0 barang · 0 unit</div>
        <div class="sb-card-bar"></div>
      </div>
    </div>

    <!-- FILTER -->
    <div class="sb-filter-bar">
      <input type="text" id="search-input" placeholder="Cari nama barang..." oninput="renderTable()">
      <select id="filter-gudang" onchange="renderTable()">
        <option value="">Semua gudang</option>
        <option value="pusat">Ada di Pusat</option>
        <option value="sodong">Ada di Sodong</option>
        <option value="sariwangi">Ada di Sariwangi</option>
        <option value="manonjaya">Ada di Manonjaya</option>
        <option value="habis">Stok Habis (semua)</option>
      </select>
      <button class="sb-btn sb-btn-outline" onclick="resetFilter()">Reset</button>
    </div>

    <!-- TABLE -->
    <div class="sb-table-wrap">
      <div class="sb-table-head">
        <span><i class="ti ti-list-details"></i> Detail Stok per Barang (4 Gudang)</span>
        <span class="sb-table-info">Harga beli dari db_draft_barang · Stok cabang dari db_mbg</span>
      </div>
      <div class="sb-table-scroll">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Barang</th>
              <th>Harga Beli</th>
              <th class="th-pusat">Pusat</th>
              <th class="th-sodong">Sodong</th>
              <th class="th-sariwangi">Sariwangi</th>
              <th class="th-manonjaya">Manonjaya</th>
              <th>Total Qty</th>
              <th>Nilai Barang</th>
            </tr>
          </thead>
          <tbody id="tb-body"></tbody>
        </table>
      </div>
      <div class="sb-footer">
        <span><span id="item-count">0 barang</span> &nbsp;·&nbsp; <span id="page-info">Menampilkan 0–0 dari 0</span></span>
        <div class="sb-pagination" id="pagination"></div>
      </div>
    </div>
  </div>
  <script src="script.js"></script>
</body>

</html>