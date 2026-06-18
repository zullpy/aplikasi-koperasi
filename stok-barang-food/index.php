<?php $activePage = 'stok-barang'; include '../components/navbar.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Transaksi Pembelian | Bina Usaha Sauyunan</title>
<meta name="description" content="Dashboard stok barang — ringkasan stok masuk, keluar, dan akhir beserta detail per produk">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body>


<div class="sb-wrap">

  <!-- HEADER -->
  <div class="sb-header">
    <div class="sb-header-left">
      <h1>Transaksi Pembelian</h1>
      <p>Bina Usaha Sauyunan</p>
    </div>
  </div>

  <p style="font-size:13px;color:#64748b;margin:0 0 18px;">
    Periode: Juni 2025 &nbsp;·&nbsp; Diperbarui hari ini
  </p>

  <!-- SUMMARY CARDS -->
  <div class="sb-cards">
    <div class="sb-card ic-masuk">
      <div class="sb-card-icon"><i class="ti ti-arrow-down-circle"></i></div>
      <div class="sb-card-label">Stok Masuk</div>
      <div class="sb-card-val" id="sum-masuk">0</div>
      <div class="sb-card-sub">Total penerimaan</div>
      <div class="sb-card-bar"></div>
    </div>

    <div class="sb-card ic-keluar">
      <div class="sb-card-icon"><i class="ti ti-arrow-up-circle"></i></div>
      <div class="sb-card-label">Stok Keluar</div>
      <div class="sb-card-val" id="sum-keluar">0</div>
      <div class="sb-card-sub">Total pengeluaran</div>
      <div class="sb-card-bar"></div>
    </div>

    <div class="sb-card ic-akhir">
      <div class="sb-card-icon"><i class="ti ti-package"></i></div>
      <div class="sb-card-label">Stok Akhir</div>
      <div class="sb-card-val" id="sum-akhir">0</div>
      <div class="sb-card-sub">Saldo akhir periode</div>
      <div class="sb-card-bar"></div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="sb-filter-bar">
    <input type="text" id="search-input" placeholder="Cari nama barang..." oninput="renderTable()">
    <select id="filter-status" onchange="renderTable()">
      <option value="">Semua status</option>
      <option value="aman">Aman</option>
      <option value="low">Stok rendah</option>
      <option value="habis">Habis</option>
    </select>
    <button class="sb-btn sb-btn-outline" onclick="resetFilter()">Reset</button>
  </div>

  <!-- TABLE -->
  <div class="sb-table-wrap">
    <div class="sb-table-head">
      <span>Detail Stok per Barang</span>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Barang</th>
          <th>Masuk</th>
          <th>Keluar</th>
          <th>Stok Akhir</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="tb-body"></tbody>
    </table>

    <div class="sb-footer">
      <span><span id="item-count">0 barang</span> &nbsp;·&nbsp; <span id="page-info">Menampilkan 0–0 dari 0</span></span>
      <div class="sb-pagination" id="pagination"></div>
    </div>
  </div>

</div>

<script src="script.js"></script>
</body>
</html>