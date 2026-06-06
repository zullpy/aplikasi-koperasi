<?php
session_start();
include 'koneksi.php';

$nama_barang = $_POST['nama_barang'];
$harga_beli = (int) str_replace(
    ['Rp.', 'Rp', '.', ',',' '],
    '',
    $_POST['harga_beli']
);

$harga_jual = $harga_beli + ($harga_beli * 30 / 100);
$suplier = $_POST['suplier'];
$alamat = $_POST['alamat'];
$satuan = $_POST['satuan'];



$query = "INSERT INTO barang (
            nama_barang,
            harga_beli,
            harga_jual,
            suplier,
            alamat,
            satuan
          ) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param(
    $stmt,
    "siisss",
    $nama_barang,
    $harga_beli,
    $harga_jual,
    $suplier,
    $alamat,
    $satuan
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