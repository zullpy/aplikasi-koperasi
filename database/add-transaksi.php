<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

$nama_barang   = $_POST['nama_barang'];
$kategori      = $_POST['kategori'] ?? [];
$keterangan    = $_POST['keterangan'];
$harga         = $_POST['harga'];
$harga_eceran  = $_POST['harga_eceran'] ?? [];
$keuntungan    = $_POST['keuntungan'] ?? [];
$volume        = $_POST['volume'];
$satuan        = $_POST['satuan'];

// FIX: tanggal bisa array atau string
$tanggal_pembelian = is_array($_POST['tanggal_pembelian'])
    ? $_POST['tanggal_pembelian'][0]
    : $_POST['tanggal_pembelian'];

$id_supplier       = $_POST['id_supplier'];
$metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';

// ✅ FIX UTAMA: pastikan biaya_admin selalu integer, minimal 0
$biaya_admin_raw = $_POST['biaya_admin'] ?? '';
$biaya_admin     = preg_replace('/[^0-9]/', '', $biaya_admin_raw);
$biaya_admin     = ($biaya_admin === '' || $biaya_admin === null) ? '0' : $biaya_admin;
$biaya_admin     = (int) $biaya_admin;

$kode_transaksi = 'TRX' . date('YmdHis');

// Track barang baru yang dibuat otomatis (untuk notifikasi)
$new_barangs_created = [];
$total_tagihan = 0;

/*
|--------------------------------------------------------------------------
| Status Pembayaran (per supplier / per nota, bukan per item)
|--------------------------------------------------------------------------
*/
$status_pembayaran = $_POST['status_pembayaran'] ?? 'lunas'; // 'lunas' | 'sebagian'
if (!in_array($status_pembayaran, ['lunas', 'sebagian'])) {
    $status_pembayaran = 'lunas';
}
$jumlah_dibayar_raw = preg_replace('/[^0-9]/', '', $_POST['jumlah_dibayar'] ?? '');
$jumlah_dibayar_awal = ($jumlah_dibayar_raw === '') ? 0 : (int) $jumlah_dibayar_raw;

// Upload bukti pembayaran awal (opsional, dipakai saat status lunas/sebagian dengan bayar awal)
$bukti_pembayaran_awal = null;
if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0 && $_FILES['bukti_pembayaran']['size'] > 0) {
    $bp_ext = strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));
    $bp_allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (in_array($bp_ext, $bp_allowed) && $_FILES['bukti_pembayaran']['size'] <= (2 * 1024 * 1024)) {
        $bp_name = uniqid('bayar_') . '.' . $bp_ext;
        if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], '../uploads/bukti_pembayaran/' . $bp_name)) {
            $bukti_pembayaran_awal = $bp_name;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Upload Nota
|--------------------------------------------------------------------------
*/
$allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 2 * 1024 * 1024; // 2 MB

function reArrayFiles($file_post)
{
    $file_ary = [];
    if (!isset($file_post['name'])) return $file_ary;
    if (is_array($file_post['name'])) {
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);
        for ($i = 0; $i < $file_count; $i++) {
            if ($file_post['error'][$i] == 0 && $file_post['size'][$i] > 0) {
                $item = [];
                foreach ($file_keys as $key) {
                    $item[$key] = $file_post[$key][$i];
                }
                $file_ary[] = $item;
            }
        }
    } else {
        if ($file_post['error'] == 0 && $file_post['size'] > 0) {
            $file_ary[] = $file_post;
        }
    }
    return $file_ary;
}

$all_files = array_merge(
    reArrayFiles($_FILES['nota_kamera'] ?? []),
    reArrayFiles($_FILES['nota_file'] ?? [])
);

foreach ($all_files as $file) {
    if ($file['size'] > $max_size) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Ukuran nota maksimal 2 MB per file'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Format file harus JPG, JPEG, PNG, atau PDF'];
        header("Location: ../transaksi-pembelian-food/index.php");
        exit;
    }
}

$uploaded_files = [];
foreach ($all_files as $file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nota_name = uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], '../uploads/nota/' . $nota_name)) {
        $uploaded_files[] = $nota_name;
    }
}

$nota = null;
if (!empty($uploaded_files)) {
    $nota = implode(',', $uploaded_files);
}

/*
|--------------------------------------------------------------------------
| Pastikan array (multi-item)
|--------------------------------------------------------------------------
*/
if (!is_array($nama_barang)) {
    $nama_barang  = [$nama_barang];
    $kategori     = [$kategori];
    $harga        = [$harga];
    $harga_eceran = [$harga_eceran];
    $keuntungan   = [$keuntungan];
    $volume       = [$volume];
    $satuan       = [$satuan];
    $keterangan   = [$keterangan];
}

