<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang = $_POST['nama_barang'];
$keterangan  = $_POST['keterangan'];
$harga       = $_POST['harga'];
$volume      = $_POST['volume'];
$satuan      = $_POST['satuan'];

// FIX 1: tanggal_pembelian bisa array (form lama) atau string (form baru)
$tanggal_pembelian = is_array($_POST['tanggal_pembelian'])
    ? $_POST['tanggal_pembelian'][0]
    : $_POST['tanggal_pembelian'];

$id_supplier = $_POST['id_supplier'];

$kode_transaksi = 'TRX' . date('YmdHis');

$nota = NULL;

/*
|--------------------------------------------------------------------------
| Upload Nota
|--------------------------------------------------------------------------
*/
$allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 2 * 1024 * 1024; // 2 MB

// FIX 2: $uploadFile tidak pernah didefinisikan sebelumnya
$uploadFile = null;
if (isset($_FILES['nota_kamera']) && $_FILES['nota_kamera']['error'] == 0) {
    $uploadFile = $_FILES['nota_kamera'];
} elseif (isset($_FILES['nota_file']) && $_FILES['nota_file']['error'] == 0) {
    $uploadFile = $_FILES['nota_file'];
}

if ($uploadFile && $uploadFile['size'] > $max_size) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Ukuran nota maksimal 2 MB'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

if (
    isset($_FILES['nota_kamera']) &&
    $_FILES['nota_kamera']['error'] == 0
) {
    $ext = strtolower(
        pathinfo($_FILES['nota_kamera']['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($ext, $allowed)) {
        die('Format file tidak didukung!');
    }

    $nota = uniqid() . '.' . $ext;
    move_uploaded_file(
        $_FILES['nota_kamera']['tmp_name'],
        '../uploads/nota/' . $nota
    );

} elseif (
    isset($_FILES['nota_file']) &&
    $_FILES['nota_file']['error'] == 0
) {
    $ext = strtolower(
        pathinfo($_FILES['nota_file']['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($ext, $allowed)) {
        die('Format file tidak didukung!');
    }

    $nota = uniqid() . '.' . $ext;
    move_uploaded_file(
        $_FILES['nota_file']['tmp_name'],
        '../uploads/nota/' . $nota
    );
}

// FIX 3: $nama_barang bisa string (1 item) atau array (multi item)
// Pastikan selalu array agar foreach berjalan konsisten
if (!is_array($nama_barang)) {
    $nama_barang  = [$nama_barang];
    $harga        = [$harga];
    $volume       = [$volume];
    $satuan       = [$satuan];
    $keterangan   = [$keterangan];
}

foreach ($nama_barang as $i => $barang_nama) {

    $harga_item      = preg_replace('/[^0-9]/', '', $harga[$i]);
    $volume_item     = $volume[$i];
    $satuan_item     = $satuan[$i];
    $keterangan_item = $keterangan[$i];

    $cari = mysqli_query(
        $koneksi,
        "SELECT * FROM barang WHERE nama_barang='$barang_nama'"
    );

    $barang = mysqli_fetch_assoc($cari);

    if (!$barang) {
        continue;
    }

    $id_barang = $barang['id_barang'];

    $result = mysqli_query(
        $koneksi,
        "SELECT stok_akhir FROM barang WHERE id_barang='$id_barang'"
    );

    $data = mysqli_fetch_assoc($result);

    $stok_lama = $data['stok_akhir'];
    $stok_baru = $stok_lama + $volume_item;

    $nota_item = ($i == 0) ? $nota : NULL;

    mysqli_query($koneksi, "
        INSERT INTO transaksi_pembelian(
            kode_transaksi,
            id_supplier,
            nama_barang,
            tanggal_pembelian,
            harga,
            volume,
            satuan,
            keterangan,
            nota
        )
        VALUES(
            '$kode_transaksi',
            '$id_supplier',
            '$barang_nama',
            '$tanggal_pembelian',
            '$harga_item',
            '$volume_item',
            '$satuan_item',
            '$keterangan_item',
            '$nota_item'
        )
    ");

    $id_pembelian = mysqli_insert_id($koneksi);

    mysqli_query($koneksi, "
        INSERT INTO riwayat_harga(
            id_barang,
            harga_beli,
            tanggal
        )
        VALUES(
            '$id_barang',
            '$harga_item',
            '$tanggal_pembelian'
        )
    ");

    mysqli_query($koneksi, "
        UPDATE barang
        SET
            stok_akhir='$stok_baru',
            harga_beli='$harga_item',
            tanggal_terupdate_baru='$tanggal_pembelian'
        WHERE id_barang='$id_barang'
    ");

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
            'masuk',
            '$volume_item',
            '$stok_lama',
            '$stok_baru',
            'Pembelian'
        )
    ");
}

$_SESSION['alert'] = [
    'icon'  => 'success',
    'title' => 'Berhasil',
    'text'  => 'Data berhasil ditambahkan'
];

header("Location: ../transaksi-pembelian-food/index.php");
exit;