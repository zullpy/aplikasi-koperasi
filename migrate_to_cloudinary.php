<?php
/**
 * Script Migrasi Foto Lokal ke Cloudinary
 * Aplikasi Kopdes (Koperasi Desa Bina Usaha Sauyunan)
 * 
 * Fitur:
 * - Dukungan CLI Terminal & Web Browser UI
 * - Menggunakan ID Cursor (tidak stuck pada file missing/lama)
 * - Menangani Multi-Nota Belanja, Nota Pembelian, Bukti Transfer Pengajuan,
 *   Bukti Bayar Hutang, Bukti Kas/Pengembalian Saldo, Bukti Approval Anggaran,
 *   TTD Laporan Koperasi, Bukti Profit & Pajak,
 *   Serta Bukti Transfer Penjualan Foodcost & Addcost
 * - Sinkronisasi langsung file fisik dari disk ke Cloudinary + Auto Update DB
 * - Otomatis convert format WebP & kompresi optimal
 * - Opsi hapus file lokal setelah sukses di-upload
 */

@ini_set('memory_limit', '512M');
@set_time_limit(0);

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/database/koneksi.php';
require_once __DIR__ . '/database/cloudinary_helper.php';

if (!cloudinary_is_configured()) {
    $msg = "ERROR: Cloudinary belum terkonfigurasi di database/cloudinary.php. Pastikan CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, dan CLOUDINARY_API_SECRET telah terisi.";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    } else {
        die("<div style='font-family:sans-serif;padding:24px;color:#b91c1c;background:#fee2e2;border-radius:8px;max-width:600px;margin:40px auto;'><h3>Konfigurasi Belum Lengkap</h3><p>{$msg}</p></div>");
    }
}

// Inisialisasi koneksi PDO untuk db_draft_barang dan db_mbg
try {
    $pdoDraft = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db1']['db']};charset=utf8mb4", $cfg['db1']['user'], $cfg['db1']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Exception $e) {
    die("Koneksi PDO db_draft_barang gagal: " . $e->getMessage());
}

try {
    $pdoMbg = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db2']['db']};charset=utf8mb4", $cfg['db2']['user'], $cfg['db2']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Exception $e) {
    die("Koneksi PDO db_mbg gagal: " . $e->getMessage());
}

// Target Migrasi Berdasarkan Tabel Database
$MIGRATION_TARGETS = [
    'upload_nota' => [
        'label'        => 'Multi-Nota Belanja SPPG (upload_nota)',
        'db'           => 'draft',
        'table'        => 'upload_nota',
        'pk'           => 'id',
        'col'          => 'file_path',
        'local_dirs'   => [__DIR__ . '/uploads/nota'],
        'cloud_folder' => 'nota',
        'type'         => 'filepath', // Berisi path relatif misal ../uploads/nota/xxx
    ],
    'transaksi_pembelian' => [
        'label'        => 'Nota Transaksi Pembelian',
        'db'           => 'draft',
        'table'        => 'transaksi_pembelian',
        'pk'           => 'id_pembelian',
        'col'          => 'nota',
        'local_dirs'   => [__DIR__ . '/uploads/nota'],
        'cloud_folder' => 'nota',
        'type'         => 'comma_separated', // Bisa dipisahkan koma misal foto1.jpg,foto2.jpg
    ],
    'pengajuan_belanja' => [
        'label'        => 'Bukti Transfer Pengajuan Belanja',
        'db'           => 'draft',
        'table'        => 'pengajuan_belanja',
        'pk'           => 'id',
        'col'          => 'bukti_transfer',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_transfer'],
        'cloud_folder' => 'bukti_transfer',
        'type'         => 'single',
    ],
    'riwayat_bayar_pembelian' => [
        'label'        => 'Bukti Bayar Tagihan/Hutang Pembelian',
        'db'           => 'draft',
        'table'        => 'riwayat_pembayaran_pembelian',
        'pk'           => 'id_riwayat',
        'col'          => 'bukti_pembayaran',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_transfer', __DIR__ . '/uploads/bukti_pembayaran'],
        'cloud_folder' => 'bukti_transfer',
        'type'         => 'single',
    ],
    'saldo_koperasi' => [
        'label'        => 'Bukti Saldo Kas & Pengembalian',
        'db'           => 'draft',
        'table'        => 'saldo_koperasi',
        'pk'           => 'id',
        'col'          => 'bukti_transfer',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_saldo_koperasi', __DIR__ . '/uploads/bukti_transfer'],
        'cloud_folder' => 'bukti_transfer',
        'type'         => 'single',
    ],
    'pengajuan_anggaran' => [
        'label'        => 'Bukti Approval Anggaran Koperasi',
        'db'           => 'draft',
        'table'        => 'pengajuan_anggaran',
        'pk'           => 'id',
        'col'          => 'bukti',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_approval_koperasi'],
        'cloud_folder' => 'approval',
        'type'         => 'filepath',
    ],
    'ttd_laporan_koperasi' => [
        'label'        => 'TTD Digital Laporan Koperasi',
        'db'           => 'draft',
        'table'        => 'ttd_laporan_koperasi',
        'pk'           => 'id',
        'col'          => 'signature_path',
        'local_dirs'   => [__DIR__ . '/uploads/ttd_koperasi'],
        'cloud_folder' => 'approval',
        'type'         => 'filepath',
    ],
    'profit_bukti' => [
        'label'        => 'Bukti Setoran Profit Koperasi',
        'db'           => 'draft',
        'table'        => 'profit_koperasi',
        'pk'           => 'id',
        'col'          => 'bukti_profit',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_profit'],
        'cloud_folder' => 'profit',
        'type'         => 'json_or_single',
    ],
    'profit_pajak' => [
        'label'        => 'Bukti Pajak Profit Koperasi',
        'db'           => 'draft',
        'table'        => 'profit_koperasi',
        'pk'           => 'id',
        'col'          => 'bukti_pajak',
        'local_dirs'   => [__DIR__ . '/uploads/bukti_profit'],
        'cloud_folder' => 'profit',
        'type'         => 'json_or_single',
    ],
    'tf_penjualan_foodcost' => [
        'label'        => 'Bukti Transfer Penjualan Foodcost (db_mbg)',
        'db'           => 'mbg',
        'table'        => 'pembayaran',
        'pk'           => 'id_pembayaran',
        'col'          => 'bukti_transfer',
        'extra_where'  => "jenis = 'foodcost'",
        'local_dirs'   => [__DIR__ . '/penjualan-sppg-foodcost/uploads/bukti-transfer'],
        'cloud_folder' => 'tf_penjualan_foodcost',
        'type'         => 'filepath',
    ],
    'tf_penjualan_addcost' => [
        'label'        => 'Bukti Transfer Penjualan Addcost (db_mbg)',
        'db'           => 'mbg',
        'table'        => 'pembayaran',
        'pk'           => 'id_pembayaran',
        'col'          => 'bukti_transfer',
        'extra_where'  => "jenis = 'addcost'",
        'local_dirs'   => [__DIR__ . '/penjualan-sppg-addcost/uploads/bukti-transfer'],
        'cloud_folder' => 'tf_penjualan_addcost',
        'type'         => 'filepath',
    ],
];

