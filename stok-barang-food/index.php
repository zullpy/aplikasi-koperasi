<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pembelian|Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" />
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css"
    />
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css"
    />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php $activePage = 'stok-barang'; include '../components/navbar.php'; ?>

    <div class="sb-wrap">
  <h2 class="sr-only">Dashboard stok barang — ringkasan stok awal, masuk, keluar, dan akhir beserta detail per produk</h2>

  <div class="sb-header">
    <div class="sb-header-left">
      <h1>Stok Barang</h1>
      <p>Periode: Juni 2025 &nbsp;·&nbsp; Diperbarui hari ini</p>
    </div>
  </div>

  <div class="sb-cards">
    <div class="sb-card ic-awal">
      <div class="sb-card-icon" aria-hidden="true"><i class="ti ti-box"></i></div>
      <div class="sb-card-label">Stok Awal</div>
      <div class="sb-card-val" id="sum-awal">0</div>
      <div class="sb-card-sub">Saldo awal periode</div>
      <div class="sb-card-bar"></div>
    </div>
    <div class="sb-card ic-masuk">
      <div class="sb-card-icon" aria-hidden="true"><i class="ti ti-package-import"></i></div>
      <div class="sb-card-label">Stok Masuk</div>
      <div class="sb-card-val" id="sum-masuk">0</div>
      <div class="sb-card-sub">Total penerimaan</div>
      <div class="sb-card-bar"></div>
    </div>
    <div class="sb-card ic-keluar">
      <div class="sb-card-icon" aria-hidden="true"><i class="ti ti-package-export"></i></div>
      <div class="sb-card-label">Stok Keluar</div>
      <div class="sb-card-val" id="sum-keluar">0</div>
      <div class="sb-card-sub">Total pengeluaran</div>
      <div class="sb-card-bar"></div>
    </div>
    <div class="sb-card ic-akhir">
      <div class="sb-card-icon" aria-hidden="true"><i class="ti ti-stack-2"></i></div>
      <div class="sb-card-label">Stok Akhir</div>
      <div class="sb-card-val" id="sum-akhir">0</div>
      <div class="sb-card-sub">Saldo akhir periode</div>
      <div class="sb-card-bar"></div>
    </div>
  </div>

  <div class="sb-filter-bar">
    <input type="text" id="search-input" placeholder="Cari nama / kode barang…" oninput="renderTable()" />
    <select id="filter-status" onchange="renderTable()">
      <option value="">Semua status</option>
      <option value="aman">Aman</option>
      <option value="low">Stok rendah</option>
      <option value="habis">Habis</option>
    </select>
    <button class="sb-btn sb-btn-outline" onclick="resetFilter()"><i class="ti ti-refresh" style="font-size:14px; vertical-align:-2px;" aria-hidden="true"></i> Reset</button>
    <button class="sb-btn sb-btn-primary" onclick="sendPrompt('Buatkan laporan stok barang bulan ini dalam format ringkasan')"><i class="ti ti-file-text" style="font-size:14px; vertical-align:-2px;" aria-hidden="true"></i> Ekspor Laporan ↗</button>
  </div>

  <div class="sb-table-wrap">
    <div class="sb-table-head">
      <span>Detail Stok per Barang</span>
      <span id="item-count" style="font-size:12px; color:#94a3b8;"></span>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Barang</th>
            <th>Stok Awal</th>
            <th>Masuk</th>
            <th>Keluar</th>
            <th>Stok Akhir</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="tb-body"></tbody>
      </table>
    </div>
    <div class="sb-footer">
      <span id="page-info"></span>
      <div class="sb-pagination" id="pagination"></div>
    </div>
  </div>
</div>
</body>
<script src="script.js"></script>
</html>