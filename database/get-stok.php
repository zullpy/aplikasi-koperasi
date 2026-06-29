<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

$sql = "SELECT nama_barang, stok_akhir, harga_beli, satuan FROM barang ORDER BY nama_barang ASC";
$result = $koneksi->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'nama_barang' => $row['nama_barang'],
            'stok_akhir'  => (float)$row['stok_akhir'],
            'harga_beli'  => (float)$row['harga_beli'],
            'satuan'      => $row['satuan'] ?? '',
        ];
    }
}

echo json_encode(['success' => true, 'data' => $data]);