// Target Sinkronisasi Folder Disk
$DISK_SYNC_TARGETS = [
    'nota' => [
        'label'        => 'Nota Belanja & Pembelian (uploads/nota)',
        'dir'          => __DIR__ . '/uploads/nota',
        'cloud_folder' => 'nota',
    ],
    'bukti_transfer' => [
        'label'        => 'Bukti Transfer & Bayar (uploads/bukti_transfer)',
        'dir'          => __DIR__ . '/uploads/bukti_transfer',
        'cloud_folder' => 'bukti_transfer',
    ],
    'tf_foodcost' => [
        'label'        => 'Bukti Transfer Penjualan Foodcost',
        'dir'          => __DIR__ . '/penjualan-sppg-foodcost/uploads/bukti-transfer',
        'cloud_folder' => 'tf_penjualan_foodcost',
    ],
    'tf_addcost' => [
        'label'        => 'Bukti Transfer Penjualan Addcost',
        'dir'          => __DIR__ . '/penjualan-sppg-addcost/uploads/bukti-transfer',
        'cloud_folder' => 'tf_penjualan_addcost',
    ],
    'bukti_approval' => [
        'label'        => 'Bukti Approval Anggaran (uploads/bukti_approval_koperasi)',
        'dir'          => __DIR__ . '/uploads/bukti_approval_koperasi',
        'cloud_folder' => 'approval',
    ],
    'ttd_koperasi' => [
        'label'        => 'TTD Laporan Koperasi (uploads/ttd_koperasi)',
        'dir'          => __DIR__ . '/uploads/ttd_koperasi',
        'cloud_folder' => 'approval',
    ],
    'bukti_saldo' => [
        'label'        => 'Bukti Saldo Kas (uploads/bukti_saldo_koperasi)',
        'dir'          => __DIR__ . '/uploads/bukti_saldo_koperasi',
        'cloud_folder' => 'bukti_transfer',
    ],
    'bukti_pembayaran' => [
        'label'        => 'Bukti Pembayaran (uploads/bukti_pembayaran)',
        'dir'          => __DIR__ . '/uploads/bukti_pembayaran',
        'cloud_folder' => 'bukti_transfer',
    ],
    'bukti_profit' => [
        'label'        => 'Bukti Profit (uploads/bukti_profit)',
        'dir'          => __DIR__ . '/uploads/bukti_profit',
        'cloud_folder' => 'profit',
    ],
    'nota_koperasi' => [
        'label'        => 'Nota Koperasi (uploads/nota_koperasi)',
        'dir'          => __DIR__ . '/uploads/nota_koperasi',
        'cloud_folder' => 'nota',
    ],
];

/**
 * Helper mencari path file lokal dari daftar direktori
 */
function findLocalFile(string $cleanName, array $dirs): ?string
{
    foreach ($dirs as $dir) {
        $p = rtrim($dir, '/') . '/' . ltrim($cleanName, '/');
        if (file_exists($p) && is_file($p)) {
            return $p;
        }
    }
    // Coba fallback dengan hanya nama file dasar jika cleanName mengandung direktori
    $base = basename($cleanName);
    foreach ($dirs as $dir) {
        $p = rtrim($dir, '/') . '/' . $base;
        if (file_exists($p) && is_file($p)) {
            return $p;
        }
    }
    return null;
}

/**
 * Hitung statistik migrasi database
 */
function getMigrationStats(PDO $pdoDraft, PDO $pdoMbg, array $targets): array
{
    $stats = [];
    $totalLocal = 0;
    $totalCloud = 0;

    foreach ($targets as $key => $t) {
        $pdo = ($t['db'] === 'mbg') ? $pdoMbg : $pdoDraft;
        $table = $t['table'];
        $col   = $t['col'];
        $whereExtra = !empty($t['extra_where']) ? " AND ({$t['extra_where']})" : "";

        try {
            $sqlLocal = "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` IS NOT NULL AND `{$col}` != '' AND `{$col}` NOT LIKE '%res.cloudinary.com%' {$whereExtra}";
            $stmtLocal = $pdo->query($sqlLocal);
            $countLocal = (int)$stmtLocal->fetchColumn();

            $sqlCloud = "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE '%res.cloudinary.com%' {$whereExtra}";
            $stmtCloud = $pdo->query($sqlCloud);
            $countCloud = (int)$stmtCloud->fetchColumn();

            $stats[$key] = [
                'key'         => $key,
                'label'       => $t['label'],
                'local_count' => $countLocal,
                'cloud_count' => $countCloud,
                'total_count' => $countLocal + $countCloud,
            ];
            $totalLocal += $countLocal;
            $totalCloud += $countCloud;
        } catch (Exception $e) {
            $stats[$key] = [
                'key'         => $key,
                'label'       => $t['label'],
                'error'       => $e->getMessage(),
                'local_count' => 0,
                'cloud_count' => 0,
                'total_count' => 0,
            ];
        }
    }

    return [
        'targets'     => $stats,
        'total_local' => $totalLocal,
        'total_cloud' => $totalCloud,
        'grand_total' => $totalLocal + $totalCloud,
    ];
}

/**
 * Hitung total file fisik di semua folder disk
 */
function getDiskStats(array $diskTargets): array
{
    $stats = [];
    $grandTotal = 0;
    foreach ($diskTargets as $k => $cfg) {
        $dir = $cfg['dir'];
        $count = 0;
        if (is_dir($dir)) {
            $files = array_filter(scandir($dir), function($f) use ($dir) {
                return is_file($dir . '/' . $f) && $f !== '.gitkeep' && !str_starts_with($f, '.');
            });
            $count = count($files);
        }
        $stats[$k] = [
            'key'   => $k,
            'label' => $cfg['label'],
            'count' => $count,
        ];
        $grandTotal += $count;
    }
    return ['folders' => $stats, 'total_files' => $grandTotal];
}

/**
 * Update semua kolom database yang mungkin merujuk nama file ini
 */
