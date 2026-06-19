<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'koneksi.php';
$id = intval($_GET['id_transaksi']);

$q = mysqli_query($koneksi, "
    SELECT tp.*, p.nama_pelanggan
    FROM transaksi_penjualan tp
    JOIN pelanggan p ON tp.id_pelanggan = p.id_pelanggan
    WHERE tp.id_transaksi = $id
");
if (!$q) {
    echo json_encode(['error' => mysqli_error($koneksi)]);
    exit;
}
$transaksi = mysqli_fetch_assoc($q);
if (!$transaksi) {
    echo json_encode(['error' => 'Transaksi tidak ditemukan']);
    exit;
}

// Items
$q2 = mysqli_query($koneksi, "
    SELECT dp.*, b.nama_barang
    FROM detail_penjualan dp
    JOIN barang b ON dp.id_barang = b.id_barang
    WHERE dp.id_transaksi = $id
");
$items = [];
while ($row = mysqli_fetch_assoc($q2)) $items[] = $row;
$transaksi['items'] = $items;

// Riwayat pembayaran
$q3 = mysqli_query($koneksi, "SELECT * FROM pembayaran WHERE id_transaksi = $id ORDER BY tanggal_bayar DESC");
$pembayaran = [];
while ($row = mysqli_fetch_assoc($q3)) $pembayaran[] = $row;
$transaksi['pembayaran'] = $pembayaran;

header('Content-Type: application/json');
echo json_encode($transaksi);
