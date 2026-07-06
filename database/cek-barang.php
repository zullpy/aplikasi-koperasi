<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'koneksi.php';

// ✅ FIX: escape input GET supaya tidak rawan SQL injection
$nama_barang = mysqli_real_escape_string($koneksi, $_GET['nama_barang'] ?? '');

$query = mysqli_query($koneksi, "
    SELECT
        b.nama_barang,
        b.kategori,
        b.harga_beli,
        b.harga_eceran,
        b.harga_jual,
        b.harga_jual_eceran,
        b.stok_akhir,
        b.satuan,
        b.satuan_eceran,
        b.isi_per_satuan,
        b.tanggal_terupdate_baru,
        COALESCE(MIN(r.harga_beli), b.harga_beli) AS harga_min,
        COALESCE(MAX(r.harga_beli), b.harga_beli) AS harga_max
    FROM barang b
    LEFT JOIN riwayat_harga r
        ON b.id_barang = r.id_barang
    WHERE b.nama_barang = '$nama_barang'
    GROUP BY
        b.id_barang,
        b.nama_barang,
        b.kategori,
        b.harga_beli,
        b.harga_eceran,
        b.harga_jual,
        b.harga_jual_eceran,
        b.stok_akhir,
        b.satuan,
        b.satuan_eceran,
        b.isi_per_satuan,
        b.tanggal_terupdate_baru
");

if ($query && mysqli_num_rows($query) > 0) {

    $data = mysqli_fetch_assoc($query);

    echo json_encode([
        'status'                 => 'ada',
        'kategori'               => $data['kategori'],
        'harga'                  => $data['harga_beli'],
        'harga_min'              => $data['harga_min'],
        'harga_max'              => $data['harga_max'],
        'harga_eceran'           => $data['harga_eceran'],
        'harga_jual'             => $data['harga_jual'],
        'harga_jual_eceran'      => $data['harga_jual_eceran'],
        'stok'                   => $data['stok_akhir'],
        // ✅ BARU: dipakai script.js untuk autofill & preview stok eceran
        'satuan'                 => $data['satuan'],
        'satuan_eceran'          => $data['satuan_eceran'],
        'isi_per_satuan'         => $data['isi_per_satuan'],
        'tanggal_terupdate_baru' => $data['tanggal_terupdate_baru']
    ]);
} else {

    echo json_encode([
        'status' => 'tidak_ada'
    ]);
}
