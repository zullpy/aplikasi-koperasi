<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Hanya admin yang dapat mengupload nota pembelian.'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

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

if (!function_exists('reArrayFiles')) {
    function reArrayFiles($file_post) {
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

if (!empty($uploaded_files)) {
    $nota = implode(',', $uploaded_files);
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
            $old_notas = explode(',', $row['nota']);
            foreach ($old_notas as $old_nota) {
                $old_nota = trim($old_nota);
                if (!empty($old_nota)) {
                    $file_path = "../uploads/nota/" . $old_nota;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
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