-- Migration: Sync SARUNG TANGAN KARET NITRIL to SARUNG TANGAN KARET BLUE NITRILE
-- Date: 2026-08-10
-- Description: Menyelaraskan nama barang 'SARUNG TANGAN KARET NITRIL' menjadi 'SARUNG TANGAN KARET BLUE NITRILE' agar rekapitulasi pembelian dan pengambilan tergabung dalam 1 baris.

-- Update di database db_draft_barang ($koneksi)
UPDATE barang 
SET nama_barang = 'SARUNG TANGAN KARET BLUE NITRILE' 
WHERE TRIM(LOWER(nama_barang)) = 'sarung tangan karet nitril';

UPDATE transaksi_pembelian 
SET nama_barang = 'SARUNG TANGAN KARET BLUE NITRILE' 
WHERE TRIM(LOWER(nama_barang)) = 'sarung tangan karet nitril';

-- Update di database db_mbg ($koneksi2)
UPDATE pengambilan_barang_detail 
SET nama_barang = 'SARUNG TANGAN KARET BLUE NITRILE' 
WHERE TRIM(LOWER(nama_barang)) = 'sarung tangan karet nitril';

UPDATE stok_barang 
SET nama_barang = 'SARUNG TANGAN KARET BLUE NITRILE' 
WHERE TRIM(LOWER(nama_barang)) = 'sarung tangan karet nitril';
