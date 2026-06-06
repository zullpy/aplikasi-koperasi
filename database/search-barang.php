<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'koneksi.php';

$keyword = $_GET['keyword'] ?? '';

$query = mysqli_query(
    $koneksi,
    "SELECT id_barang,nama_barang,harga_beli,satuan
     FROM barang
     WHERE nama_barang LIKE '%$keyword%'
     LIMIT 10"
);

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode($data);