<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'koneksi.php';

if (isset($_POST['id_barang'])) {
    $id_barang = mysqli_real_escape_string($koneksi, $_POST['id_barang']);
    $nama_barang = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $harga_beli = mysqli_real_escape_string($koneksi, $_POST['harga_beli']);
    $stok_akhir = mysqli_real_escape_string($koneksi, $_POST['stok_akhir']);
    $satuan = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    
    $nota = NULL;
    $has_new_nota = false;
    
    /*
    |--------------------------------------------------------------------------
    | Upload dari Kamera
    |--------------------------------------------------------------------------
    */
    if (
        isset($_FILES['nota_kamera']) &&
        $_FILES['nota_kamera']['error'] == 0
    ) {
        $ext = strtolower(
            pathinfo(
                $_FILES['nota_kamera']['name'],
                PATHINFO_EXTENSION
            )
        );
        $nota = uniqid() . '.' . $ext;
        move_uploaded_file(
            $_FILES['nota_kamera']['tmp_name'],
            '../uploads/nota/' . $nota
        );
        $has_new_nota = true;
    }
    /*
    |--------------------------------------------------------------------------
    | Upload dari File/Galeri
    |--------------------------------------------------------------------------
    */
    elseif (
        isset($_FILES['nota_file']) &&
        $_FILES['nota_file']['error'] == 0
    ) {
        $ext = strtolower(
            pathinfo(
                $_FILES['nota_file']['name'],
                PATHINFO_EXTENSION
            )
        );
        $nota = uniqid() . '.' . $ext;
        move_uploaded_file(
            $_FILES['nota_file']['tmp_name'],
            '../uploads/nota/' . $nota
        );
        $has_new_nota = true;
    }
    
    if ($has_new_nota) {
        // Hapus nota lama jika ada
        $get_old_nota = mysqli_query($koneksi, "SELECT nota FROM barang WHERE id_barang='$id_barang'");
        if ($get_old_nota && mysqli_num_rows($get_old_nota) > 0) {
            $row = mysqli_fetch_assoc($get_old_nota);
            $old_nota = $row['nota'];
            if (!empty($old_nota)) {
                $file_path = "../uploads/nota/" . $old_nota;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        
        $query = "UPDATE barang SET 
                    nama_barang = '$nama_barang',
                    tgl_terupdate = '$tanggal',
                    harga_beli = '$harga_beli',
                    stok_akhir = '$stok_akhir',
                    satuan = '$satuan',
                    keterangan = '$keterangan',
                    nota = '$nota'
                  WHERE id_barang = '$id_barang'";
    } else {
        $query = "UPDATE barang SET 
                    nama_barang = '$nama_barang',
                    tgl_terupdate = '$tanggal',
                    harga_beli = '$harga_beli',
                    stok_akhir = '$stok_akhir',
                    satuan = '$satuan',
                    keterangan = '$keterangan'
                  WHERE id_barang = '$id_barang'";
    }
    
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Data Berhasil Diubah!'); window.location.href = '../transaksi-pembelian/index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Data Gagal Diubah! " . mysqli_real_escape_string($koneksi, mysqli_error($koneksi)) . "'); window.location.href = '../transaksi-pembelian/index.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID Barang tidak valid!'); window.location.href = '../transaksi-pembelian/index.php';</script>";
    exit;
}
?>
