<?php
include 'koneksi.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $query = mysqli_query($koneksi, "SELECT * FROM barang WHERE id_barang = '$id'");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        
        // Format the date to YYYY-MM-DD for date input in frontend
        if (!empty($data['tanggal_terupdate_baru'])) {
            $date_str = $data['tanggal_terupdate_baru'];
            
            // Try standard PHP parsing (supports English months e.g. "28 May 2026")
            $timestamp = strtotime($date_str);
            if ($timestamp !== false) {
                $data['tanggal_terupdate_baru'] = date('Y-m-d', $timestamp);
            } else {
                // Handle Indonesian month names translation
                $indonesian_months = [
                    'Januari' => 'January', 'Februari' => 'February', 'Maret' => 'March',
                    'April' => 'April', 'Mei' => 'May', 'Juni' => 'June',
                    'Juli' => 'July', 'Agustus' => 'August', 'September' => 'September',
                    'Oktober' => 'October', 'November' => 'November', 'Desember' => 'December'
                ];
                $date_translated = strtr($date_str, $indonesian_months);
                $timestamp = strtotime($date_translated);
                if ($timestamp !== false) {
                    $data['tanggal_terupdate_baru'] = date('Y-m-d', $timestamp);
                }
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
}
