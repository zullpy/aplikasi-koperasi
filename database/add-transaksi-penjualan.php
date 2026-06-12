<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

$no_faktur   = $_POST['no_faktur'];
$id_pelanggan = $_POST['id_pelanggan'];
$tanggal = $_POST['tanggal'] . ' ' . date('H:i:s');

$id_barang = $_POST['id_barang'];
$qty       = $_POST['qty'];
$satuan    = $_POST['satuan'];
$harga     = $_POST['harga'];
$subtotal  = $_POST['subtotal'];

$total = 0;

/*
|--------------------------------------------------------------------------
| Hitung Total Transaksi
|--------------------------------------------------------------------------
*/

foreach ($subtotal as $s) {

    $angka = preg_replace('/[^0-9]/', '', $s);

    $total += (int)$angka;
}

/*
|--------------------------------------------------------------------------
| Simpan Transaksi
|--------------------------------------------------------------------------
*/

$query_transaksi = mysqli_query(
    $koneksi,
    "INSERT INTO transaksi_penjualan (
        no_faktur,
        tanggal,
        total,
        id_pelanggan
    )
    VALUES (
        '$no_faktur',
        '$tanggal',
        '$total',
        '$id_pelanggan'
    )"
);

if (!$query_transaksi) {

    die(
        "Gagal menyimpan transaksi: " .
        mysqli_error($koneksi)
    );
}

/*
|--------------------------------------------------------------------------
| Ambil ID Transaksi Terakhir
|--------------------------------------------------------------------------
*/

$id_transaksi = mysqli_insert_id($koneksi);

/*
|--------------------------------------------------------------------------
| Simpan Detail Barang
|--------------------------------------------------------------------------
*/

for ($i = 0; $i < count($id_barang); $i++) {

    if (empty($id_barang[$i])) {
        continue;
    }

    $harga_bersih = preg_replace(
        '/[^0-9]/',
        '',
        $harga[$i]
    );

    $subtotal_bersih = preg_replace(
        '/[^0-9]/',
        '',
        $subtotal[$i]
    );

    $query_detail = mysqli_query(
        $koneksi,
        "INSERT INTO detail_penjualan (
            id_transaksi,
            id_barang,
            qty,
            satuan,
            harga_jual,
            subtotal
        )
        VALUES (
            '$id_transaksi',
            '{$id_barang[$i]}',
            '{$qty[$i]}',
            '{$satuan[$i]}',
            '$harga_bersih',
            '$subtotal_bersih'
        )"
    );

    if (!$query_detail) {

        die(
            "Gagal menyimpan detail transaksi: " .
            mysqli_error($koneksi)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Notifikasi
|--------------------------------------------------------------------------
*/

$_SESSION['alert'] = [
    'icon'  => 'success',
    'title' => 'Berhasil',
    'text'  => 'Transaksi berhasil disimpan'
];

header("Location: ../transaksi-penjualan-food/index.php");
exit;