-- Migration: Add ttd_pembuat column to pengajuan_anggaran table
-- Date: 2026-08-09

ALTER TABLE pengajuan_anggaran ADD COLUMN IF NOT EXISTS ttd_pembuat MEDIUMTEXT NULL AFTER ttd_admin;
