<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Hanya admin yang dapat mengubah transaksi pembelian.'
    ];
    header("Location: ../transaksi-pembelian-food/index.php");
    exit;
}

if (isset($_POST['id_pembelian'])) {
    $id_pembelian = mysqli_real_escape_string($koneksi, $_POST['id_pembelian']);
    $id_supplier  = mysqli_real_escape_string($koneksi, $_POST['id_supplier']);

    $nama_barang_input = is_array($_POST['nama_barang']) ? $_POST['nama_barang'][0] : $_POST['nama_barang'];
    $nama_barang       = mysqli_real_escape_string($koneksi, trim($nama_barang_input));
    $tanggal           = mysqli_real_escape_string($koneksi, is_array($_POST['tanggal_pembelian']) ? $_POST['tanggal_pembelian'][0] : $_POST['tanggal_pembelian']);

    $harga_raw = is_array($_POST['harga']) ? $_POST['harga'][0] : $_POST['harga'];
    $harga     = (int) preg_replace('/[^0-9]/', '', $harga_raw);

    // ✅ Ambil keuntungan dan hitung harga jual
    $keuntungan_raw = is_array($_POST['keuntungan']) ? $_POST['keuntungan'][0] : ($_POST['keuntungan'] ?? '0');
    $keuntungan     = (int) preg_replace('/[^0-9]/', '', $keuntungan_raw);
    $harga_jual     = $harga + $keuntungan;

    $volume_raw = is_array($_POST['volume']) ? $_POST['volume'][0] : $_POST['volume'];
    $volume_num = (float) preg_replace('/[^0-9.]/', '', $volume_raw);
    $volume     = mysqli_real_escape_string($koneksi, (string)$volume_num);

    $satuan     = mysqli_real_escape_string($koneksi, is_array($_POST['satuan']) ? $_POST['satuan'][0] : $_POST['satuan']);
    $keterangan = mysqli_real_escape_string($koneksi, is_array($_POST['keterangan']) ? $_POST['keterangan'][0] : $_POST['keterangan']);

    // 1. Ambil data transaksi lama (volume & nama_barang lama)
    $qLama = mysqli_query($koneksi, "SELECT volume, nama_barang FROM transaksi_pembelian WHERE id_pembelian = '$id_pembelian'");
    if (!$qLama || mysqli_num_rows($qLama) === 0) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data transaksi lama tidak ditemukan'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }

    $dataLama         = mysqli_fetch_assoc($qLama);
    $volume_lama_num  = (float) preg_replace('/[^0-9.]/', '', $dataLama['volume'] ?? '0');
    $nama_barang_lama = trim($dataLama['nama_barang'] ?? '');
    $nama_barang_lama_esc = mysqli_real_escape_string($koneksi, $nama_barang_lama);

    // 2. Query data barang lama dari master (pakai LOWER & TRIM)
    $qBarangLama = mysqli_query($koneksi, "SELECT id_barang, nama_barang, stok_akhir FROM barang WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang_lama_esc')) LIMIT 1");
    $barangLama  = $qBarangLama ? mysqli_fetch_assoc($qBarangLama) : null;

    // Query data barang baru (jika nama barang diubah saat edit)
    $qBarangBaru = mysqli_query($koneksi, "SELECT id_barang, nama_barang, stok_akhir FROM barang WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang')) LIMIT 1");
    $barangBaru  = $qBarangBaru ? mysqli_fetch_assoc($qBarangBaru) : null;

    // 3. Update transaksi_pembelian
    $queryUpdateTrx = "UPDATE transaksi_pembelian SET
        id_supplier       = '$id_supplier',
        nama_barang       = '$nama_barang',
        keterangan        = '$keterangan',
        harga             = '$harga',
        volume            = '$volume',
        satuan            = '$satuan',
        tanggal_pembelian = '$tanggal'
        WHERE id_pembelian = '$id_pembelian'";

    if (mysqli_query($koneksi, $queryUpdateTrx)) {

        $namaChanged = (strtolower($nama_barang_lama) !== strtolower(trim($nama_barang_input))) && !empty($nama_barang_lama);

        if ($namaChanged) {
            // Jika nama barang diubah: kurangi stok barang lama sebesar volume_lama
            if ($barangLama) {
                $stok_lama_old = (float) ($barangLama['stok_akhir'] ?? 0);
                $stok_baru_old = max(0, $stok_lama_old - $volume_lama_num);
                $id_barang_old = $barangLama['id_barang'];
                mysqli_query($koneksi, "UPDATE barang SET stok_akhir = '$stok_baru_old' WHERE id_barang = '$id_barang_old'");
            }

            // Tambahkan stok ke barang baru
            if ($barangBaru) {
                $id_barang_target = $barangBaru['id_barang'];
                $stok_lama_target = (float) ($barangBaru['stok_akhir'] ?? 0);
                $stok_baru_target = $stok_lama_target + $volume_num;

                mysqli_query($koneksi, "
                    UPDATE barang
                    SET stok_akhir            = '$stok_baru_target',
                        harga_beli            = '$harga',
                        harga_jual            = '$harga_jual',
                        tanggal_terupdate_baru = '$tanggal'
                    WHERE id_barang = '$id_barang_target'
                ");
            } else {
                // Auto-create barang baru di master jika belum ada
                $satuan_default = !empty($satuan) ? $satuan : 'Pcs';
                mysqli_query($koneksi, "
                    INSERT INTO barang (
                        nama_barang, stok_akhir, harga_beli, harga_jual, satuan, tanggal_terupdate_baru
                    ) VALUES (
                        '$nama_barang', '$volume_num', '$harga', '$harga_jual', '$satuan_default', '$tanggal'
                    )
                ");
                $id_barang_target = mysqli_insert_id($koneksi);
                $stok_lama_target = 0;
                $stok_baru_target = $volume_num;
            }

            $selisih = $volume_num;
            $id_barang = $id_barang_target;
            $stok_lama = $stok_lama_target;
            $stok_baru = $stok_baru_target;
        } else {
            // Nama barang tidak berubah
            $selisih = $volume_num - $volume_lama_num;

            if ($barangLama || $barangBaru) {
                $targetBarang = $barangBaru ?: $barangLama;
                $id_barang    = $targetBarang['id_barang'];
                $stok_lama    = (float) ($targetBarang['stok_akhir'] ?? 0);
                $stok_baru    = max(0, $stok_lama + $selisih);

                mysqli_query($koneksi, "
                    UPDATE barang
                    SET stok_akhir            = '$stok_baru',
                        harga_beli            = '$harga',
                        harga_jual            = '$harga_jual',
                        tanggal_terupdate_baru = '$tanggal'
                    WHERE id_barang = '$id_barang'
                ");
            } else {
                // Barang belum terdaftar di tabel master barang → buatkan otomatis
                $satuan_default = !empty($satuan) ? $satuan : 'Pcs';
                mysqli_query($koneksi, "
                    INSERT INTO barang (
                        nama_barang, stok_akhir, harga_beli, harga_jual, satuan, tanggal_terupdate_baru
                    ) VALUES (
                        '$nama_barang', '$volume_num', '$harga', '$harga_jual', '$satuan_default', '$tanggal'
                    )
                ");
                $id_barang = mysqli_insert_id($koneksi);
                $stok_lama = 0;
                $stok_baru = $volume_num;
            }
        }

        if ($selisih != 0 && !empty($id_barang)) {
            mysqli_query($koneksi, "
                INSERT INTO mutasi_stok(
                    id_pembelian, id_barang, tanggal, jenis, qty,
                    stok_sebelum, stok_sesudah, keterangan
                ) VALUES(
                    '$id_pembelian', '$id_barang', NOW(), 'perubahan', '$selisih',
                    '$stok_lama', '$stok_baru', 'Edit transaksi pembelian'
                )
            ");
        }

        // ✅ SYNC KE TABEL estimasi_harga (Dompet Belanja Harian)
        if ($harga > 0 && !empty($nama_barang)) {
            $nama_barang_esc = mysqli_real_escape_string($koneksi, $nama_barang);
            $satuan_esc      = mysqli_real_escape_string($koneksi, $satuan);
            $chkEst = mysqli_query($koneksi, "SELECT id FROM estimasi_harga WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang_esc')) LIMIT 1");
            if ($chkEst && mysqli_num_rows($chkEst) > 0) {
                $rowEst = mysqli_fetch_assoc($chkEst);
                $idEst  = (int)$rowEst['id'];
                mysqli_query($koneksi, "UPDATE estimasi_harga SET harga_beli = '$harga', satuan = '$satuan_esc', tanggal_terupdate = CURDATE() WHERE id = '$idEst'");
            } else {
                mysqli_query($koneksi, "INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES ('$nama_barang_esc', '$harga', '$satuan_esc', CURDATE())");
            }
        }

        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Berhasil',
            'text'  => 'Data transaksi berhasil diubah. Stok, harga beli, harga jual & tanggal otomatis terupdate.'
        ];
    } else {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => mysqli_error($koneksi)];
    }
} else {
    $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID Pembelian tidak ditemukan'];
}

header("Location: ../transaksi-pembelian-food/index.php");
exit;

