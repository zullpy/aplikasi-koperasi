<?php
require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/auth.php';
$activePage = 'rekap-hutang-piutang';
include __DIR__ . '/../components/navbar.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

// 1. Ambil data Hutang (Pembelian yang belum lunas)
$queryHutang = "
    SELECT 
        pp.id_pembayaran,
        pp.kode_transaksi,
        pp.tanggal_transaksi,
        s.nama_supplier AS nama_toko,
        pp.total_tagihan,
        pp.jumlah_dibayar,
        (pp.total_tagihan - pp.jumlah_dibayar) AS sisa_pembayaran,
        (SELECT tp.nota FROM db_draft_barang.transaksi_pembelian tp WHERE tp.kode_transaksi = pp.kode_transaksi COLLATE utf8mb4_unicode_ci LIMIT 1) AS nota
    FROM db_draft_barang.pembayaran_pembelian pp
    INNER JOIN db_draft_barang.suplier s ON s.id_supplier = pp.id_supplier
    WHERE pp.status_pembayaran != 'lunas' AND (pp.total_tagihan - pp.jumlah_dibayar) > 0
    ORDER BY pp.tanggal_transaksi DESC
";
$resultHutang = $koneksi->query($queryHutang);

// 2. Ambil data Piutang (Penjualan SPPG yang belum lunas)
$queryPiutang = "
    SELECT 
        pb.id_pengambilan,
        pb.no_pengambilan,
        pb.tanggal_pengambilan,
        pb.nama_sppg AS nama_pelanggan,
        pb.total_tagihan,
        COALESCE(p.total_bayar, 0) AS uang_masuk,
        (pb.total_tagihan - COALESCE(p.total_bayar, 0)) AS sisa_pembayaran,
        (SELECT ft.file_faktur FROM db_mbg.faktur_ttd ft WHERE ft.tanggal = pb.tanggal_pengambilan LIMIT 1) AS file_faktur
    FROM (
        SELECT 
            pb.id_pengambilan,
            pb.no_pengambilan,
            pb.tanggal_pengambilan,
            pb.nama_sppg,
            SUM(pbd.qty * COALESCE(CAST(REPLACE(REPLACE(REPLACE(b.harga_beli, 'Rp', ''), '.', ''), ' ', '') AS UNSIGNED), 0)) AS total_tagihan
        FROM db_mbg.pengambilan_barang pb
        INNER JOIN db_mbg.pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
        LEFT JOIN db_draft_barang.barang b ON LOWER(TRIM(b.nama_barang)) = LOWER(TRIM(pbd.nama_barang))
        WHERE pb.status = 'verified'
        GROUP BY pb.id_pengambilan, pb.no_pengambilan, pb.tanggal_pengambilan, pb.nama_sppg
    ) pb
    LEFT JOIN (
        SELECT id_pengambilan, SUM(jumlah_dibayar) AS total_bayar
        FROM db_mbg.pembayaran
        GROUP BY id_pengambilan
    ) p ON p.id_pengambilan = pb.id_pengambilan
    WHERE pb.total_tagihan > COALESCE(p.total_bayar, 0)
    ORDER BY pb.tanggal_pengambilan DESC
";
$resultPiutang = $koneksi2->query($queryPiutang);

// Hitung total keseluruhan
$totalHutang = 0;
$dataHutang = [];
if ($resultHutang) {
    while ($row = $resultHutang->fetch_assoc()) {
        $totalHutang += $row['sisa_pembayaran'];
        $dataHutang[] = $row;
    }
}

$totalPiutang = 0;
$dataPiutang = [];
if ($resultPiutang) {
    while ($row = $resultPiutang->fetch_assoc()) {
        $totalPiutang += $row['sisa_pembayaran'];
        $dataPiutang[] = $row;
    }
}

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
                                        <a href="../penjualan-sppg-foodcost/cetak-faktur.php?id=<?= $p['id_pengambilan']; ?>" target="_blank" class="nota-link" style="background:#f0fdf4; border-color:#bbf7d0; color:#15803d;" title="Cetak Faktur Digital">
                                            <i class="ph ph-printer"></i> Cetak Faktur
                                        </a>
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
