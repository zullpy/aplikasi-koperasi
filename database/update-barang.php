<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

if (isset($_POST['id_barang'])) {

    $id_barang = mysqli_real_escape_string($koneksi, $_POST['id_barang']);

    $id_supplier = mysqli_real_escape_string(
        $koneksi,
        $_POST['id_supplier']
    );

    $nama_barang = mysqli_real_escape_string(
        $koneksi,
        $_POST['nama_barang']
    );

    $tanggal = mysqli_real_escape_string(
        $koneksi,
        $_POST['tanggal']
    );

    $harga_beli = preg_replace(
        '/[^0-9]/',
        '',
        $_POST['harga_beli']
    );

    $volume = mysqli_real_escape_string(
        $koneksi,
        $_POST['stok_akhir']
    );

    $satuan = mysqli_real_escape_string(
        $koneksi,
        $_POST['satuan']
    );

    $keterangan = mysqli_real_escape_string(
        $koneksi,
        $_POST['keterangan']
    );

    $nota = null;
    $has_new_nota = false;

    /*
    |--------------------------------------------------------------------------
    | Upload dari Kamera
    |--------------------------------------------------------------------------
    */
    if (
        isset($_FILES['nota_kamera']) &&
        $_FILES['nota_kamera']['error'] == 0
    ) {

        $ext = strtolower(
            pathinfo(
                $_FILES['nota_kamera']['name'],
                PATHINFO_EXTENSION
            )
        );

        $nota = uniqid() . '.' . $ext;

        move_uploaded_file(
            $_FILES['nota_kamera']['tmp_name'],
            '../uploads/nota/' . $nota
        );

        $has_new_nota = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Upload dari File
    |--------------------------------------------------------------------------
    */
    elseif (
        isset($_FILES['nota_file']) &&
        $_FILES['nota_file']['error'] == 0
    ) {

        $ext = strtolower(
            pathinfo(
                $_FILES['nota_file']['name'],
                PATHINFO_EXTENSION
            )
        );

        $nota = uniqid() . '.' . $ext;

        move_uploaded_file(
            $_FILES['nota_file']['tmp_name'],
            '../uploads/nota/' . $nota
        );

        $has_new_nota = true;
    }

    if ($has_new_nota) {

        // Ambil nota lama
        $get_old_nota = mysqli_query(
            $koneksi,
            "SELECT nota
             FROM transaksi_pembelian
             WHERE id_pembelian = '$id_barang'"
        );

        if (
            $get_old_nota &&
            mysqli_num_rows($get_old_nota) > 0
        ) {

            $row = mysqli_fetch_assoc($get_old_nota);

            if (!empty($row['nota'])) {

                $file_path =
                    "../uploads/nota/" .
                    $row['nota'];

                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }

        $query = "
        UPDATE transaksi_pembelian SET
            id_supplier = '$id_supplier',
            nama_barang = '$nama_barang',
            keterangan = '$keterangan',
            harga = '$harga_beli',
            volume = '$volume',
            satuan = '$satuan',
            tanggal_pembelian = '$tanggal',
            nota = '$nota'
        WHERE id_pembelian = '$id_barang'
        ";

    } else {

        $query = "
        UPDATE transaksi_pembelian SET
            id_supplier = '$id_supplier',
            nama_barang = '$nama_barang',
            keterangan = '$keterangan',
            harga = '$harga_beli',
            volume = '$volume',
            satuan = '$satuan',
            tanggal_pembelian = '$tanggal'
        WHERE id_pembelian = '$id_barang'
        ";
    }

    if (mysqli_query($koneksi, $query)) {

        $_SESSION['alert'] = [
            'icon' => 'success',
            'title' => 'Berhasil',
            'text' => 'Data berhasil diubah'
        ];

    } else {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Gagal',
            'text' => mysqli_error($koneksi)
        ];
    }

} else {

    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Gagal',
        'text' => 'ID Pembelian tidak ditemukan'
    ];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;