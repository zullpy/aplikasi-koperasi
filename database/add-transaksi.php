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
// FIX: sebelumnya backend baca $_POST['keuntungan'] padahal field form bernama
// keuntungan_dus[] & keuntungan_eceran[] -> markup harga jual selalu 0. Dipisah & diperbaiki.
$keuntungan_dus    = $_POST['keuntungan_dus'] ?? [];
$keuntungan_eceran = $_POST['keuntungan_eceran'] ?? [];
$volume        = $_POST['volume'];
$satuan        = $_POST['satuan'];

// ✅ BARU: input manual untuk isi per satuan & satuan eceran (dari form)
$isi_per_satuan_manual = $_POST['isi_per_satuan'] ?? [];
$satuan_eceran_manual  = $_POST['satuan_eceran'] ?? [];

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
$ada_penambahan_stok_eceran = false; // ✅ BARU: flag untuk notifikasi stok eceran

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
    $keuntungan_dus        = [$keuntungan_dus];
    $keuntungan_eceran     = [$keuntungan_eceran];
    $isi_per_satuan_manual = [$isi_per_satuan_manual];
    $satuan_eceran_manual  = [$satuan_eceran_manual];
    $volume       = [$volume];
    $satuan       = [$satuan];
    $keterangan   = [$keterangan];
}

