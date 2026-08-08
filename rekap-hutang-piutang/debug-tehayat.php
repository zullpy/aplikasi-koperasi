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

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
