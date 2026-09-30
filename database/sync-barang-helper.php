<?php
/**
 * Helper sinkronisasi barang dua arah antara Dompet Harian dan Daftar Harga Barang.
 */

if (!function_exists('syncBarangDompetHarian')) {
    /**
     * Menyinkronkan barang yang diinput dari Dompet Harian ke tabel `barang`, `riwayat_harga`, dan `estimasi_harga`.
     *
     * @param mysqli $koneksi
     * @param string $namaBarang
     * @param float|int $hargaBeli
     * @param string $satuan
     * @param string|null $tanggal YYYY-MM-DD
     * @param string|null $kategori
     * @return int|null id_barang dari tabel `barang`
     */
    function syncBarangDompetHarian($koneksi, $namaBarang, $hargaBeli, $satuan, $tanggal = null, $kategori = null)
    {
        $namaBarang = trim((string)$namaBarang);
        if ($namaBarang === '') {
            return null;
        }

        $hargaBeli = floatval($hargaBeli);
        $satuan = trim((string)$satuan);
        if ($satuan === '') {
            $satuan = 'Pcs';
        }

        $tanggal = !empty($tanggal) ? substr(trim($tanggal), 0, 10) : date('Y-m-d');
        $kategori = !empty($kategori) ? trim($kategori) : 'BAHAN BAKU';

        $idBarang = null;

        // 1. Cek keberadaan di tabel `barang`
        $stmtCek = $koneksi->prepare("
            SELECT id_barang, harga_beli, harga_jual, satuan, tanggal_terupdate_baru 
            FROM barang 
            WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM(?)) 
            LIMIT 1
        ");
        if ($stmtCek) {
            $stmtCek->bind_param('s', $namaBarang);
            $stmtCek->execute();
            $res = $stmtCek->get_result();
            $rowB = $res ? $res->fetch_assoc() : null;
            $stmtCek->close();

            if ($rowB) {
                $idBarang = intval($rowB['id_barang']);
                $oldHargaBeli = floatval($rowB['harga_beli']);
                $oldHargaJual = floatval($rowB['harga_jual']);

                // Pertahankan margin keuntungan jika sudah pernah diset oleh admin
                if ($oldHargaJual > 0 && $oldHargaBeli > 0 && $oldHargaJual >= $oldHargaBeli) {
                    $margin = $oldHargaJual - $oldHargaBeli;
                    $newHargaJual = $hargaBeli > 0 ? ($hargaBeli + $margin) : $oldHargaJual;
                } else {
                    $newHargaJual = $hargaBeli > 0 ? $hargaBeli : $oldHargaJual;
                }

                if ($hargaBeli > 0) {
                    $stmtUpd = $koneksi->prepare("
                        UPDATE barang 
                        SET harga_beli = ?, 
                            harga_jual = ?, 
                            satuan = COALESCE(NULLIF(?, ''), satuan),
                            tanggal_terupdate_baru = ?
                        WHERE id_barang = ?
                    ");
                    if ($stmtUpd) {
                        $stmtUpd->bind_param('ddssi', $hargaBeli, $newHargaJual, $satuan, $tanggal, $idBarang);
                        $stmtUpd->execute();
                        $stmtUpd->close();
                    }
                } else {
                    $stmtUpd = $koneksi->prepare("
                        UPDATE barang 
                        SET satuan = COALESCE(NULLIF(?, ''), satuan),
                            tanggal_terupdate_baru = ?
                        WHERE id_barang = ?
                    ");
                    if ($stmtUpd) {
                        $stmtUpd->bind_param('ssi', $satuan, $tanggal, $idBarang);
                        $stmtUpd->execute();
                        $stmtUpd->close();
                    }
                }
            } else {
                // INSERT barang baru ke tabel `barang`
                $hargaJual = $hargaBeli > 0 ? $hargaBeli : 0;
                $stmtIns = $koneksi->prepare("
                    INSERT INTO barang (
                        nama_barang, harga_beli, harga_jual, suplier, satuan, kategori, stok_akhir, tanggal_terupdate_baru
                    ) VALUES (?, ?, ?, '-', ?, ?, 0, ?)
                ");
                if ($stmtIns) {
                    $stmtIns->bind_param('sddsss', $namaBarang, $hargaBeli, $hargaJual, $satuan, $kategori, $tanggal);
                    $stmtIns->execute();
                    $idBarang = $stmtIns->insert_id;
                    $stmtIns->close();
                }
            }
        }

        // 2. Catat ke tabel `riwayat_harga`
        if ($idBarang && $hargaBeli > 0) {
            $stmtChkR = $koneksi->prepare("
                SELECT harga_beli, tanggal 
                FROM riwayat_harga 
                WHERE id_barang = ? 
                ORDER BY tanggal DESC, id_riwayat DESC 
                LIMIT 1
            ");
            $needInsertR = true;
            if ($stmtChkR) {
                $stmtChkR->bind_param('i', $idBarang);
                $stmtChkR->execute();
                $resR = $stmtChkR->get_result();
                if ($resR && $rowR = $resR->fetch_assoc()) {
                    if (floatval($rowR['harga_beli']) == $hargaBeli && substr($rowR['tanggal'], 0, 10) === $tanggal) {
                        $needInsertR = false;
                    }
                }
                $stmtChkR->close();
            }

            if ($needInsertR) {
                $stmtInsR = $koneksi->prepare("INSERT INTO riwayat_harga (id_barang, harga_beli, tanggal) VALUES (?, ?, ?)");
                if ($stmtInsR) {
                    $stmtInsR->bind_param('ids', $idBarang, $hargaBeli, $tanggal);
                    $stmtInsR->execute();
                    $stmtInsR->close();
                }
            }
        }

        // 3. Sinkronkan juga ke tabel `estimasi_harga` (agar dropdown Dompet Harian konsisten)
        $stmtCekE = $koneksi->prepare("SELECT id FROM estimasi_harga WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmtCekE) {
            $stmtCekE->bind_param('s', $namaBarang);
            $stmtCekE->execute();
            $resE = $stmtCekE->get_result();
            $rowE = $resE ? $resE->fetch_assoc() : null;
            $stmtCekE->close();

            if ($rowE) {
                if ($hargaBeli > 0) {
                    $stmtUpdE = $koneksi->prepare("UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = ? WHERE id = ?");
                    if ($stmtUpdE) {
                        $stmtUpdE->bind_param('dssi', $hargaBeli, $satuan, $tanggal, $rowE['id']);
                        $stmtUpdE->execute();
                        $stmtUpdE->close();
                    }
                }
            } else {
                $stmtInsE = $koneksi->prepare("INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES (?, ?, ?, ?)");
                if ($stmtInsE) {
                    $stmtInsE->bind_param('sdss', $namaBarang, $hargaBeli, $satuan, $tanggal);
                    $stmtInsE->execute();
                    $stmtInsE->close();
                }
            }
        }

        return $idBarang;
    }
}

if (!function_exists('syncBarangFromDaftarHarga')) {
    /**
     * Menyinkronkan perubahan dari Daftar Harga Barang ke riwayat_harga dan estimasi_harga.
     *
     * @param mysqli $koneksi
     * @param int $idBarang
     * @param string $namaBarang
     * @param float|int $hargaBeli
     * @param string $satuan
     * @param string|null $tanggal
     */
    function syncBarangFromDaftarHarga($koneksi, $idBarang, $namaBarang, $hargaBeli, $satuan, $tanggal = null)
    {
        $idBarang = intval($idBarang);
        $namaBarang = trim((string)$namaBarang);
        $hargaBeli = floatval($hargaBeli);
        $satuan = trim((string)$satuan) !== '' ? trim((string)$satuan) : 'Pcs';
        $tanggal = !empty($tanggal) ? substr(trim($tanggal), 0, 10) : date('Y-m-d');

        if ($idBarang > 0 && $hargaBeli > 0) {
            $stmtChkR = $koneksi->prepare("
                SELECT harga_beli, tanggal 
                FROM riwayat_harga 
                WHERE id_barang = ? 
                ORDER BY tanggal DESC, id_riwayat DESC 
                LIMIT 1
            ");
            $needInsertR = true;
            if ($stmtChkR) {
                $stmtChkR->bind_param('i', $idBarang);
                $stmtChkR->execute();
                $resR = $stmtChkR->get_result();
                if ($resR && $rowR = $resR->fetch_assoc()) {
                    if (floatval($rowR['harga_beli']) == $hargaBeli && substr($rowR['tanggal'], 0, 10) === $tanggal) {
                        $needInsertR = false;
                    }
                }
                $stmtChkR->close();
            }

            if ($needInsertR) {
                $stmtInsR = $koneksi->prepare("INSERT INTO riwayat_harga (id_barang, harga_beli, tanggal) VALUES (?, ?, ?)");
                if ($stmtInsR) {
                    $stmtInsR->bind_param('ids', $idBarang, $hargaBeli, $tanggal);
                    $stmtInsR->execute();
                    $stmtInsR->close();
                }
            }
        }

        if ($namaBarang !== '') {
            $stmtCekE = $koneksi->prepare("SELECT id FROM estimasi_harga WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM(?)) LIMIT 1");
            if ($stmtCekE) {
                $stmtCekE->bind_param('s', $namaBarang);
                $stmtCekE->execute();
                $resE = $stmtCekE->get_result();
                $rowE = $resE ? $resE->fetch_assoc() : null;
                $stmtCekE->close();

                if ($rowE) {
                    $stmtUpdE = $koneksi->prepare("UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = ? WHERE id = ?");
                    if ($stmtUpdE) {
                        $stmtUpdE->bind_param('dssi', $hargaBeli, $satuan, $tanggal, $rowE['id']);
                        $stmtUpdE->execute();
                        $stmtUpdE->close();
                    }
                } else {
                    $stmtInsE = $koneksi->prepare("INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES (?, ?, ?, ?)");
                    if ($stmtInsE) {
                        $stmtInsE->bind_param('sdss', $namaBarang, $hargaBeli, $satuan, $tanggal);
                        $stmtInsE->execute();
                        $stmtInsE->close();
                    }
                }
            }
        }
    }
}