foreach ($nama_barang as $i => $barang_nama) {
    $harga_item          = preg_replace('/[^0-9]/', '', $harga[$i] ?? '0');
    $harga_eceran_item   = preg_replace('/[^0-9]/', '', $harga_eceran[$i] ?? '0');
    $keuntungan_item     = preg_replace('/[^0-9]/', '', $keuntungan[$i] ?? '0');
    $volume_item         = $volume[$i];
    $satuan_item         = $satuan[$i];
    $keterangan_item     = $keterangan[$i];
    $kategori_item       = trim($kategori[$i] ?? '');

    // ✅ Hitung harga jual per dus di server
    $jumlah_isi = 0;
    if (preg_match('/isi\s*(\d+)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
    } elseif (preg_match('/(\d+)\s*(?:pcs|bungkus|pack|botol|sachet|batang|lembar|butir|biji|kotak|dus)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
    }

    $harga_jual_item = $jumlah_isi > 0
        ? $harga_item + ($keuntungan_item * $jumlah_isi)
        : $harga_item + $keuntungan_item;

    $harga_jual_eceran_item = $harga_eceran_item + $keuntungan_item;

    // ✅ ESCAPE VARIABEL DI AWAL (dipindah ke atas supaya bisa dipakai di INSERT barang baru)
    $kode_transaksi_esc    = mysqli_real_escape_string($koneksi, $kode_transaksi);
    $id_supplier_esc       = (int) $id_supplier;
    $barang_nama_esc       = mysqli_real_escape_string($koneksi, $barang_nama);
    $kategori_esc          = mysqli_real_escape_string($koneksi, $kategori_item);
    $tanggal_esc           = mysqli_real_escape_string($koneksi, $tanggal_pembelian);
    $harga_esc             = (int) $harga_item;
    $harga_eceran_esc      = (int) $harga_eceran_item;
    $harga_jual_esc        = (int) $harga_jual_item;
    $harga_jual_eceran_esc = (int) $harga_jual_eceran_item;
    $volume_esc            = (int) $volume_item;
    $satuan_esc            = mysqli_real_escape_string($koneksi, $satuan_item);
    $keterangan_esc        = mysqli_real_escape_string($koneksi, $keterangan_item);
    $metode_esc            = mysqli_real_escape_string($koneksi, $metode_pembayaran);
    $biaya_admin_item      = ($i == 0) ? $biaya_admin : 0;
    $biaya_admin_esc       = (int) $biaya_admin_item;

    // ✅ CEK BARANG + AUTO-REGISTER JIKA BELUM ADA
    $cari = mysqli_query($koneksi, "SELECT * FROM barang WHERE nama_barang='$barang_nama_esc'");
    $barang = mysqli_fetch_assoc($cari);

    if (!$barang) {
        // 🆕 AUTO-CREATE: barang belum terdaftar → daftarkan otomatis ke tabel barang
        $satuan_default = !empty($satuan_item) ? $satuan_esc : 'Pcs';

        $insert_barang = mysqli_query($koneksi, "
            INSERT INTO barang (
                nama_barang, kategori, stok_akhir, harga_beli, harga_eceran,
                harga_jual, harga_jual_eceran, satuan, tanggal_terupdate_baru
            ) VALUES (
                '$barang_nama_esc',
                " . ($kategori_esc !== '' ? "'$kategori_esc'" : "NULL") . ",
                0,
                '$harga_esc',
                '$harga_eceran_esc',
                '$harga_jual_esc',
                '$harga_jual_eceran_esc',
                '$satuan_default',
                '$tanggal_esc'
            )
        ") or die('INSERT barang Error: ' . mysqli_error($koneksi));

        $id_barang = mysqli_insert_id($koneksi);
        $new_barangs_created[] = $barang_nama;

        // Stok lama = 0 karena barang baru
        $stok_lama = 0;
    } else {
        $id_barang = $barang['id_barang'];
        $stok_lama = (int) $barang['stok_akhir'];
    }

    $stok_baru = $stok_lama + $volume_esc;

    $nota_item = $nota;
    $nota_sql  = $nota_item !== null ? "'" . mysqli_real_escape_string($koneksi, $nota_item) . "'" : "NULL";

    // ✅ INSERT TRANSAKSI PEMBELIAN
    mysqli_query($koneksi, "
        INSERT INTO transaksi_pembelian(
            kode_transaksi, id_supplier, nama_barang, kategori, tanggal_pembelian,
            harga, harga_eceran, harga_jual_dus, harga_jual_eceran, volume, satuan, keterangan, nota,
            metode_pembayaran, biaya_admin
        ) VALUES(
            '$kode_transaksi_esc', '$id_supplier_esc', '$barang_nama_esc',
            " . ($kategori_esc !== '' ? "'$kategori_esc'" : "NULL") . ",
            '$tanggal_esc',
            '$harga_esc', '$harga_eceran_esc', '$harga_jual_esc', '$harga_jual_eceran_esc',
            '$volume_esc', '$satuan_esc', '$keterangan_esc',
            $nota_sql,
            '$metode_esc', $biaya_admin_esc
        )
    ") or die('INSERT Error: ' . mysqli_error($koneksi));

    $id_pembelian = mysqli_insert_id($koneksi);

    // ✅ RIWAYAT HARGA
    mysqli_query($koneksi, "
        INSERT INTO riwayat_harga(id_barang, harga_beli, tanggal)
        VALUES('$id_barang', '$harga_esc', '$tanggal_esc')
    ");

    // ✅ UPDATE TABEL BARANG
    $kategori_set_sql = ($kategori_esc !== '') ? "kategori='$kategori_esc'," : "";
    mysqli_query($koneksi, "
        UPDATE barang
        SET $kategori_set_sql
            stok_akhir='$stok_baru',
            harga_beli='$harga_esc',
            harga_eceran='$harga_eceran_esc',
            harga_jual='$harga_jual_esc',
            harga_jual_eceran='$harga_jual_eceran_esc',
            tanggal_terupdate_baru='$tanggal_esc'
        WHERE id_barang='$id_barang'
    ");

    // ✅ MUTASI STOK
    mysqli_query($koneksi, "
        INSERT INTO mutasi_stok(
            id_pembelian, id_barang, tanggal, jenis, qty,
            stok_sebelum, stok_sesudah, keterangan
        ) VALUES(
            '$id_pembelian', '$id_barang', NOW(), 'masuk', '$volume_esc',
            '$stok_lama', '$stok_baru', 'Pembelian'
        )
    ");

    $total_tagihan += ($harga_esc * $volume_esc);
}

// ✅ Tambahkan biaya admin (hanya sekali) ke total tagihan
$total_tagihan += $biaya_admin;

/*
|--------------------------------------------------------------------------
| Simpan Status Pembayaran (per nota / per supplier, sekali per transaksi)
|--------------------------------------------------------------------------
*/
if ($status_pembayaran === 'lunas') {
    $jumlah_dibayar_final = $total_tagihan;
} else {
    // sebagian: jangan sampai melebihi total tagihan
    $jumlah_dibayar_final = min($jumlah_dibayar_awal, $total_tagihan);
}
$status_final = ($jumlah_dibayar_final <= 0)
    ? 'belum_bayar'
    : (($jumlah_dibayar_final >= $total_tagihan) ? 'lunas' : 'sebagian');

$id_supplier_int = (int) $id_supplier;
mysqli_query($koneksi, "
    INSERT INTO pembayaran_pembelian(
        kode_transaksi, id_supplier, total_tagihan, jumlah_dibayar, status_pembayaran, tanggal_transaksi
    ) VALUES(
        '$kode_transaksi_esc', '$id_supplier_int', '$total_tagihan', '$jumlah_dibayar_final', '$status_final', '$tanggal_esc'
    )
") or die('INSERT pembayaran Error: ' . mysqli_error($koneksi));

if ($jumlah_dibayar_final > 0) {
    $bukti_sql = $bukti_pembayaran_awal !== null
        ? "'" . mysqli_real_escape_string($koneksi, $bukti_pembayaran_awal) . "'"
        : "NULL";
    $keterangan_bayar = ($status_final === 'lunas') ? 'Pembayaran lunas saat transaksi' : 'Pembayaran awal (cicilan)';
    mysqli_query($koneksi, "
        INSERT INTO riwayat_pembayaran_pembelian(
            kode_transaksi, jumlah_bayar, tanggal_bayar, bukti_pembayaran, keterangan
        ) VALUES(
            '$kode_transaksi_esc', '$jumlah_dibayar_final', '$tanggal_esc', $bukti_sql, '$keterangan_bayar'
        )
    ");
}

// ✅ NOTIFIKASI: tampilkan info barang baru yang otomatis didaftarkan
$alert_text = 'Data transaksi berhasil ditambahkan.';
if (!empty($new_barangs_created)) {
    $list_barang = implode(', ', $new_barangs_created);
    $alert_text .= " 🆕 Barang baru otomatis didaftarkan: " . $list_barang;
}
$alert_text .= " Harga beli, harga eceran, harga jual & tanggal otomatis terupdate ke tabel barang.";
if ($status_final === 'lunas') {
    $alert_text .= " 💰 Status: LUNAS.";
} elseif ($status_final === 'sebagian') {
    $sisa = $total_tagihan - $jumlah_dibayar_final;
    $alert_text .= " 💰 Status: Dibayar sebagian, sisa Rp " . number_format($sisa, 0, ',', '.') . ".";
} else {
    $alert_text .= " 💰 Status: Belum dibayar.";
}

$_SESSION['alert'] = [
    'icon'  => 'success',
    'title' => 'Berhasil',
    'text'  => $alert_text
];

header("Location: ../transaksi-pembelian-food/index.php");
exit;
