<?php
require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/auth.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

$activePage = 'rekap-hutang-piutang';
include __DIR__ . '/../components/navbar.php';
// ----------------------------------------------------------
// 1. DATA HUTANG (Pembelian yang belum lunas)
// Semua tabel ada di 1 database yang sama (pakai $koneksi),
// jadi JOIN langsung aman, tidak perlu prefix database.
// ----------------------------------------------------------
$queryHutang = "
    SELECT 
        pp.id_pembayaran,
        pp.kode_transaksi,
        pp.tanggal_transaksi,
        s.nama_supplier AS nama_toko,
        pp.total_tagihan,
        pp.jumlah_dibayar,
        (pp.total_tagihan - pp.jumlah_dibayar) AS sisa_pembayaran,
        (SELECT tp.nota FROM transaksi_pembelian tp WHERE tp.kode_transaksi COLLATE utf8mb4_unicode_ci = pp.kode_transaksi COLLATE utf8mb4_unicode_ci LIMIT 1) AS nota
    FROM pembayaran_pembelian pp
    INNER JOIN suplier s ON s.id_supplier COLLATE utf8mb4_unicode_ci = pp.id_supplier COLLATE utf8mb4_unicode_ci
    WHERE pp.status_pembayaran != 'lunas' AND (pp.total_tagihan - pp.jumlah_dibayar) > 0
    ORDER BY pp.tanggal_transaksi DESC
";
$resultHutang = $koneksi->query($queryHutang);

$totalHutang = 0;
$dataHutang = [];
if ($resultHutang) {
    while ($row = $resultHutang->fetch_assoc()) {
        $totalHutang += $row['sisa_pembayaran'];
        $dataHutang[] = $row;
    }
}

// ----------------------------------------------------------
// FUNGSI BANTU PERHITUNGAN HARGA
// ----------------------------------------------------------
function bersihkanHarga($str)
{
    if ($str === null) return 0;
    $bersih = preg_replace('/[^0-9]/', '', $str);
    return $bersih === '' ? 0 : (float) $bersih;
}

function cariHargaBerlaku($riwayatList, $tglTransaksi)
{
    $hargaTerpilih = null;

    foreach ($riwayatList as $r) {
        if ($r['tanggal'] <= $tglTransaksi) {
            $hargaTerpilih = $r['harga_beli'];
        } else {
            break;
        }
    }

    if ($hargaTerpilih === null && !empty($riwayatList)) {
        $hargaTerpilih = $riwayatList[0]['harga_beli'];
    }

    return $hargaTerpilih;
}

// ----------------------------------------------------------
// 2. DATA PIUTANG (Penjualan SPPG yang belum lunas)
// Mengikuti logika perhitungan presisi dari Penjualan SPPG
// Foodcost dan Addcost (termasuk satuan eceran vs grosir & riwayat harga).
// ----------------------------------------------------------

// 2a. Ambil info barang, satuan grosir/eceran, dan harga dari db_barang (pakai $koneksi)
$barangMap = [];
$resBarang = $koneksi->query("SELECT nama_barang, satuan, satuan_eceran, isi_per_satuan, harga_beli, harga_eceran FROM barang");
if ($resBarang) {
    while ($rb = $resBarang->fetch_assoc()) {
        $key = strtolower(trim($rb['nama_barang']));
        $hargaGrosir    = bersihkanHarga($rb['harga_beli'] ?? 0);
        $hargaEceranRaw = bersihkanHarga($rb['harga_eceran'] ?? 0);
        $isiRaw         = ((float)($rb['isi_per_satuan'] ?? 0) > 0) ? (float)$rb['isi_per_satuan'] : 0;
        $satGrosir      = strtolower(trim($rb['satuan'] ?? ''));
        $satEceran      = strtolower(trim($rb['satuan_eceran'] ?? ''));

        if ($satEceran !== '' && $hargaEceranRaw > 0) {
            $hargaEceran = $hargaEceranRaw;
        } elseif ($satEceran !== '' && $isiRaw > 0 && $hargaGrosir > 0) {
            $hargaEceran = $hargaGrosir / $isiRaw;
        } else {
            $hargaEceran = $hargaGrosir;
        }

        $barangMap[$key] = [
            'satuan_grosir'  => $satGrosir,
            'satuan_eceran'  => $satEceran,
            'isi_per_satuan' => $isiRaw,
            'harga_grosir'   => $hargaGrosir,
            'harga_eceran'   => $hargaEceran,
        ];
    }
}

