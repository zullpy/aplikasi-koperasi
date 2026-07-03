<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../database/koneksi.php';
require_once '../database/auth.php';

$id = $_GET['id'] ?? 0;

$query = mysqli_query($koneksi,"
SELECT
    tp.*,
    p.nama_pelanggan,
    p.no_telepon,
    p.alamat
FROM transaksi_penjualan tp
JOIN pelanggan p
ON tp.id_pelanggan = p.id_pelanggan
WHERE tp.id_transaksi = '$id'
");

$trx = mysqli_fetch_assoc($query);

if(!$trx){
    die("Data transaksi tidak ditemukan");
}

$detail = mysqli_query($koneksi,"
SELECT
    dp.*,
    b.nama_barang
FROM detail_penjualan dp
JOIN barang b
ON dp.id_barang = b.id_barang
WHERE dp.id_transaksi = '$id'
");

$items = [];

while($row = mysqli_fetch_assoc($detail)){
    $items[] = $row;
}

// Minimal 8 baris agar tampilan rapi
$min_rows = 8;
$item_count = count($items);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Faktur <?= $trx['no_faktur'] ?></title>
<link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
<style>

/* ===== PAGE SETUP A5 ===== */
@page {
    size: A5 portrait;
    margin: 6mm 7mm 6mm 7mm;
}

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
}

/* ===== HEADER KOP SURAT ===== */
.header-table {
    width: 100%;
    border-collapse: separate;
    border:none;
    border-bottom: 1.5px double #000;
    margin-bottom: 5px;
}

.header-table td {
    padding: 4px 5px;
    vertical-align: middle;
}

.col-logo {
    width: 75px;
    text-align: center;
    vertical-align: middle;
}

.col-logo img {
    width: 65px;
    height: auto;
}

.col-kop {
    text-align: center;
    vertical-align: middle;
    padding: 4px 0;
}

.col-kop .label-koperasi {
    font-size: 9pt;
    font-weight: bold;
    color: #6b3fa0;
    margin: 0;
    line-height: 1.3;
    letter-spacing: 1px;
}

.col-kop .nama-koperasi {
    font-size: 15pt;
    font-weight: bold;
    color: #6b3fa0;
    margin: 0;
    line-height: 1.2;
}

.col-kop .tagline {
    color: #b8860b;
    font-style: italic;
    font-weight: bold;
    font-size: 8pt;
    margin: 2px 0 1px;
}

.col-kop .alamat {
    font-size: 8pt;
    line-height: 1.5;
}

.col-logo-kanan {
    width: 75px;
    text-align: center;
    vertical-align: middle;
}

.col-logo-kanan img {
    width: 55px;
    height: auto;
}

/* ===== INFO KONSUMEN ===== */
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 2px;
}

.info-table td {
    border: none;
    padding: 4px 6px;
    font-size: 8.5pt;
    line-height: 1.4;
}

.info-table .label {
    width: 110px;
    white-space: nowrap;
}

.info-table .value {
    min-width: 100px;
}

.info-table .label-right {
    width: 90px;
    white-space: nowrap;
}

.info-table .value-right {
    width: 130px;
}

/* ===== JUDUL FAKTUR ===== */
.judul {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    margin: 6px 0 4px;
    letter-spacing: 1px;
}

/* ===== TABEL BARANG ===== */
.barang {
    width: 100%;
    border-collapse: collapse;
}

.barang th {
    border: 1.5px solid #000;
    padding: 4px 5px;
    text-align: center;
    font-size: 8.5pt;
    background: #fff;
    font-weight: bold;
}

.barang td {
    border: 1px solid #000;
    padding: 3px 5px;
    font-size: 8.5pt;
    height: 18px;
}

.col-qty    { width: 40px; }
.col-satuan { width: 55px; }
.col-harga  { width: 80px; }
.col-sub    { width: 85px; }

.barang td.center { text-align: center; }
.barang td.right  { text-align: right; }

/* Row total */
.row-total td {
    border: 1px solid #000;
    padding: 4px 5px;
    font-size: 8.5pt;
    font-weight: bold;
}

/* ===== CATATAN ===== */
.catatan-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
}

.catatan-table td {
    font-size: 8pt;
    padding: 1px 2px;
    vertical-align: top;
}

.catatan-label {
    width: 55px;
    font-style: italic;
    text-decoration: underline;
    white-space: nowrap;
}

.catatan-isi {
    font-style: italic;
    line-height: 1.6;
}

