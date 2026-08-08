<?php
include 'koneksi.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/*
* LOGIKA BARU:
* - GUDANG PUSAT     : barang.stok_akhir (dalam satuan grosir)
* - GUDANG CABANG    : stok_barang (qty_grosir & qty_eceran langsung)
* - NILAI BARANG     : total_qty_eceran × harga_eceran
* - MATCHING         : LOWER(TRIM(nama_barang))
*/

// ===== 1. MASTER BARANG + HARGA + STOK PUSAT =====
$sqlMaster = "SELECT id_barang, nama_barang, satuan, satuan_eceran, isi_per_satuan,
              harga_beli, harga_eceran, stok_akhir
              FROM barang 
              ORDER BY nama_barang ASC";
$resMaster = mysqli_query($koneksi, $sqlMaster);
if (!$resMaster) {
    echo json_encode(['status' => 'error', 'message' => 'Master: ' . mysqli_error($koneksi)]);
    exit;
}

$items = [];
while ($r = mysqli_fetch_assoc($resMaster)) {
    $id  = (int)$r['id_barang'];
    $isi = (isset($r['isi_per_satuan']) && (float)$r['isi_per_satuan'] > 0) ? (float)$r['isi_per_satuan'] : null;
    $hargaBeli      = (float)($r['harga_beli'] ?? 0);
    $hargaEceranRaw = (float)($r['harga_eceran'] ?? 0);
    
    // Tentukan harga eceran final (per-pcs)
    if ($hargaEceranRaw > 0 && ($hargaEceranRaw != $hargaBeli || !$isi || $isi <= 1)) {
        $hargaEceran = $hargaEceranRaw;
    } elseif ($isi && $isi > 1) {
        $hargaBase = ($hargaEceranRaw > 0) ? $hargaEceranRaw : $hargaBeli;
        $hargaEceran = $hargaBase / $isi;
    } else {
        $hargaEceran = $hargaBeli;
    }
    
    $satuanGrosir  = trim($r['satuan'] ?? '') ?: '-';
    $satuanEceran  = trim($r['satuan_eceran'] ?? '') ?: $satuanGrosir;
    
    // Stok pusat (dalam satuan grosir)
    $stokGrosirPusat = (int)$r['stok_akhir'];
    // Konversi ke eceran untuk nilai barang
    $stokEceranPusat = $isi ? ($stokGrosirPusat * $isi) : $stokGrosirPusat;
    
    $items[$id] = [
        'id_barang'      => $id,
        'nama'           => $r['nama_barang'],
        'nama_key'       => strtolower(trim($r['nama_barang'])),
        'satuan'         => $satuanGrosir,
        'satuan_eceran'  => $satuanEceran,
        'isi_per_satuan' => $isi,
        'harga_beli'     => $hargaBeli,
        'harga_eceran'   => $hargaEceran,
        'pusat'          => [
            'stok_grosir'  => $stokGrosirPusat,
            'stok_eceran'  => $stokEceranPusat
        ],
        'sodong'         => ['stok_grosir' => 0, 'stok_eceran' => 0],
        'sariwangi'      => ['stok_grosir' => 0, 'stok_eceran' => 0],
        'manonjaya'      => ['stok_grosir' => 0, 'stok_eceran' => 0],
    ];
}

// ===== 2. HITUNG TOTAL PEMBELIAN (transaksi_pembelian) =====
$purchasesMap = [];
$resP = mysqli_query($koneksi, "SELECT LOWER(TRIM(nama_barang)) AS nama_key, volume, satuan FROM transaksi_pembelian");
if ($resP) {
    while ($p = mysqli_fetch_assoc($resP)) {
        $key = $p['nama_key'];
        $vol = (float)($p['volume'] ?? 0);
        $sat = strtolower(trim($p['satuan'] ?? ''));
        
        if (!isset($purchasesMap[$key])) $purchasesMap[$key] = 0;
        
        $isi = null;
        $satEceran = null;
        foreach ($items as $it) {
            if ($it['nama_key'] === $key) {
                $isi = $it['isi_per_satuan'];
                $satEceran = strtolower(trim($it['satuan_eceran']));
                break;
            }
        }
        
        if ($isi && $sat !== $satEceran) {
            $purchasesMap[$key] += ($vol * $isi);
        } else {
            $purchasesMap[$key] += $vol;
        }
    }
}

