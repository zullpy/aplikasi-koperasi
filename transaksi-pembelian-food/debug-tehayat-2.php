<?php
include __DIR__ . '/../database/koneksi.php';

$out = [];

$out['transaksi_pembelian'] = [];
$res1 = mysqli_query($koneksi, "SELECT p.*, s.nama_supplier FROM transaksi_pembelian p JOIN suplier s ON s.id_supplier = p.id_supplier WHERE s.nama_supplier LIKE '%TEH AYAT%' OR p.tanggal_pembelian = '2026-07-26'");
while ($r = mysqli_fetch_assoc($res1)) { $out['transaksi_pembelian'][] = $r; }

$out['pembayaran_pembelian'] = [];
$res2 = mysqli_query($koneksi, "SELECT pp.*, s.nama_supplier FROM pembayaran_pembelian pp JOIN suplier s ON s.id_supplier = pp.id_supplier WHERE s.nama_supplier LIKE '%TEH AYAT%' OR pp.tanggal_transaksi = '2026-07-26'");
while ($r = mysqli_fetch_assoc($res2)) { $out['pembayaran_pembelian'][] = $r; }

$out['riwayat_pembayaran_pembelian'] = [];
$res3 = mysqli_query($koneksi, "SELECT * FROM riwayat_pembayaran_pembelian");
if ($res3) { while ($r = mysqli_fetch_assoc($res3)) { $out['riwayat_pembayaran_pembelian'][] = $r; } }

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
