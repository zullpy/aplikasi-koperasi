<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

if (isset($_POST['id_pembelian'])) {

    $id_pembelian = mysqli_real_escape_string(
        $koneksi,
        $_POST['id_pembelian']
    );

    $id_supplier = mysqli_real_escape_string(
        $koneksi,
        $_POST['id_supplier']
    );

    // FIX: nama_barang[] dikirim sebagai array dari form edit
    $nama_barang = mysqli_real_escape_string(
        $koneksi,
        is_array($_POST['nama_barang']) ? $_POST['nama_barang'][0] : $_POST['nama_barang']
    );

    // FIX: tanggal_pembelian[] dikirim sebagai array dari form edit
    $tanggal = mysqli_real_escape_string(
        $koneksi,
        is_array($_POST['tanggal_pembelian']) ? $_POST['tanggal_pembelian'][0] : $_POST['tanggal_pembelian']
    );

    // FIX: harga[] dikirim sebagai array dari form edit
    $harga_raw = is_array($_POST['harga']) ? $_POST['harga'][0] : $_POST['harga'];
    $harga = preg_replace('/[^0-9]/', '', $harga_raw);

    // FIX: volume[] dikirim sebagai array dari form edit
    $volume = mysqli_real_escape_string(
        $koneksi,
        is_array($_POST['volume']) ? $_POST['volume'][0] : $_POST['volume']
    );

    // FIX: satuan[] dikirim sebagai array dari form edit
    $satuan = mysqli_real_escape_string(
        $koneksi,
        is_array($_POST['satuan']) ? $_POST['satuan'][0] : $_POST['satuan']
    );

    // FIX: keterangan[] dikirim sebagai array dari form edit
    $keterangan = mysqli_real_escape_string(
        $koneksi,
        is_array($_POST['keterangan']) ? $_POST['keterangan'][0] : $_POST['keterangan']
    );

    // ambil data transaksi lama
    $qLama = mysqli_query($koneksi, "
        SELECT volume
        FROM transaksi_pembelian
        WHERE id_pembelian = '$id_pembelian'
    ");

    $dataLama    = mysqli_fetch_assoc($qLama);
    $volume_lama = $dataLama['volume'];

    // hitung selisih volume
    $selisih = $volume - $volume_lama;

    // cari barang
    $qBarang = mysqli_query($koneksi, "
        SELECT id_barang, stok_akhir
        FROM barang
        WHERE nama_barang = '$nama_barang'
    ");

    $barang    = mysqli_fetch_assoc($qBarang);
    $id_barang = $barang['id_barang'];
    $stok_lama = $barang['stok_akhir'];
    $stok_baru = $stok_lama + $selisih;

    $query = "
        UPDATE transaksi_pembelian SET
            id_supplier       = '$id_supplier',
            nama_barang       = '$nama_barang',
            keterangan        = '$keterangan',
            harga             = '$harga',
            volume            = '$volume',
            satuan            = '$satuan',
            tanggal_pembelian = '$tanggal'
        WHERE id_pembelian = '$id_pembelian'
    ";

    if (mysqli_query($koneksi, $query)) {

        // update stok barang
        mysqli_query($koneksi, "
            UPDATE barang
            SET
                stok_akhir            = '$stok_baru',
                harga_beli            = '$harga',
                tanggal_terupdate_baru = '$tanggal'
            WHERE id_barang = '$id_barang'
        ");

        // jika ada perubahan qty
        if ($selisih != 0) {

            mysqli_query($koneksi, "
                INSERT INTO mutasi_stok(
                    id_pembelian,
                    id_barang,
                    tanggal,
                    jenis,
                    qty,
                    stok_sebelum,
                    stok_sesudah,
                    keterangan
                )
                VALUES(
                    '$id_pembelian',
                    '$id_barang',
                    NOW(),
                    'perubahan',
                    '$selisih',
                    '$stok_lama',
                    '$stok_baru',
                    'Edit transaksi pembelian'
                )
            ");
        }

        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Berhasil',
            'text'  => 'Data berhasil diubah'
        ];

    } else {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Gagal',
            'text'  => mysqli_error($koneksi)
        ];
    }

} else {

    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'ID Pembelian tidak ditemukan'
    ];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;