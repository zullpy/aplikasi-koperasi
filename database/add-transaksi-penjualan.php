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

for ($i = 0; $i < count($id_barang); $i++) {

    if (empty($id_barang[$i])) {
        continue;
    }

    $cek_barang = mysqli_query($koneksi,"
    SELECT nama_barang, stok_akhir
    FROM barang
    WHERE id_barang = '{$id_barang[$i]}'
    ");

    $barang = mysqli_fetch_assoc($cek_barang);

    if ($barang['stok_akhir'] < $qty[$i]) {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Gagal',
            'text'  => 'Stok ' . $barang['nama_barang'] .
                       ' tidak mencukupi. Sisa stok: ' .
                       $barang['stok_akhir']
        ];

        header("Location: ../transaksi-penjualan-food/index.php");
        exit;
    }
}

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

$stok_lama = $data_barang['stok_akhir'];

if ($stok_lama < $qty[$i]) {
    continue; // atau exit
}

$stok_baru = $stok_lama - $qty[$i];

mysqli_query($koneksi,"
UPDATE barang
SET stok_akhir = '$stok_baru'
WHERE id_barang = '{$id_barang[$i]}'
");

mysqli_query($koneksi,"
INSERT INTO mutasi_stok(
    id_barang,
    tanggal,
    jenis,
    qty,
    stok_sebelum,
    stok_sesudah,
    keterangan
)
VALUES(
    '{$id_barang[$i]}',
    NOW(),
    'keluar',
    '{$qty[$i]}',
    '$stok_lama',
    '$stok_baru',
    'Penjualan $no_faktur'
)
");
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