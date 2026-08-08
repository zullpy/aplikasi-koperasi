<?php
require_once '../database/auth.php';
require_once '../database/koneksi.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses halaman hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

$isBendahara = in_array($userRole, ['admin', 'bendahara']);

$activePage = 'omset-sppg';
include '../components/navbar.php';

// Pastikan kolom pajak ada di database (pengecekan aman)
try {
    $cekPajak = $koneksi->query("SHOW COLUMNS FROM omset_sppg_harian LIKE 'pajak'");
    if ($cekPajak && $cekPajak->num_rows === 0) {
        @$koneksi->query("ALTER TABLE omset_sppg_harian ADD COLUMN pajak DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER biaya_admin");
    }
} catch (Throwable $e) {
    // abaikan jika sudah ada
}

// Ambil daftar bulan yang tersedia datanya di tabel harian untuk filter dropdown
$listBulanPHP = [];
try {
    $resBulanPHP = $koneksi->query("SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan_val FROM omset_sppg_harian ORDER BY bulan_val DESC");
    if ($resBulanPHP) {
        while ($rowB = $resBulanPHP->fetch_assoc()) {
            if (!empty($rowB['bulan_val'])) {
                $listBulanPHP[] = $rowB['bulan_val'];
            }
        }
    }
} catch (Throwable $e) {}
$bulanIniPHP = date('Y-m');
if (!in_array($bulanIniPHP, $listBulanPHP, true)) {
    array_unshift($listBulanPHP, $bulanIniPHP);
}

// Ambil data rekap mingguan berdasarkan rentang tanggal
$queryRekap = "
    SELECT 
        DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY) AS tgl_mulai,
        DATE_ADD(DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY), INTERVAL 6 DAY) AS tgl_selesai,
        SUM(nominal_koperasi) AS koperasi,
        SUM(nominal_yayasan) AS yayasan,
        SUM(nominal_helmi) AS helmi,
        SUM(nominal_management) AS management,
        SUM(pajak) AS pajak
    FROM omset_sppg_harian
    GROUP BY 1, 2
    ORDER BY 1 ASC
