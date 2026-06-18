<?php
include 'koneksi.php';
header('Content-Type: application/json');

/*
* Logika stok:
*  - stok_masuk  = SUM qty mutasi_stok WHERE jenis IN ('masuk', 'perubahan')
*  - stok_keluar = SUM qty mutasi_stok WHERE jenis = 'keluar'
*  - stok_akhir  = stok_akhir dari tabel barang (saldo berjalan)
*/
$sql = "
SELECT
  b.id_barang,
  b.nama_barang,
  b.satuan,
  b.keterangan,
  COALESCE(SUM(CASE WHEN m.jenis IN ('masuk', 'perubahan') THEN m.qty ELSE 0 END), 0) AS total_masuk,
  COALESCE(SUM(CASE WHEN m.jenis = 'keluar' THEN m.qty ELSE 0 END), 0) AS total_keluar,
  b.stok_akhir AS stok_saldo
FROM barang b
LEFT JOIN mutasi_stok m ON b.id_barang = m.id_barang
GROUP BY b.id_barang, b.nama_barang, b.satuan, b.keterangan, b.stok_akhir
ORDER BY b.nama_barang ASC
";

$result = mysqli_query($koneksi, $sql);
if (!$result) {
  echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
  exit;
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
  $masuk  = (float) $row['total_masuk'];
  $keluar = (float) $row['total_keluar'];
  $saldo  = (int)   $row['stok_saldo'];

  $rows[] = [
    'id_barang'  => (int)    $row['id_barang'],
    'nama'       => $row['nama_barang'],
    'satuan'     => $row['satuan'] ?? '-',
    'keterangan' => $row['keterangan'] ?? '',
    'masuk'      => (int) $masuk,
    'keluar'     => (int) $keluar,
    'akhir'      => $saldo,
  ];
}

echo json_encode(['status' => 'success', 'data' => $rows]);