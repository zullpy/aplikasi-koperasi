<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang = $_POST['nama_barang'];
$kategori    = $_POST['kategori'];
$suplier     = $_POST['suplier'];
$satuan      = $_POST['satuan'];
$tanggal_terupdate_baru = $_POST['tanggal_terupdate_baru'];

// Bersihkan angka
$harga_beli = (int) preg_replace('/[^0-9]/', '', $_POST['harga_beli']);
$keuntungan = (int) preg_replace('/[^0-9]/', '', $_POST['keuntungan']);

// Hitung harga jual di server (lebih aman)
$harga_jual = $harga_beli + $keuntungan;

// Validasi
if ($harga_beli <= 0) {
    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Gagal',
        'text' => 'Harga beli harus lebih dari 0!'
    ];
    header("Location: ../daftar-harga-barang-food/index.php");
    exit;
}

$query = "INSERT INTO barang (
    nama_barang,
    harga_beli,
    harga_jual,
    suplier,
    satuan,
    kategori,
    tanggal_terupdate_baru
) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param(
    $stmt,
    "siissss",
    $nama_barang,
    $harga_beli,
    $harga_jual,
    $suplier,
    $satuan,
    $kategori,
    $tanggal_terupdate_baru
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['alert'] = [
        'icon' => 'success',
        'title' => 'Berhasil',
        'text' => 'Data barang berhasil ditambahkan'
    ];
} else {
    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Gagal',
        'text' => mysqli_error($koneksi)
    ];
}

header("Location: ../daftar-harga-barang-food/index.php");
exit;