foreach ($nama_barang as $i => $barang_nama) {
    $harga_item          = preg_replace('/[^0-9]/', '', $harga[$i] ?? '0');
    $harga_eceran_item   = preg_replace('/[^0-9]/', '', $harga_eceran[$i] ?? '0');
    $keuntungan_dus_item    = preg_replace('/[^0-9]/', '', $keuntungan_dus[$i] ?? '0');
    $keuntungan_eceran_item = preg_replace('/[^0-9]/', '', $keuntungan_eceran[$i] ?? '0');
    $volume_item         = $volume[$i];
    $satuan_item         = $satuan[$i];
    $keterangan_item     = $keterangan[$i];
    $kategori_item       = trim($kategori[$i] ?? '');

    // ✅ Hitung harga jual per dus di server (deteksi otomatis dari kolom "Isi")
    $jumlah_isi = 0;
    $satuan_eceran_terdeteksi = null;
    if (preg_match('/isi\s*(\d+)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
    } elseif (preg_match('/(\d+)\s*(pcs|bungkus|pack|botol|sachet|batang|lembar|butir|biji|kotak|dus)/i', $keterangan_item, $matches)) {
        $jumlah_isi = (int) $matches[1];
        $satuan_eceran_terdeteksi = strtoupper($matches[2]);
    }

    // ✅ BARU: isi per satuan & satuan eceran — input manual (dari form) diprioritaskan,
    // kalau kosong baru fallback ke hasil deteksi otomatis dari kolom "Isi".
    $isi_manual_raw = preg_replace('/[^0-9]/', '', $isi_per_satuan_manual[$i] ?? '');
    $isi_manual_item = ($isi_manual_raw === '') ? 0 : (int) $isi_manual_raw;
    $jumlah_isi_final = $isi_manual_item > 0 ? $isi_manual_item : $jumlah_isi;

    $satuan_eceran_manual_item = trim($satuan_eceran_manual[$i] ?? '');
    $satuan_eceran_final = $satuan_eceran_manual_item !== ''
        ? strtoupper($satuan_eceran_manual_item)
        : $satuan_eceran_terdeteksi;

    $harga_jual_item = $jumlah_isi_final > 0
        ? $harga_item + ($keuntungan_dus_item * $jumlah_isi_final)
        : $harga_item + $keuntungan_dus_item;

    $harga_jual_eceran_item = $harga_eceran_item + $keuntungan_eceran_item;

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

    // Konversi grosir->eceran (manual diutamakan, fallback ke hasil deteksi dari keterangan)
    $isi_per_satuan_sql = $jumlah_isi_final > 0 ? (int) $jumlah_isi_final : null;
    $satuan_eceran_esc  = ($satuan_eceran_final !== null && $satuan_eceran_final !== '')
        ? mysqli_real_escape_string($koneksi, $satuan_eceran_final)
        : null;

    // ✅ CEK BARANG + AUTO-REGISTER JIKA BELUM ADA
    $cari = mysqli_query($koneksi, "SELECT * FROM barang WHERE nama_barang='$barang_nama_esc'");
    $barang = mysqli_fetch_assoc($cari);

    // ✅ BARU: hitung penambahan stok eceran = volume dus/satuan yang dibeli x isi per satuan
    $tambahan_stok_eceran = $isi_per_satuan_sql !== null ? ($volume_esc * $isi_per_satuan_sql) : 0;
    $stok_eceran_lama = $barang ? (int) ($barang['stok_eceran'] ?? 0) : 0;
    $stok_eceran_baru = $stok_eceran_lama + $tambahan_stok_eceran;

    if (!$barang) {
        // 🆕 AUTO-CREATE: barang belum terdaftar → daftarkan otomatis ke tabel barang
        $satuan_default = !empty($satuan_item) ? $satuan_esc : 'Pcs';

        $insert_barang = mysqli_query($koneksi, "
            INSERT INTO barang (
                nama_barang, kategori, stok_akhir, harga_beli, harga_eceran,
                harga_jual, harga_jual_eceran, satuan, satuan_eceran, isi_per_satuan, stok_eceran,
                tanggal_terupdate_baru
            ) VALUES (
                '$barang_nama_esc',
                " . ($kategori_esc !== '' ? "'$kategori_esc'" : "NULL") . ",
                0,
                '$harga_esc',
                '$harga_eceran_esc',
                '$harga_jual_esc',
                '$harga_jual_eceran_esc',
                '$satuan_default',
                " . ($satuan_eceran_esc !== null ? "'$satuan_eceran_esc'" : "NULL") . ",
                " . ($isi_per_satuan_sql !== null ? $isi_per_satuan_sql : "NULL") . ",
                $stok_eceran_baru,
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

    // ✅ BARU: simpan/refresh konversi grosir->eceran setiap kali ada nilai baru (manual atau deteksi otomatis),
    // supaya isi per satuan & satuan eceran bisa dikoreksi user kapan saja, bukan cuma sekali di awal.
    $konversi_set_sql = "";
    if ($isi_per_satuan_sql !== null) {
        $konversi_set_sql .= "isi_per_satuan=$isi_per_satuan_sql,";
    }
    if ($satuan_eceran_esc !== null) {
        $konversi_set_sql .= "satuan_eceran='$satuan_eceran_esc',";
    }

    mysqli_query($koneksi, "
        UPDATE barang
        SET $kategori_set_sql
            $konversi_set_sql
            stok_akhir='$stok_baru',
            stok_eceran='$stok_eceran_baru',
            harga_beli='$harga_esc',
            harga_eceran='$harga_eceran_esc',
            harga_jual='$harga_jual_esc',
            harga_jual_eceran='$harga_jual_eceran_esc',
            tanggal_terupdate_baru='$tanggal_esc'
        WHERE id_barang='$id_barang'
    ");

    // ✅ MUTASI STOK (+ catatan stok eceran kalau ada penambahan)
    $ket_mutasi = 'Pembelian';
    if ($tambahan_stok_eceran > 0) {
        $satuan_eceran_label = $satuan_eceran_final ?: 'eceran';
        $ket_mutasi .= " (stok eceran +{$tambahan_stok_eceran} {$satuan_eceran_label}: {$stok_eceran_lama} -> {$stok_eceran_baru})";
        $ada_penambahan_stok_eceran = true;
    }
    $ket_mutasi_esc = mysqli_real_escape_string($koneksi, $ket_mutasi);

    mysqli_query($koneksi, "
        INSERT INTO mutasi_stok(
            id_pembelian, id_barang, tanggal, jenis, qty,
            stok_sebelum, stok_sesudah, keterangan
        ) VALUES(
            '$id_pembelian', '$id_barang', NOW(), 'masuk', '$volume_esc',
            '$stok_lama', '$stok_baru', '$ket_mutasi_esc'
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
if ($ada_penambahan_stok_eceran) {
    $alert_text .= " 📦 Stok eceran otomatis bertambah sesuai isi per satuan.";
}
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
