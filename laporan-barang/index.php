<?php
require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/auth.php';
$activePage = 'laporan-barang';
include __DIR__ . '/../components/navbar.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

// 1. Hitung Total Pembelian Barang
$qPembelian = "SELECT SUM(harga * volume + biaya_admin) AS total FROM db_draft_barang.transaksi_pembelian";
$resPembelian = $koneksi->query($qPembelian);
$totalPembelian = $resPembelian ? (float)$resPembelian->fetch_assoc()['total'] : 0;

// 2. Hitung Total Penjualan SPPG (Foodcost + Addcost)
$qPenjualan = "
    SELECT 
        SUM(pbd.qty * COALESCE(CAST(REPLACE(REPLACE(REPLACE(b.harga_beli, 'Rp', ''), '.', ''), ' ', '') AS UNSIGNED), 0)) AS total
    FROM db_mbg.pengambilan_barang pb
    INNER JOIN db_mbg.pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
    LEFT JOIN db_draft_barang.barang b ON LOWER(TRIM(b.nama_barang)) = LOWER(TRIM(pbd.nama_barang))
    WHERE pb.status = 'verified'
";
$resPenjualan = $koneksi2->query($qPenjualan);
$totalPenjualan = $resPenjualan ? (float)$resPenjualan->fetch_assoc()['total'] : 0;

// 3. Ambil Rincian Aset Barang di Pusat & Transit
$qBarang = "SELECT id_barang, nama_barang, satuan, satuan_eceran, isi_per_satuan,
              harga_beli, harga_eceran, stok_akhir
              FROM db_draft_barang.barang 
              ORDER BY nama_barang ASC";
$resMaster = $koneksi->query($qBarang);

$items = [];
if ($resMaster) {
    while ($r = $resMaster->fetch_assoc()) {
        $id  = (int)$r['id_barang'];
        $isi = (isset($r['isi_per_satuan']) && (float)$r['isi_per_satuan'] > 0) ? (float)$r['isi_per_satuan'] : null;
        
        $hargaBeliClean = (float)preg_replace('/[^0-9]/', '', $r['harga_beli'] ?? '0');
        $hargaEceranRaw = (float)$r['harga_eceran'];
        
        if ($hargaEceranRaw > 0) {
            $hargaEceran = $hargaEceranRaw;
        } elseif ($isi) {
            $hargaEceran = $hargaBeliClean / $isi;
        } else {
            $hargaEceran = $hargaBeliClean;
        }
        
        $satuanGrosir  = trim($r['satuan'] ?? '') ?: '-';
        $satuanEceran  = trim($r['satuan_eceran'] ?? '') ?: $satuanGrosir;
        
        $stokGrosirPusat = (int)$r['stok_akhir'];
        $stokEceranPusat = $isi ? ($stokGrosirPusat * $isi) : $stokGrosirPusat;
        
        $items[$id] = [
            'id_barang'      => $id,
            'nama'           => $r['nama_barang'],
            'nama_key'       => strtolower(trim($r['nama_barang'])),
            'satuan'         => $satuanGrosir,
            'satuan_eceran'  => $satuanEceran,
            'isi_per_satuan' => $isi,
            'harga_beli'     => $hargaBeliClean,
            'harga_beli_raw' => $r['harga_beli'],
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
}

$gudangList = ['sodong', 'sariwangi', 'manonjaya'];

foreach ($gudangList as $gudang) {
    $sqlStok = "
        SELECT LOWER(TRIM(nama_barang)) AS nama_key,
               qty_grosir,
               qty_eceran
        FROM db_mbg.stok_barang
        WHERE lokasi = ?
    ";
    
    $stmt = $koneksi2->prepare($sqlStok);
    if ($stmt) {
        $stmt->bind_param('s', $gudang);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $stokMap = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $stokMap[$r['nama_key']] = [
                    'grosir' => (float)($r['qty_grosir'] ?? 0),
                    'eceran' => (float)($r['qty_eceran'] ?? 0)
                ];
            }
        }
        $stmt->close();
        
        foreach ($items as $id => &$it) {
            $key = $it['nama_key'];
            if (isset($stokMap[$key])) {
                $it[$gudang]['stok_grosir'] = $stokMap[$key]['grosir'];
                $it[$gudang]['stok_eceran'] = $stokMap[$key]['eceran'];
            }
        }
        unset($it);
    }
}

$totalNilaiBarang = 0;
$dataBarang = [];
foreach ($items as $it) {
    $stokTransitGrosir = $it['sodong']['stok_grosir'] + $it['sariwangi']['stok_grosir'] + $it['manonjaya']['stok_grosir'];
    $stokTransitEceran = $it['sodong']['stok_eceran'] + $it['sariwangi']['stok_eceran'] + $it['manonjaya']['stok_eceran'];
    
    $totalQtyEceran = $it['pusat']['stok_eceran'] + $stokTransitEceran;
    $nilaiAset = $totalQtyEceran * $it['harga_eceran'];
    $totalNilaiBarang += $nilaiAset;
    
    $it['stok_transit_grosir'] = $stokTransitGrosir;
    $it['stok_transit_eceran'] = $stokTransitEceran;
    $it['total_qty_eceran'] = $totalQtyEceran;
    $it['nilai_aset'] = $nilaiAset;
    
    $dataBarang[] = $it;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Barang</title>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h1><i class="ph ph-package"></i> Laporan Barang</h1>
    <div class="subtitle">Laporan ringkasan total nilai aset barang, total pembelian, dan total penjualan SPPG</div>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <div class="summary-card info">
            <div class="card-icon">
                <i class="ph ph-shopping-cart"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Pembelian Barang</span>
                <span class="value">Rp <?= number_format($totalPembelian, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="summary-card success">
            <div class="card-icon">
                <i class="ph ph-currency-dollar"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Penjualan SPPG</span>
                <span class="value">Rp <?= number_format($totalPenjualan, 0, ',', '.'); ?></span>
            </div>
        </div>
        <div class="summary-card primary">
            <div class="card-icon">
                <i class="ph ph-archive"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Nilai Barang (Aset Gudang)</span>
                <span class="value">Rp <?= number_format($totalNilaiBarang, 0, ',', '.'); ?></span>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../components/made-by.php'; ?>

</body>
</html>