// 2b. Ambil riwayat harga (gabungan riwayat_harga & transaksi_pembelian) dari db_barang
$riwayatByBarang = [];
$sqlRiwayat = "
    SELECT nama_barang, harga_beli, tanggal FROM (
        SELECT b.nama_barang, r.harga_beli, r.tanggal
        FROM riwayat_harga r
        INNER JOIN barang b ON b.id_barang = r.id_barang
        UNION
        SELECT tp.nama_barang, tp.harga AS harga_beli, CONCAT(tp.tanggal_pembelian, ' 00:00:00') AS tanggal
        FROM transaksi_pembelian tp
        WHERE tp.harga > 0 AND tp.tanggal_pembelian IS NOT NULL
    ) AS combined
    ORDER BY nama_barang ASC, tanggal ASC
";
$resRiwayat = $koneksi->query($sqlRiwayat);
if ($resRiwayat) {
    while ($rr = $resRiwayat->fetch_assoc()) {
        $key = strtolower(trim($rr['nama_barang']));
        $riwayatByBarang[$key][] = [
            'tanggal'    => $rr['tanggal'],
            'harga_beli' => (float) $rr['harga_beli'],
        ];
    }
}

// 2c. Ambil header + detail pengambilan dari db_mbg (pakai $koneksi2)
$queryPiutangRaw = "
    SELECT 
        pb.id_pengambilan,
        pb.no_pengambilan,
        pb.tanggal_pengambilan,
        pb.jam_pengambilan,
        pb.nama_sppg,
        pbd.nama_barang,
        pbd.satuan,
        pbd.qty, 
        pbd.jenis
    FROM pengambilan_barang pb
    INNER JOIN pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
    WHERE pb.status = 'verified'
";
$resultPiutangRaw = $koneksi2->query($queryPiutangRaw);

$piutangRaw = [];
if ($resultPiutangRaw) {
    while ($row = $resultPiutangRaw->fetch_assoc()) {
        $id = $row['id_pengambilan'];

        if (!isset($piutangRaw[$id])) {
            $piutangRaw[$id] = [
                'id_pengambilan'      => $id,
                'no_pengambilan'      => $row['no_pengambilan'],
                'tanggal_pengambilan' => $row['tanggal_pengambilan'],
                'nama_pelanggan'      => $row['nama_sppg'],
                'total_tagihan'       => 0,
                'jenis_list'          => [],
            ];
        }

        // Catat jenis item ini (foodcost/addcost) untuk tombol cetak faktur
        $jenisRow = strtolower(trim($row['jenis'] ?? 'foodcost'));
        if ($jenisRow !== '' && !in_array($jenisRow, $piutangRaw[$id]['jenis_list'], true)) {
            $piutangRaw[$id]['jenis_list'][] = $jenisRow;
        }

        $keyBarang    = strtolower(trim($row['nama_barang']));
        $satuanInput  = strtolower(trim($row['satuan'] ?? ''));
        $tglTransaksi = trim($row['tanggal_pengambilan'] . ' ' . (!empty($row['jam_pengambilan']) && $row['jam_pengambilan'] !== '00:00:00' ? $row['jam_pengambilan'] : '23:59:59'));

        $b = $barangMap[$keyBarang] ?? null;

        $isEceran = false;
        if ($b) {
            $satEceranNorm = $b['satuan_eceran'];
            $satGrosirNorm = $b['satuan_grosir'];
            if ($satEceranNorm !== '' && isSatuanEceranMatch($satuanInput, $satEceranNorm, $satGrosirNorm)) {
                $isEceran = true;
            }
        }

        // Logika harga berlaku sesuai tanggal transaksi (berlaku untuk foodcost & addcost)
        if (!empty($riwayatByBarang[$keyBarang])) {
            $hargaGrosirBerlaku = cariHargaBerlaku($riwayatByBarang[$keyBarang], $tglTransaksi);
        } else {
            $hargaGrosirBerlaku = $b ? $b['harga_grosir'] : 0;
        }

        if ($isEceran && $b) {
            if ($b['harga_grosir'] > 0 && $b['harga_eceran'] > 0) {
                $ratio = $b['harga_eceran'] / $b['harga_grosir'];
                $hargaTerpakai = $hargaGrosirBerlaku * $ratio;
            } elseif ($b['isi_per_satuan'] > 0) {
                $hargaTerpakai = $hargaGrosirBerlaku / $b['isi_per_satuan'];
            } else {
                $hargaTerpakai = $hargaGrosirBerlaku;
            }
        } else {
            $hargaTerpakai = $hargaGrosirBerlaku;
        }

        $qty      = (float) $row['qty'];
        $subtotal = $hargaTerpakai * $qty;

        $piutangRaw[$id]['total_tagihan'] += $subtotal;
    }
}

