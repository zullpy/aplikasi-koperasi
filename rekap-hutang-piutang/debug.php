<?php
// DEBUG ONLY - DELETE AFTER DONE
require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/auth.php';

function bersihH($str) {
    if ($str === null) return 0;
    $b = preg_replace('/[^0-9]/', '', $str);
    return $b === '' ? 0 : (float)$b;
}

// ========== METODE REKAP (id_barang lookup) ==========
$idBarangLookup = [];
$hargaSekarangLookup = [];
$resB = $koneksi->query("SELECT id_barang, nama_barang, harga_beli FROM barang");
while ($rb = $resB->fetch_assoc()) {
    $key = strtolower(trim($rb['nama_barang']));
    $idBarangLookup[$key] = $rb['id_barang'];
    $bersih = preg_replace('/[^0-9]/', '', $rb['harga_beli']);
    $hargaSekarangLookup[$rb['id_barang']] = $bersih === '' ? 0 : (float)$bersih;
}

$riwayatHargaMap = [];
$resR = $koneksi->query("SELECT id_barang, harga_beli, tanggal FROM riwayat_harga ORDER BY id_barang ASC, tanggal ASC");
while ($rr = $resR->fetch_assoc()) {
    $riwayatHargaMap[$rr['id_barang']][] = ['tanggal' => $rr['tanggal'], 'harga' => (float)$rr['harga_beli']];
}

function cariHargaRekap($idBarang, $tgl, $riwayatHargaMap, $hargaSekarangLookup) {
    if (empty($riwayatHargaMap[$idBarang])) return $hargaSekarangLookup[$idBarang] ?? 0;
    $ts = strtotime($tgl);
    $h = null;
    foreach ($riwayatHargaMap[$idBarang] as $r) {
        if (strtotime($r['tanggal']) <= $ts) { $h = $r['harga']; } else { break; }
    }
    return $h ?? $riwayatHargaMap[$idBarang][0]['harga'];
}

// ========== METODE PENJUALAN (nama_barang lookup) ==========
$riwayatByBarang = [];
$hargaFallback = [];
$resB2 = $koneksi->query("SELECT nama_barang, harga_beli FROM barang");
while ($rb = $resB2->fetch_assoc()) {
    $key = strtolower(trim($rb['nama_barang']));
    $hargaFallback[$key] = $rb['harga_beli'];
}
$resR2 = $koneksi->query("SELECT b.nama_barang, r.harga_beli, r.tanggal FROM riwayat_harga r INNER JOIN barang b ON b.id_barang = r.id_barang ORDER BY r.id_barang ASC, r.tanggal ASC, r.id_riwayat ASC");
while ($rr = $resR2->fetch_assoc()) {
    $key = strtolower(trim($rr['nama_barang']));
    $riwayatByBarang[$key][] = ['tanggal' => $rr['tanggal'], 'harga_beli' => (float)$rr['harga_beli']];
}

function cariHargaPenjualan($keyBarang, $tgl, $riwayatByBarang, $hargaFallback) {
    if (empty($riwayatByBarang[$keyBarang])) return bersihH($hargaFallback[$keyBarang] ?? null);
    $ts = strtotime($tgl);
    $h = null;
    foreach ($riwayatByBarang[$keyBarang] as $r) {
        if (strtotime($r['tanggal']) <= $ts) { $h = $r['harga_beli']; } else { break; }
    }
    return (float)($h ?? $riwayatByBarang[$keyBarang][0]['harga_beli']);
}

