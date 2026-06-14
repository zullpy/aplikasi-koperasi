<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'koneksi.php';

$nama_barang = $_GET['nama_barang'];

$query = mysqli_query($koneksi,"
    SELECT
        b.nama_barang,
        b.harga_beli,
        b.stok_akhir,
        b.satuan,
        COALESCE(MIN(r.harga_beli), b.harga_beli) AS harga_min,
        COALESCE(MAX(r.harga_beli), b.harga_beli) AS harga_max
    FROM barang b
    LEFT JOIN riwayat_harga r
        ON b.id_barang = r.id_barang
    WHERE b.nama_barang = '$nama_barang'
    GROUP BY
        b.id_barang,
        b.nama_barang,
        b.harga_beli,   
        b.stok_akhir,
        b.satuan
");

if(mysqli_num_rows($query) > 0){

    $data = mysqli_fetch_assoc($query);

    echo json_encode([
        'status' => 'ada',
        'harga' => $data['harga_beli'],
        'harga_min' => $data['harga_min'],
        'harga_max' => $data['harga_max'],
        'stok' => $data['stok_akhir'],
        'satuan' => $data['satuan']
    ]);

}else{

    echo json_encode([
        'status' => 'tidak_ada'
    ]);

}

