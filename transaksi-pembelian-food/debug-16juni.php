<?php
include __DIR__ . '/../database/koneksi.php';

$tanggal = '2026-06-16';

$query = "SELECT 
    p.id_pembelian, 
    p.kode_transaksi, 
    p.id_supplier,
    s.nama_supplier,
    p.nama_barang, 
    p.harga, 
    p.volume, 
    p.biaya_admin, 
    p.diskon,
    pp.total_tagihan,
    pp.diskon AS pp_diskon
FROM transaksi_pembelian p
INNER JOIN suplier s ON p.id_supplier = s.id_supplier
LEFT JOIN pembayaran_pembelian pp ON pp.kode_transaksi COLLATE utf8mb4_unicode_ci = p.kode_transaksi COLLATE utf8mb4_unicode_ci
WHERE p.tanggal_pembelian = '$tanggal'
ORDER BY s.nama_supplier ASC, p.id_pembelian ASC";

$res = mysqli_query($koneksi, $query);
$items = [];
while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode($items, JSON_PRETTY_PRINT);
