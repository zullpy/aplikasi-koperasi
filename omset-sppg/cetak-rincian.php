<?php
// cetak-rincian.php - Preview & Cetak Rincian Omset Bulanan (Harian)
require_once '../database/koneksi.php';
require_once '../database/auth.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses halaman hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

if (!isset($_GET['bulan']) || empty($_GET['bulan'])) {
    die('Bulan tidak ditemukan');
}

$bulan = $_GET['bulan'];
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    die('Format bulan tidak valid');
}

// Ambil data rincian harian untuk bulan terpilih
$stmt = $koneksi->prepare("
    SELECT * FROM omset_sppg_harian
    WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?
    ORDER BY tanggal ASC
");
$stmt->bind_param('s', $bulan);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    die('Data omset harian untuk bulan tersebut tidak ditemukan');
}

// Hitung total untuk footer tabel
$total = [
    'total_anggaran' => 0,
    'total_kpm' => 0,
    'pagu_belanja' => 0,
    'nominal_koperasi' => 0,
    'nominal_yayasan' => 0,
    'nominal_helmi' => 0,
    'nominal_management' => 0,
];
foreach ($rows as $r) {
    $total['total_anggaran']      += (float)$r['total_anggaran'];
    $total['total_kpm']           += (int)$r['total_kpm'];
    $total['pagu_belanja']        += (float)$r['pagu_belanja'];
    $total['nominal_koperasi']    += (float)$r['nominal_koperasi'];
    $total['nominal_yayasan']     += (float)$r['nominal_yayasan'];
    $total['nominal_helmi']       += (float)$r['nominal_helmi'];
    $total['nominal_management']  += (float)$r['nominal_management'];
}

// Format bulan ke Indonesia
$bulanIndo = match(date('F', strtotime($bulan . '-01'))) {
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
    default => date('F', strtotime($bulan . '-01'))
};
$tahun = date('Y', strtotime($bulan . '-01'));
$labelBulan = $bulanIndo . ' ' . $tahun;

function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincian Omset SPPG - <?= htmlspecialchars($labelBulan) ?></title>
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* ─── Toolbar ─────────────────────────────────────────────── */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1a2b4a 0%, #2d4a7c 100%);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            min-height: 60px;
        }

        .toolbar-icon {
            font-size: 22px;
            flex-shrink: 0;
        }

        .toolbar-title {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .toolbar-subtitle {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 2px;
            white-space: nowrap;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
            overflow: hidden;
        }

        .toolbar-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-download {
            background: #10b981;
            color: white;
        }

        .btn-download:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .preview-wrapper {
            margin-top: 80px;
            padding: 30px;
            display: flex;
            justify-content: center;
        }

        .preview-container {
            background: white;
            width: 297mm; /* Landscape A4 */
            min-height: 210mm;
            padding: 15mm 20mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            flex-direction: column;
            gap: 20px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #10b981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: white;
            font-size: 16px;
            font-weight: 500;
        }

        /* ─── Kop Surat ───────────────────────────────────────────── */
        .kop-surat {
            display: flex;
            align-items: center;
            padding-bottom: 5px;
            position: relative;
            background: #fff;
        }

        .kop-logo {
            width: 90px;
            margin-right: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .kop-logo img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }

        .kop-text {
            flex: 1;
        }

        .kop-text-title {
            font-weight: bold;
            font-size: 16px;
            line-height: 1.25;
            color: #14213D;
        }

        .kop-text-address {
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.3;
            color: #000;
        }

        .garis-ganda {
            border-top: 3px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-bottom: 4px;
            background: #fff;
        }

        /* ─── Judul ───────────────────────────────────────────────── */
        .judul-laporan {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .5px;
            margin-top: 15px;
            background: #fff;
            color: #000;
        }

        .subjudul-laporan {
            text-align: center;
            font-size: 14px;
            margin-top: 4px;
            background: #fff;
            color: #000;
            margin-bottom: 20px;
        }

        /* ─── Tabel Data ──────────────────────────────────────────── */
        .tabel-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            color: #000;
        }

        .tabel-data th,
        .tabel-data td {
            border: 1px solid #000;
            padding: 6px 4px;
            background: #fff;
        }

        .tabel-data th {
            font-weight: 700;
            text-align: center;
            background: #f1f5f9;
        }

        .tabel-data td.center,
        .tabel-data th.center {
            text-align: center;
        }

        .tabel-data td.right,
        .tabel-data th.right {
            text-align: right;
        }

        .row-total td {
            font-weight: bold;
            background: #f1f5f9;
        }

        /* ─── Tanda Tangan ────────────────────────────────────────── */
        .ttd-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
            background: #fff;
            padding: 0 80px;
        }

        .ttd-box {
            width: 200px;
            text-align: center;
            font-size: 12px;
            color: #000;
            background: #fff;
        }

        .ttd-label {
            margin-bottom: 60px;
            color: #000;
        }

        .ttd-name {
            font-weight: bold;
            text-decoration: underline;
            color: #000;
        }
    </style>
</head>

