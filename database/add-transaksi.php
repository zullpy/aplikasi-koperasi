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

$cari = mysqli_query($koneksi,"
SELECT id_barang
FROM barang
WHERE nama_barang = '$nama_barang'
");

$barang = mysqli_fetch_assoc($cari);

if (!$barang) {
    $_SESSION['alert'] = [
        'icon' => 'warning',
        'title' => 'Barang Belum Terdaftar',
        'text' => 'Silakan daftarkan barang terlebih dahulu pada menu Data Barang.'
    ];

    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

$id_barang = $barang['id_barang'];

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
)";

$result = mysqli_query($koneksi,"
SELECT stok_akhir
FROM barang
WHERE id_barang = '$id_barang'
");

$data = mysqli_fetch_assoc($result);

$stok_lama = $data['stok_akhir'];
$stok_baru = $stok_lama + $volume;

if (mysqli_query($koneksi, $query)) {
    $id_pembelian = mysqli_insert_id($koneksi);

        // simpan riwayat harga
    mysqli_query($koneksi,"
    INSERT INTO riwayat_harga (
        id_barang,
        harga_beli,
        tanggal
    )
    VALUES (
        '$id_barang',
        '$harga',
        '$tanggal_pembelian'
    )
    ");

    mysqli_query($koneksi,"
    UPDATE barang
    SET stok_akhir = '$stok_baru',  harga_beli = '$harga', tanggal_terupdate_baru = '$tanggal_pembelian'
    WHERE id_barang = '$id_barang'
    ");

    mysqli_query($koneksi,"
    INSERT INTO mutasi_stok(
        id_pembelian,
        id_barang,
        tanggal,
        jenis,
        qty,
        stok_sebelum,
        stok_sesudah,
        keterangan
    )
    VALUES(
        '$id_pembelian',
        '$id_barang',
        NOW(),
        'masuk',
        '$volume',
        '$stok_lama',
        '$stok_baru',
        'Pembelian'
    )
    ");

    $_SESSION['alert'] = [
        'icon' => 'success',
        'title' => 'Berhasil',
        'text' => 'Data berhasil ditambahkan'
    ];

    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}