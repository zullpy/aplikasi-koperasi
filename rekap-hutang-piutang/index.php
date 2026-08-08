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
// 2. DATA PIUTANG (Penjualan SPPG yang belum lunas)
// pengambilan_barang, pengambilan_barang_detail, pembayaran,
// faktur_ttd ada di database $koneksi2. Tabel barang & riwayat_harga
// ada di database $koneksi (db_barang). Karena beda database & beda
// user MySQL, tidak bisa di-JOIN langsung -> diambil terpisah lalu
// digabung manual di PHP.
//
// PENTING #1: harga yang dipakai untuk hitung total_tagihan HARUS
// harga yang berlaku PADA tanggal_pengambilan, bukan harga_beli
// terkini di tabel barang. Kalau pakai harga terkini, nilai rekap
// bisa beda dengan nilai di faktur yang sudah dicetak (karena harga
// barang bisa berubah setelah faktur dibuat). Makanya di sini kita
// ambil dari tabel riwayat_harga (snapshot histori harga per barang).
//
// PENTING #2: satu id_pengambilan bisa menghasilkan 2 faktur terpisah
// (foodcost & addcost), karena item di dalamnya ditandai per baris
// lewat kolom pbd.jenis. Jadi total_tagihan HARUS dihitung per
// (id_pengambilan + jenis), bukan digabung jadi satu per id_pengambilan
// -> supaya nilai transaksi foodcost dan addcost tidak nyampur.
//
// PENTING #3: tabel pembayaran cuma mencatat total bayar per
// id_pengambilan (tidak dipisah foodcost/addcost). Karena itu, saat
// satu id_pengambilan punya 2 baris (foodcost & addcost), uang masuk
// dibagi PROPORSIONAL sesuai porsi nilai transaksi masing-masing
// terhadap total gabungan id_pengambilan tersebut.
// ----------------------------------------------------------

// 2a. Ambil header + detail pengambilan (tanpa harga), pakai $koneksi2
$queryPiutangRaw = "
    SELECT 
        pb.id_pengambilan,
        pb.no_pengambilan,
        pb.tanggal_pengambilan,
        pb.nama_sppg,
        pbd.nama_barang,
        pbd.qty, 
        pbd.jenis
    FROM pengambilan_barang pb
    INNER JOIN pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
    WHERE pb.status = 'verified'
";
$resultPiutangRaw = $koneksi2->query($queryPiutangRaw);

// 2b. Mapping nama_barang -> id_barang, pakai $koneksi (database db_barang)
$idBarangLookup = [];
$resBarang = $koneksi->query("SELECT id_barang, nama_barang, harga_beli FROM barang");
$hargaSekarangLookup = []; // fallback kalau barang belum punya riwayat_harga sama sekali
if ($resBarang) {
    while ($rb = $resBarang->fetch_assoc()) {
        $key = strtolower(trim($rb['nama_barang']));
        $idBarangLookup[$key] = $rb['id_barang'];

        $bersih = preg_replace('/[^0-9]/', '', $rb['harga_beli']);
        $hargaSekarangLookup[$rb['id_barang']] = $bersih === '' ? 0 : (float) $bersih;
    }
}

// 2c. Ambil semua riwayat harga, dikelompokkan per id_barang, urut tanggal ASC
$riwayatHargaMap = []; // id_barang => [ ['tanggal' => ..., 'harga' => ...], ... ] urut ascending
$resRiwayat = $koneksi->query("SELECT id_barang, harga_beli, tanggal FROM riwayat_harga ORDER BY id_barang ASC, tanggal ASC");
if ($resRiwayat) {
    while ($rr = $resRiwayat->fetch_assoc()) {
        $riwayatHargaMap[$rr['id_barang']][] = [
            'tanggal' => $rr['tanggal'],
            'harga'   => (float) $rr['harga_beli'],
        ];
    }
}

/**
 * Cari harga barang yang berlaku pada tanggal tertentu.
 * Ambil record riwayat_harga terakhir yang tanggalnya <= tanggal transaksi.
 * Kalau belum ada riwayat yang <= tanggal itu (barang "baru" setelah transaksi
 * lama), pakai riwayat paling awal yang ada. Kalau riwayat kosong total,
 * fallback ke harga_beli terkini di tabel barang.
 */
