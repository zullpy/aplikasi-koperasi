<?php

include 'koneksi.php';

$q = mysqli_real_escape_string($koneksi, $_GET['q']);

$query = mysqli_query($koneksi,"
    SELECT *
    FROM barang
    WHERE nama_barang LIKE '%$q%'
    LIMIT 5
");

while($row = mysqli_fetch_assoc($query)){
    echo "
    <div class='suggestion-item'
         onclick=\"pilihBarang('".$row['nama_barang']."')\">
         ".$row['nama_barang']."
    </div>";
}