// ========== QUERY TRANSAKSI ==========
$resRaw = $koneksi2->query("
    SELECT pb.id_pengambilan, pb.no_pengambilan, pb.tanggal_pengambilan, pb.jam_pengambilan,
           pbd.nama_barang, pbd.qty, pbd.jenis
    FROM pengambilan_barang pb
    INNER JOIN pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
    WHERE pb.status = 'verified'
    ORDER BY pb.id_pengambilan ASC
");

$totalRekap = [];
$totalPenjualan = [];
while ($row = $resRaw->fetch_assoc()) {
    $id = $row['id_pengambilan'];
    $jenis = strtolower(trim($row['jenis'] ?? ''));
    if (!in_array($jenis, ['foodcost', 'addcost'], true)) continue;

    $ck = $id . '_' . $jenis;
    if (!isset($totalRekap[$ck])) {
        $totalRekap[$ck] = ['no' => $row['no_pengambilan'], 'tgl' => $row['tanggal_pengambilan'], 'jenis' => $jenis, 'total' => 0];
        $totalPenjualan[$ck] = ['no' => $row['no_pengambilan'], 'tgl' => $row['tanggal_pengambilan'], 'jenis' => $jenis, 'total' => 0];
    }

    $keyBarang = strtolower(trim($row['nama_barang']));
    $idBarang = $idBarangLookup[$keyBarang] ?? null;
    $tgl = $row['tanggal_pengambilan'];
    $tglJam = trim($tgl . ' ' . ($row['jam_pengambilan'] ?: '00:00:00'));

    // Metode rekap (via id_barang)
    $hargaRekap = $idBarang ? cariHargaRekap($idBarang, $tgl, $riwayatHargaMap, $hargaSekarangLookup) : 0;
    // Metode penjualan (via nama_barang)
    $hargaPenjualan = cariHargaPenjualan($keyBarang, $tglJam, $riwayatByBarang, $hargaFallback);

    $qty = (float)$row['qty'];
    $totalRekap[$ck]['total'] += $qty * $hargaRekap;
    $totalPenjualan[$ck]['total'] += $qty * $hargaPenjualan;
}

// ========== BAYAR ==========
$bayarByJenis = [];
$resBayar = $koneksi2->query("SELECT id_pengambilan, jenis, SUM(jumlah_dibayar) AS total_bayar FROM pembayaran WHERE jenis IN ('foodcost','addcost') GROUP BY id_pengambilan, jenis");
while ($rb = $resBayar->fetch_assoc()) {
    $bayarByJenis[$rb['id_pengambilan'] . '_' . $rb['jenis']] = (float)$rb['total_bayar'];
}

// ========== OUTPUT ==========
$grandSisaRekap = 0;
$grandSisaPenjualan = 0;
$beda = [];

echo "<pre style='font-size:12px'>";
echo str_pad("NO", 12) . str_pad("JENIS", 12) . str_pad("TOTAL_REKAP", 18) . str_pad("TOTAL_PENJUALAN", 18) . str_pad("BAYAR", 14) . str_pad("SISA_REKAP", 16) . str_pad("SISA_PENJUALAN", 16) . "DIFF\n";
echo str_repeat("-", 120) . "\n";

foreach ($totalRekap as $ck => $r) {
    $bayar = $bayarByJenis[$ck] ?? 0;
    $sisaR = max(0, $r['total'] - $bayar);
    $sisaP = max(0, $totalPenjualan[$ck]['total'] - $bayar);
    $grandSisaRekap += $sisaR;
    $grandSisaPenjualan += $sisaP;

    if (abs($sisaR - $sisaP) > 1) {
        $beda[] = compact('ck', 'r', 'bayar', 'sisaR', 'sisaP');
        echo str_pad($r['no'], 12) . str_pad($r['jenis'], 12)
            . str_pad(number_format($r['total'], 0, ',', '.'), 18)
            . str_pad(number_format($totalPenjualan[$ck]['total'], 0, ',', '.'), 18)
            . str_pad(number_format($bayar, 0, ',', '.'), 14)
            . str_pad(number_format($sisaR, 0, ',', '.'), 16)
            . str_pad(number_format($sisaP, 0, ',', '.'), 16)
            . number_format($r['total'] - $totalPenjualan[$ck]['total'], 0, ',', '.') . "\n";
    }
}

echo str_repeat("=", 120) . "\n";
echo "GRAND SISA METODE REKAP    : Rp " . number_format($grandSisaRekap, 0, ',', '.') . "\n";
echo "GRAND SISA METODE PENJUALAN: Rp " . number_format($grandSisaPenjualan, 0, ',', '.') . "\n";
echo "SELISIH                    : Rp " . number_format($grandSisaRekap - $grandSisaPenjualan, 0, ',', '.') . "\n";
echo str_repeat("=", 120) . "\n";
echo "Baris yang beda: " . count($beda) . "\n";

// Cek barang tidak ketemu id
echo "\n--- BARANG TIDAK KETEMU id_barang (rekap pakai harga 0) ---\n";
$resRaw2 = $koneksi2->query("SELECT DISTINCT pbd.nama_barang FROM pengambilan_barang pb INNER JOIN pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan WHERE pb.status='verified' AND pbd.jenis IN ('foodcost','addcost')");
while ($rr = $resRaw2->fetch_assoc()) {
    $k = strtolower(trim($rr['nama_barang']));
    if (!isset($idBarangLookup[$k])) {
        echo " - [{$rr['nama_barang']}] → tidak ada di idBarangLookup\n";
    }
}
echo "</pre>";
