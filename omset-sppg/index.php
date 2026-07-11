<?php
require_once '../database/auth.php';
require_once '../database/koneksi.php';
$activePage = 'omset-sppg';
include '../components/navbar.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses halaman hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

$isBendahara = ($userRole === 'bendahara');

// Ambil data rekap bulanan (menggabungkan rekap_omset_bulanan untuk bulan lalu dan omset_sppg_harian untuk bulan berjalan)
$queryRekap = "
    SELECT 
        bulan_val,
        SUM(koperasi) AS koperasi,
        SUM(yayasan) AS yayasan,
        SUM(helmi) AS helmi,
        SUM(management) AS management
    FROM (
        SELECT 
            bulan AS bulan_val,
            total_nominal_koperasi AS koperasi,
            total_nominal_yayasan AS yayasan,
            total_nominal_helmi AS helmi,
            total_nominal_management AS management
        FROM rekap_omset_bulanan
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(tanggal, '%Y-%m') AS bulan_val,
            SUM(nominal_koperasi) AS koperasi,
            SUM(nominal_yayasan) AS yayasan,
            SUM(nominal_helmi) AS helmi,
            SUM(nominal_management) AS management
        FROM omset_sppg_harian
        WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        GROUP BY bulan_val
    ) AS gabungan
    GROUP BY bulan_val
    ORDER BY bulan_val DESC
";
$resultRekap = $koneksi->query($queryRekap);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Omset SPPG</title>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="style.css">
<link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="container">
    <h1><i class="ph ph-currency-circle-dollar"></i> Omset SPPG</h1>
    <div class="subtitle">Input KPM harian & rincian pembagian omset bulan <span id="labelBulan">-</span></div>

    <!-- TABEL RINCIAN BULAN BERJALAN -->
    <div class="card">
        <div class="card-header-row">
            <h2><i class="ph ph-table"></i> Rincian Omset Harian </h2>
            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                <?php if ($isBendahara): ?>
                <button class="btn btn-primary" onclick="bukaModalInput()">
                    <i class="ph ph-plus-circle"></i> Input KPM Hari Ini
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
                        <th rowspan="2">KPM</th>
                        <th rowspan="2">Belanja<br>Foodcost</th>
                        <th colspan="2">KBUS</th>
                        <th colspan="2">Yayasan</th>
                        <th colspan="2">Koperasi</th>
                        <th rowspan="2">Nominal<br>Management</th>
                    </tr>
                    <tr class="group-header">
                        <th>Revenue</th><th>Nominal</th>
                        <th>Revenue</th><th>Nominal</th>
                        <th>Revenue</th><th>Nominal</th>
                    </tr>
                </thead>
                <tbody id="tbodyOmset">
                    <tr><td colspan="11" class="empty-state">Memuat data...</td></tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td></td>
                        <td></td>
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

    <!-- TABEL REKAP KPM SPPG (BULANAN) -->
    <div class="card" id="rekap-kpm-bulanan" style="margin-top: 32px;">
        <div class="card-header-row">
            <h2><i class="ph ph-trend-up"></i> Rekap KPM SPPG (Bulanan)</h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="80px">No</th>
                        <th>Bulan</th>
                        <th>KBUS</th>
                        <th>Yayasan</th>
                        <th>Koperasi</th>
                        <th>Management</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($resultRekap && $resultRekap->num_rows > 0): 
                        $no = 1;
                        while ($row = $resultRekap->fetch_assoc()): 
                            // Translate month to Indonesian
                            $bulanIndo = match(date('F', strtotime($row['bulan_val'] . '-01'))) {
                                'January' => 'Januari',
                                'February' => 'Februari',
                                'March' => 'Maret',
                                'April' => 'April',
                                'May' => 'Mei',
                                'June' => 'Juni',
                                'July' => 'Juli',
                                'August' => 'Agustus',
                                'September' => 'September',
                                'October' => 'Oktober',
                                'November' => 'November',
                                'December' => 'Desember',
                                default => date('F', strtotime($row['bulan_val'] . '-01'))
                            };
                            $tahun = date('Y', strtotime($row['bulan_val'] . '-01'));
                            $label = $bulanIndo . ' ' . $tahun;
                    ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td style="font-weight: 600; text-align: left;"><?= $label; ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['koperasi'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['yayasan'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['helmi'], 0, ',', '.'); ?></td>
                            <td class="nominal-cell">Rp <?= number_format($row['management'], 0, ',', '.'); ?></td>
                            <td>
                                <a href="cetak-rincian.php?bulan=<?= $row['bulan_val']; ?>" target="_blank" class="btn-print" title="Cetak PDF Rincian Bulanan">
                                    <i class="ph ph-file-pdf"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="7" class="empty-state">
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

<!-- MODAL INPUT KPM HARI INI -->
<div class="modal-overlay" id="modalInput">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="ph ph-plus-circle"></i> Input KPM Hari Ini</h2>
            <button class="modal-close" onclick="tutupModalInput()"><i class="ph ph-x"></i></button>
        </div>

        <div class="modal-body">
            <div id="infoSudahInput" style="display:none;" class="badge-info">
                <i class="ph ph-info"></i> Data hari ini sudah pernah diinput. Menyimpan ulang akan menimpa data sebelumnya.
            </div>

            <div class="form-grid">
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
                <i class="ph ph-floppy-disk"></i> Simpan Omset Hari Ini
            </button>
        </div>
    </div>
</div>

<script>
    const HARGA_BESAR = <?= 9950 ?>;
    const HARGA_KECIL = <?= 7950 ?>;
    const IS_BENDAHARA = <?= $isBendahara ? 'true' : 'false' ?>;
</script>
<script src="script.js"></script>

</body>
</html>