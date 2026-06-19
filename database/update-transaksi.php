<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$id_transaksi = intval($_POST['id_transaksi']);
$no_faktur = mysqli_real_escape_string($koneksi, $_POST['no_faktur']);
$tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
$id_pelanggan = intval($_POST['id_pelanggan']);
$id_barang = $_POST['id_barang'];
$qty = $_POST['qty'];
$satuan = $_POST['satuan'];
$harga = $_POST['harga'];
$subtotal = $_POST['subtotal'];

// Hitung total baru
$total = 0;
foreach ($subtotal as $s) $total += intval(preg_replace('/[^0-9]/', '', $s));

// Update header
mysqli_query($koneksi, "
    UPDATE transaksi_penjualan
    SET tanggal = '$tanggal $tanggal',
        id_pelanggan = $id_pelanggan,
        total = $total
    WHERE id_transaksi = $id_transaksi
");

// Hapus detail lama
mysqli_query($koneksi, "DELETE FROM detail_penjualan WHERE id_transaksi = $id_transaksi");

// Insert detail baru
for ($i = 0; $i < count($id_barang); $i++) {
    if (empty($id_barang[$i])) continue;
    $harga_bersih = preg_replace('/[^0-9]/', '', $harga[$i]);
    $subtotal_bersih = preg_replace('/[^0-9]/', '', $subtotal[$i]);
    mysqli_query($koneksi, "
        INSERT INTO detail_penjualan (id_transaksi, id_barang, qty, satuan, harga_jual, subtotal)
        VALUES ($id_transaksi, '{$id_barang[$i]}', '{$qty[$i]}', '{$satuan[$i]}', '$harga_bersih', '$subtotal_bersih')
    ");
}

// Recalculate status pembayaran berdasarkan total baru
$q = mysqli_query($koneksi, "SELECT total_bayar FROM transaksi_penjualan WHERE id_transaksi = $id_transaksi");
$r = mysqli_fetch_assoc($q);
$total_bayar = $r['total_bayar'];
$sisa = $total - $total_bayar;
$status = $sisa <= 0 ? 'lunas' : ($total_bayar > 0 ? 'sebagian' : 'belum_lunas');
mysqli_query($koneksi, "
    UPDATE transaksi_penjualan
    SET sisa_bayar = $sisa, status_pembayaran = '$status'
    WHERE id_transaksi = $id_transaksi
");

$_SESSION['alert'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Transaksi berhasil diupdate'];
header("Location: ../transaksi-penjualan-food/index.php");
exit;