/* ===== TTD ===== */
.ttd-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.ttd-table td {
    font-size: 8.5pt;
    vertical-align: top;
    padding: 0 4px;
}

.ttd-kiri {
    text-align: left;
    width: 50%;
}

.ttd-kanan {
    text-align: right;
    width: 50%;
}

.ttd-gap {
    height: 55px;
    display: block;
}

.ttd-line {
    display: block;
    margin-top: 2px;
}

.cap-img {
    width: 65px;
    height: auto;
    opacity: 0.88;
    display: inline-block;
    margin: 2px 0;
}

/* ===== PRINT ===== */
@media print {
    body { margin: 0; }
}

</style>
</head>
<body>

<!-- ===== KOP SURAT ===== -->
<table class="header-table">
    <tr>
        <td class="col-logo">
            <img src="../assets/logo.png" alt="Logo KBUS">
        </td>

        <td class="col-kop">
            <div class="label-koperasi">KOPERASI</div>
            <div class="nama-koperasi">BINA USAHA SAUYUNAN</div>
            <div class="tagline">"Bersama Membangun Usaha, Bersatu Meraih Sejahtera"</div>
            <div class="alamat">Kp. Panyingkiran - Singaparna - Kab. Tasikmalaya</div>
            <div class="alamat">email : kop.binausahasauyunan@gmail.com</div>
        </td>

        <td class="col-logo-kanan">
            <img src="../assets/logo-kbus.png" alt="Logo KBUS Kanan">
        </td>
    </tr>
</table>

<!-- ===== INFO KONSUMEN ===== -->
<table class="info-table">
    <tr>
        <td class="label">Nama Konsumen</td>
        <td class="value">: <?= htmlspecialchars($trx['nama_pelanggan']) ?></td>
        <td class="label-right">Tanggal</td>
        <td class="value-right">: <?= date('d-m-Y', strtotime($trx['tanggal'])) ?></td>
    </tr>
    <tr>
        <td class="label">No Kontak</td>
        <td class="value">: <?= htmlspecialchars($trx['no_telepon']) ?></td>
        <td class="label-right">No Faktur</td>
        <td class="value-right">: <?= htmlspecialchars($trx['no_faktur']) ?></td>
    </tr>
    <tr>
        <td class="label">Alamat</td>
        <td colspan="3">: <?= htmlspecialchars($trx['alamat']) ?></td>
    </tr>
</table>

<!-- ===== JUDUL ===== -->
<div class="judul">FAKTUR PENJUALAN</div>

<!-- ===== TABEL BARANG ===== -->
<table class="barang">
    <thead>
        <tr>
            <th class="col-qty">QTY</th>
            <th class="col-satuan">SATUAN</th>
            <th class="col-nama">NAMA BARANG</th>
            <th class="col-harga">HARGA</th>
            <th class="col-sub">SUB TOTAL</th>
        </tr>
    </thead>
    <tbody>

        <?php foreach($items as $item): ?>
        <tr>
            <td class="center"><?= $item['qty'] ?></td>
            <td class="center"><?= htmlspecialchars($item['satuan']) ?></td>
            <td><?= htmlspecialchars($item['nama_barang']) ?></td>
            <td class="right"><?= number_format($item['harga_jual'], 0, ',', '.') ?></td>
            <td class="right"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>

        

        <!-- Baris Total -->
        <tr class="row-total">
            <td colspan="4" class="right">TOTAL :</td>
            <td class="right"><?= number_format($trx['total'], 0, ',', '.') ?></td>
        </tr>

    </tbody>
</table>

<!-- ===== CATATAN ===== -->
<table class="catatan-table">
    <tr>
        <td class="catatan-label">Catatan :</td>
        <td class="catatan-isi">
            Terimakasih telah belanja di tempat kami<br>
            Mohon di cek dengan teliti barang yang sudah dibeli
        </td>
    </tr>
</table>

<!-- ===== TANDA TANGAN ===== -->
<table class="ttd-table">
    <tr>
        <td class="ttd-kiri">
            Penerima / Pembeli
            <span class="ttd-gap"></span>
            <span class="ttd-line">...................................</span>
        </td>

        <td class="ttd-kanan">
            Hormat Kami,
            <br>
            <img src="../assets/logo-kbus.png" class="cap-img" alt="Cap KBUS">
            <br>
            <span class="ttd-line">...................................</span>
        </td>
    </tr>
</table>

<script>
window.onload = function(){
    window.print();
};
</script>

</body>
</html>