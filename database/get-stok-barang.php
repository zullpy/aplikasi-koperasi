<?php
include 'koneksi.php';
header('Content-Type: application/json');

/*
 * LOGIKA FINAL:
 *  - GUDANG PUSAT     : stok_akhir dari db_draft_barang.barang
 *  - GUDANG CABANG    : dihitung dari db_mbg
 *       MASUK  = SUM qty detail_pengiriman yang SUDAH DITERIMA (status IN 'ada','kurang')
 *       KELUAR = SUM qty pengambilan_barang_detail
 *       STOK   = MASUK - KELUAR
 *  - HARGA BELI       : dari db_draft_barang.barang.harga_beli
 *  - MATCHING         : LOWER(TRIM(nama_barang))
 *
 *  - Satuan besar (DUS, KARUNG, dll) dipakai apa adanya sebagai stok_grosir.
 *    isi_per_satuan & satuan_eceran hanya dipakai untuk info konversi,
 *    tidak lagi dihitung jadi stok eceran terpisah.
 */

// ===== 1. MASTER BARANG + HARGA BELI + STOK PUSAT =====
$sqlMaster = "SELECT id_barang, nama_barang, satuan, satuan_eceran, isi_per_satuan,
                     harga_beli, harga_eceran, stok_akhir
              FROM barang ORDER BY nama_barang ASC";
$resMaster = mysqli_query($koneksi, $sqlMaster);
if (!$resMaster) {
  echo json_encode(['status' => 'error', 'message' => 'Master: ' . mysqli_error($koneksi)]);
  exit;
}

$items = [];
while ($r = mysqli_fetch_assoc($resMaster)) {
  $id  = (int)$r['id_barang'];
  $isi = (isset($r['isi_per_satuan']) && (float)$r['isi_per_satuan'] > 0) ? (float)$r['isi_per_satuan'] : null;

  $hargaBeli      = (float)($r['harga_beli'] ?? 0);
  $hargaEceranRaw = (float)($r['harga_eceran'] ?? 0);

  // Tentukan harga eceran final
  if ($hargaEceranRaw > 0) {
    $hargaEceran = $hargaEceranRaw;
  } elseif ($isi) {
    $hargaEceran = $hargaBeli / $isi;
  } else {
    $hargaEceran = $hargaBeli;
  }

  $satuanGrosir  = trim($r['satuan'] ?? '') ?: '-';
  $satuanEceran  = trim($r['satuan_eceran'] ?? '') ?: $satuanGrosir;

  // Stok pusat
  $stokGrosirPusat = (int)$r['stok_akhir'];

  $items[$id] = [
    'id_barang'      => $id,
    'nama'           => $r['nama_barang'],
    'nama_key'       => strtolower(trim($r['nama_barang'])),
    'satuan'         => $satuanGrosir,
    'satuan_eceran'  => $satuanEceran,
    'isi_per_satuan' => $isi,
    'harga_beli'     => $hargaBeli,
    'harga_eceran'   => $hargaEceran,
    'pusat'          => ['stok_grosir' => $stokGrosirPusat],
    'sodong'         => ['masuk' => 0, 'keluar' => 0, 'stok_grosir' => 0],
    'sariwangi'      => ['masuk' => 0, 'keluar' => 0, 'stok_grosir' => 0],
    'manonjaya'      => ['masuk' => 0, 'keluar' => 0, 'stok_grosir' => 0],
  ];
}

// ===== 2. STOK PER GUDANG CABANG (dari db_mbg) =====
$gudangList = ['sodong', 'sariwangi', 'manonjaya'];

foreach ($gudangList as $gudang) {
  // STOK MASUK = detail_pengiriman yang sudah diterima
  $sqlMasuk = "
        SELECT LOWER(TRIM(dp.nama_barang)) AS nama_key, 
               SUM(dp.qty) AS qty
        FROM detail_pengiriman dp
        JOIN pengiriman p        ON dp.pengiriman_id = p.id
        JOIN penerimaan pc       ON pc.pengiriman_id = p.id
        JOIN detail_penerimaan d ON d.penerimaan_id = pc.id 
                                AND d.detail_pengiriman_id = dp.id
        WHERE p.lokasi = ?
          AND d.status_barang IN ('ada','kurang')
        GROUP BY nama_key
    ";
  $stmtMasuk = mysqli_prepare($koneksi2, $sqlMasuk);
  mysqli_stmt_bind_param($stmtMasuk, 's', $gudang);
  mysqli_stmt_execute($stmtMasuk);
  $resMasuk = mysqli_stmt_get_result($stmtMasuk);

  $masukMap = [];
  if ($resMasuk) {
    while ($r = mysqli_fetch_assoc($resMasuk)) {
      $masukMap[$r['nama_key']] = (float)$r['qty'];
    }
  }
  mysqli_stmt_close($stmtMasuk);

  // STOK KELUAR = pengambilan_barang_detail
  $sqlKeluar = "
        SELECT LOWER(TRIM(pd.nama_barang)) AS nama_key, 
               SUM(pd.qty) AS qty
        FROM pengambilan_barang_detail pd
        JOIN pengambilan_barang p ON pd.id_pengambilan = p.id_pengambilan
        WHERE p.lokasi = ?
        GROUP BY nama_key
    ";
  $stmtKeluar = mysqli_prepare($koneksi2, $sqlKeluar);
  mysqli_stmt_bind_param($stmtKeluar, 's', $gudang);
  mysqli_stmt_execute($stmtKeluar);
  $resKeluar = mysqli_stmt_get_result($stmtKeluar);

  $keluarMap = [];
  if ($resKeluar) {
    while ($r = mysqli_fetch_assoc($resKeluar)) {
      $keluarMap[$r['nama_key']] = (float)$r['qty'];
    }
  }
  mysqli_stmt_close($stmtKeluar);

  // Match ke master
  foreach ($items as $id => &$it) {
    $key = $it['nama_key'];
    $m = $masukMap[$key] ?? 0;
    $k = $keluarMap[$key] ?? 0;

    $stokGrosirCabang = $m - $k;

    $it[$gudang]['masuk']       = $m;
    $it[$gudang]['keluar']      = $k;
    $it[$gudang]['stok_grosir'] = $stokGrosirCabang;
  }
  unset($it);
}

// ===== 3. HITUNG TOTAL & NILAI (GROSIR) =====
$rows = [];
foreach ($items as $it) {
  $totalQtyGrosir = $it['pusat']['stok_grosir']
    + $it['sodong']['stok_grosir']
    + $it['sariwangi']['stok_grosir']
    + $it['manonjaya']['stok_grosir'];

  $totalNilaiGrosir = $totalQtyGrosir * $it['harga_beli'];

  $rows[] = [
    'id_barang'          => $it['id_barang'],
    'nama'               => $it['nama'],
    'satuan'             => $it['satuan'],
    'satuan_eceran'      => $it['satuan_eceran'],
    'isi_per_satuan'     => $it['isi_per_satuan'],
    'harga_beli'         => $it['harga_beli'],
    'harga_eceran'       => $it['harga_eceran'],
    'pusat'              => $it['pusat'],
    'sodong'             => $it['sodong'],
    'sariwangi'          => $it['sariwangi'],
    'manonjaya'          => $it['manonjaya'],
    'total_qty_grosir'   => $totalQtyGrosir,
    'total_nilai_grosir' => $totalNilaiGrosir,
  ];
}

echo json_encode(['status' => 'success', 'data' => $rows]);