<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang   = $_POST['nama_barang'];
$keterangan    = $_POST['keterangan'];
$harga         = $_POST['harga'];
$harga_eceran  = $_POST['harga_eceran'] ?? []; // ✅ Field baru: Harga Eceran
$keuntungan    = $_POST['keuntungan'] ?? [];
$volume        = $_POST['volume'];
$satuan        = $_POST['satuan'];

// FIX: tanggal bisa array atau string
$tanggal_pembelian = is_array($_POST['tanggal_pembelian'])
    ? $_POST['tanggal_pembelian'][0]
    : $_POST['tanggal_pembelian'];

$id_supplier       = $_POST['id_supplier'];
$metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';

// ✅ FIX UTAMA: pastikan biaya_admin selalu integer, minimal 0
$biaya_admin_raw = $_POST['biaya_admin'] ?? '';
$biaya_admin     = preg_replace('/[^0-9]/', '', $biaya_admin_raw);
$biaya_admin     = ($biaya_admin === '' || $biaya_admin === null) ? '0' : $biaya_admin;
$biaya_admin     = (int) $biaya_admin;

$kode_transaksi = 'TRX' . date('YmdHis');

/*
|--------------------------------------------------------------------------
| Upload Nota
|--------------------------------------------------------------------------
*/
$allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 2 * 1024 * 1024; // 2 MB

function reArrayFiles($file_post)
{
    $file_ary = [];
    if (!isset($file_post['name'])) return $file_ary;

    if (is_array($file_post['name'])) {
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);
        for ($i = 0; $i < $file_count; $i++) {
            if ($file_post['error'][$i] == 0 && $file_post['size'][$i] > 0) {
                $item = [];
                foreach ($file_keys as $key) {
                    $item[$key] = $file_post[$key][$i];
                }
                $file_ary[] = $item;
            }
        }
    } else {
        if ($file_post['error'] == 0 && $file_post['size'] > 0) {
            $file_ary[] = $file_post;
        }
    }
    return $file_ary;
}

$all_files = array_merge(
    reArrayFiles($_FILES['nota_kamera'] ?? []),
    reArrayFiles($_FILES['nota_file'] ?? [])
);

foreach ($all_files as $file) {
    if ($file['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran nota maksimal 2 MB per file'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Format file harus JPG, JPEG, PNG, atau PDF'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
}

$uploaded_files = [];
foreach ($all_files as $file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nota_name = uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], '../uploads/nota/' . $nota_name)) {
        $uploaded_files[] = $nota_name;
    }
}

$nota = null;
if (!empty($uploaded_files)) {
    $nota = implode(',', $uploaded_files);
}

/*
|--------------------------------------------------------------------------
| Pastikan array (multi-item)
|--------------------------------------------------------------------------
*/
if (!is_array($nama_barang)) {
    $nama_barang   = [$nama_barang];
    $harga         = [$harga];
    $harga_eceran  = [$harga_eceran];
    $keuntungan    = [$keuntungan];
    $volume        = [$volume];
    $satuan        = [$satuan];
    $keterangan    = [$keterangan];
}

