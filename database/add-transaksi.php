<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang = $_POST['nama_barang'];
$keterangan = $_POST['keterangan'];
$harga = preg_replace('/[^0-9]/', '', $_POST['harga']);
$volume = $_POST['volume'];
$satuan = $_POST['satuan'];
$tanggal_pembelian = $_POST['tanggal_pembelian'];
$id_supplier = $_POST['id_supplier'];

$nota = NULL;

/*
|--------------------------------------------------------------------------
| Upload dari Kamera
|--------------------------------------------------------------------------
*/
$allowed = ['jpg', 'jpeg', 'png', 'pdf'];

if (
    isset($_FILES['nota_kamera']) &&
    $_FILES['nota_kamera']['error'] == 0
) {

    $ext = strtolower(
        pathinfo(
            $_FILES['nota_kamera']['name'],
            PATHINFO_EXTENSION
        )
    );

    if (!in_array($ext, $allowed)) {
        die('Format file tidak didukung!');
    }

    $nota = uniqid() . '.' . $ext;

    move_uploaded_file(
        $_FILES['nota_kamera']['tmp_name'],
        '../uploads/nota/' . $nota
    );
}

elseif (
    isset($_FILES['nota_file']) &&
    $_FILES['nota_file']['error'] == 0
) {

    $ext = strtolower(
        pathinfo(
            $_FILES['nota_file']['name'],
            PATHINFO_EXTENSION
        )
    );

    if (!in_array($ext, $allowed)) {
        die('Format file tidak didukung!');
    }

    $nota = uniqid() . '.' . $ext;

    move_uploaded_file(
        $_FILES['nota_file']['tmp_name'],
        '../uploads/nota/' . $nota
    );
}

$query = "
INSERT INTO transaksi_pembelian (
    id_supplier,
    nama_barang,
    tanggal_pembelian,
    harga,
    volume,
    satuan,
    keterangan,
    nota
)
VALUES (
    '$id_supplier',
    '$nama_barang',
    '$tanggal_pembelian',
    '$harga',
    '$volume',
    '$satuan',
    '$keterangan',
    '$nota'
)
";

if (mysqli_query($koneksi, $query)) {
    $_SESSION['alert'] = [
    'icon' => 'success',
    'title' => 'Berhasil',
    'text' => 'Data berhasil ditambahkan'
    ];

    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
} else {
    $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Gagal',
            'text' => 'Data gagal ditambahkan'
        ];
        
        header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}