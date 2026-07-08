<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Hanya admin yang dapat mengubah transaksi pembelian.'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

if (isset($_POST['id_pembelian'])) {
    $id_pembelian = mysqli_real_escape_string($koneksi, $_POST['id_pembelian']);
    $id_supplier  = mysqli_real_escape_string($koneksi, $_POST['id_supplier']);

    $nama_barang = mysqli_real_escape_string($koneksi, is_array($_POST['nama_barang']) ? $_POST['nama_barang'][0] : $_POST['nama_barang']);
    $tanggal     = mysqli_real_escape_string($koneksi, is_array($_POST['tanggal_pembelian']) ? $_POST['tanggal_pembelian'][0] : $_POST['tanggal_pembelian']);

    $harga_raw = is_array($_POST['harga']) ? $_POST['harga'][0] : $_POST['harga'];
    $harga     = preg_replace('/[^0-9]/', '', $harga_raw);

    // ✅ Ambil keuntungan dan hitung harga jual
    $keuntungan_raw = is_array($_POST['keuntungan']) ? $_POST['keuntungan'][0] : ($_POST['keuntungan'] ?? '0');
    $keuntungan     = preg_replace('/[^0-9]/', '', $keuntungan_raw);
    $harga_jual     = $harga + $keuntungan;

    $volume     = mysqli_real_escape_string($koneksi, is_array($_POST['volume']) ? $_POST['volume'][0] : $_POST['volume']);
    $satuan     = mysqli_real_escape_string($koneksi, is_array($_POST['satuan']) ? $_POST['satuan'][0] : $_POST['satuan']);
    $keterangan = mysqli_real_escape_string($koneksi, is_array($_POST['keterangan']) ? $_POST['keterangan'][0] : $_POST['keterangan']);

    // ambil data transaksi lama
    $qLama = mysqli_query($koneksi, "SELECT volume FROM transaksi_pembelian WHERE id_pembelian = '$id_pembelian'");
    $dataLama    = mysqli_fetch_assoc($qLama);
    $volume_lama = $dataLama['volume'];
    $selisih = $volume - $volume_lama;

    // cari barang
    $qBarang = mysqli_query($koneksi, "SELECT id_barang, stok_akhir FROM barang WHERE nama_barang = '$nama_barang'");
    $barang    = mysqli_fetch_assoc($qBarang);
    $id_barang = $barang['id_barang'];
    $stok_lama = $barang['stok_akhir'];
    $stok_baru = $stok_lama + $selisih;

    $query = "UPDATE transaksi_pembelian SET
        id_supplier       = '$id_supplier',
        nama_barang       = '$nama_barang',
        keterangan        = '$keterangan',
        harga             = '$harga',
        volume            = '$volume',
        satuan            = '$satuan',
        tanggal_pembelian = '$tanggal'
        WHERE id_pembelian = '$id_pembelian'";

    if (mysqli_query($koneksi, $query)) {
        // ✅ update stok, harga beli, harga jual, dan tanggal di tabel barang
        mysqli_query($koneksi, "
            UPDATE barang
            SET stok_akhir            = '$stok_baru',
                harga_beli            = '$harga',
                harga_jual            = '$harga_jual',
                tanggal_terupdate_baru = '$tanggal'
            WHERE id_barang = '$id_barang'
        ");

        if ($selisih != 0) {
            mysqli_query($koneksi, "
                INSERT INTO mutasi_stok(
                    id_pembelian, id_barang, tanggal, jenis, qty,
                    stok_sebelum, stok_sesudah, keterangan
                ) VALUES(
                    '$id_pembelian', '$id_barang', NOW(), 'perubahan', '$selisih',
                    '$stok_lama', '$stok_baru', 'Edit transaksi pembelian'
                )
            ");
        }

        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Berhasil',
            'text'  => 'Data transaksi berhasil diubah. Harga beli, harga jual & tanggal otomatis terupdate ke tabel barang.'
        ];
    } else {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => mysqli_error($koneksi)];
    }
} else {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID Pembelian tidak ditemukan'];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;
