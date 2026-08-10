-- Migration: Add status_lunas column to detail_item_belanja table
-- Date: 2026-08-10
-- Description: Tambah kolom status_lunas untuk fitur konfirmasi lunas per item (khusus admin)

ALTER TABLE detail_item_belanja
    ADD COLUMN IF NOT EXISTS status_lunas ENUM('belum','lunas') NOT NULL DEFAULT 'belum' AFTER status_beli;
