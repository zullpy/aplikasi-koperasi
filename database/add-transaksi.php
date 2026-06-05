<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

    $nota = uniqid() . '.' . $ext;

    move_uploaded_file(
        $_FILES['nota_kamera']['tmp_name'],
        '../uploads/nota/' . $nota
    );
}

/*
|--------------------------------------------------------------------------
| Upload dari File/Galeri
|--------------------------------------------------------------------------
*/
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
    echo "<script>alert('Data Berhasil Ditambahkan!'); window.location.href = '../transaksi-pembelian-food/index.php';</script>";
    exit;
} else {
    echo "<script>alert('Data Gagal Ditambahkan!');</script>";
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}