// ===== 3. HITUNG TOTAL PENGAMBILAN (pengambilan_barang_detail) =====
$takingsMap = [];
$resT = mysqli_query($koneksi2, "
    SELECT LOWER(TRIM(pbd.nama_barang)) AS nama_key, pbd.qty, pbd.satuan
    FROM pengambilan_barang_detail pbd
    JOIN pengambilan_barang pb ON pb.id_pengambilan = pbd.id_pengambilan
    WHERE pb.status = 'verified'
");
if ($resT) {
    while ($t = mysqli_fetch_assoc($resT)) {
        $key = $t['nama_key'];
        $qty = (float)($t['qty'] ?? 0);
        $sat = strtolower(trim($t['satuan'] ?? ''));
        
        if (!isset($takingsMap[$key])) $takingsMap[$key] = 0;
        
        $isi = null;
        $satEceran = null;
        foreach ($items as $it) {
            if ($it['nama_key'] === $key) {
                $isi = $it['isi_per_satuan'];
                $satEceran = strtolower(trim($it['satuan_eceran']));
                break;
            }
        }
        
        if ($isi && $sat !== $satEceran) {
            $takingsMap[$key] += ($qty * $isi);
        } else {
            $takingsMap[$key] += $qty;
        }
    }
}

// ===== 4. STOK PER GUDANG CABANG (dari stok_barang) =====
$gudangList = ['sodong', 'sariwangi', 'manonjaya'];

foreach ($gudangList as $gudang) {
    $sqlStok = "
        SELECT LOWER(TRIM(nama_barang)) AS nama_key,
               qty_grosir,
               qty_eceran
        FROM stok_barang
        WHERE lokasi = ?
    ";
    
    $stmt = mysqli_prepare($koneksi2, $sqlStok);
    mysqli_stmt_bind_param($stmt, 's', $gudang);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $stokMap = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $stokMap[$r['nama_key']] = [
                'grosir' => (float)($r['qty_grosir'] ?? 0),
                'eceran' => (float)($r['qty_eceran'] ?? 0)
            ];
        }
    }
    mysqli_stmt_close($stmt);
    
    // Match ke master
    foreach ($items as $id => &$it) {
        $key = $it['nama_key'];
        if (isset($stokMap[$key])) {
            $grosir = $stokMap[$key]['grosir'];
            $eceran = $stokMap[$key]['eceran'];
            $isi    = $it['isi_per_satuan'];

            $it[$gudang]['stok_grosir'] = $grosir;
            if (!$isi) {
                $it[$gudang]['stok_eceran'] = $grosir;
            } else {
                $it[$gudang]['stok_eceran'] = ($eceran > 0) ? $eceran : ($grosir * $isi);
            }
        }
    }
    unset($it);
}

// ===== 5. HITUNG TOTAL BARANG, PENGAMBILAN & SISA =====
$rows = [];
foreach ($items as $it) {
    $key = $it['nama_key'];
    
    $totalSisaEceran = $it['pusat']['stok_eceran']
                     + $it['sodong']['stok_eceran']
                     + $it['sariwangi']['stok_eceran']
                     + $it['manonjaya']['stok_eceran'];
    
    $totalPengambilanEceran = $takingsMap[$key] ?? 0;
    $totalPembelianEceran   = $purchasesMap[$key] ?? 0;
    
    // Total barang dibeli (dalam eceran), minimal sisa + pengambilan
    $totalBarangEceran = max($totalPembelianEceran, $totalSisaEceran + $totalPengambilanEceran);
    
    $totalNilaiEceran = $totalSisaEceran * $it['harga_eceran'];
    
    $rows[] = [
        'id_barang'          => $it['id_barang'],
        'nama'               => $it['nama'],
        'satuan'             => $it['satuan'],
        'satuan_eceran'      => $it['satuan_eceran'],
        'isi_per_satuan'     => $it['isi_per_satuan'],
        'harga_beli'         => $it['harga_beli'],
        'harga_eceran'       => $it['harga_eceran'],
        'pusat'              => $it['pusat'],
        'sodong'             => $it['sodong'],
        'sariwangi'          => $it['sariwangi'],
        'manonjaya'          => $it['manonjaya'],
        'total_barang'       => $totalBarangEceran,
        'total_pengambilan'  => $totalPengambilanEceran,
        'total_qty_eceran'   => $totalSisaEceran,
        'total_nilai_eceran' => $totalNilaiEceran,
    ];
}

echo json_encode(['status' => 'success', 'data' => $rows]);