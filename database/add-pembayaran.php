<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Deteksi apakah request dari AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond($success, $icon, $title, $text, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'icon' => $icon, 'title' => $title, 'text' => $text]);
        exit;
    } else {
        $_SESSION['alert'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
        header("Location: ../transaksi-penjualan-food/index.php");
        exit;
    }
}

$id_transaksi = intval($_POST['id_transaksi']);
$jumlah_bayar = preg_replace('/[^0-9]/', '', $_POST['jumlah_bayar']);
$keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

// Upload bukti
$bukti_filename = null;
if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        respond(false, 'error', 'Gagal', 'Format file tidak didukung', $isAjax);
    }
    $upload_dir = __DIR__ . '/../uploads/bukti-bayar/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $bukti_filename = 'bukti_' . $id_transaksi . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $upload_dir . $bukti_filename);
}

// Ambil data transaksi
$q = mysqli_query($koneksi, "SELECT total, total_bayar FROM transaksi_penjualan WHERE id_transaksi = $id_transaksi");
$trx = mysqli_fetch_assoc($q);
if (!$trx) {
    respond(false, 'error', 'Gagal', 'Transaksi tidak ditemukan', $isAjax);
}

$total = $trx['total'];
$sudah_bayar_lama = $trx['total_bayar'];
$total_bayar_baru = $sudah_bayar_lama + $jumlah_bayar;
$sisa_bayar_baru = $total - $total_bayar_baru;

// Validasi tidak overpay
if ($total_bayar_baru > $total) {
    respond(false, 'warning', 'Perhatian', 'Jumlah bayar melebihi total transaksi', $isAjax);
}

// Tentukan status
$status = 'sebagian';
if ($sisa_bayar_baru <= 0) {
    $status = 'lunas';
    $sisa_bayar_baru = 0;
    $total_bayar_baru = $total;
}

// Insert pembayaran
$bukti_sql = $bukti_filename ? "'$bukti_filename'" : "NULL";
mysqli_query($koneksi, "
    INSERT INTO pembayaran (id_transaksi, jumlah_bayar, bukti_bayar, keterangan)
    VALUES ($id_transaksi, $jumlah_bayar, $bukti_sql, '$keterangan')
");

// Update transaksi
mysqli_query($koneksi, "
    UPDATE transaksi_penjualan
    SET total_bayar = $total_bayar_baru,
        sisa_bayar = $sisa_bayar_baru,
        status_pembayaran = '$status'
    WHERE id_transaksi = $id_transaksi
");

if ($status === 'lunas') {
    respond(true, 'success', 'LUNAS! 🎉', 'Pembayaran telah lunas. Sisa Rp 0', $isAjax);
} else {
    respond(true, 'success', 'Berhasil', 'Pembayaran Rp ' . number_format($jumlah_bayar, 0, ',', '.') . ' tercatat. Sisa: Rp ' . number_format($sisa_bayar_baru, 0, ',', '.'), $isAjax);
}
