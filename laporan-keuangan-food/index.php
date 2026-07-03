<?php
                            error_reporting(E_ALL);
                            ini_set('display_errors', 1);
session_start();
include '../database/koneksi.php';

/* =========================================================
   PROSES FORM (POST)
========================================================= */

/* Simpan input saldo masuk manual baru */
$pesanSaldoMasuk = '';
if (isset($_POST['simpan_saldo_masuk'])) {
    $tglMasuk    = $_POST['tanggal_masuk'] ?? date('Y-m-d');
    $ketMasuk    = trim($_POST['keterangan_masuk'] ?? '');
    $jumlahMasuk = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah_masuk'] ?? '0');

    if ($ketMasuk !== '' && $jumlahMasuk > 0) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO saldo_masuk_manual (tanggal, keterangan, jumlah) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssd', $tglMasuk, $ketMasuk, $jumlahMasuk);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $pesanSaldoMasuk = 'Saldo masuk berhasil disimpan.';
    } else {
        $pesanSaldoMasuk = 'Keterangan dan jumlah wajib diisi dengan benar.';
    }
}

/* Hapus input saldo masuk manual */
if (isset($_POST['hapus_saldo_masuk_id'])) {
    $idHapus = (int)$_POST['hapus_saldo_masuk_id'];
    mysqli_query($koneksi, "DELETE FROM saldo_masuk_manual WHERE id_saldo_masuk = $idHapus");
}

/* Simpan modal uang (kas) manual ke session */
if (isset($_POST['modal_uang'])) {
    $_SESSION['modal_uang'] = (float)str_replace(['.', ','], ['', '.'], $_POST['modal_uang']);
}

/* =========================================================
   FILTER TAHUN
========================================================= */
$tahunSekarang = (int)date('Y');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunSekarang;

