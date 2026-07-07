<?php
include 'koneksi.php';
header('Content-Type: application/json');

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
              ORDER BY stok_akhir DESC";
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
    
    // Tentukan harga eceran final
    if ($hargaEceranRaw > 0) {
        $hargaEceran = $hargaEceranRaw;
    } elseif ($isi) {
        $hargaEceran = $hargaBeli / $isi;
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

// ===== 2. STOK PER GUDANG CABANG (dari stok_barang) =====
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
            $it[$gudang]['stok_grosir'] = $stokMap[$key]['grosir'];
            $it[$gudang]['stok_eceran'] = $stokMap[$key]['eceran'];
        }
    }
    unset($it);
}

// ===== 3. HITUNG TOTAL & NILAI (ECERAN) =====
$rows = [];
foreach ($items as $it) {
    $totalQtyEceran = $it['pusat']['stok_eceran']
                    + $it['sodong']['stok_eceran']
                    + $it['sariwangi']['stok_eceran']
                    + $it['manonjaya']['stok_eceran'];
    
    $totalNilaiEceran = $totalQtyEceran * $it['harga_eceran'];
    
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
        'total_qty_eceran'   => $totalQtyEceran,
        'total_nilai_eceran' => $totalNilaiEceran,
    ];
}

echo json_encode(['status' => 'success', 'data' => $rows]);