function cariHargaPadaTanggal($idBarang, $tanggalTransaksi, array $riwayatHargaMap, array $hargaSekarangLookup)
{
    if (!isset($riwayatHargaMap[$idBarang]) || empty($riwayatHargaMap[$idBarang])) {
        return $hargaSekarangLookup[$idBarang] ?? 0;
    }

    $tsTransaksi = strtotime($tanggalTransaksi);
    $hargaTerpilih = null;

    foreach ($riwayatHargaMap[$idBarang] as $r) {
        if (strtotime($r['tanggal']) <= $tsTransaksi) {
            $hargaTerpilih = $r['harga']; // terus ditimpa sampai lewat tanggal transaksi
        } else {
            break; // sudah urut ASC, begitu lewat tanggal transaksi langsung berhenti
        }
    }

    // Belum ada riwayat sebelum/at tanggal transaksi -> pakai riwayat paling awal yang tercatat
    if ($hargaTerpilih === null) {
        $hargaTerpilih = $riwayatHargaMap[$idBarang][0]['harga'];
    }

    return $hargaTerpilih;
}

// 2d. Susun total_tagihan per transaksi, GABUNG lagi jadi 1 baris per id_pengambilan
// (foodcost + addcost dijumlahkan). Tapi tetap dicatat daftar jenis apa saja yang
// ada di transaksi itu (jenis_list), supaya nanti tombol Cetak Faktur bisa
// dimunculkan sesuai jenis yang benar-benar ada (bisa 1 atau 2 tombol).
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

        // Catat jenis item ini (foodcost/addcost) kalau belum pernah tercatat
        $jenisRow = strtolower(trim($row['jenis'] ?? ''));
        if ($jenisRow !== '' && !in_array($jenisRow, $piutangRaw[$id]['jenis_list'], true)) {
            $piutangRaw[$id]['jenis_list'][] = $jenisRow;
        }

        // Harga diambil dari riwayat_harga sesuai tanggal_pengambilan
        $key = strtolower(trim($row['nama_barang']));
        $idBarang = $idBarangLookup[$key] ?? null;

        $harga = 0;
        if ($idBarang !== null) {
            $harga = cariHargaPadaTanggal($idBarang, $row['tanggal_pengambilan'], $riwayatHargaMap, $hargaSekarangLookup);
        }

        $piutangRaw[$id]['total_tagihan'] += ((float) $row['qty']) * $harga;
    }
}

// 2e. Ambil total pembayaran per id_pengambilan (gabungan, belum dipisah jenis), pakai $koneksi2
$bayarMap = [];
$resBayar = $koneksi2->query("SELECT id_pengambilan, SUM(jumlah_dibayar) AS total_bayar FROM pembayaran GROUP BY id_pengambilan");
if ($resBayar) {
    while ($rb = $resBayar->fetch_assoc()) {
        $bayarMap[$rb['id_pengambilan']] = (float) $rb['total_bayar'];
    }
}

// 2f. Ambil file faktur per tanggal, pakai $koneksi2
$fakturMap = [];
$resFaktur = $koneksi2->query("SELECT tanggal, file_faktur FROM faktur_ttd");
if ($resFaktur) {
    while ($rf = $resFaktur->fetch_assoc()) {
        $fakturMap[$rf['tanggal']] = $rf['file_faktur'];
    }
}

// 2g. Gabungkan semuanya jadi $dataPiutang (1 baris per id_pengambilan).
// Uang masuk diambil langsung dari total pembayaran transaksi itu (tidak dipecah lagi).
// Hanya yang masih ada sisa pembayaran yang ditampilkan.
$dataPiutang = [];
$totalPiutang = 0;
foreach ($piutangRaw as $p) {
    $id = $p['id_pengambilan'];
    $uangMasuk = $bayarMap[$id] ?? 0;
    $sisa = $p['total_tagihan'] - $uangMasuk;

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

// $selisih = $totalPiutang - $totalHutang;
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