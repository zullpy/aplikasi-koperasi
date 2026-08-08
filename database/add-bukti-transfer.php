<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Hanya admin yang dapat mengunggah bukti transfer.'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

$id_barang      = mysqli_real_escape_string($koneksi, $_POST['id_barang'] ?? '');
$kode_transaksi = mysqli_real_escape_string($koneksi, trim($_POST['kode_transaksi'] ?? ''));

// Ambil info supplier & tanggal dari id_barang jika ada
$id_supplier = 0;
$tanggal_pembelian = '';
if (!empty($id_barang)) {
    $get_kode = mysqli_query($koneksi, "SELECT kode_transaksi, id_supplier, tanggal_pembelian FROM transaksi_pembelian WHERE id_pembelian = '$id_barang'");
    if ($get_kode && mysqli_num_rows($get_kode) > 0) {
        $r_kode = mysqli_fetch_assoc($get_kode);
        $id_supplier = (int) ($r_kode['id_supplier'] ?? 0);
        $tanggal_pembelian = $r_kode['tanggal_pembelian'] ?? '';
        if (empty($kode_transaksi)) {
            $kode_transaksi = trim($r_kode['kode_transaksi'] ?? '');
        }
    }
}

// Jika kode_transaksi masih kosong, buatkan baru berdasarkan tanggal & id_barang
if (empty($kode_transaksi)) {
    $tgl_prefix = !empty($tanggal_pembelian) ? date('Ymd', strtotime($tanggal_pembelian)) : date('Ymd');
    $kode_transaksi = 'TRX' . $tgl_prefix . $id_barang;
}

// Sinkronkan kode_transaksi ke SEMUA item transaksi_pembelian & pembayaran_pembelian untuk supplier & tanggal tersebut
if (!empty($id_supplier) && !empty($tanggal_pembelian)) {
    $tgl_esc = mysqli_real_escape_string($koneksi, $tanggal_pembelian);
    mysqli_query($koneksi, "UPDATE transaksi_pembelian SET kode_transaksi = '$kode_transaksi' WHERE id_supplier = '$id_supplier' AND tanggal_pembelian = '$tgl_esc' AND (kode_transaksi IS NULL OR kode_transaksi = '' OR id_pembelian = '$id_barang')");
    mysqli_query($koneksi, "UPDATE pembayaran_pembelian SET kode_transaksi = '$kode_transaksi' WHERE id_supplier = '$id_supplier' AND tanggal_transaksi = '$tgl_esc' AND (kode_transaksi IS NULL OR kode_transaksi = '')");
} else if (!empty($id_barang)) {
    mysqli_query($koneksi, "UPDATE transaksi_pembelian SET kode_transaksi = '$kode_transaksi' WHERE id_pembelian = '$id_barang'");
}

if (empty($kode_transaksi)) {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Kode transaksi atau ID barang tidak valid.'];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0 && $_FILES['bukti_transfer']['size'] > 0) {
    $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size = 2 * 1024 * 1024;
    $ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));

    if ($_FILES['bukti_transfer']['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran file maksimal 2 MB.'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Format file harus JPG, JPEG, PNG, atau PDF.'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }

    $targetDir = '../uploads/bukti_transfer/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $bp_name = uniqid('bayar_') . '.' . $ext;
    if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $targetDir . $bp_name)) {
        compressImage($targetDir . $bp_name);

        @mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS riwayat_pembayaran_pembelian (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode_transaksi VARCHAR(50) NOT NULL,
            jumlah_bayar INT DEFAULT 0,
            tanggal_bayar DATE NOT NULL,
            bukti_pembayaran VARCHAR(255) DEFAULT NULL,
            keterangan TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $bp_esc = mysqli_real_escape_string($koneksi, $bp_name);
        $tgl_esc = !empty($tanggal_pembelian) ? mysqli_real_escape_string($koneksi, $tanggal_pembelian) : date('Y-m-d');
        $ket_esc = mysqli_real_escape_string($koneksi, 'Bukti Transfer');

        mysqli_query($koneksi, "
            INSERT INTO riwayat_pembayaran_pembelian(
                kode_transaksi, jumlah_bayar, tanggal_bayar, bukti_pembayaran, keterangan
            ) VALUES(
                '$kode_transaksi', 0, '$tgl_esc', '$bp_esc', '$ket_esc'
            )
        ") or die('INSERT riwayat_pembayaran Error: ' . mysqli_error($koneksi));

        $_SESSION['alert'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Bukti transfer berhasil diunggah.'];
    } else {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menyimpan file bukti transfer.'];
    }
} else {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'File bukti transfer belum dipilih.'];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;
