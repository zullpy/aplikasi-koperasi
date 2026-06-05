<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'koneksi.php';

if (isset($_POST['id_barang'])) {
    $id_barang = mysqli_real_escape_string($koneksi, $_POST['id_barang']);
    
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
        
        $query = "UPDATE barang SET nota = '$nota' WHERE id_barang = '$id_barang'";
        if (mysqli_query($koneksi, $query)) {
            echo "<script>alert('Nota Berhasil Diunggah!'); window.location.href = '../transaksi-pembelian-food/index.php';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal memperbarui database!'); window.location.href = '../transaksi-pembelian-food/index.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Silakan pilih atau ambil foto nota terlebih dahulu!'); window.location.href = '../transaksi-pembelian-food/index.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID Barang tidak ditemukan!'); window.location.href = '../transaksi-pembelian-food/index.php';</script>";
    exit;
}
?>
