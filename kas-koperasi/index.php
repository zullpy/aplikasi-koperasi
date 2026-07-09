<?php
session_start();
require_once '../database/koneksi.php';

$userRole = $_SESSION['role'] ?? '';

/* ==========================================================
   FILTER TAHUN
   ========================================================== */
$tahunSekarang = (int) date('Y');
$tahunDipilih  = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $tahunSekarang;

// Daftar tahun yang tersedia (dari data transaksi), fallback ke tahun sekarang
$tahunList = [];
$resTahun  = $koneksi->query("SELECT DISTINCT YEAR(tanggal) AS thn FROM kas_koperasi ORDER BY thn DESC");
if ($resTahun) {
    while ($row = $resTahun->fetch_assoc()) {
        $tahunList[] = (int) $row['thn'];
    }
}
if (!in_array($tahunSekarang, $tahunList, true)) {
    array_unshift($tahunList, $tahunSekarang);
}
if (!in_array($tahunDipilih, $tahunList, true)) {
    $tahunDipilih = $tahunSekarang;
}

/* ==========================================================
   SALDO AWAL TAHUN (akumulasi transaksi sebelum tahun terpilih)
   ========================================================== */
$saldoAwal = 0.0;
$stmtAwal = $koneksi->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END), 0) AS total_keluar
     FROM kas_koperasi
     WHERE YEAR(tanggal) < ?"
);
$stmtAwal->bind_param('i', $tahunDipilih);
$stmtAwal->execute();
$rowAwal = $stmtAwal->get_result()->fetch_assoc();
$saldoAwal = (float) $rowAwal['total_masuk'] - (float) $rowAwal['total_keluar'];

/* ==========================================================
   DATA TRANSAKSI TAHUN TERPILIH
   ========================================================== */
$transaksi = [];
$stmt = $koneksi->prepare(
    "SELECT id, tanggal, keterangan, jenis, nominal
     FROM kas_koperasi
     WHERE YEAR(tanggal) = ?
     ORDER BY tanggal ASC, id ASC"
);
$stmt->bind_param('i', $tahunDipilih);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transaksi[] = $row;
}

/* ==========================================================
   HITUNG SALDO BERJALAN + TOTAL
   ========================================================== */
$saldoBerjalan  = $saldoAwal;
$totalMasuk     = 0.0;
$totalKeluar    = 0.0;
$rows           = [];

foreach ($transaksi as $t) {
    $nominal = (float) $t['nominal'];
    if ($t['jenis'] === 'masuk') {
        $saldoBerjalan += $nominal;
        $totalMasuk    += $nominal;
    } else {
        $saldoBerjalan -= $nominal;
        $totalKeluar   += $nominal;
    }
    $rows[] = [
        'id'         => $t['id'],
        'tanggal'    => $t['tanggal'],
        'keterangan' => $t['keterangan'],
        'jenis'      => $t['jenis'],
        'nominal'    => $nominal,
        'saldo'      => $saldoBerjalan,
    ];
}

