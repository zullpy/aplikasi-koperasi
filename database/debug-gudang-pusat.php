<?php
/**
 * File Debug Nilai Barang Gudang Pusat
 * Path: database/debug-gudang-pusat.php
 * 
 * Digunakan untuk memeriksa rincian stok, konversi satuan, harga beli, harga eceran,
 * dan kalkulasi nilai aset barang khusus di Gudang Pusat.
 */

// Gunakan koneksi database jika ada, atau include koneksi.php
$koneksiFile = __DIR__ . '/koneksi.php';
if (file_exists($koneksiFile)) {
    require_once $koneksiFile;
} else {
    header('Content-Type: text/plain');
    die("Error: File koneksi.php tidak ditemukan di " . $koneksiFile);
}

// Mode JSON jika diminta via URL parameter ?format=json
$format = $_GET['format'] ?? 'html';

// ----------------------------------------------------
// 1. QUERY MASTER BARANG & STOK GUDANG PUSAT
// ----------------------------------------------------
$sql = "SELECT id_barang, nama_barang, satuan, satuan_eceran, isi_per_satuan, 
               harga_beli, harga_eceran, stok_akhir 
        FROM barang 
        ORDER BY nama_barang ASC";

$res = $koneksi->query($sql);
if (!$res) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $koneksi->error]);
    } else {
        echo "<h2 style='color:red;'>Query Error: " . htmlspecialchars($koneksi->error) . "</h2>";
    }
    exit;
}

$items = [];
$totalItems = 0;
$totalStokGrosirPusat = 0;
$totalStokEceranPusat = 0;
$totalNilaiBeliPusat = 0;
$totalNilaiEceranPusat = 0;
$warningList = [];

while ($r = $res->fetch_assoc()) {
    $id = (int)$r['id_barang'];
    $nama = $r['nama_barang'];
    $satuanGrosir = trim($r['satuan'] ?? '') ?: '-';
    $satuanEceran = trim($r['satuan_eceran'] ?? '') ?: $satuanGrosir;
    $isi = (isset($r['isi_per_satuan']) && (float)$r['isi_per_satuan'] > 0) ? (float)$r['isi_per_satuan'] : 1;
    
    // Parsing Harga Beli (bersihkan karakter non-digit jika disimpan sebagai string formatted)
    $hargaBeliRaw = $r['harga_beli'] ?? '0';
    if (is_numeric($hargaBeliRaw)) {
        $hargaBeliClean = (float)$hargaBeliRaw;
    } else {
        $hargaBeliClean = (float)preg_replace('/[^0-9]/', '', $hargaBeliRaw);
    }

    // Parsing Harga Eceran (per-pcs)
    $hargaEceranRaw = (float)($r['harga_eceran'] ?? 0);
    if ($hargaEceranRaw > 0 && ($hargaEceranRaw != $hargaBeliClean || $isi <= 1)) {
        $hargaEceranFinal = $hargaEceranRaw;
    } elseif ($isi > 1) {
        $hargaBase = ($hargaEceranRaw > 0) ? $hargaEceranRaw : $hargaBeliClean;
        $hargaEceranFinal = $hargaBase / $isi;
    } else {
        $hargaEceranFinal = $hargaBeliClean;
    }

    // Stok Gudang Pusat dari tabel `barang.stok_akhir`
    $stokGrosirPusat = (float)($r['stok_akhir'] ?? 0);
    $stokEceranPusat = $stokGrosirPusat * $isi;

    // Calculasi Nilai Barang Gudang Pusat
    $nilaiBeliPusat = $stokGrosirPusat * $hargaBeliClean;
    $nilaiEceranPusat = $stokEceranPusat * $hargaEceranFinal;

    // Catat warning jika ada anomali data
    $warnings = [];
    if ($stokGrosirPusat < 0) {
        $warnings[] = "Stok Pusat Minus (" . $stokGrosirPusat . ")";
    }
    if ($hargaBeliClean <= 0) {
        $warnings[] = "Harga Beli 0/Kosong";
    }
    if ($hargaEceranFinal <= 0) {
        $warnings[] = "Harga Eceran 0/Kosong";
    }
    if ($r['isi_per_satuan'] === null || (float)$r['isi_per_satuan'] <= 0) {
        $warnings[] = "Isi per satuan belum diisi/0 (default 1)";
    }

    $itemData = [
        'id_barang'            => $id,
        'nama_barang'          => $nama,
        'satuan_grosir'        => $satuanGrosir,
        'satuan_eceran'        => $satuanEceran,
        'isi_per_satuan'       => $isi,
        'stok_grosir_pusat'    => $stokGrosirPusat,
        'stok_eceran_pusat'    => $stokEceranPusat,
        'harga_beli_raw'       => $hargaBeliRaw,
        'harga_beli_clean'     => $hargaBeliClean,
        'harga_eceran_raw'     => $hargaEceranRaw,
        'harga_eceran_final'   => $hargaEceranFinal,
        'nilai_beli_pusat'     => $nilaiBeliPusat,
        'nilai_eceran_pusat'   => $nilaiEceranPusat,
        'warnings'             => $warnings
    ];

    $items[] = $itemData;

    $totalItems++;
    $totalStokGrosirPusat += $stokGrosirPusat;
    $totalStokEceranPusat += $stokEceranPusat;
    $totalNilaiBeliPusat += $nilaiBeliPusat;
    $totalNilaiEceranPusat += $nilaiEceranPusat;

    if (!empty($warnings)) {
        $warningList[] = [
            'id' => $id,
            'nama' => $nama,
            'issues' => implode(', ', $warnings)
        ];
    }
}

