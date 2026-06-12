<?php 
session_start();
include 'koneksi.php';

if(isset($_GET['id'])){
    $id = $_GET['id'];
    
    $get_nota = mysqli_query($koneksi, "SELECT nota FROM transaksi_pembelian WHERE id_pembelian='$id'");
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
    
    $query = mysqli_query($koneksi, "DELETE FROM transaksi_pembelian WHERE id_pembelian='$id'")
             or die(mysqli_error($koneksi));
             
    $_SESSION['alert'] = [
    'icon' => 'success',
    'title' => 'Berhasil',
    'text' => 'Data berhasil dihapus'
];

header("Location: ../transaksi-pembelian-food/");
exit;
}
?>