$saldoAkhir = $saldoBerjalan;

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$namaBulan = [
    1  => 'Januari', 2  => 'Februari', 3  => 'Maret',
    4  => 'April',   5  => 'Mei',      6  => 'Juni',
    7  => 'Juli',    8  => 'Agustus',  9  => 'September',
    10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

function formatTanggal($tanggal, $namaBulan)
{
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $namaBulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kas Koperasi | Bina Usaha Sauyunan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php $activePage = 'kas-koperasi';
    include '../components/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Kas Koperasi</h1>
                <p>Bina Usaha Sauyunan &mdash; Pencatatan pemasukan, pengeluaran, dan saldo kas</p>
            </div>
            <div class="header-actions no-print">
                <select id="tahunFilter" onchange="gantiTahun(this.value)">
                    <?php foreach ($tahunList as $thn): ?>
                        <option value="<?= $thn ?>" <?= $thn === $tahunDipilih ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($userRole === 'bendahara'): ?>
                <button type="button" class="btn btn-primary" onclick="bukaModalTambah()">
                    + Tambah Keterangan
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="summary-grid">
            <div class="card in">
                <div class="icon-badge">&#8595;</div>
                <div class="label">Total Pemasukan <?= $tahunDipilih ?></div>
                <div class="value"><?= formatRupiah($totalMasuk) ?></div>
            </div>
            <div class="card out">
                <div class="icon-badge">&#8593;</div>
                <div class="label">Total Pengeluaran <?= $tahunDipilih ?></div>
                <div class="value"><?= formatRupiah($totalKeluar) ?></div>
            </div>
            <div class="card balance">
                <div class="icon-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 256 256" fill="currentColor">
                        <path d="M216,64H176V56a24,24,0,0,0-24-24H104A24,24,0,0,0,80,56v8H40A16,16,0,0,0,24,80V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V80A16,16,0,0,0,216,64ZM96,56a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96ZM216,80V115.7a184.6,184.6,0,0,1-88,22.3,184.4,184.4,0,0,1-88-22.3V80Zm0,112H40V133.4a200.6,200.6,0,0,0,88,20.6,200.6,200.6,0,0,0,88-20.6V192Z"></path>
                    </svg>
                </div>
                <div class="label">Saldo Akhir</div>
                <div class="value"><?= formatRupiah($saldoAkhir) ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">
                Riwayat Transaksi Kas
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:48px;">No</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th class="num">Masuk</th>
                        <th class="num">Keluar</th>
                        <th class="num">Saldo Akhir</th>
                        <?php if ($userRole === 'bendahara'): ?>
                        <th class="no-print" style="width:110px;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?= $userRole === 'bendahara' ? 7 : 6 ?>" style="text-align:center; color:var(--text-muted); padding:32px;">
                                Belum ada transaksi untuk tahun <?= $tahunDipilih ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr
                                data-id="<?= $r['id'] ?>"
                                data-tanggal="<?= $r['tanggal'] ?>"
                                data-keterangan="<?= htmlspecialchars($r['keterangan'], ENT_QUOTES) ?>"
                                data-jenis="<?= $r['jenis'] ?>"
                                data-nominal="<?= $r['nominal'] ?>"
                            >
                                <td><?= $i + 1 ?></td>
                                <td><?= formatTanggal($r['tanggal'], $namaBulan) ?></td>
                                <td><?= htmlspecialchars($r['keterangan']) ?></td>
                                <td class="num text-success"><?= $r['jenis'] === 'masuk' ? formatRupiah($r['nominal']) : '-' ?></td>
                                <td class="num text-danger"><?= $r['jenis'] === 'keluar' ? formatRupiah($r['nominal']) : '-' ?></td>
                                <td class="num text-primary"><?= formatRupiah($r['saldo']) ?></td>
                                <?php if ($userRole === 'bendahara'): ?>
                                <td class="no-print">
                                    <button type="button" class="btn-link-danger" style="color:var(--primary); margin-right:10px;" onclick="bukaModalEdit(this)">Edit</button>
                                    <button type="button" class="btn-link-danger" onclick="hapusTransaksi(<?= $r['id'] ?>)">Hapus</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <!-- <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num text-success"><?= formatRupiah($totalMasuk) ?></td>
                        <td class="num text-danger"><?= formatRupiah($totalKeluar) ?></td>
                        <td class="num text-primary"><?= formatRupiah($saldoAkhir) ?></td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot> -->
            </table>
        </div>
    </div>

    <!-- ============ MODAL TRANSAKSI ============ -->
    <div id="modalTransaksi" class="modal-overlay no-print" style="display:none;">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Transaksi</h3>
                <button type="button" class="modal-close" onclick="tutupModal()">&times;</button>
            </div>

            <form id="formTransaksi">
                <input type="hidden" name="id" id="fId" value="">

                <div class="modal-grid" style="padding-top:20px;">
                    <div class="field">
                        <label>Jenis Transaksi</label>
                        <div class="jenis-toggle">
                            <label class="jenis-option masuk">
                                <input type="radio" name="jenis" value="masuk" checked>
                                <span>Pemasukan</span>
                            </label>
                            <label class="jenis-option keluar">
                                <input type="radio" name="jenis" value="keluar">
                                <span>Pengeluaran</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="input-form-row">
                    <div class="field">
                        <label for="fTanggal">Tanggal</label>
                        <input type="date" id="fTanggal" name="tanggal" class="input-text" required>
                    </div>
                    <div class="field">
                        <label for="fNominal">Nominal (Rp)</label>
                        <input type="text" id="fNominal" name="nominal" class="input-text" inputmode="numeric" placeholder="0" required>
                    </div>
                </div>

                <div class="input-form-row" style="border-bottom:none;">
                    <div class="field wide">
                        <label for="fKeterangan">Keterangan</label>
                        <input type="text" id="fKeterangan" name="keterangan" class="input-text" placeholder="Contoh: Simpanan wajib bulan Juli" required maxlength="255">
                    </div>
                </div>

                <p id="formError" class="note-text" style="display:none; color:var(--danger);"></p>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e2e8f0; color:var(--text-main);" onclick="tutupModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const TAHUN_DIPILIH = <?= json_encode($tahunDipilih) ?>;
    </script>
    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>