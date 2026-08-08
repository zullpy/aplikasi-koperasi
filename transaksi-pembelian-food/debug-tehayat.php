<?php
include __DIR__ . '/../database/koneksi.php';

$query = "SELECT pp.*, s.nama_supplier 
FROM pembayaran_pembelian pp
JOIN suplier s ON s.id_supplier = pp.id_supplier
WHERE s.nama_supplier LIKE '%TEH AYAT%' OR pp.tanggal_transaksi = '2026-07-26'";

$res = mysqli_query($koneksi, $query);
$out = [];
while ($row = mysqli_fetch_assoc($res)) {
    $out[] = $row;
}

$query2 = "SELECT p.*, s.nama_supplier 
FROM transaksi_pembelian p
JOIN suplier s ON s.id_supplier = p.id_supplier
WHERE s.nama_supplier LIKE '%TEH AYAT%' OR p.tanggal_pembelian = '2026-07-26'";

$res2 = mysqli_query($koneksi, $query2);
$out2 = [];
while ($row2 = mysqli_fetch_assoc($res2)) {
    $out2[] = $row2;
}

header('Content-Type: application/json');
echo json_encode(['pembayaran' => $out, 'transaksi' => $out2], JSON_PRETTY_PRINT);
