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
            $old_notas = explode(',', $nota);
            foreach ($old_notas as $old_nota) {
                $old_nota = trim($old_nota);
                if (!empty($old_nota)) {
                    // Check if any other row in transaksi_pembelian uses this specific file name
                    $check_other = mysqli_query($koneksi, "SELECT id_pembelian FROM transaksi_pembelian WHERE nota LIKE '%" . mysqli_real_escape_string($koneksi, $old_nota) . "%' AND id_pembelian != '$id'");
                    if ($check_other && mysqli_num_rows($check_other) == 0) {
                        $file_path = "../uploads/nota/" . $old_nota;
                        if(file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
            }
        }
    }
    
    $query = mysqli_query($koneksi, "DELETE FROM transaksi_pembelian WHERE id_pembelian='$id'")
             or die(mysqli_error($koneksi));
    
    $query = mysqli_query($koneksi, "DELETE FROM mutasi_stok WHERE id_pembelian='$id'")
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
