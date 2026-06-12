<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

if (isset($_POST['id_barang'])) {

    $id_barang = mysqli_real_escape_string(
        $koneksi,
        $_POST['id_barang']
    );

    $nota = null;
    $has_new_nota = false;

    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

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

        if (!in_array($ext, $allowed)) {

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Gagal',
                'text' => 'Ekstensi file harus JPG, JPEG, PNG, atau PDF'
            ];

            header("Location: ../transaksi-pembelian-food/index.php");
            exit;
        }

        $nota = uniqid() . '.' . $ext;

        move_uploaded_file(
            $_FILES['nota_kamera']['tmp_name'],
            '../uploads/nota/' . $nota
        );

        $has_new_nota = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Upload dari File / Galeri
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

        if (!in_array($ext, $allowed)) {

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Gagal',
                'text' => 'Ekstensi file harus JPG, JPEG, PNG, atau PDF'
            ];

            header("Location: ../transaksi-pembelian-food/index.php");
            exit;
        }

        $nota = uniqid() . '.' . $ext;

        move_uploaded_file(
            $_FILES['nota_file']['tmp_name'],
            '../uploads/nota/' . $nota
        );

        $has_new_nota = true;
    }

    if ($has_new_nota) {

        /*
        |--------------------------------------------------------------------------
        | Ambil Nota Lama
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Update Nota
        |--------------------------------------------------------------------------
        */
        $query = "
            UPDATE transaksi_pembelian
            SET nota = '$nota'
            WHERE id_pembelian = '$id_barang'
        ";

        if (mysqli_query($koneksi, $query)) {

            $_SESSION['alert'] = [
                'icon' => 'success',
                'title' => 'Berhasil',
                'text' => 'Nota berhasil diunggah'
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
            'text' => 'Silakan pilih atau ambil foto nota terlebih dahulu!'
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