<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (isset($_POST['id_barang'])) {

    $id_barang   = mysqli_real_escape_string($koneksi, $_POST['id_barang']);
    $nama_barang = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
    $kategori    = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $suplier     = mysqli_real_escape_string($koneksi, $_POST['suplier']);
    $alamat      = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $satuan      = mysqli_real_escape_string($koneksi, $_POST['satuan']);

    // Hilangkan Rp, titik, spasi, dll
    $harga_beli = preg_replace('/[^0-9]/', '', $_POST['harga_beli']);
    $harga_jual = $harga_beli + ($harga_beli * 30 / 100);

    $query = "UPDATE barang SET
                nama_barang = '$nama_barang',
                kategori = '$kategori',
                harga_beli = '$harga_beli',
                harga_jual = '$harga_jual',
                suplier = '$suplier',
                alamat = '$alamat',
                satuan = '$satuan'
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