";
try {
    $resultRekap = $koneksi->query($queryRekap);
} catch (Throwable $e) {
    error_log("Error queryRekap: " . $e->getMessage());
    $resultRekap = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Omset SPPG</title>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
<link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="container">
    <h1><i class="ph ph-currency-circle-dollar"></i> Omset SPPG</h1>
    <div class="subtitle">Input KPM harian & rincian pembagian omset</div>

    <!-- TABEL RINCIAN BULAN BERJALAN -->
    <div class="card">
        <div class="card-header-row">
            <h2><i class="ph ph-table"></i> Rincian Omset Harian Bulan <span id="labelBulan" style="margin-left: 5px;">-</span></h2>
            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                <select id="filterBulan" onchange="gantiBulanFilter(this.value)" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 14px; font-weight: 600; background: #fff; cursor: pointer;">
                    <?php foreach ($listBulanPHP as $b): 
                        $p = explode('-', $b);
                        $bulanIndo = $b;
                        if (count($p) >= 2) {
                            $namaBulanList = [
                                '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
                                '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
                                '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                            ];
                            $bulanIndo = ($namaBulanList[$p[1]] ?? $p[1]) . ' ' . $p[0];
                        }
                    ?>
                        <option value="<?= $b; ?>"><?= $bulanIndo; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isBendahara): ?>
                <button class="btn btn-primary" onclick="bukaModalInput()">
                    <i class="ph ph-plus-circle"></i> Input KPM 
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-wrapper">
            <table id="tabelOmset">
                <thead>
    <tr class="group-header">
        <th rowspan="2">Tanggal</th>
        <th rowspan="2">Total<br>Anggaran</th>
        <th rowspan="2">Anggaran<br>Diterima</th>
        <th colspan="3">KPM</th>
        <th rowspan="2">Belanja<br>Foodcost</th>
        <th colspan="2">KBUS</th>
        <th colspan="2">Yayasan</th>
        <th colspan="2">Koperasi</th>
        <th rowspan="2">Nominal<br>Management</th>
    </tr>
    <tr class="group-header">
        <th>Besar</th><th>Kecil</th><th>Total</th>
        <th>Profit Sharing</th><th>Nominal</th>
        <th>Profit Sharing</th><th>Nominal</th>
        <th>Profit Sharing</th><th>Nominal</th>
    </tr>
</thead>
<tbody id="tbodyOmset">
    <tr><td colspan="14" class="empty-state">Memuat data...</td></tr>
</tbody>
<tfoot>
    <tr>
        <td>TOTAL</td>
        <td id="fTotalAnggaran">Rp 0</td>
        <td id="fAnggaranDiterima">Rp 0</td>
        <td></td><td></td><td id="fTotalKpm">0</td>
        <td id="fBelanjaFoodcost">Rp 0</td>
        <td></td><td id="fNomKoperasi">Rp 0</td>
        <td></td><td id="fNomYayasan">Rp 0</td>
        <td></td><td id="fNomHelmi">Rp 0</td>
        <td id="fNomManagement">Rp 0</td>
    </tr>
</tfoot>
            </table>
        </div>
    </div>

    <!-- TABEL REKAP KPM SPPG (MINGGUAN) -->
    <div class="card" id="rekap-kpm-mingguan" style="margin-top: 32px;">
        <div class="card-header-row">
            <h2><i class="ph ph-trend-up"></i> Rekap Profit Mingguan</h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="80px">No</th>
                        <th>Rentang Tanggal</th>
                        <th>KBUS</th>
                        <th>Yayasan</th>
                        <th>Koperasi</th>
                        <th>Management</th>
                        <th>Pajak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbodyRekapMingguan">
                    <?php 
                    if ($resultRekap && $resultRekap->num_rows > 0): 
                        $no = 1;
                        while ($row = $resultRekap->fetch_assoc()): 
                            $tglMulai = date('d/m/Y', strtotime($row['tgl_mulai']));
                            $tglSelesai = date('d/m/Y', strtotime($row['tgl_selesai']));
                            $rentangTanggal = $tglMulai . ' - ' . $tglSelesai;
                    ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td style="font-weight: 600; text-align: center;"><?= $rentangTanggal; ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['koperasi'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['yayasan'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['helmi'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['management'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">
                                <input type="text" class="rate-input rate-input-lg" 
                                    value="<?= $row['pajak'] > 0 ? number_format($row['pajak'], 0, ',', '.') : ''; ?>" 
                                    <?= !$isBendahara ? 'disabled' : ''; ?>
                                    oninput="inputMask(this)"
                                    onchange="updatePajakMingguan('<?= $row['tgl_mulai']; ?>', '<?= $row['tgl_selesai']; ?>', this)">
                            </td>
                            <td>
                                <a href="cetak-rincian.php?start=<?= $row['tgl_mulai']; ?>&end=<?= $row['tgl_selesai']; ?>" target="_blank" class="btn-print" title="Cetak PDF Rincian Mingguan">
                                    <i class="ph ph-file-pdf"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="ph ph-calendar-x" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                Belum ada data rekap KPM
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL INPUT KPM -->
<div class="modal-overlay" id="modalInput">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="ph ph-plus-circle"></i> Input KPM</h2>
            <button class="modal-close" onclick="tutupModalInput()"><i class="ph ph-x"></i></button>
        </div>

        <div class="modal-body">
            <div id="infoSudahInput" style="display:none;" class="badge-info">
                <i class="ph ph-info"></i> Data tanggal ini sudah pernah diinput. Menyimpan ulang akan menimpa data sebelumnya.
            </div>

            <div class="form-grid">
                <div class="form-group full">
                    <label>Tanggal</label>
                    <input type="date" id="tanggalInput">
                </div>
                <div class="form-group">
                    <label>KPM Porsi Besar (Rp 9.950)</label>
                    <input type="text" id="kpmBesar" placeholder="0">
                </div>
                <div class="form-group">
                    <label>KPM Porsi Kecil (Rp 7.950)</label>
                    <input type="text" id="kpmKecil" placeholder="0">
                </div>
                <div class="form-group readonly">
                    <label>Anggaran Porsi Besar</label>
                    <input type="text" id="anggaranBesar" readonly value="Rp 0">
                </div>
                <div class="form-group readonly">
                    <label>Anggaran Porsi Kecil</label>
                    <input type="text" id="anggaranKecil" readonly value="Rp 0">
                </div>
            </div>

            <div class="summary-row">
                <div class="summary-box">
                    <div class="label">Jumlah KPM</div>
                    <div class="value" id="jumlahKpm">0</div>
                </div>
                <div class="summary-box total">
                    <div class="label">Total Anggaran</div>
                    <div class="value" id="totalAnggaranPreview">Rp 0</div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="tutupModalInput()">Batal</button>
            <button class="btn btn-primary" id="btnSimpan" onclick="simpanHarian()">
                <i class="ph ph-floppy-disk"></i> Simpan Omset
            </button>
        </div>
    </div>
</div>

<script>
    const HARGA_BESAR = <?= 9950 ?>;
    const HARGA_KECIL = <?= 7950 ?>;
    const IS_BENDAHARA = <?= $isBendahara ? 'true' : 'false' ?>;
</script>
<script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>

</body>
</html>