// 2d. Ambil total pembayaran per id_pengambilan dari db_mbg (pakai $koneksi2)
$bayarMap = [];
$resBayar = $koneksi2->query("SELECT id_pengambilan, SUM(jumlah_dibayar) AS total_bayar FROM pembayaran GROUP BY id_pengambilan");
if ($resBayar) {
    while ($rb = $resBayar->fetch_assoc()) {
        $bayarMap[$rb['id_pengambilan']] = (float) $rb['total_bayar'];
    }
}

// 2e. Ambil file faktur per tanggal dari db_mbg
$fakturMap = [];
$resFaktur = $koneksi2->query("SELECT tanggal, file_faktur FROM faktur_ttd");
if ($resFaktur) {
    while ($rf = $resFaktur->fetch_assoc()) {
        $fakturMap[$rf['tanggal']] = $rf['file_faktur'];
    }
}

// 2f. Gabungkan semuanya jadi $dataPiutang (1 baris per id_pengambilan).
$dataPiutang = [];
$totalPiutang = 0;
foreach ($piutangRaw as $p) {
    $id = $p['id_pengambilan'];
    $uangMasuk = $bayarMap[$id] ?? 0;
    $sisa = max($p['total_tagihan'] - $uangMasuk, 0);

    if ($sisa > 0) {
        $p['uang_masuk']      = $uangMasuk;
        $p['sisa_pembayaran'] = $sisa;
        $p['file_faktur']     = $fakturMap[$p['tanggal_pengambilan']] ?? null;
        $dataPiutang[] = $p;
        $totalPiutang += $sisa;
    }
}
// Urutkan terbaru dulu (menggantikan ORDER BY di query asli)
usort($dataPiutang, fn($a, $b) => strcmp($b['tanggal_pengambilan'], $a['tanggal_pengambilan']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Hutang Piutang</title>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h1><i class="ph ph-handshake"></i> Rekap Hutang Piutang</h1>
    <div class="subtitle">Laporan ringkasan saldo hutang pembelian dan piutang penjualan SPPG</div>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <div class="summary-card danger">
            <div class="card-icon">
                <i class="ph ph-arrow-circle-up"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Hutang (Koperasi)</span>
                <span class="value">Rp <?= number_format($totalHutang, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="summary-card success">
            <div class="card-icon">
                <i class="ph ph-arrow-circle-down"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Piutang (SPPG)</span>
                <span class="value">Rp <?= number_format($totalPiutang, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>

    <!-- DETAILS CONTAINER -->
    <div class="details-container">
        <!-- HUTANG COLUMN -->
        <div class="card" style="margin-bottom: 24px;">
            <h2><i class="ph ph-storefront"></i> Daftar Hutang (Ke Supplier/Toko)</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="60px">No</th>
                            <th width="130px">Tanggal Transaksi</th>
                            <th>Nama Toko / Supplier</th>
                            <th>Bukti Nota / Kwitansi</th>
                            <th style="text-align: right; padding-right: 15px;">Nilai Transaksi</th>
                            <th style="text-align: right; padding-right: 15px;">Uang Masuk</th>
                            <th style="text-align: right; padding-right: 15px;">Sisa Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dataHutang)): ?>
                            <?php $no = 1; foreach ($dataHutang as $h): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= date('d-m-Y', strtotime($h['tanggal_transaksi'])); ?></td>
                                    <td style="font-weight: 600; text-align: left;"><?= htmlspecialchars($h['nama_toko']); ?></td>
                                    <td>
                                        <?php 
                                        $notas = !empty($h['nota']) ? explode(',', $h['nota']) : [];
                                        if (!empty($notas)):
                                            foreach ($notas as $index => $nota):
                                                $notaTrim = trim($nota);
                                                if ($notaTrim !== ''):
                                        ?>
                                                    <a href="../uploads/nota/<?= htmlspecialchars($notaTrim); ?>" target="_blank" class="nota-link">
                                                        <i class="ph ph-file-image"></i> Nota <?= ($index + 1); ?>
                                                    </a>
                                        <?php 
                                                endif;
                                            endforeach;
                                        else:
                                        ?>
                                            <span class="no-nota">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="nominal-cell">Rp <?= number_format($h['total_tagihan'], 0, ',', '.'); ?></td>
                                    <td class="nominal-cell success-text">Rp <?= number_format($h['jumlah_dibayar'], 0, ',', '.'); ?></td>
                                    <td class="nominal-cell danger-text">Rp <?= number_format($h['sisa_pembayaran'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">Tidak ada hutang yang belum lunas</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PIUTANG COLUMN -->
        <div class="card">
            <h2><i class="ph ph-users"></i> Daftar Piutang (Dari Pelanggan/SPPG)</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="60px">No</th>
                            <th width="130px">Tanggal Transaksi</th>
                            <th>Nama Pelanggan / SPPG</th>
                            <th>Bukti Faktur Penjualan</th>
                            <th style="text-align: right; padding-right: 15px;">Nilai Transaksi</th>
                            <th style="text-align: right; padding-right: 15px;">Uang Masuk</th>
                            <th style="text-align: right; padding-right: 15px;">Sisa Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dataPiutang)): ?>
                            <?php $no = 1; foreach ($dataPiutang as $p): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= date('d-m-Y', strtotime($p['tanggal_pengambilan'])); ?></td>
                                    <td style="font-weight: 600; text-align: left;"><?= htmlspecialchars($p['nama_pelanggan']); ?></td>
                                    <td>
                                        <?php if (!empty($p['file_faktur'])): ?>
                                            <a href="../aplikasi-MBG/uploads/faktur/<?= htmlspecialchars($p['file_faktur']); ?>" target="_blank" class="nota-link">
                                                <i class="ph ph-file-image"></i> Faktur TTD
                                            </a>
                                        <?php endif; ?>
                                        <?php
                                            // Munculkan tombol Cetak Faktur untuk SETIAP jenis yang ada di transaksi ini.
                                            // Kalau transaksi cuma punya 1 jenis -> 1 tombol. Kalau ada foodcost & addcost -> 2 tombol.
                                            $jenisListRow = !empty($p['jenis_list']) ? $p['jenis_list'] : ['foodcost'];
                                            foreach ($jenisListRow as $jenisItem):
                                                $folder = ($jenisItem === 'addcost') ? 'penjualan-sppg-addcost' : 'penjualan-sppg-foodcost';
                                                $labelTombol = ($jenisItem === 'addcost') ? 'Cetak Faktur Addcost' : 'Cetak Faktur Foodcost';
                                        ?>
                                                <a href="../<?= $folder; ?>/cetak-faktur.php?id=<?= $p['id_pengambilan']; ?>" 
                                                   target="_blank" 
                                                   class="nota-link" 
                                                   style="background:#f0fdf4; border-color:#bbf7d0; color:#15803d;" 
                                                   title="Cetak Faktur Digital">
                                                    <i class="ph ph-printer"></i> <?= $labelTombol; ?>
                                                </a>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="nominal-cell">Rp <?= number_format($p['total_tagihan'], 0, ',', '.'); ?></td>
                                    <td class="nominal-cell success-text">Rp <?= number_format($p['uang_masuk'], 0, ',', '.'); ?></td>
                                    <td class="nominal-cell danger-text">Rp <?= number_format($p['sisa_pembayaran'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">Tidak ada piutang yang belum dibayar</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/made-by.php'; ?>

</body>
</html>