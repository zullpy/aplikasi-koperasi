<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Gagal',
        'text' => 'Akses ditolak! Anda bukan admin.'
    ];
    header("Location: ../daftar-harga-barang-food/index.php");
    exit;
}

include 'koneksi.php';

if (isset($_POST['id_barang'])) {
    $id_barang   = mysqli_real_escape_string($koneksi, $_POST['id_barang']);
    $nama_barang = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
    $kategori    = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $suplier     = mysqli_real_escape_string($koneksi, $_POST['suplier']);
    $tanggal     = mysqli_real_escape_string($koneksi, $_POST['tanggal_terupdate_baru']);
    $satuan      = mysqli_real_escape_string($koneksi, $_POST['satuan']);

    $harga_beli = (int) preg_replace('/[^0-9]/', '', $_POST['harga_beli']);
    $keuntungan = (int) preg_replace('/[^0-9]/', '', $_POST['keuntungan']);
    $harga_jual = $harga_beli + $keuntungan;

    if ($harga_beli <= 0) {
        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Gagal',
            'text'  => 'Harga beli harus lebih dari 0!'
        ];
        header("Location: ../daftar-harga-barang-food/index.php");
        exit;
    }

    $query = "UPDATE barang SET
        nama_barang = '$nama_barang',
        kategori = '$kategori',
        harga_beli = '$harga_beli',
        harga_jual = '$harga_jual',
        suplier = '$suplier',
        satuan = '$satuan',
        tanggal_terupdate_baru = '$tanggal'
        WHERE id_barang = '$id_barang'";

    if (mysqli_query($koneksi, $query)) {
        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Berhasil',
            'text'  => 'Data berhasil diubah'
        ];
    } else {
        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Gagal',
            'text'  => 'Data gagal diubah'
        ];
    }
} else {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'ID Barang tidak valid'
    ];
}

header("Location: ../daftar-harga-barang-food/index.php");
exit;
