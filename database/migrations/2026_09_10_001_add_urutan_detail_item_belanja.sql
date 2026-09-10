-- Migration: Add urutan column to detail_item_belanja table
-- Date: 2026-09-10
-- Description: Menambahkan kolom urutan untuk fitur drag and drop reorder barang per menu di dompet harian

ALTER TABLE detail_item_belanja
    ADD COLUMN IF NOT EXISTS urutan INT(11) NOT NULL DEFAULT 0 AFTER id;

-- Inisialisasi data yang sudah ada agar urutan sesuai urutan ID
UPDATE detail_item_belanja
SET urutan = id
WHERE urutan = 0;