/* Ambil daftar tahun yang tersedia dari data transaksi (gabungan pembelian & penjualan) */
$tahunList = [];
$qTahun = mysqli_query($koneksi, "
    SELECT YEAR(tanggal_pembelian) AS thn FROM transaksi_pembelian
    UNION
    SELECT YEAR(tanggal) AS thn FROM transaksi_penjualan
    ORDER BY thn DESC
");
if ($qTahun) {
    while ($r = mysqli_fetch_assoc($qTahun)) {
        if (!empty($r['thn'])) $tahunList[] = (int)$r['thn'];
    }
}
if (empty($tahunList)) $tahunList[] = $tahunSekarang;
if (!in_array($tahun, $tahunList)) $tahun = $tahunList[0];

/* =========================================================
   AMBIL DATA SALDO MASUK MANUAL PER BULAN
========================================================= */
$saldoMasukManualPerBulan = array_fill(1, 12, 0.0);
$qSaldoManual = mysqli_query($koneksi, "
    SELECT MONTH(tanggal) AS bln, SUM(jumlah) AS total
    FROM saldo_masuk_manual
    WHERE YEAR(tanggal) = $tahun
    GROUP BY MONTH(tanggal)
");
if ($qSaldoManual) {
    while ($r = mysqli_fetch_assoc($qSaldoManual)) {
        $saldoMasukManualPerBulan[(int)$r['bln']] = (float)$r['total'];
    }
}

/* Riwayat lengkap saldo masuk manual tahun berjalan */
$riwayatSaldoMasuk = [];
$qRiwayat = mysqli_query($koneksi, "
    SELECT id_saldo_masuk, tanggal, keterangan, jumlah
    FROM saldo_masuk_manual
    WHERE YEAR(tanggal) = $tahun
    ORDER BY tanggal DESC, id_saldo_masuk DESC
");
if ($qRiwayat) {
    while ($r = mysqli_fetch_assoc($qRiwayat)) {
        $riwayatSaldoMasuk[] = $r;
    }
}

/* =========================================================
   AMBIL DATA PEMBELIAN PER BULAN (SALDO KELUAR)
   total = (harga * volume) + biaya_admin
========================================================= */
$pembelianPerBulan = array_fill(1, 12, 0.0);
$qBeli = mysqli_query($koneksi, "
    SELECT MONTH(tanggal_pembelian) AS bln,
           SUM((harga * volume) + biaya_admin) AS total
    FROM transaksi_pembelian
    WHERE YEAR(tanggal_pembelian) = $tahun
    GROUP BY MONTH(tanggal_pembelian)
");
if ($qBeli) {
    while ($r = mysqli_fetch_assoc($qBeli)) {
        $pembelianPerBulan[(int)$r['bln']] = (float)$r['total'];
    }
}

/* =========================================================
   AMBIL DATA PENJUALAN PER BULAN (UNTUK LABA RUGI SAJA)
   total mengacu pada kolom total di transaksi_penjualan
========================================================= */
$penjualanPerBulan = array_fill(1, 12, 0.0);
$qJual = mysqli_query($koneksi, "
    SELECT MONTH(tanggal) AS bln,
           SUM(total) AS total
    FROM transaksi_penjualan
    WHERE YEAR(tanggal) = $tahun
    GROUP BY MONTH(tanggal)
");
if ($qJual) {
    while ($r = mysqli_fetch_assoc($qJual)) {
        $penjualanPerBulan[(int)$r['bln']] = (float)$r['total'];
    }
}

/* =========================================================
   HITUNG HPP (HARGA POKOK PENJUALAN) PER BULAN UNTUK LABA RUGI
   HPP didekati dari detail_penjualan x harga_beli barang (jika harga_beli numerik)
   Jika tidak tersedia, HPP didekati dari total pembelian bulan berjalan
========================================================= */
$hppPerBulan = array_fill(1, 12, 0.0);
$qHpp = mysqli_query($koneksi, "
    SELECT MONTH(tp.tanggal) AS bln,
           SUM(dp.qty * CAST(b.harga_beli AS DECIMAL(15,2))) AS hpp
    FROM detail_penjualan dp
    JOIN transaksi_penjualan tp ON tp.id_transaksi = dp.id_transaksi
    JOIN barang b ON b.id_barang = dp.id_barang
    WHERE YEAR(tp.tanggal) = $tahun
      AND b.harga_beli REGEXP '^[0-9]+(\\.[0-9]+)?$'
    GROUP BY MONTH(tp.tanggal)
");
if ($qHpp) {
    while ($r = mysqli_fetch_assoc($qHpp)) {
        $hppPerBulan[(int)$r['bln']] = (float)$r['hpp'];
    }
}

/* =========================================================
   MODAL BARANG (NILAI STOK SAAT INI)
   Dihitung dari stok_akhir x harga_beli tiap barang.
========================================================= */
$modalBarang = 0.0;
$daftarModalBarang = [];
$qModalBarang = mysqli_query($koneksi, "
    SELECT id_barang, nama_barang, satuan, stok_akhir,
           CAST(harga_beli AS DECIMAL(15,2)) AS harga_beli_num
    FROM barang
    WHERE harga_beli REGEXP '^[0-9]+(\\.[0-9]+)?$'
      AND stok_akhir > 0
    ORDER BY (stok_akhir * CAST(harga_beli AS DECIMAL(15,2))) DESC
");
if ($qModalBarang) {
    while ($r = mysqli_fetch_assoc($qModalBarang)) {
        $nilai = (float)$r['stok_akhir'] * (float)$r['harga_beli_num'];
        $modalBarang += $nilai;
        $daftarModalBarang[] = [
            'nama'   => $r['nama_barang'],
            'stok'   => $r['stok_akhir'],
            'satuan' => $r['satuan'],
            'harga'  => (float)$r['harga_beli_num'],
            'nilai'  => $nilai,
        ];
    }
}

/* =========================================================
   MODAL UANG (KAS) - dari session, default 0
========================================================= */
$modalUang  = isset($_SESSION['modal_uang']) ? (float)$_SESSION['modal_uang'] : 0.0;
$totalModal = $modalUang + $modalBarang;

/* =========================================================
   SUSUN DATA LAPORAN BULANAN + SALDO BERJALAN
========================================================= */
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$laporan = [];
$saldoBerjalan = 0.0;
$totalMasuk = 0.0;
$totalKeluar = 0.0;
$totalLaba = 0.0;
$totalSaldoMasukManual = 0.0;

for ($b = 1; $b <= 12; $b++) {
    $masuk  = $saldoMasukManualPerBulan[$b];
    $keluar = $pembelianPerBulan[$b];
    $totalSaldoMasukManual += $saldoMasukManualPerBulan[$b];
    $saldoBerjalan += ($masuk - $keluar);

    // Laba kotor = Penjualan murni - HPP (saldo masuk manual TIDAK dihitung sebagai omzet)
    $hpp  = $hppPerBulan[$b] > 0 ? $hppPerBulan[$b] : $keluar;
    $laba = $penjualanPerBulan[$b] - $hpp;

    $totalMasuk  += $masuk;
    $totalKeluar += $keluar;
    $totalLaba   += $laba;

    $laporan[$b] = [
        'bulan'        => $namaBulan[$b],
        'penjualan'    => $penjualanPerBulan[$b],
        'masuk_manual' => $saldoMasukManualPerBulan[$b],
        'saldo_masuk'  => $masuk,
        'saldo_keluar' => $keluar,
        'saldo_akhir'  => $saldoBerjalan,
        'laba_rugi'    => $laba,
    ];
}

/* =========================================================
   HELPER FORMAT RUPIAH
========================================================= */
if (!function_exists('rp')) {
    function rp($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
} ?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan | Bina Usaha Sauyunan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php $activePage = 'laporan-keuangan';
    include '../components/navbar.php'; ?>
    <div class="container" id="laporanArea" data-tahun="<?= $tahun ?>">

        <div class="page-header">
            <div>
                <h1>Laporan Keuangan</h1>
                <p>Bina Usaha Sauyunan &mdash; Ringkasan saldo, pembelian, penjualan, dan laba rugi tahun <?= $tahun ?></p>
            </div>
            <div class="header-actions no-print">
                <form method="GET" id="filterForm" style="display:flex; gap:10px;">
                    <select name="tahun" id="tahunFilter">
                        <?php foreach ($tahunList as $ty): ?>
                            <option value="<?= $ty ?>" <?= $ty == $tahun ? 'selected' : '' ?>><?= $ty ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="btn btn-export" id="btnExportPDF" type="button">⬇ Ekspor PDF</button>
            </div>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="card in">
                <div class="icon-badge">↑</div>
                <div class="label">Total Saldo Masuk</div>
                <div class="value"><?= rp($totalMasuk) ?></div>
            </div>
            <div class="card out">
                <div class="icon-badge">↓</div>
                <div class="label">Total Saldo Keluar</div>
                <div class="value"><?= rp($totalKeluar) ?></div>
            </div>
            <div class="card balance">
                <div class="icon-badge">≡</div>
                <div class="label">Saldo Akhir Tahun</div>
                <div class="value"><?= rp($saldoBerjalan) ?></div>
            </div>
            <div class="card profit <?= $totalLaba < 0 ? 'loss' : '' ?>">
                <div class="icon-badge"><?= $totalLaba < 0 ? '▼' : '▲' ?></div>
                <div class="label"><?= $totalLaba < 0 ? 'Total Rugi' : 'Total Laba' ?></div>
                <div class="value"><?= rp(abs($totalLaba)) ?></div>
            </div>
        </div>

        <!-- MODAL USAHA: UANG + BARANG -->
        <div class="panel">
            <div class="panel-title">
                Modal Usaha
                <span class="sub">Modal uang (kas) + modal barang (nilai stok saat ini)</span>
            </div>

            <div class="modal-grid">
                <div class="card balance flat">
                    <div class="icon-badge">💰</div>
                    <div class="label">Modal Uang (Kas)</div>
                    <div class="value"><?= rp($modalUang) ?></div>
                    <form method="POST" class="no-print" style="margin-top:12px; display:flex; gap:8px;">
                        <input type="text" name="modal_uang" placeholder="Masukkan nominal modal uang"
                            value="<?= number_format($modalUang, 0, ',', '.') ?>" class="input-text">
                        <button type="submit" class="btn btn-primary" style="padding:8px 14px; font-size:13px;">Simpan</button>
                    </form>
                </div>
                <div class="card in flat">
                    <div class="icon-badge">📦</div>
                    <div class="label">Modal Barang (Nilai Stok)</div>
                    <div class="value"><?= rp($modalBarang) ?></div>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:8px;">
                        Dihitung dari stok akhir × harga beli pada <?= count($daftarModalBarang) ?> item barang bersisa.
                    </p>
                </div>
                <div class="card profit flat">
                    <div class="icon-badge">Σ</div>
                    <div class="label">Total Modal Usaha</div>
                    <div class="value"><?= rp($totalModal) ?></div>
                </div>
            </div>

            <?php if (!empty($daftarModalBarang)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th class="num">Stok Akhir</th>
                            <th>Satuan</th>
                            <th class="num">Harga Beli</th>
                            <th class="num">Nilai Modal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarModalBarang as $mb): ?>
                            <tr>
                                <td><?= htmlspecialchars($mb['nama']) ?></td>
                                <td class="num"><?= number_format($mb['stok'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($mb['satuan']) ?></td>
                                <td class="num"><?= rp($mb['harga']) ?></td>
                                <td class="num text-primary"><?= rp($mb['nilai']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Total Modal Barang</td>
                            <td class="num text-primary"><?= rp($modalBarang) ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="note-text">Belum ada barang dengan stok akhir &gt; 0 dan harga beli numerik.</p>
            <?php endif; ?>
        </div>

        <!-- INPUT SALDO MASUK MANUAL -->
        <div class="panel">
            <div class="panel-title">
                Input Saldo Masuk Manual
                <span class="sub">Dana masuk di luar penjualan (mis. modal tambahan, pinjaman, dll) — tersimpan ke tabel <code>saldo_masuk_manual</code></span>
            </div>

            <form method="POST" class="no-print input-form-row">
                <div class="field">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal_masuk" value="<?= date('Y-m-d') ?>" required class="input-text">
                </div>
                <div class="field wide">
                    <label>Keterangan</label>
                    <input type="text" name="keterangan_masuk" placeholder="Contoh: Modal tambahan dari pemilik" required class="input-text">
                </div>
                <div class="field">
                    <label>Jumlah (Rp)</label>
                    <input type="text" name="jumlah_masuk" placeholder="0" required class="input-text">
                </div>
                <button type="submit" name="simpan_saldo_masuk" value="1" class="btn btn-primary">Simpan</button>
            </form>

            <?php if ($pesanSaldoMasuk): ?>
                <p style="padding: 12px 22px; font-size:13px; color: var(--primary); font-weight:600;"><?= htmlspecialchars($pesanSaldoMasuk) ?></p>
            <?php endif; ?>

            <?php if (!empty($riwayatSaldoMasuk)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th class="num">Jumlah</th>
                            <th class="no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatSaldoMasuk as $rs): ?>
                            <tr>
                                <td><?= date('d-m-Y', strtotime($rs['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($rs['keterangan']) ?></td>
                                <td class="num text-success"><?= rp($rs['jumlah']) ?></td>
                                <td class="no-print">
                                    <form method="POST" class="form-hapus-saldo" style="display:inline;">
                                        <input type="hidden" name="hapus_saldo_masuk_id" value="<?= $rs['id_saldo_masuk'] ?>">
                                        <button type="submit" class="btn-link-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Total Saldo Masuk Manual</td>
                            <td class="num text-success"><?= rp($totalSaldoMasukManual) ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="note-text">Belum ada catatan saldo masuk manual untuk tahun <?= $tahun ?>.</p>
            <?php endif; ?>
        </div>

        <!-- TABEL SALDO MASUK / KELUAR / AKHIR -->
        <div class="panel">
            <div class="panel-title">
                Rekapitulasi Saldo Bulanan
                <span class="sub">Saldo masuk dari input manual &middot; Tahun <?= $tahun ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="num">Saldo Masuk</th>
                        <th class="num">Saldo Keluar</th>
                        <th class="num">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($laporan as $row): ?>
                        <tr>
                            <td><?= $row['bulan'] ?></td>
                            <td class="num text-success"><?= rp($row['saldo_masuk']) ?></td>
                            <td class="num text-danger"><?= rp($row['saldo_keluar']) ?></td>
                            <td class="num text-primary"><?= rp($row['saldo_akhir']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num text-success"><?= rp($totalMasuk) ?></td>
                        <td class="num text-danger"><?= rp($totalKeluar) ?></td>
                        <td class="num text-primary"><?= rp($saldoBerjalan) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- TABEL PEMBELIAN & PENJUALAN PER BULAN -->
        <div class="panel">
            <div class="panel-title">
                Laporan Pembelian &amp; Penjualan Bulanan
                <span class="sub">Tahun <?= $tahun ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="num">Total Pembelian</th>
                        <th class="num">Total Penjualan</th>
                        <th class="num">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($laporan as $row):
                        $selisih = $row['penjualan'] - $row['saldo_keluar'];
                    ?>
                        <tr>
                            <td><?= $row['bulan'] ?></td>
                            <td class="num"><?= rp($row['saldo_keluar']) ?></td>
                            <td class="num"><?= rp($row['penjualan']) ?></td>
                            <td class="num <?= $selisih < 0 ? 'text-danger' : 'text-success' ?>"><?= rp($selisih) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num"><?= rp($totalKeluar) ?></td>
                        <td class="num"><?= rp(array_sum($penjualanPerBulan)) ?></td>
                        <td class="num <?= (array_sum($penjualanPerBulan) - $totalKeluar) < 0 ? 'text-danger' : 'text-success' ?>"><?= rp(array_sum($penjualanPerBulan) - $totalKeluar) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- TABEL LABA RUGI -->
        <div class="panel">
            <div class="panel-title">
                Laporan Laba Rugi
                <span class="sub">Penjualan dikurangi Harga Pokok Penjualan (HPP)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="num">Penjualan</th>
                        <th class="num">HPP / Pembelian</th>
                        <th class="num">Laba / Rugi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($laporan as $row):
                        $hppTampil = $row['penjualan'] - $row['laba_rugi'];
                    ?>
                        <tr>
                            <td><?= $row['bulan'] ?></td>
                            <td class="num"><?= rp($row['penjualan']) ?></td>
                            <td class="num"><?= rp($hppTampil) ?></td>
                            <td class="num <?= $row['laba_rugi'] < 0 ? 'text-danger' : 'text-success' ?>"><?= rp($row['laba_rugi']) ?></td>
                            <td>
                                <span class="badge-profit <?= $row['laba_rugi'] < 0 ? 'rugi' : 'untung' ?>">
                                    <?= $row['laba_rugi'] < 0 ? 'Rugi' : 'Untung' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num"><?= rp(array_sum($penjualanPerBulan)) ?></td>
                        <td class="num"><?= rp(array_sum($penjualanPerBulan) - $totalLaba) ?></td>
                        <td class="num <?= $totalLaba < 0 ? 'text-danger' : 'text-success' ?>"><?= rp($totalLaba) ?></td>
                        <td>
                            <span class="badge-profit <?= $totalLaba < 0 ? 'rugi' : 'untung' ?>">
                                <?= $totalLaba < 0 ? 'Rugi' : 'Untung' ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <footer class="report-footer">
            Dicetak pada <?= date('d-m-Y H:i') ?> &mdash; Sistem Bina Usaha Sauyunan
        </footer>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>