foreach ($nama_barang as $i => $barang_nama) {
    $harga_item          = preg_replace('/[^0-9]/', '', $harga[$i] ?? '0');
    $harga_eceran_item  = preg_replace('/[^0-9]/', '', $harga_eceran[$i] ?? '0');
    $keuntungan_item     = preg_replace('/[^0-9]/', '', $keuntungan[$i] ?? '0');
    $volume_item         = $volume[$i];
    $satuan_item         = $satuan[$i];
    $keterangan_item     = $keterangan[$i];

    // ✅ Hitung harga jual per dus di server
    // Parser keterangan untuk dapat jumlah isi
    $jumlah_isi = 0;
    if (preg_match('/isi\s*(\d+)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
    } elseif (preg_match('/(\d+)\s*(?:pcs|bungkus|pack|botol|sachet|batang|lembar|butir|biji|kotak|dus)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
    }

    // Harga Jual per Dus = Harga Beli + (Keuntungan × Jumlah Isi)
    $harga_jual_item = $jumlah_isi > 0
        ? $harga_item + ($keuntungan_item * $jumlah_isi)
        : $harga_item + $keuntungan_item;

    // Harga Jual Eceran = Harga Eceran + Keuntungan
    $harga_jual_eceran_item = $harga_eceran_item + $keuntungan_item;

    $cari = mysqli_query($koneksi, "SELECT * FROM barang WHERE nama_barang='$barang_nama'");
    $barang = mysqli_fetch_assoc($cari);

    if (!$barang) continue;

    $id_barang = $barang['id_barang'];

    $result = mysqli_query($koneksi, "SELECT stok_akhir FROM barang WHERE id_barang='$id_barang'");
    $data = mysqli_fetch_assoc($result);
    $stok_lama = $data['stok_akhir'];
    $stok_baru = $stok_lama + $volume_item;

    // ✅ Biaya admin hanya di item pertama
    $biaya_admin_item = ($i == 0) ? $biaya_admin : 0;

    $nota_item = $nota;
    $nota_sql = $nota_item !== null ? "'" . mysqli_real_escape_string($koneksi, $nota_item) . "'" : "NULL";

    // Escape string untuk keamanan
    $kode_transaksi_esc    = mysqli_real_escape_string($koneksi, $kode_transaksi);
    $id_supplier_esc       = (int) $id_supplier;
    $barang_nama_esc       = mysqli_real_escape_string($koneksi, $barang_nama);
    $tanggal_esc           = mysqli_real_escape_string($koneksi, $tanggal_pembelian);
    $harga_esc             = (int) $harga_item;
    $harga_eceran_esc      = (int) $harga_eceran_item;
    $harga_jual_esc        = (int) $harga_jual_item;
    $harga_jual_eceran_esc = (int) $harga_jual_eceran_item;
    $volume_esc            = (int) $volume_item;
    $satuan_esc            = mysqli_real_escape_string($koneksi, $satuan_item);
    $keterangan_esc        = mysqli_real_escape_string($koneksi, $keterangan_item);
    $metode_esc            = mysqli_real_escape_string($koneksi, $metode_pembayaran);
    $biaya_admin_esc       = (int) $biaya_admin_item;

    mysqli_query($koneksi, "
        INSERT INTO transaksi_pembelian(
            kode_transaksi, id_supplier, nama_barang, tanggal_pembelian,
            harga, harga_eceran, harga_jual_dus, harga_jual_eceran, volume, satuan, keterangan, nota,
            metode_pembayaran, biaya_admin
        ) VALUES(
            '$kode_transaksi_esc', '$id_supplier_esc', '$barang_nama_esc', '$tanggal_esc',
            '$harga_esc', '$harga_eceran_esc', '$harga_jual_esc', '$harga_jual_eceran_esc', 
            '$volume_esc', '$satuan_esc', '$keterangan_esc',
            $nota_sql,
            '$metode_esc', $biaya_admin_esc
        )
    ") or die('INSERT Error: ' . mysqli_error($koneksi));

    $id_pembelian = mysqli_insert_id($koneksi);

    mysqli_query($koneksi, "
        INSERT INTO riwayat_harga(id_barang, harga_beli, tanggal)
        VALUES('$id_barang', '$harga_esc', '$tanggal_esc')
    ");

    // ✅ UPDATE TABEL BARANG: Harga Beli, Harga Eceran, Harga Jual, Harga Jual Eceran, dan Tanggal Terupdate
    mysqli_query($koneksi, "
        UPDATE barang
        SET stok_akhir='$stok_baru',
            harga_beli='$harga_esc',
            harga_eceran='$harga_eceran_esc',
            harga_jual='$harga_jual_esc',
            harga_jual_eceran='$harga_jual_eceran_esc',
            tanggal_terupdate_baru='$tanggal_esc'
        WHERE id_barang='$id_barang'
    ");

    mysqli_query($koneksi, "
        INSERT INTO mutasi_stok(
            id_pembelian, id_barang, tanggal, jenis, qty,
            stok_sebelum, stok_sesudah, keterangan
        ) VALUES(
            '$id_pembelian', '$id_barang', NOW(), 'masuk', '$volume_esc',
            '$stok_lama', '$stok_baru', 'Pembelian'
        )
    ");
}

$_SESSION['alert'] = [
    'icon'  => 'success',
    'title' => 'Berhasil',
    'text'  => 'Data transaksi berhasil ditambahkan. Harga beli, harga eceran, harga jual & tanggal otomatis terupdate ke tabel barang.'
];

header("Location: ../transaksi-pembelian-food/index.php");
exit;
