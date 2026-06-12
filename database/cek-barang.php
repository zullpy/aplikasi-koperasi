<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'koneksi.php';

$nama_barang = $_GET['nama_barang'];

$query = mysqli_query($koneksi,"
    SELECT nama_barang, harga_beli, stok_akhir
    FROM barang
    WHERE nama_barang = '$nama_barang'
");

if(mysqli_num_rows($query) > 0){

    $data = mysqli_fetch_assoc($query);

    echo json_encode([
        'status' => 'ada',
        'harga' => $data['harga_beli'],
        'stok' => $data['stok_akhir']
    ]);

}else{

    echo json_encode([
        'status' => 'tidak_ada'
    ]);

}