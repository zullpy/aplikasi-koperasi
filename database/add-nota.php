<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$max_size = 2 * 1024 * 1024; // 2 MB

if (!isset($_POST['id_barang']) || empty($_POST['id_barang'])) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'ID Pembelian tidak ditemukan'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

$id_barang = mysqli_real_escape_string($koneksi, $_POST['id_barang']);

/*
|--------------------------------------------------------------------------
| ✅ FIX: Ambil kode_transaksi dari id_pembelian (untuk update per supplier)
|--------------------------------------------------------------------------
*/
$get_kode = mysqli_query(
    $koneksi,
    "SELECT kode_transaksi FROM transaksi_pembelian WHERE id_pembelian = '$id_barang'"
);
if (!$get_kode || mysqli_num_rows($get_kode) == 0) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Data transaksi tidak ditemukan'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}
$kode_row       = mysqli_fetch_assoc($get_kode);
$kode_transaksi = $kode_row['kode_transaksi'];

/*
|--------------------------------------------------------------------------
| Upload Nota Baru
|--------------------------------------------------------------------------
*/
$allowed      = ['jpg', 'jpeg', 'png', 'pdf'];
$nota         = null;
$has_new_nota = false;

if (isset($_FILES['nota_kamera']) && $_FILES['nota_kamera']['error'] == 0) {
    $uploadFile = $_FILES['nota_kamera'];
    if ($uploadFile['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran nota maksimal 2 MB'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    $ext = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ekstensi harus JPG/JPEG/PNG/PDF'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    $nota = uniqid() . '.' . $ext;
    move_uploaded_file($uploadFile['tmp_name'], '../uploads/nota/' . $nota);
    $has_new_nota = true;
} elseif (isset($_FILES['nota_file']) && $_FILES['nota_file']['error'] == 0) {
    $uploadFile = $_FILES['nota_file'];
    if ($uploadFile['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran nota maksimal 2 MB'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    $ext = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ekstensi harus JPG/JPEG/PNG/PDF'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    $nota = uniqid() . '.' . $ext;
    move_uploaded_file($uploadFile['tmp_name'], '../uploads/nota/' . $nota);
    $has_new_nota = true;
}

if ($has_new_nota) {
    /*
    |--------------------------------------------------------------------------
    | ✅ FIX: Hapus nota lama dari SEMUA item dengan kode_transaksi yang sama
    |--------------------------------------------------------------------------
    */
    $get_old_nota = mysqli_query(
        $koneksi,
        "SELECT nota FROM transaksi_pembelian
         WHERE kode_transaksi = '$kode_transaksi' AND nota IS NOT NULL
         LIMIT 1"
    );
    if ($get_old_nota && mysqli_num_rows($get_old_nota) > 0) {
        $row = mysqli_fetch_assoc($get_old_nota);
        if (!empty($row['nota'])) {
            $file_path = "../uploads/nota/" . $row['nota'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ FIX: Update nota di SEMUA item dengan kode_transaksi yang sama
    |--------------------------------------------------------------------------
    */
    $nota_escaped = mysqli_real_escape_string($koneksi, $nota);
    $query = "UPDATE transaksi_pembelian
              SET nota = '$nota_escaped'
              WHERE kode_transaksi = '$kode_transaksi'";

    if (mysqli_query($koneksi, $query)) {
        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Berhasil',
            'text'  => 'Nota berhasil diunggah untuk semua item supplier ini'
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
        'text'  => 'Silakan pilih atau ambil foto nota terlebih dahulu!'
    ];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;