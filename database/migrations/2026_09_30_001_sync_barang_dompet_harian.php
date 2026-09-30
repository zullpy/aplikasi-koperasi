<?php
/**
 * Migration: Sync Barang Dompet Harian ke Daftar Harga Barang
 * Date: 2026-09-30
 * Description: Menyelaraskan seluruh barang dari Dompet Harian (estimasi_harga & detail_item_belanja)
 * ke tabel barang dan riwayat_harga agar terintegrasi penuh di Daftar Harga Barang.
 */

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    return;
}

require_once __DIR__ . '/../sync-barang-helper.php';

// 1. Pastikan kolom di tabel barang fleksibel dan memiliki default yang aman
try {
    @$koneksi->query("ALTER TABLE barang MODIFY COLUMN nama_barang VARCHAR(150) NULL");
} catch (Throwable $e) {}

try {
    @$koneksi->query("ALTER TABLE barang MODIFY COLUMN harga_jual BIGINT(20) NOT NULL DEFAULT 0");
} catch (Throwable $e) {}

try {
    $chkIdx = $koneksi->query("SHOW INDEX FROM barang WHERE Key_name = 'idx_nama_barang'");
    if ($chkIdx && $chkIdx->num_rows === 0) {
        @$koneksi->query("ALTER TABLE barang ADD INDEX idx_nama_barang (nama_barang)");
    }
} catch (Throwable $e) {}

try {
    $chkIdxE = $koneksi->query("SHOW INDEX FROM estimasi_harga WHERE Key_name = 'idx_nama_barang'");
    if ($chkIdxE && $chkIdxE->num_rows === 0) {
        @$koneksi->query("ALTER TABLE estimasi_harga ADD INDEX idx_nama_barang (nama_barang(50))");
    }
} catch (Throwable $e) {}

// 2. Sinkronkan seluruh barang dari estimasi_harga yang belum ada di tabel barang
$qEst = $koneksi->query("
    SELECT id, nama_barang, harga_beli, satuan, tanggal_terupdate
    FROM estimasi_harga
    WHERE nama_barang IS NOT NULL AND TRIM(nama_barang) != ''
    ORDER BY id ASC
");
if ($qEst) {
    while ($row = $qEst->fetch_assoc()) {
        syncBarangDompetHarian(
            $koneksi,
            $row['nama_barang'],
            $row['harga_beli'],
            $row['satuan'],
            $row['tanggal_terupdate']
        );
    }
}

// 3. Sinkronkan barang dari detail_item_belanja dan catat riwayat harganya
$qDetail = $koneksi->query("
    SELECT 
        d.id AS detail_id,
        d.nama_barang, 
        d.harga, 
        d.satuan, 
        COALESCE(p.tanggal, CURDATE()) AS tanggal_transaksi
    FROM detail_item_belanja d
    LEFT JOIN pengajuan_belanja p ON p.id = d.pengajuan_id
    WHERE d.nama_barang IS NOT NULL AND TRIM(d.nama_barang) != ''
    ORDER BY d.id ASC
");
if ($qDetail) {
    while ($row = $qDetail->fetch_assoc()) {
        $idBarang = syncBarangDompetHarian(
            $koneksi,
            $row['nama_barang'],
            $row['harga'],
            $row['satuan'],
            $row['tanggal_transaksi']
        );

        if ($idBarang) {
            $stmtUpdDetail = $koneksi->prepare("UPDATE detail_item_belanja SET id_barang = ? WHERE id = ?");
            if ($stmtUpdDetail) {
                $stmtUpdDetail->bind_param('ii', $idBarang, $row['detail_id']);
                $stmtUpdDetail->execute();
                $stmtUpdDetail->close();
            }
        }
    }
}

// 4. Pastikan barang yang ada di tabel barang juga ada di estimasi_harga
try {
    $qBarang = $koneksi->query("
        SELECT nama_barang, harga_beli, satuan, tanggal_terupdate_baru
        FROM barang
        WHERE nama_barang IS NOT NULL AND TRIM(nama_barang) != ''
    ");
    if ($qBarang) {
        while ($rowB = $qBarang->fetch_assoc()) {
            $nama = trim($rowB['nama_barang']);
            $harga = floatval($rowB['harga_beli']);
            $sat = trim($rowB['satuan'] ?? '') ?: 'Pcs';
            $tgl = !empty($rowB['tanggal_terupdate_baru']) ? $rowB['tanggal_terupdate_baru'] : date('Y-m-d');

            $chk = $koneksi->prepare("SELECT id FROM estimasi_harga WHERE LOWER(TRIM(nama_barang)) = LOWER(?) LIMIT 1");
            if ($chk) {
                $chk->bind_param('s', $nama);
                $chk->execute();
                $resChk = $chk->get_result();
                if ($resChk && $resChk->num_rows === 0) {
                    $ins = $koneksi->prepare("INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES (?, ?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param('sdss', $nama, $harga, $sat, $tgl);
                        $ins->execute();
                        $ins->close();
                    }
                }
                $chk->close();
            }
        }
    }
} catch (Throwable $e) {}
