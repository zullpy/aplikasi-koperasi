<?php
// Mengembalikan daftar kategori unik dari tabel barang, untuk dropdown/datalist
// yang juga bisa diketik bebas (free text) di form tambah transaksi.
header('Content-Type: application/json');
include 'koneksi.php';

$result = mysqli_query($koneksi, "
    SELECT DISTINCT kategori
    FROM barang
    WHERE kategori IS NOT NULL AND kategori <> ''
    ORDER BY kategori ASC
");

$kategori_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $kategori_list[] = $row['kategori'];
}

echo json_encode($kategori_list);