<body>

    <div class="toolbar">
        <div class="toolbar-left">
            <a href="index.php" class="btn btn-back">
                <i class="ph ph-arrow-left"></i> Kembali
            </a>
            <i class="ph ph-file-pdf toolbar-icon"></i>
            <div>
                <div class="toolbar-title">Rincian Omset SPPG Bulanan</div>
                <div class="toolbar-subtitle">Koperasi Bina Usaha Sauyunan</div>
            </div>
        </div>
        <div class="toolbar-buttons">
            <button class="btn btn-download" id="downloadBtn" onclick="downloadPDF()">
                <i class="ph ph-download-simple"></i> Download PDF
            </button>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">Sedang membuat PDF...</div>
    </div>

    <div class="preview-wrapper">
        <div class="preview-container" id="pdfContent">

            <div class="kop-surat">
                <div class="kop-logo"><img src="../assets/logo.png" alt="Logo KBUS"></div>
                <div class="kop-text">
                    <div class="kop-text-title">KOPERASI<br>BINA USAHA SAUYUNAN</div>
                    <div class="kop-text-address">Panyingkiran - Singaparna<br>Kab. Tasikmalaya<br>email : kop.binausahasauyunan@gmail.com</div>
                </div>
            </div>
            <div class="garis-ganda"></div>

            <div class="judul-laporan">LAPORAN RINCIAN OMSET HARIAN SPPG</div>
            <div class="subjudul-laporan">Bulan: <?= htmlspecialchars($labelBulan) ?></div>

            <table class="tabel-data">
                <thead>
                    <tr>
                        <th rowspan="2" class="center" style="width: 10%;">Tanggal</th>
                        <th rowspan="2" class="right" style="width: 10%;">Total Anggaran</th>
                        <th rowspan="2" class="center" style="width: 6%;">KPM</th>
                        <th rowspan="2" class="right" style="width: 10%;">Belanja Foodcost</th>
                        <th colspan="2" class="center" style="width: 15%;">KBUS</th>
                        <th colspan="2" class="center" style="width: 15%;">Yayasan</th>
                        <th colspan="2" class="center" style="width: 15%;">Koperasi</th>
                        <th rowspan="2" class="right" style="width: 11%;">Nominal Management</th>
                    </tr>
                    <tr>
                        <th class="center" style="width: 5%;">Revenue</th>
                        <th class="right" style="width: 10%;">Nominal</th>
                        <th class="center" style="width: 5%;">Revenue</th>
                        <th class="right" style="width: 10%;">Nominal</th>
                        <th class="center" style="width: 5%;">Revenue</th>
                        <th class="right" style="width: 10%;">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="center"><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                            <td class="right"><?= number_format($row['total_anggaran'], 0, ',', '.') ?></td>
                            <td class="center"><?= $row['total_kpm'] ?></td>
                            <td class="right"><?= number_format($row['pagu_belanja'], 0, ',', '.') ?></td>
                            
                            <td class="center"><?= number_format($row['keuntungan_koperasi'], 0, ',', '.') ?></td>
                            <td class="right"><?= number_format($row['nominal_koperasi'], 0, ',', '.') ?></td>
                            
                            <td class="center"><?= number_format($row['keuntungan_yayasan'], 0, ',', '.') ?></td>
                            <td class="right"><?= number_format($row['nominal_yayasan'], 0, ',', '.') ?></td>
                            
                            <td class="center"><?= number_format($row['keuntungan_helmi'], 0, ',', '.') ?></td>
                            <td class="right"><?= number_format($row['nominal_helmi'], 0, ',', '.') ?></td>
                            
                            <td class="right"><?= number_format($row['nominal_management'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="row-total">
                        <td class="center">TOTAL</td>
                        <td class="right"><?= number_format($total['total_anggaran'], 0, ',', '.') ?></td>
                        <td class="center"><?= $total['total_kpm'] ?></td>
                        <td class="right"><?= number_format($total['pagu_belanja'], 0, ',', '.') ?></td>
                        
                        <td class="center">-</td>
                        <td class="right"><?= number_format($total['nominal_koperasi'], 0, ',', '.') ?></td>
                        
                        <td class="center">-</td>
                        <td class="right"><?= number_format($total['nominal_yayasan'], 0, ',', '.') ?></td>
                        
                        <td class="center">-</td>
                        <td class="right"><?= number_format($total['nominal_helmi'], 0, ',', '.') ?></td>
                        
                        <td class="right"><?= number_format($total['nominal_management'], 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

        </div>
    </div>

    <script>
        const BULAN_VAL = <?php echo json_encode($bulan); ?>;

        async function downloadPDF() {
            const btn = document.getElementById('downloadBtn');
            const overlay = document.getElementById('loadingOverlay');
            const content = document.getElementById('pdfContent');
            btn.disabled = true;
            overlay.classList.add('active');

            const opt = {
                margin: [8, 8, 8, 8],
                filename: `RINCIAN-OMSET-${BULAN_VAL}.pdf`,
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    letterRendering: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'landscape'
                }
            };
            try {
                await html2pdf().set(opt).from(content).save();
                setTimeout(() => {
                    overlay.classList.remove('active');
                    btn.disabled = false;
                    const toast = document.createElement('div');
                    toast.style.cssText = `position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; z-index: 3000;`;
                    toast.innerHTML = '<i class="ph ph-check-circle"></i> PDF berhasil diunduh!';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                }, 1000);
            } catch (err) {
                console.error(err);
                overlay.classList.remove('active');
                btn.disabled = false;
                alert('Gagal membuat PDF: ' + err.message);
            }
        }
    </script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>
