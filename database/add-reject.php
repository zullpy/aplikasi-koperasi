<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'koneksi.php';
require_once 'auth.php';

// Batasi akses hanya untuk admin, bendahara, dan ketua
$userRole = $_SESSION['role'] ?? null;
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Anda tidak memiliki wewenang untuk mencatat barang reject.'
    ];
    header("Location: ../laporan-barang/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Metode request tidak diizinkan.'
    ];
    header("Location: ../laporan-barang/index.php");
    exit;
}

$nama_barang        = trim($_POST['nama_barang'] ?? '');
$gudang             = trim($_POST['gudang'] ?? '');
$tipe               = trim($_POST['tipe'] ?? '');
$qty                = floatval($_POST['qty'] ?? 0);
$harga_beli_eceran  = floatval(str_replace('.', '', $_POST['harga_beli_eceran'] ?? '0'));
$keterangan         = trim($_POST['keterangan'] ?? '');

if (empty($nama_barang) || empty($gudang) || empty($tipe) || $qty <= 0) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Semua field (Nama Barang, Gudang, Tipe Unit, Qty) wajib diisi dengan benar.'
    ];
    header("Location: ../laporan-barang/index.php");
    exit;
}

$nama_barang_esc = mysqli_real_escape_string($koneksi, $nama_barang);

// Cek barang di master barang
$barangRes = mysqli_query($koneksi, "SELECT id_barang, nama_barang, stok_akhir, isi_per_satuan FROM barang WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang_esc')) LIMIT 1");
if (!$barangRes || mysqli_num_rows($barangRes) === 0) {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Nama barang tidak terdaftar di database.'
    ];
    header("Location: ../laporan-barang/index.php");
    exit;
}

$barangRow = mysqli_fetch_assoc($barangRes);
$id_barang = (int)$barangRow['id_barang'];
$isi_per_satuan = $barangRow['isi_per_satuan'] ? (float)$barangRow['isi_per_satuan'] : 1;

// Hitung total nilai reject
$total = 0;
if ($tipe === 'grosir') {
    $total = $qty * $isi_per_satuan * $harga_beli_eceran;
} else {
    $total = $qty * $harga_beli_eceran;
}

$koneksi->begin_transaction();
$koneksi2->begin_transaction();

try {
    // 1. Kurangi stok di gudang yang dipilih
    if ($gudang === 'pusat') {
        $stok_lama = (float)$barangRow['stok_akhir'];
        if ($tipe === 'grosir') {
            $qty_grosir_kurang = $qty;
        } else {
            $qty_grosir_kurang = $qty / $isi_per_satuan;
        }
        $stok_baru = $stok_lama - $qty_grosir_kurang;

        // Update stok_akhir
        $updateQuery = "UPDATE barang SET stok_akhir = ? WHERE id_barang = ?";
        $stmtUpdate = $koneksi->prepare($updateQuery);
        $stmtUpdate->bind_param("di", $stok_baru, $id_barang);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Catat mutasi stok
        $ket_mutasi = 'Barang Reject (Pusat) - ' . $keterangan;
        $insertMutasi = "INSERT INTO mutasi_stok (id_barang, tanggal, jenis, qty, stok_sebelum, stok_sesudah, keterangan) 
                         VALUES (?, NOW(), 'keluar', ?, ?, ?, ?)";
        $stmtMutasi = $koneksi->prepare($insertMutasi);
        $stmtMutasi->bind_param("iddds", $id_barang, $qty_grosir_kurang, $stok_lama, $stok_baru, $ket_mutasi);
        $stmtMutasi->execute();
        $stmtMutasi->close();

    } else {
        // Cabang: sodong, sariwangi, manonjaya (menggunakan $koneksi2 / db_mbg)
        $gudang_esc = mysqli_real_escape_string($koneksi2, $gudang);
        $nama_barang_esc2 = mysqli_real_escape_string($koneksi2, $nama_barang);

        $stokCabangRes = mysqli_query($koneksi2, "SELECT id, qty_grosir, qty_eceran FROM stok_barang WHERE lokasi = '$gudang_esc' AND LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang_esc2')) LIMIT 1");
        if (!$stokCabangRes || mysqli_num_rows($stokCabangRes) === 0) {
            throw new Exception("Stok barang tidak ditemukan di gudang cabang " . ucfirst($gudang) . ".");
        }

        $stokCabang = mysqli_fetch_assoc($stokCabangRes);
        $id_stok_cabang = (int)$stokCabang['id'];
        $qty_grosir_lama = (float)$stokCabang['qty_grosir'];
        $qty_eceran_lama = (float)$stokCabang['qty_eceran'];

        if ($tipe === 'grosir') {
            $qty_grosir_baru = $qty_grosir_lama - $qty;
            $qty_eceran_baru = $qty_eceran_lama - ($qty * $isi_per_satuan);
        } else {
            $qty_eceran_baru = $qty_eceran_lama - $qty;
            $qty_grosir_baru = $qty_grosir_lama - ($qty / $isi_per_satuan);
        }

        // Update branch stock
        $updateCabangQuery = "UPDATE stok_barang SET qty_grosir = ?, qty_eceran = ? WHERE id = ?";
        $stmtUpdateCabang = $koneksi2->prepare($updateCabangQuery);
        $stmtUpdateCabang->bind_param("ddi", $qty_grosir_baru, $qty_eceran_baru, $id_stok_cabang);
        $stmtUpdateCabang->execute();
        $stmtUpdateCabang->close();
    }

    // 2. Simpan transaksi reject
    $insertReject = "INSERT INTO barang_reject (id_barang, nama_barang, gudang, tipe, qty, harga_beli_eceran, total, keterangan, tanggal) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmtReject = $koneksi->prepare($insertReject);
    $stmtReject->bind_param("isssddds", $id_barang, $nama_barang, $gudang, $tipe, $qty, $harga_beli_eceran, $total, $keterangan);
    $stmtReject->execute();
    $stmtReject->close();

    $koneksi->commit();
    $koneksi2->commit();

    $_SESSION['alert'] = [
        'icon'  => 'success',
        'title' => 'Berhasil',
        'text'  => 'Pencatatan barang reject berhasil disimpan dan stok gudang dikurangi.'
    ];

} catch (Exception $e) {
    $koneksi->rollback();
    $koneksi2->rollback();

    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Gagal',
        'text'  => 'Terjadi kesalahan: ' . $e->getMessage()
    ];
}

header("Location: ../laporan-barang/index.php");
exit;
