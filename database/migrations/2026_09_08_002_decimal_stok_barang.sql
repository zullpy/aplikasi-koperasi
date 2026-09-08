-- Migration: Support decimal pada stok_awal & stok_akhir barang serta mutasi_stok
-- Date: 2026-09-08
-- Context: Menyelaraskan presisi desimal dengan transaksi_pembelian.volume DECIMAL(12,3)
--          agar stok_awal (sebelumnya INT) dan stok_akhir/mutasi (sebelumnya DECIMAL(10,2))
--          tidak membulatkan nilai pecahan 3 desimal.

-- db_draft_barang: stok_awal dan stok_akhir di tabel barang
ALTER TABLE barang
    MODIFY COLUMN stok_awal DECIMAL(12,3) DEFAULT 0.000,
    MODIFY COLUMN stok_akhir DECIMAL(12,3) NOT NULL DEFAULT 0.000;

-- db_draft_barang: tabel mutasi_stok
ALTER TABLE mutasi_stok
    MODIFY COLUMN qty DECIMAL(12,3) NOT NULL,
    MODIFY COLUMN stok_sebelum DECIMAL(12,3) NOT NULL,
    MODIFY COLUMN stok_sesudah DECIMAL(12,3) NOT NULL;

-- db_draft_barang: tabel barang_reject
ALTER TABLE barang_reject
    MODIFY COLUMN qty DECIMAL(12,3) NOT NULL;
