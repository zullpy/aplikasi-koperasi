<?php
include __DIR__ . '/koneksi.php';

$output = [];

$output[] = "=== SHOW COLUMNS FROM transaksi_pembelian ===";
$r = mysqli_query($koneksi, "SHOW COLUMNS FROM transaksi_pembelian");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = $row['Field'] . " (" . $row['Type'] . ")";
    }
}

$output[] = "\n=== SHOW COLUMNS FROM pembayaran_pembelian ===";
$r = mysqli_query($koneksi, "SHOW COLUMNS FROM pembayaran_pembelian");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = $row['Field'] . " (" . $row['Type'] . ")";
    }
}

$output[] = "\n=== SHOW COLUMNS FROM riwayat_pembayaran_pembelian ===";
$r = mysqli_query($koneksi, "SHOW COLUMNS FROM riwayat_pembayaran_pembelian");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = $row['Field'] . " (" . $row['Type'] . ")";
    }
}

$output[] = "\n=== TRANSAKSI PEMBELIAN (LAST 10) ===";
$r = mysqli_query($koneksi, "SELECT id_pembelian, kode_transaksi, id_supplier, nama_barang, tanggal_pembelian, nota, metode_pembayaran FROM transaksi_pembelian ORDER BY id_pembelian DESC LIMIT 10");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = json_encode($row);
    }
}

$output[] = "\n=== PEMBAYARAN PEMBELIAN (LAST 10) ===";
$r = mysqli_query($koneksi, "SELECT * FROM pembayaran_pembelian ORDER BY id_pembayaran DESC LIMIT 10");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = json_encode($row);
    }
}

$output[] = "\n=== RIWAYAT PEMBAYARAN PEMBELIAN (ALL) ===";
$r = mysqli_query($koneksi, "SELECT * FROM riwayat_pembayaran_pembelian ORDER BY id_riwayat DESC");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $output[] = json_encode($row);
    }
}

file_put_contents(__DIR__ . '/debug_result.txt', implode("\n", $output));
echo "DONE";
