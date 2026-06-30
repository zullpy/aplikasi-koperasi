<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$kode_transaksi = mysqli_real_escape_string($koneksi, $_POST['kode_transaksi'] ?? '');
$jumlah_bayar_raw = preg_replace('/[^0-9]/', '', $_POST['jumlah_bayar'] ?? '');
$jumlah_bayar = ($jumlah_bayar_raw === '') ? 0 : (int) $jumlah_bayar_raw;
$tanggal_bayar = !empty($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : date('Y-m-d');
$tanggal_bayar_esc = mysqli_real_escape_string($koneksi, $tanggal_bayar);
$keterangan_bayar = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? 'Pelunasan cicilan');

if ($kode_transaksi === '' || $jumlah_bayar <= 0) {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Kode transaksi atau jumlah bayar tidak valid.'];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

$cek = mysqli_query($koneksi, "SELECT * FROM pembayaran_pembelian WHERE kode_transaksi='$kode_transaksi'");
$pembayaran = mysqli_fetch_assoc($cek);

if (!$pembayaran) {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tagihan untuk transaksi ini tidak ditemukan.'];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

$total_tagihan   = (int) $pembayaran['total_tagihan'];
$sudah_dibayar   = (int) $pembayaran['jumlah_dibayar'];
$sisa_sebelumnya = $total_tagihan - $sudah_dibayar;

if ($sisa_sebelumnya <= 0) {
    $_SESSION['alert'] = ['icon' => 'info', 'title' => 'Info', 'text' => 'Transaksi ini sudah lunas.'];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

// Jangan sampai bayar melebihi sisa tagihan
if ($jumlah_bayar > $sisa_sebelumnya) {
    $jumlah_bayar = $sisa_sebelumnya;
}

/*
|--------------------------------------------------------------------------
| Upload Bukti Pembayaran
|--------------------------------------------------------------------------
*/
$bukti_pembayaran = null;
if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0 && $_FILES['bukti_pembayaran']['size'] > 0) {
    $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size = 2 * 1024 * 1024;
    $ext = strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));

    if ($_FILES['bukti_pembayaran']['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran bukti pembayaran maksimal 2 MB.'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Format bukti pembayaran harus JPG, JPEG, PNG, atau PDF.'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }

    $bp_name = uniqid('bayar_') . '.' . $ext;
    if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], '../uploads/bukti_pembayaran/' . $bp_name)) {
        $bukti_pembayaran = $bp_name;
    }
}

$bukti_sql = $bukti_pembayaran !== null
    ? "'" . mysqli_real_escape_string($koneksi, $bukti_pembayaran) . "'"
    : "NULL";

/*
|--------------------------------------------------------------------------
| Simpan riwayat pembayaran & update total
|--------------------------------------------------------------------------
*/
mysqli_query($koneksi, "
    INSERT INTO riwayat_pembayaran_pembelian(
        kode_transaksi, jumlah_bayar, tanggal_bayar, bukti_pembayaran, keterangan
    ) VALUES(
        '$kode_transaksi', '$jumlah_bayar', '$tanggal_bayar_esc', $bukti_sql, '$keterangan_bayar'
    )
") or die('INSERT riwayat_pembayaran Error: ' . mysqli_error($koneksi));

$jumlah_dibayar_baru = $sudah_dibayar + $jumlah_bayar;
$status_baru = ($jumlah_dibayar_baru >= $total_tagihan) ? 'lunas' : 'sebagian';

mysqli_query($koneksi, "
    UPDATE pembayaran_pembelian
    SET jumlah_dibayar='$jumlah_dibayar_baru', status_pembayaran='$status_baru'
    WHERE kode_transaksi='$kode_transaksi'
") or die('UPDATE pembayaran Error: ' . mysqli_error($koneksi));

$sisa_baru = $total_tagihan - $jumlah_dibayar_baru;
$alert_text = "Pembayaran Rp " . number_format($jumlah_bayar, 0, ',', '.') . " berhasil dicatat.";
$alert_text .= ($status_baru === 'lunas')
    ? " Status sekarang: LUNAS 🎉"
    : " Sisa tagihan: Rp " . number_format($sisa_baru, 0, ',', '.');

$_SESSION['alert'] = [
    'icon'  => 'success',
    'title' => 'Berhasil',
    'text'  => $alert_text
];

header("Location: ../transaksi-pembelian-food/index.php");
exit;
