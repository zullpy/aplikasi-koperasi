<?php 
include 'koneksi.php';

if(isset($_GET['id'])){
    $id = $_GET['id'];
    
    $get_nota = mysqli_query($koneksi, "SELECT nota FROM barang WHERE id_barang='$id'");
    if($get_nota && mysqli_num_rows($get_nota) > 0) {
        $row = mysqli_fetch_assoc($get_nota);
        $nota = $row['nota'];
        
        if(!empty($nota)) {
            $file_path = "../uploads/nota/" . $nota;
            if(file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    $query = mysqli_query($koneksi, "DELETE FROM barang WHERE id_barang='$id'")
             or die(mysqli_error($koneksi));
             
    echo "<script>alert('Data Berhasil Dihapus!'); window.location.href = '../transaksi-pembelian/';</script>";
    exit;
}
?>