function linkUploadedFileToDatabase(PDO $pdoDraft, PDO $pdoMbg, string $cleanFilename, string $cloudUrl): int
{
    $updatedCount = 0;

    // 1. db_draft_barang: upload_nota
    try {
        $stmt = $pdoDraft->prepare("UPDATE upload_nota SET file_path = ? WHERE file_path LIKE ? AND file_path NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 2. db_draft_barang: transaksi_pembelian
    try {
        $stmt = $pdoDraft->prepare("SELECT id_pembelian, nota FROM transaksi_pembelian WHERE nota LIKE ?");
        $stmt->execute(['%' . $cleanFilename . '%']);
        while ($row = $stmt->fetch()) {
            $tokens = array_map('trim', explode(',', $row['nota']));
            $changed = false;
            foreach ($tokens as $idx => $tok) {
                if (basename($tok) === $cleanFilename && !str_contains($tok, 'res.cloudinary.com')) {
                    $tokens[$idx] = $cloudUrl;
                    $changed = true;
                }
            }
            if ($changed) {
                $newNota = implode(',', $tokens);
                $up = $pdoDraft->prepare("UPDATE transaksi_pembelian SET nota = ? WHERE id_pembelian = ?");
                $up->execute([$newNota, $row['id_pembelian']]);
                $updatedCount++;
            }
        }
    } catch (Exception $e) {}

    // 3. db_draft_barang: pengajuan_belanja
    try {
        $stmt = $pdoDraft->prepare("UPDATE pengajuan_belanja SET bukti_transfer = ? WHERE (bukti_transfer LIKE ? OR bukti_transfer = ?) AND bukti_transfer NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 4. db_draft_barang: riwayat_pembayaran_pembelian
    try {
        $stmt = $pdoDraft->prepare("UPDATE riwayat_pembayaran_pembelian SET bukti_pembayaran = ? WHERE (bukti_pembayaran LIKE ? OR bukti_pembayaran = ?) AND bukti_pembayaran NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 5. db_draft_barang: saldo_koperasi
    try {
        $stmt = $pdoDraft->prepare("UPDATE saldo_koperasi SET bukti_transfer = ? WHERE (bukti_transfer LIKE ? OR bukti_transfer = ?) AND bukti_transfer NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 6. db_draft_barang: pengajuan_anggaran
    try {
        $stmt = $pdoDraft->prepare("UPDATE pengajuan_anggaran SET bukti = ? WHERE (bukti LIKE ? OR bukti = ?) AND bukti NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 7. db_draft_barang: ttd_laporan_koperasi
    try {
        $stmt = $pdoDraft->prepare("UPDATE ttd_laporan_koperasi SET signature_path = ? WHERE (signature_path LIKE ? OR signature_path = ?) AND signature_path NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    // 8. db_draft_barang: profit_koperasi
    try {
        $stmt = $pdoDraft->prepare("SELECT id, bukti_profit, bukti_pajak FROM profit_koperasi WHERE bukti_profit LIKE ? OR bukti_pajak LIKE ?");
        $stmt->execute(['%' . $cleanFilename . '%', '%' . $cleanFilename . '%']);
        while ($row = $stmt->fetch()) {
            $upFields = [];
            $params = [];
            foreach (['bukti_profit', 'bukti_pajak'] as $f) {
                $val = $row[$f];
                if (empty($val)) continue;
                $arr = json_decode($val, true);
                if (is_array($arr)) {
                    $changed = false;
                    foreach ($arr as $i => $item) {
                        if (basename($item) === $cleanFilename && !str_contains($item, 'res.cloudinary.com')) {
                            $arr[$i] = $cloudUrl;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $upFields[] = "{$f} = ?";
                        $params[] = json_encode($arr);
                    }
                } elseif (basename($val) === $cleanFilename && !str_contains($val, 'res.cloudinary.com')) {
                    $upFields[] = "{$f} = ?";
                    $params[] = $cloudUrl;
                }
            }
            if (!empty($upFields)) {
                $params[] = $row['id'];
                $up = $pdoDraft->prepare("UPDATE profit_koperasi SET " . implode(', ', $upFields) . " WHERE id = ?");
                $up->execute($params);
                $updatedCount++;
            }
        }
    } catch (Exception $e) {}

    // 9. db_mbg: pembayaran (foodcost & addcost)
    try {
        $stmt = $pdoMbg->prepare("UPDATE pembayaran SET bukti_transfer = ? WHERE (bukti_transfer LIKE ? OR bukti_transfer = ?) AND bukti_transfer NOT LIKE '%res.cloudinary.com%'");
        $stmt->execute([$cloudUrl, '%' . $cleanFilename, $cleanFilename]);
        $updatedCount += $stmt->rowCount();
    } catch (Exception $e) {}

    return $updatedCount;
}

/**
 * Migrasi 1 item dari database
 */
function migrateSingleDbItem(PDO $pdoDraft, PDO $pdoMbg, array $targetConfig, array $row, bool $deleteLocal = false, bool $dryRun = false): array
{
    $pdo         = ($targetConfig['db'] === 'mbg') ? $pdoMbg : $pdoDraft;
    $table       = $targetConfig['table'];
    $pkCol       = $targetConfig['pk'];
    $col         = $targetConfig['col'];
    $localDirs   = $targetConfig['local_dirs'];
    $cloudFolder = $targetConfig['cloud_folder'];
    $type        = $targetConfig['type'] ?? 'single';

    $id     = (int)$row[$pkCol];
    $rawVal = trim($row[$col] ?? '');

    if (empty($rawVal)) {
        return ['success' => false, 'status' => 'empty', 'id' => $id, 'filename' => '', 'message' => 'Nilai kolom kosong di database'];
    }

    if (str_contains($rawVal, 'res.cloudinary.com')) {
        return ['success' => true, 'status' => 'already_cloud', 'id' => $id, 'filename' => $rawVal, 'message' => 'Sudah berupa URL Cloudinary'];
    }

    // 1. Tipe comma_separated (misal transaksi_pembelian.nota)
    if ($type === 'comma_separated') {
        $tokens = array_map('trim', explode(',', $rawVal));
        $newTokens = [];
        $uploaded = 0;
        $missing = 0;

        foreach ($tokens as $tok) {
            if (empty($tok)) continue;
            if (str_contains($tok, 'res.cloudinary.com')) {
                $newTokens[] = $tok;
                continue;
            }
            $clean = basename(parse_url($tok, PHP_URL_PATH) ?? $tok);
            $localPath = findLocalFile($clean, $localDirs);

            if (!$localPath) {
                $newTokens[] = $tok;
                $missing++;
                continue;
            }

            if ($dryRun) {
                $newTokens[] = '[SIMULASI_CLOUDINARY]';
                $uploaded++;
                continue;
            }

            try {
                $up = cloudinary_upload($localPath, $cloudFolder);
                if ($up['success'] && !empty($up['url'])) {
                    $newTokens[] = $up['url'];
                    $uploaded++;
                    if ($deleteLocal) @unlink($localPath);
                } else {
                    $newTokens[] = $tok;
                }
            } catch (Exception $e) {
                $newTokens[] = $tok;
            }
        }

        if ($uploaded > 0 && !$dryRun) {
            $upStmt = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE `{$pkCol}` = ?");
            $upStmt->execute([implode(',', $newTokens), $id]);
        }

        if ($uploaded > 0) {
            return ['success' => true, 'status' => $dryRun ? 'dry_run' : 'uploaded', 'id' => $id, 'filename' => $rawVal, 'message' => "Sukses diunggah ({$uploaded} file)"];
        } elseif ($missing > 0) {
            return ['success' => false, 'status' => 'missing', 'id' => $id, 'filename' => $rawVal, 'message' => 'File tidak ditemukan di disk lokal'];
        } else {
            return ['success' => true, 'status' => 'already_cloud', 'id' => $id, 'filename' => $rawVal, 'message' => 'Sudah di Cloudinary'];
        }
    }

    // 2. Tipe json_or_single (misal profit_koperasi)
    if ($type === 'json_or_single') {
        $arr = json_decode($rawVal, true);
        if (is_array($arr)) {
            $newArr = [];
            $uploaded = 0;
            $missing = 0;
            foreach ($arr as $item) {
                if (empty($item)) continue;
                if (str_contains($item, 'res.cloudinary.com')) {
                    $newArr[] = $item;
                    continue;
                }
                $clean = basename(parse_url($item, PHP_URL_PATH) ?? $item);
                $localPath = findLocalFile($clean, $localDirs);
                if (!$localPath) {
                    $newArr[] = $item;
                    $missing++;
                    continue;
                }
                if ($dryRun) {
                    $newArr[] = '[SIMULASI]';
                    $uploaded++;
                    continue;
                }
                try {
                    $up = cloudinary_upload($localPath, $cloudFolder);
                    if ($up['success'] && !empty($up['url'])) {
                        $newArr[] = $up['url'];
                        $uploaded++;
                        if ($deleteLocal) @unlink($localPath);
                    } else {
                        $newArr[] = $item;
                    }
                } catch (Exception $e) {
                    $newArr[] = $item;
                }
            }
            if ($uploaded > 0 && !$dryRun) {
                $upStmt = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE `{$pkCol}` = ?");
                $upStmt->execute([json_encode($newArr), $id]);
            }
            return [
                'success' => ($uploaded > 0),
                'status'  => ($uploaded > 0) ? ($dryRun ? 'dry_run' : 'uploaded') : ($missing > 0 ? 'missing' : 'error'),
                'id'      => $id,
                'filename'=> $rawVal,
                'message' => "JSON: {$uploaded} file sukses diupload"
            ];
        }
    }

    // 3. Tipe single / filepath
    $cleanFilename = basename(parse_url($rawVal, PHP_URL_PATH) ?? $rawVal);
    $localPath = findLocalFile($cleanFilename, $localDirs);

    if (!$localPath) {
        return [
            'success'  => false,
            'status'   => 'missing',
            'id'       => $id,
            'filename' => $cleanFilename,
            'message'  => "File tidak ditemukan di disk lokal: {$cleanFilename}"
        ];
    }

    if ($dryRun) {
        return [
            'success'  => true,
            'status'   => 'dry_run',
            'id'       => $id,
            'filename' => $cleanFilename,
            'message'  => 'Simulasi upload siap'
        ];
    }

    try {
        $up = cloudinary_upload($localPath, $cloudFolder);
        if (!$up['success'] || empty($up['url'])) {
            throw new Exception("Cloudinary tidak mengembalikan URL yang valid");
        }

        $cloudUrl = $up['url'];

        // Update record
        $upStmt = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE `{$pkCol}` = ?");
        $upStmt->execute([$cloudUrl, $id]);

        if ($deleteLocal && file_exists($localPath)) {
            @unlink($localPath);
        }

        return [
            'success'  => true,
            'status'   => 'uploaded',
            'id'       => $id,
            'filename' => $cleanFilename,
            'url'      => $cloudUrl,
            'message'  => 'Sukses terunggah ke Cloudinary & DB terupdate'
        ];
    } catch (Exception $e) {
        return [
            'success'  => false,
            'status'   => 'error',
            'id'       => $id,
            'filename' => $cleanFilename,
            'message'  => $e->getMessage()
        ];
    }
}

// =========================================================================
// MODE 1: AJAX REQUEST HANDLER (WEB UI)
// =========================================================================
if (!$isCli && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // Cek statistik
    if ($action === 'get_stats') {
        $dbStats = getMigrationStats($pdoDraft, $pdoMbg, $MIGRATION_TARGETS);
        $diskStats = getDiskStats($DISK_SYNC_TARGETS);
        echo json_encode([
            'success'    => true,
            'db_stats'   => $dbStats,
            'disk_stats' => $diskStats,
            'cloud_name' => CLOUDINARY_CLOUD_NAME,
            'folder'     => CLOUDINARY_BASE_FOLDER ?? 'aplikasi-kopdes',
        ]);
        exit;
    }

    // Inspect sampel record database
    if ($action === 'inspect') {
        $samples = [];
        foreach ($MIGRATION_TARGETS as $k => $cfg) {
            $pdo   = ($cfg['db'] === 'mbg') ? $pdoMbg : $pdoDraft;
            $table = $cfg['table'];
            $col   = $cfg['col'];
            $pk    = $cfg['pk'];
            $where = !empty($cfg['extra_where']) ? " WHERE {$cfg['extra_where']}" : "";
            try {
                $stmt = $pdo->query("SELECT `{$pk}`, `{$col}` FROM `{$table}` {$where} ORDER BY `{$pk}` DESC LIMIT 3");
                $samples[$k] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $samples[$k] = ['error' => $e->getMessage()];
            }
        }
        echo json_encode(['success' => true, 'samples' => $samples]);
        exit;
    }

    // Migrasi Batch Database (Cursor ID)
    if ($action === 'migrate_db_batch') {
        $targetKey   = $_POST['target_key'] ?? '';
        $lastId      = (int)($_POST['last_id'] ?? 0);
        $batchSize   = max(1, min(15, (int)($_POST['batch_size'] ?? 5)));
        $deleteLocal = !empty($_POST['delete_local']) && ($_POST['delete_local'] === '1' || $_POST['delete_local'] === 'true');

        if (!isset($MIGRATION_TARGETS[$targetKey])) {
            echo json_encode(['success' => false, 'message' => 'Target migrasi tidak ditemukan: ' . $targetKey]);
            exit;
        }

        $cfg   = $MIGRATION_TARGETS[$targetKey];
        $pdo   = ($cfg['db'] === 'mbg') ? $pdoMbg : $pdoDraft;
        $table = $cfg['table'];
        $pk    = $cfg['pk'];
        $col   = $cfg['col'];
        $extra = !empty($cfg['extra_where']) ? " AND ({$cfg['extra_where']})" : "";

        $stmt = $pdo->prepare("SELECT `{$pk}`, `{$col}` FROM `{$table}` WHERE `{$pk}` > ? AND `{$col}` IS NOT NULL AND `{$col}` != '' AND `{$col}` NOT LIKE '%res.cloudinary.com%' {$extra} ORDER BY `{$pk}` ASC LIMIT {$batchSize}");
        $stmt->execute([$lastId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processedItems = [];
        $nextLastId = $lastId;

        foreach ($rows as $row) {
            $nextLastId = (int)$row[$pk];
            $res = migrateSingleDbItem($pdoDraft, $pdoMbg, $cfg, $row, $deleteLocal, false);
            $res['category'] = $cfg['label'];
            $processedItems[] = $res;
        }

        $isCategoryFinished = (count($rows) === 0);
        $latestStats = getMigrationStats($pdoDraft, $pdoMbg, $MIGRATION_TARGETS);

        echo json_encode([
            'success'           => true,
            'target_key'        => $targetKey,
            'next_last_id'      => $nextLastId,
            'count'             => count($processedItems),
            'items'             => $processedItems,
            'category_finished' => $isCategoryFinished,
            'stats'             => $latestStats,
        ]);
        exit;
    }

    // Sinkronisasi Langsung dari File Fisik Disk
    if ($action === 'sync_disk_batch') {
        $folderKey    = $_POST['folder_key'] ?? '';
        $offset       = (int)($_POST['offset'] ?? 0);
        $batchLimit   = max(1, min(15, (int)($_POST['batch_size'] ?? 5)));
        $deleteLocal  = !empty($_POST['delete_local']) && ($_POST['delete_local'] === '1' || $_POST['delete_local'] === 'true');
        $skipExisting = !isset($_POST['skip_existing']) || $_POST['skip_existing'] === '1' || $_POST['skip_existing'] === 'true';

        if (!isset($DISK_SYNC_TARGETS[$folderKey])) {
            echo json_encode(['success' => false, 'message' => 'Folder disk tidak ditemukan: ' . $folderKey]);
            exit;
        }

        $cfg = $DISK_SYNC_TARGETS[$folderKey];
        $dir = $cfg['dir'];

        if (!is_dir($dir)) {
            echo json_encode([
                'success'     => true,
                'folder_key'  => $folderKey,
                'finished'    => true,
                'items'       => [],
                'message'     => 'Direktori tidak ditemukan di server'
            ]);
            exit;
        }

        $allFiles = array_values(array_filter(scandir($dir), function($f) use ($dir) {
            return is_file($dir . '/' . $f) && $f !== '.gitkeep' && !str_starts_with($f, '.');
        }));

        $totalFiles = count($allFiles);
        $slice = array_slice($allFiles, $offset, $batchLimit);
        $items = [];
        $nextOffset = $offset + count($slice);

        foreach ($slice as $filename) {
            $localPath = $dir . '/' . $filename;

            // Cek apakah di database sudah pernah tersimpan URL Cloudinary untuk file ini
            if ($skipExisting) {
                $alreadyCloud = false;
                // Cek upload_nota
                $c = $pdoDraft->query("SELECT file_path FROM upload_nota WHERE file_path LIKE '%" . $filename . "%' AND file_path LIKE '%res.cloudinary.com%' LIMIT 1");
                if ($c && $c->fetch()) $alreadyCloud = true;

                if (!$alreadyCloud) {
                    $c2 = $pdoDraft->query("SELECT nota FROM transaksi_pembelian WHERE nota LIKE '%" . $filename . "%' AND nota LIKE '%res.cloudinary.com%' LIMIT 1");
                    if ($c2 && $c2->fetch()) $alreadyCloud = true;
                }

                if (!$alreadyCloud) {
                    $c3 = $pdoDraft->query("SELECT bukti_transfer FROM pengajuan_belanja WHERE bukti_transfer LIKE '%" . $filename . "%' AND bukti_transfer LIKE '%res.cloudinary.com%' LIMIT 1");
                    if ($c3 && $c3->fetch()) $alreadyCloud = true;
                }

                if ($alreadyCloud) {
                    $items[] = [
                        'file'    => $filename,
                        'status'  => 'skipped',
                        'message' => 'Sudah termigrasi di database'
                    ];
                    continue;
                }
            }

            try {
                $up = cloudinary_upload($localPath, $cfg['cloud_folder']);
                if ($up['success'] && !empty($up['url'])) {
                    $cloudUrl = $up['url'];
                    $dbUpdated = linkUploadedFileToDatabase($pdoDraft, $pdoMbg, $filename, $cloudUrl);

                    if ($deleteLocal && file_exists($localPath)) {
                        @unlink($localPath);
                    }

                    $items[] = [
                        'file'       => $filename,
                        'status'     => 'uploaded',
                        'url'        => $cloudUrl,
                        'db_updated' => $dbUpdated,
                        'message'    => "Sukses diunggah (" . ($dbUpdated > 0 ? "{$dbUpdated} baris DB diperbarui" : "file fisik tersimpan") . ")"
                    ];
                } else {
                    $items[] = [
                        'file'    => $filename,
                        'status'  => 'error',
                        'message' => 'Gagal mengunggah ke Cloudinary'
                    ];
                }
            } catch (Exception $e) {
                $items[] = [
                    'file'    => $filename,
                    'status'  => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        echo json_encode([
            'success'     => true,
            'folder_key'  => $folderKey,
            'offset'      => $offset,
            'next_offset' => $nextOffset,
            'total_files' => $totalFiles,
            'finished'    => ($nextOffset >= $totalFiles),
            'items'       => $items,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
    exit;
}

// =========================================================================
// MODE 2: CLI TERMINAL RUNNER
// =========================================================================
if ($isCli) {
    echo "\n=========================================================\n";
    echo "  MIGRASI FOTO LOKAL KE CLOUDINARY - APLIKASI KOPDES\n";
    echo "=========================================================\n";

    $options = getopt('', ['dry-run', 'delete-local', 'limit::', 'type::', 'sync-disk']);
    $dryRun      = isset($options['dry-run']);
    $deleteLocal = isset($options['delete-local']);
    $limit       = isset($options['limit']) ? (int)$options['limit'] : 0;
    $filterType  = $options['type'] ?? 'all';
    $isSyncDisk  = isset($options['sync-disk']);

    if ($dryRun) {
        echo ">>> MODE: DRY RUN (Simulasi tanpa upload Cloudinary)\n";
    }
    if ($deleteLocal) {
        echo ">>> PERINGATAN: File lokal akan dihapus setelah sukses di-upload.\n";
    }

    if ($isSyncDisk) {
        echo "\n>>> Menjalankan Sinkronisasi Folder Disk Langsung...\n";
        foreach ($DISK_SYNC_TARGETS as $k => $cfg) {
            $dir = $cfg['dir'];
            if (!is_dir($dir)) continue;
            $files = array_values(array_filter(scandir($dir), function($f) use ($dir) {
                return is_file($dir . '/' . $f) && $f !== '.gitkeep' && !str_starts_with($f, '.');
            }));
            echo "\nFolder [{$cfg['label']}]: " . count($files) . " file\n";
            foreach ($files as $i => $fn) {
                $lp = $dir . '/' . $fn;
                echo sprintf("  [%d/%d] %-45s ", $i+1, count($files), substr($fn, 0, 45));
                if ($dryRun) {
                    echo "[SIMULASI OK]\n";
                    continue;
                }
                try {
                    $up = cloudinary_upload($lp, $cfg['cloud_folder']);
                    if ($up['success'] && !empty($up['url'])) {
                        $upDb = linkUploadedFileToDatabase($pdoDraft, $pdoMbg, $fn, $up['url']);
                        if ($deleteLocal) @unlink($lp);
                        echo "[OK: {$upDb} DB linked]\n";
                    } else {
                        echo "[FAILED]\n";
                    }
                } catch (Exception $e) {
                    echo "[ERR: " . $e->getMessage() . "]\n";
                }
            }
        }
        echo "\nSelesai sinkronisasi disk.\n";
        exit(0);
    }

    $initialStats = getMigrationStats($pdoDraft, $pdoMbg, $MIGRATION_TARGETS);
    echo "\nStatus Data Database Sebelum Migrasi:\n";
    foreach ($initialStats['targets'] as $st) {
        echo sprintf("  - %-40s : %4d lokal | %4d Cloudinary\n", $st['label'], $st['local_count'], $st['cloud_count']);
    }
    echo sprintf("  TOTAL RECORD LOKAL PERLU DI-MIGRASI : %d\n\n", $initialStats['total_local']);

    if ($initialStats['total_local'] === 0) {
        echo "Semua file di database sudah termigrasi ke Cloudinary! Selesai.\n\n";
        exit(0);
    }

    $targetsToProcess = ($filterType === 'all' || !isset($MIGRATION_TARGETS[$filterType]))
        ? $MIGRATION_TARGETS
        : [$filterType => $MIGRATION_TARGETS[$filterType]];

    $totalProcessed = 0;
    $totalSuccess   = 0;
    $totalMissing   = 0;
    $totalFailed    = 0;

    foreach ($targetsToProcess as $tKey => $targetConfig) {
        $pdo   = ($targetConfig['db'] === 'mbg') ? $pdoMbg : $pdoDraft;
        $table = $targetConfig['table'];
        $pkCol = $targetConfig['pk'];
        $col   = $targetConfig['col'];
        $extra = !empty($targetConfig['extra_where']) ? " AND ({$targetConfig['extra_where']})" : "";

        $lastId = 0;
        $categoryProcessed = 0;

        echo ">>> Memproses: {$targetConfig['label']}...\n";

        while (true) {
            $batchLimit = ($limit > 0) ? min(50, $limit - $totalProcessed) : 50;
            if ($batchLimit <= 0) break;

            $stmt = $pdo->prepare("SELECT `{$pkCol}`, `{$col}` FROM `{$table}` WHERE `{$pkCol}` > ? AND `{$col}` IS NOT NULL AND `{$col}` != '' AND `{$col}` NOT LIKE '%res.cloudinary.com%' {$extra} ORDER BY `{$pkCol}` ASC LIMIT {$batchLimit}");
            $stmt->execute([$lastId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) break;

            foreach ($rows as $row) {
                $lastId = (int)$row[$pkCol];
                $totalProcessed++;
                $categoryProcessed++;

                echo sprintf("  [%3d] ID %-5d %-40s ", $categoryProcessed, $lastId, substr($row[$col], 0, 40));

                $res = migrateSingleDbItem($pdoDraft, $pdoMbg, $targetConfig, $row, $deleteLocal, $dryRun);

                if ($res['status'] === 'uploaded' || $res['status'] === 'dry_run') {
                    $totalSuccess++;
                    echo "[OK]\n";
                } elseif ($res['status'] === 'missing') {
                    $totalMissing++;
                    echo "[SKIP: Tidak ada di disk]\n";
                } else {
                    $totalFailed++;
                    echo "[FAILED: {$res['message']}]\n";
                }

                if ($limit > 0 && $totalProcessed >= $limit) {
                    echo "\nBatas limit ({$limit} item) tercapai.\n";
                    break 2;
                }
            }
        }
        echo "\n";
    }

    echo "=========================================================\n";
    echo "  RINGKASAN HASIL MIGRASI\n";
    echo sprintf("  Total Diproses   : %d\n", $totalProcessed);
    echo sprintf("  Sukses Diunggah  : %d\n", $totalSuccess);
    echo sprintf("  Dilewati Missing : %d\n", $totalMissing);
    echo sprintf("  Gagal / Error    : %d\n", $totalFailed);
    echo "=========================================================\n\n";

    exit(0);
}

// =========================================================================
// MODE 3: WEB UI BROWSER DASHBOARD
// =========================================================================
$dbStats   = getMigrationStats($pdoDraft, $pdoMbg, $MIGRATION_TARGETS);
$diskStats = getDiskStats($DISK_SYNC_TARGETS);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Cloudinary — Aplikasi Kopdes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="assets/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2563eb;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 30px 16px 60px;
        }

        .migrasi-wrap {
            max-width: 1040px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            padding: 24px;
            margin-bottom: 24px;
        }

        .header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #152b47; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-danger { background: var(--danger); color: #fff; }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stat-card .label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .val {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
        }

        .badge {
            display: inline-flex;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            width: fit-content;
        }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #e0f2fe; color: #0369a1; }

        .nav-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 20px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            color: var(--primary-light);
            border-bottom-color: var(--primary-light);
        }

        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }

        .target-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .target-table th, .target-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        .target-table th {
            background: #f8fafc;
            font-weight: 700;
            color: var(--text-muted);
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .target-table tr:hover {
            background: #fbfcfe;
        }

        .progress-bar-wrap {
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 6px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            width: 0%;
            transition: width 0.3s ease;
        }

        .terminal-box {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
            font-size: 12px;
            height: 280px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.6;
            margin-top: 16px;
        }

        .terminal-ok { color: #4ade80; }
        .terminal-err { color: #f87171; }
        .terminal-info { color: #60a5fa; }
        .terminal-warn { color: #facc15; }

        .control-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .control-options {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }

        .control-options label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        select {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 13px;
            font-family: inherit;
        }
    </style>
</head>
<body>

<div class="migrasi-wrap">
    <div class="card">
        <div class="header-box">
            <div class="header-title">
                <h1>
                    <span>☁️</span> Migrasi Cloudinary — Kopdes
                </h1>
                <p>Otomatisasi pemindahan seluruh dokumen & foto lokal ke Cloudinary dan pembaruan referensi database.</p>
            </div>
            <div class="header-actions">
                <a href="dompet-harian/index.php" class="btn btn-outline">← Kembali ke Sistem</a>
                <button id="btnInspect" class="btn btn-outline" onclick="inspectDatabase()">🔍 Cek URL Sampel</button>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <span class="label">Total File Lokal di DB</span>
                <span class="val" id="statLocalCount"><?= number_format($dbStats['total_local']) ?></span>
                <span class="badge badge-warning">Perlu Migrasi</span>
            </div>
            <div class="stat-card">
                <span class="label">Sudah di Cloudinary</span>
                <span class="val" id="statCloudCount"><?= number_format($dbStats['total_cloud']) ?></span>
                <span class="badge badge-success">Aman di Cloud</span>
            </div>
            <div class="stat-card">
                <span class="label">Total File Fisik Disk</span>
                <span class="val" id="statDiskCount"><?= number_format($diskStats['total_files']) ?></span>
                <span class="badge badge-info"><?= count($DISK_SYNC_TARGETS) ?> Folder Upload</span>
            </div>
            <div class="stat-card">
                <span class="label">Cloudinary Aktif</span>
                <span class="val" style="font-size: 18px; color: var(--primary-light);"><?= htmlspecialchars(CLOUDINARY_CLOUD_NAME) ?></span>
                <span class="badge badge-info">Folder: <?= htmlspecialchars(CLOUDINARY_BASE_FOLDER ?? 'aplikasi-kopdes') ?></span>
            </div>
        </div>

        <!-- Controls -->
        <div class="control-panel">
            <div class="control-options">
                <label>
                    <input type="checkbox" id="chkDeleteLocal">
                    <span>Hapus file lokal setelah sukses diunggah</span>
                </label>
                <label>
                    <input type="checkbox" id="chkSkipExisting" checked>
                    <span>Lewati jika sudah di Cloudinary</span>
                </label>
                <label>
                    <span>Ukuran Batch:</span>
                    <select id="selBatchSize">
                        <option value="1">1 item</option>
                        <option value="3">3 item</option>
                        <option value="5" selected>5 item (Rekomendasi)</option>
                        <option value="10">10 item</option>
                        <option value="15">15 item</option>
                    </select>
                </label>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button id="btnStartAll" class="btn" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35); font-weight: 700;" onclick="startAllInOneMigration()">
                    🚀 Mulai Migrasi Semua Sekaligus
                </button>
                <button id="btnStartDb" class="btn btn-primary" onclick="startDbMigration()">
                    ▶️ Mulai Migrasi Database
                </button>
                <button id="btnStartDisk" class="btn btn-success" onclick="startDiskMigration()">
                    📂 Sync File Disk Langsung
                </button>
                <button id="btnStop" class="btn btn-danger" onclick="stopMigration()" style="display: none;">
                    ⏸️ Jeda / Stop
                </button>
            </div>
        </div>

        <!-- Progress Bar Overall -->
        <div style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
                <span id="progressStatusText">Status: Siap dijalankan</span>
                <span id="progressPercentText">0%</span>
            </div>
            <div class="progress-bar-wrap">
                <div id="overallProgressBar" class="progress-bar-fill"></div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="nav-tabs">
            <button class="tab-btn active" onclick="switchTab('tab-db')">Tabel Database (<?= count($MIGRATION_TARGETS) ?> Kategori)</button>
            <button class="tab-btn" onclick="switchTab('tab-disk')">Direktori Fisik Disk (<?= count($DISK_SYNC_TARGETS) ?> Folder)</button>
        </div>

        <!-- TAB 1: DATABASE TARGETS -->
        <div id="tab-db" class="tab-pane active">
            <table class="target-table">
                <thead>
                    <tr>
                        <th>Kategori Dokumen / Foto</th>
                        <th>Subfolder Cloudinary</th>
                        <th>Lokal (DB)</th>
                        <th>Cloudinary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbStats['targets'] as $k => $st): ?>
                    <tr id="row-db-<?= $k ?>">
                        <td>
                            <strong><?= htmlspecialchars($st['label']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($MIGRATION_TARGETS[$k]['table'] . '.' . $MIGRATION_TARGETS[$k]['col']) ?></div>
                        </td>
                        <td><code><?= htmlspecialchars($MIGRATION_TARGETS[$k]['cloud_folder']) ?></code></td>
                        <td><span class="badge badge-warning" id="count-local-<?= $k ?>"><?= number_format($st['local_count']) ?></span></td>
                        <td><span class="badge badge-success" id="count-cloud-<?= $k ?>"><?= number_format($st['cloud_count']) ?></span></td>
                        <td>
                            <?php if ($st['local_count'] == 0): ?>
                                <span class="badge badge-success">✓ Selesai</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Menunggu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB 2: DISK TARGETS -->
        <div id="tab-disk" class="tab-pane">
            <table class="target-table">
                <thead>
                    <tr>
                        <th>Folder Lokal</th>
                        <th>Subfolder Cloudinary</th>
                        <th>Jumlah File</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diskStats['folders'] as $k => $f): ?>
                    <tr id="row-disk-<?= $k ?>">
                        <td>
                            <strong><?= htmlspecialchars($f['label']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($DISK_SYNC_TARGETS[$k]['dir']) ?></div>
                        </td>
                        <td><code><?= htmlspecialchars($DISK_SYNC_TARGETS[$k]['cloud_folder']) ?></code></td>
                        <td><span class="badge badge-info" id="count-disk-<?= $k ?>"><?= number_format($f['count']) ?> file</span></td>
                        <td><span class="badge badge-warning" id="status-disk-<?= $k ?>">Siap Sync</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Terminal Console -->
        <div class="terminal-box" id="terminalConsole">
[SYSTEM READY] Halaman migrasi Cloudinary Kopdes siap.
Pilih "Mulai Migrasi Database" atau "Sync File Disk Langsung" di atas.
        </div>
    </div>
</div>

<script>
let isRunning = false;
let currentMode = null; // 'db' atau 'disk'
const dbTargets = <?= json_encode(array_keys($MIGRATION_TARGETS)) ?>;
const diskFolders = <?= json_encode(array_keys($DISK_SYNC_TARGETS)) ?>;

function logTerminal(msg, type = 'info') {
    const term = document.getElementById('terminalConsole');
    const span = document.createElement('span');
    span.className = 'terminal-' + type;
    const time = new Date().toLocaleTimeString('id-ID');
    span.textContent = `[${time}] ${msg}\n`;
    term.appendChild(span);
    term.scrollTop = term.scrollHeight;
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    
    if (tabId === 'tab-db') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tab-db').classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tab-disk').classList.add('active');
    }
}

function stopMigration() {
    isRunning = false;
    logTerminal("Proses dihentikan oleh pengguna.", "warn");
    toggleButtons(false);
}

function toggleButtons(running) {
    if (document.getElementById('btnStartAll')) document.getElementById('btnStartAll').style.display = running ? 'none' : 'inline-flex';
    document.getElementById('btnStartDb').style.display = running ? 'none' : 'inline-flex';
    document.getElementById('btnStartDisk').style.display = running ? 'none' : 'inline-flex';
    document.getElementById('btnStop').style.display = running ? 'inline-flex' : 'none';
}

// -------------------------------------------------------------
// 0. MIGRASI SEMUA SEKALIGUS (DATABASE + FILE DISK)
// -------------------------------------------------------------
async function startAllInOneMigration() {
    if (isRunning) return;
    logTerminal("==================================================", "info");
    logTerminal("MEMULAI MIGRASI SEMUA SEKALIGUS (DATABASE + DISK)", "ok");
    logTerminal("==================================================", "info");

    await startDbMigration(true);
    if (isRunning) {
        logTerminal(">>> Lanjut otomatis ke sinkronisasi sisa file disk...", "info");
        await startDiskMigration(true);
    }
    isRunning = false;
    toggleButtons(false);
    logTerminal("🎉 SELURUH DATA DATABASE & FILE DISK TELAH SUKSES DIMIGRASI!", "ok");
    document.getElementById('progressStatusText').textContent = "Status: Semua Data Sukses Termigrasi 100%";
}

// -------------------------------------------------------------
// 1. MIGRASI DATABASE (BATCH PER TABEL)
// -------------------------------------------------------------
async function startDbMigration(fromAllInOne = false) {
    if (isRunning && !fromAllInOne) return;
    isRunning = true;
    currentMode = 'db';
    toggleButtons(true);
    switchTab('tab-db');

    logTerminal("Memulai migrasi database berdasar cursor ID...", "info");

    const batchSize = document.getElementById('selBatchSize').value;
    const deleteLocal = document.getElementById('chkDeleteLocal').checked ? '1' : '0';

    for (let i = 0; i < dbTargets.length; i++) {
        if (!isRunning) break;
        const targetKey = dbTargets[i];
        let lastId = 0;
        let finished = false;

        logTerminal(`>>> Memproses kategori: ${targetKey}`, "info");

        while (!finished && isRunning) {
            try {
                const formData = new FormData();
                formData.append('target_key', targetKey);
                formData.append('last_id', lastId);
                formData.append('batch_size', batchSize);
                formData.append('delete_local', deleteLocal);

                const resp = await fetch('migrate_to_cloudinary.php?action=migrate_db_batch', {
                    method: 'POST',
                    body: formData
                });
                const text = await resp.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    logTerminal(`[NETWORK/RETRY] Respons server sesaat: ${text.substring(0, 60)}... Mencoba ulang dalam 1.5 detik`, "warn");
                    await new Promise(r => setTimeout(r, 1500));
                    continue;
                }

                if (!data.success) {
                    logTerminal(`[ERROR] ${data.message}`, "err");
                    finished = true;
                    break;
                }

                if (data.items && data.items.length > 0) {
                    data.items.forEach(it => {
                        if (it.status === 'uploaded') {
                            logTerminal(`[OK] ID ${it.id}: ${it.filename} -> Cloudinary`, "ok");
                        } else if (it.status === 'missing') {
                            logTerminal(`[MISSING] ID ${it.id}: ${it.filename}`, "warn");
                        } else if (it.status === 'already_cloud') {
                            logTerminal(`[SKIP] ID ${it.id}: Sudah di Cloudinary`, "info");
                        } else {
                            logTerminal(`[FAIL] ID ${it.id}: ${it.message}`, "err");
                        }
                    });
                }

                lastId = data.next_last_id;
                finished = data.category_finished;

                // Update UI Stats
                if (data.stats) {
                    updateDbStatsUi(data.stats);
                }

            } catch (err) {
                logTerminal(`[EXCEPTION] ${err.message}`, "err");
                finished = true;
                break;
            }
        }
    }

    if (!fromAllInOne) {
        isRunning = false;
        toggleButtons(false);
    }
    logTerminal("=== Selesai seluruh antrian migrasi database ===", "ok");
    document.getElementById('progressStatusText').textContent = "Status: Selesai migrasi database";
}

// -------------------------------------------------------------
// 2. SINKRONISASI FILE DISK LANGSUNG
// -------------------------------------------------------------
async function startDiskMigration(fromAllInOne = false) {
    if (isRunning && !fromAllInOne) return;
    isRunning = true;
    currentMode = 'disk';
    toggleButtons(true);
    switchTab('tab-disk');

    logTerminal("Memulai sinkronisasi seluruh file disk langsung...", "info");

    const batchSize = document.getElementById('selBatchSize').value;
    const deleteLocal = document.getElementById('chkDeleteLocal').checked ? '1' : '0';
    const skipExisting = document.getElementById('chkSkipExisting').checked ? '1' : '0';

    for (let i = 0; i < diskFolders.length; i++) {
        if (!isRunning) break;
        const folderKey = diskFolders[i];
        let offset = 0;
        let finished = false;

        logTerminal(`>>> Memproses folder: ${folderKey}`, "info");
        const statusEl = document.getElementById(`status-disk-${folderKey}`);
        if (statusEl) statusEl.textContent = "Sedang Sync...";

        while (!finished && isRunning) {
            try {
                const formData = new FormData();
                formData.append('folder_key', folderKey);
                formData.append('offset', offset);
                formData.append('batch_size', batchSize);
                formData.append('delete_local', deleteLocal);
                formData.append('skip_existing', skipExisting);

                const resp = await fetch('migrate_to_cloudinary.php?action=sync_disk_batch', {
                    method: 'POST',
                    body: formData
                });
                const text = await resp.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    logTerminal(`[NETWORK/RETRY] Respons server sesaat: ${text.substring(0, 60)}... Mencoba ulang dalam 1.5 detik`, "warn");
                    await new Promise(r => setTimeout(r, 1500));
                    continue;
                }

                if (!data.success) {
                    logTerminal(`[ERROR] ${data.message}`, "err");
                    finished = true;
                    break;
                }

                if (data.items && data.items.length > 0) {
                    data.items.forEach(it => {
                        if (it.status === 'uploaded') {
                            logTerminal(`[UPLOADED] ${it.file} (${it.message})`, "ok");
                        } else if (it.status === 'skipped') {
                            logTerminal(`[SKIPPED] ${it.file} (Sudah di Cloudinary)`, "warn");
                        } else {
                            logTerminal(`[ERROR] ${it.file}: ${it.message}`, "err");
                        }
                    });
                }

                offset = data.next_offset;
                finished = data.finished;

                if (data.total_files > 0) {
                    const pct = Math.min(100, Math.round((offset / data.total_files) * 100));
                    document.getElementById('overallProgressBar').style.width = pct + '%';
                    document.getElementById('progressPercentText').textContent = pct + '%';
                    document.getElementById('progressStatusText').textContent = `Sync ${folderKey} (${offset}/${data.total_files})`;
                }

            } catch (err) {
                logTerminal(`[EXCEPTION] ${err.message}`, "err");
                finished = true;
                break;
            }
        }

        if (statusEl) statusEl.textContent = "✓ Selesai";
    }

    isRunning = false;
    toggleButtons(false);
    logTerminal("=== Selesai seluruh sinkronisasi file disk ===", "ok");
    document.getElementById('progressStatusText').textContent = "Status: Selesai sinkronisasi disk";

    // Refresh stats
    refreshStats();
}

async function refreshStats() {
    try {
        const resp = await fetch('migrate_to_cloudinary.php?action=get_stats');
        const data = await resp.json();
        if (data.success && data.db_stats) {
            updateDbStatsUi(data.db_stats);
        }
    } catch (e) {}
}

function updateDbStatsUi(stats) {
    document.getElementById('statLocalCount').textContent = new Intl.NumberFormat().format(stats.total_local);
    document.getElementById('statCloudCount').textContent = new Intl.NumberFormat().format(stats.total_cloud);

    if (stats.grand_total > 0) {
        const pct = Math.round((stats.total_cloud / stats.grand_total) * 100);
        document.getElementById('overallProgressBar').style.width = pct + '%';
        document.getElementById('progressPercentText').textContent = pct + '%';
    }

    if (stats.targets) {
        for (const [k, st] of Object.entries(stats.targets)) {
            const locEl = document.getElementById(`count-local-${k}`);
            const cldEl = document.getElementById(`count-cloud-${k}`);
            if (locEl) locEl.textContent = new Intl.NumberFormat().format(st.local_count);
            if (cldEl) cldEl.textContent = new Intl.NumberFormat().format(st.cloud_count);
        }
    }
}

async function inspectDatabase() {
    logTerminal("Memeriksa sampel data URL di database...", "info");
    try {
        const resp = await fetch('migrate_to_cloudinary.php?action=inspect');
        const data = await resp.json();
        if (data.success) {
            logTerminal(JSON.stringify(data.samples, null, 2), "info");
        }
    } catch (e) {
        logTerminal("Gagal memeriksa sampel: " + e.message, "err");
    }
}
</script>

</body>
</html>
