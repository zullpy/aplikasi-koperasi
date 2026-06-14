<?php
include 'koneksi.php';
header('Content-Type: application/json');

/*
 * Logika stok:
 *  - stok_awal  = stok_akhir barang  (kolom `stok_akhir` di tabel barang)
 *                 dikurangi total mutasi masuk, ditambah total mutasi keluar
 *                 → agar stok_awal + masuk - keluar = stok_akhir (saldo berjalan)
 *  - stok_masuk = SUM qty mutasi_stok WHERE jenis = 'masuk'
 *  - stok_keluar= SUM qty mutasi_stok WHERE jenis = 'keluar'
 *  - stok_akhir = stok_awal + stok_masuk - stok_keluar  (== b.stok_akhir jika saldo sinkron)
 *
 * Jika belum ada mutasi sama sekali, stok_awal = stok_akhir, masuk=0, keluar=0.
 */

$sql = "
    SELECT
        b.id_barang,
        b.nama_barang,
        b.satuan,
        b.keterangan,
        COALESCE(SUM(CASE WHEN m.jenis = 'masuk'  THEN m.qty ELSE 0 END), 0) AS total_masuk,
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
    $saldo  = (int)   $row['stok_saldo'];      // nilai stok_akhir di tabel barang

    /*
     * Hitung stok_awal:
     * Jika ada data mutasi, stok_awal bisa dihitung mundur dari saldo:
     *   stok_awal = saldo - masuk + keluar
     * Jika tidak ada mutasi sama sekali, stok_awal = saldo.
     */
    $stok_awal   = $saldo - $masuk + $keluar;
    $stok_akhir  = $stok_awal + $masuk - $keluar;   // == saldo

    $rows[] = [
        'id_barang'  => (int)    $row['id_barang'],
        'nama'       => $row['nama_barang'],
        'satuan'     => $row['satuan'] ?? '-',
        'keterangan' => $row['keterangan'] ?? '',
        'awal'       => (int) $stok_awal,
        'masuk'      => (int) $masuk,
        'keluar'     => (int) $keluar,
        'akhir'      => (int) $stok_akhir,
    ];
}

echo json_encode(['status' => 'success', 'data' => $rows]);
