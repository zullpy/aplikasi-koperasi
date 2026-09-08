-- Migration: Support decimal volume pada transaksi_pembelian dan qty pada detail_penjualan
-- Date: 2026-09-08
-- Context: Sebelumnya volume dan qty disimpan sebagai INT sehingga nilai pecahan
--          seperti 0.5 atau 1.5 dibulatkan. Diubah ke DECIMAL agar bisa menyimpan
--          nilai satuan seperti 0.5 dus, 1.5 meter, dll.

-- db_draft_barang: kolom volume di transaksi_pembelian (INT -> DECIMAL)
ALTER TABLE transaksi_pembelian
    MODIFY COLUMN volume DECIMAL(12,3) NOT NULL;

-- db_draft_barang: kolom qty di detail_penjualan (INT -> DECIMAL)
ALTER TABLE detail_penjualan
    MODIFY COLUMN qty DECIMAL(12,3) NOT NULL;