// ----------------------------------------------------
// OUTPUT FORMAT JSON
// ----------------------------------------------------
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total_items'              => $totalItems,
            'total_stok_grosir_pusat'  => $totalStokGrosirPusat,
            'total_stok_eceran_pusat'  => $totalStokEceranPusat,
            'total_nilai_beli_pusat'   => $totalNilaiBeliPusat,
            'total_nilai_eceran_pusat' => $totalNilaiEceranPusat,
            'anomali_count'            => count($warningList)
        ],
        'anomali' => $warningList,
        'items'   => $items
    ], JSON_PRETTY_PRINT);
    exit;
}

// ----------------------------------------------------
// OUTPUT FORMAT HTML (UI Modern & Clean)
// ----------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Nilai Barang Gudang Pusat</title>
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-blue: #38bdf8;
            --accent-green: #4ade80;
            --accent-amber: #fbbf24;
            --accent-red: #f87171;
            --border-color: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            padding: 24px;
            font-size: 14px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .header h1 {
            font-size: 24px;
            color: var(--accent-blue);
        }

        .header p {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .btn-json {
            background: var(--bg-hover);
            color: var(--accent-blue);
            border: 1px solid var(--accent-blue);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-json:hover {
            background: var(--accent-blue);
            color: #0f172a;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            background: var(--bg-card);
            padding: 18px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .card .card-title {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .card .card-value {
            font-size: 20px;
            font-weight: bold;
        }

        .card.blue .card-value { color: var(--accent-blue); }
        .card.green .card-value { color: var(--accent-green); }
        .card.amber .card-value { color: var(--accent-amber); }
        .card.red .card-value { color: var(--accent-red); }

        .table-container {
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th, td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        th {
            background: rgba(15, 23, 42, 0.6);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: var(--bg-hover);
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .badge-warning {
            background: rgba(248, 113, 113, 0.2);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin: 2px;
        }

        .badge-ok {
            background: rgba(74, 222, 128, 0.15);
            color: var(--accent-green);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .alert-box {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid var(--accent-amber);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .alert-box h3 {
            color: var(--accent-amber);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .alert-box ul {
            margin-left: 20px;
            color: var(--text-muted);
        }

        .formula-info {
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
            color: var(--text-main);
        }
        .formula-info code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--accent-blue);
        }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h1>Debug Nilai Barang Gudang Pusat</h1>
            <p>Database: <code>u673037475_db_barang</code> | Tabel: <code>barang</code></p>
        </div>
        <div>
            <a href="?format=json" target="_blank" class="btn-json">View JSON Output</a>
        </div>
    </div>

    <!-- Ringkasan Kunci -->
    <div class="cards-grid">
        <div class="card blue">
            <div class="card-title">Total Jenis Barang</div>
            <div class="card-value"><?= number_format($totalItems, 0, ',', '.') ?> Item</div>
        </div>
        <div class="card blue">
            <div class="card-title">Stok Grosir (Pusat)</div>
            <div class="card-value"><?= number_format($totalStokGrosirPusat, 0, ',', '.') ?></div>
        </div>
        <div class="card blue">
            <div class="card-title">Stok Eceran (Pusat)</div>
            <div class="card-value"><?= number_format($totalStokEceranPusat, 0, ',', '.') ?></div>
        </div>
        <div class="card green">
            <div class="card-title">Nilai Barang (Harga Beli)</div>
            <div class="card-value">Rp <?= number_format($totalNilaiBeliPusat, 0, ',', '.') ?></div>
        </div>
        <div class="card amber">
            <div class="card-title">Nilai Aset (Harga Eceran)</div>
            <div class="card-value">Rp <?= number_format($totalNilaiEceranPusat, 0, ',', '.') ?></div>
        </div>
        <div class="card <?= count($warningList) > 0 ? 'red' : 'green' ?>">
            <div class="card-title">Item Dengan Anomali</div>
            <div class="card-value"><?= count($warningList) ?> Item</div>
        </div>
    </div>

    <!-- Info Rumus -->
    <div class="formula-info">
        <strong>Penjelasan Kalkulasi Stok & Nilai Gudang Pusat:</strong>
        <ul style="margin-top: 6px; margin-left: 20px;">
            <li><code>Stok Grosir Pusat</code> = <code>barang.stok_akhir</code></li>
            <li><code>Stok Eceran Pusat</code> = <code>stok_akhir × (isi_per_satuan > 0 ? isi_per_satuan : 1)</code></li>
            <li><code>Nilai Beli Pusat</code> = <code>Stok Grosir Pusat × Harga Beli</code></li>
            <li><code>Nilai Aset (Eceran)</code> = <code>Stok Eceran Pusat × Harga Eceran</code></li>
        </ul>
    </div>

    <?php if (count($warningList) > 0): ?>
    <div class="alert-box">
        <h3>Perhatian: Ditemukan <?= count($warningList) ?> Item dengan Potensi Masalah Data</h3>
        <ul>
            <?php foreach ($warningList as $w): ?>
                <li><strong>[ID <?= $w['id'] ?>] <?= htmlspecialchars($w['nama']) ?>:</strong> <?= htmlspecialchars($w['issues']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tabel Rincian Data Debug -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th class="text-center">ID</th>
                    <th>Nama Barang</th>
                    <th class="text-right">Stok Grosir</th>
                    <th class="text-center">Satuan</th>
                    <th class="text-center">Isi/Pck</th>
                    <th class="text-right">Stok Eceran</th>
                    <th class="text-center">Sat. Eceran</th>
                    <th class="text-right">Harga Beli</th>
                    <th class="text-right">Harga Eceran</th>
                    <th class="text-right">Nilai (Harga Beli)</th>
                    <th class="text-right">Nilai Aset (Eceran)</th>
                    <th class="text-center">Status / Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td class="text-center"><code><?= $it['id_barang'] ?></code></td>
                    <td><strong><?= htmlspecialchars($it['nama_barang']) ?></strong></td>
                    <td class="text-right" style="font-weight:600; color: <?= $it['stok_grosir_pusat'] < 0 ? 'var(--accent-red)' : 'var(--text-main)' ?>;">
                        <?= number_format($it['stok_grosir_pusat'], 0, ',', '.') ?>
                    </td>
                    <td class="text-center"><code><?= htmlspecialchars($it['satuan_grosir']) ?></code></td>
                    <td class="text-center"><?= number_format($it['isi_per_satuan'], 0, ',', '.') ?></td>
                    <td class="text-right" style="font-weight:600;"><?= number_format($it['stok_eceran_pusat'], 0, ',', '.') ?></td>
                    <td class="text-center"><code><?= htmlspecialchars($it['satuan_eceran']) ?></code></td>
                    <td class="text-right">Rp <?= number_format($it['harga_beli_clean'], 0, ',', '.') ?></td>
                    <td class="text-right">Rp <?= number_format($it['harga_eceran_final'], 0, ',', '.') ?></td>
                    <td class="text-right" style="color:var(--accent-green);">Rp <?= number_format($it['nilai_beli_pusat'], 0, ',', '.') ?></td>
                    <td class="text-right" style="color:var(--accent-amber);">Rp <?= number_format($it['nilai_eceran_pusat'], 0, ',', '.') ?></td>
                    <td class="text-center">
                        <?php if (empty($it['warnings'])): ?>
                            <span class="badge-ok">OK</span>
                        <?php else: ?>
                            <?php foreach ($it['warnings'] as $warn): ?>
                                <span class="badge-warning"><?= htmlspecialchars($warn) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: rgba(15, 23, 42, 0.9); font-weight: bold;">
                    <td colspan="2" class="text-center">TOTAL KESELURUHAN</td>
                    <td class="text-right" style="color: var(--accent-blue);"><?= number_format($totalStokGrosirPusat, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                    <td class="text-right" style="color: var(--accent-blue);"><?= number_format($totalStokEceranPusat, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                    <td></td>
                    <td class="text-right" style="color: var(--accent-green); font-size: 15px;">Rp <?= number_format($totalNilaiBeliPusat, 0, ',', '.') ?></td>
                    <td class="text-right" style="color: var(--accent-amber); font-size: 15px;">Rp <?= number_format($totalNilaiEceranPusat, 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

</body>
</html>
