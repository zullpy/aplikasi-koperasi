<?php
// ==========================================
// DATABASE 1: db_draft_barang (TIDAK DIUBAH)
// ==========================================
$host = "localhost";
$username = "root";
$password = "";
$dbname = "db_draft_barang";

$koneksi = new mysqli($host, $username, $password, $dbname);

if ($koneksi->connect_error) {
    die("Koneksi db_draft_barang gagal: " . $koneksi->connect_error);
}


// ==========================================
// DATABASE 2: db_mbg (TAMBAHAN BARU)
// ==========================================
$host2 = "localhost"; // Sama, karena di server lokal yang sama
$username2 = "root";
$password2 = "";
$dbname2 = "db_mbg";

$koneksi2 = new mysqli($host2, $username2, $password2, $dbname2);

if ($koneksi2->connect_error) {
    die("Koneksi db_mbg gagal: " . $koneksi2->connect_error);
}
