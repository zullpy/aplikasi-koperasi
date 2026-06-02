<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "db_draft_barang";

$koneksi = new mysqli($host, $username, $password, $dbname);

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
?>