<?php 
session_start();
include 'koneksi.php';

$no_faktur = $_POST['no_faktur'];
$nama_konsumen = $_POST['nama_konsumen'];
$no_kontak = $_POST['no_kontak'];
$alamat = $_POST['alamat'];
$tanggal = $_POST['tanggal'];

$id_barang = $_POST['id_barang'];
$qty = $_POST['qty'];
$satuan = $_POST['satuan'];
$harga = $_POST['harga'];
$subtotal = $_POST['subtotal'];

$total = 0;

foreach($subtotal as $s){

    $angka =
        preg_replace('/[^0-9]/', '', $s);

    $total += $angka;
}

mysqli_query(
    $koneksi,
    "INSERT INTO transaksi_penjualan(
        no_faktur,
        nama_konsumen,
        no_kontak,
        alamat,
        tanggal,
        total
    )
    VALUES(
        '$no_faktur',
        '$nama_konsumen',
        '$no_kontak',
        '$alamat',
        '$tanggal',
        '$total'
    )"
);

$id_transaksi =
    mysqli_insert_id($koneksi);

for($i=0; $i<count($id_barang); $i++){

    $harga_bersih =
        preg_replace(
            '/[^0-9]/',
            '',
            $harga[$i]
        );

    $subtotal_bersih =
        preg_replace(
            '/[^0-9]/',
            '',
            $subtotal[$i]
        );

    mysqli_query(
        $koneksi,
        "INSERT INTO detail_penjualan(
            id_transaksi,
            id_barang,
            qty,
            satuan,
            harga_jual,
            subtotal
        )
        VALUES(
            '$id_transaksi',
            '{$id_barang[$i]}',
            '{$qty[$i]}',
            '{$satuan[$i]}',
            '$harga_bersih',
            '$subtotal_bersih'
        )"
    );
}



$_SESSION['alert'] = [
    'icon' => 'success',
    'title' => 'Berhasil',
    'text' => 'Transaksi berhasil disimpan'
];

header("Location: ../transaksi-penjualan-food/index.php");
exit;
