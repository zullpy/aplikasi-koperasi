<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang = $_POST['nama_barang'];
$tanggal = $_POST['tanggal'];
$harga_beli = $_POST['harga_beli'];
$stok_akhir = $_POST['stok_akhir'];
$satuan = $_POST['satuan'];
$keterangan = $_POST['keterangan'];

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
INSERT INTO barang (
    nama_barang,
    tgl_terupdate,
    harga_beli,
    stok_akhir,
    satuan,
    keterangan,
    nota
)
VALUES (
    '$nama_barang',
    '$tanggal',
    '$harga_beli',
    '$stok_akhir',
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