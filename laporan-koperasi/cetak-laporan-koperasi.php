<?php
// cetak-laporan-koperasi.php - Preview & Cetak PDF Laporan Belanja Koperasi
// Cuma dipakai untuk pengajuan jenis 'stok' (satu-satunya jenis yang punya
// tombol Cetak + Tanda Tangan). Jenis lain (peralatan/operasional) memakai
// upload kwitansi/nota manual, bukan laporan cetak ini.
require_once '../database/koneksi.php';
require_once '../database/auth.php';
require_once '../database/laporan-koperasi-func.php';

// Check jika tidak ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID pengajuan tidak ditemukan');
}

$id = intval($_GET['id']);

// ── AMBIL DATA PENGAJUAN ──────────────────────────────────────────
$sql  = "SELECT * FROM pengajuan_anggaran WHERE id = ?";
$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res  = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$data) {
    die('Data pengajuan tidak ditemukan');
}

// Laporan cetak cuma untuk jenis 'stok' — jenis lain pakai upload kwitansi/nota.
if ($data['jenis'] !== 'stok') {
    die('Laporan cetak hanya tersedia untuk pengajuan jenis Stok. Untuk jenis lain, gunakan fitur upload kwitansi/nota.');
}

// ── AMBIL DETAIL ITEM BELANJA ─────────────────────────────────────
$items = getDetailItemKoperasi($koneksi, $id);

// ── AMBIL TANDA TANGAN DARI DATABASE (ttd_laporan_koperasi) ──────
$ttdData = getTtdPengajuanKoperasi($koneksi, $id);

// ── HITUNG RINGKASAN (pakai nominal yang sudah di-ACC admin) ─────
$nominalDisetujui = isset($data['saldo']) && $data['saldo'] !== null ? (float) $data['saldo'] : (float) $data['jumlah'];

$saldoMasuk   = hitungSaldoMasukKoperasi($koneksi, $id, $nominalDisetujui);
$totalBelanja = hitungTotalBelanjaKoperasi($koneksi, $id, $data['jenis'], $nominalDisetujui);
$sisaSaldo    = $saldoMasuk - $totalBelanja;

$textSaldoMasuk = $saldoMasuk > 0 ? rupiah($saldoMasuk) : '.............';

// Label & nominal sisa saldo: berubah jadi "MINUS SISA SALDO" saat defisit
$labelSisaSaldo   = 'SISA SALDO';
$nominalSisaSaldo = $sisaSaldo;
if ($sisaSaldo < 0) {
    $labelSisaSaldo   = 'MINUS SISA SALDO';
    $nominalSisaSaldo = abs($sisaSaldo);
}
$textSisaSaldo = ($saldoMasuk > 0 || $totalBelanja > 0) ? rupiah($nominalSisaSaldo) : '.............';

// ── FORMAT TANGGAL KE BAHASA INDONESIA ────────────────────────────
function formatTanggalIndo($tanggal)
{
    if (empty($tanggal)) return '';
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function formatWaktuIndo($ts)
{
    if (empty($ts)) return '';
    $bulanSingkat = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];
    $t = strtotime($ts);
    return date('d', $t) . ' ' . $bulanSingkat[(int) date('n', $t)] . ' ' . date('Y H:i', $t);
}

// ── PEMETAAN ROLE PENANDA TANGAN SESUAI FORM ──
// Urutan tampil dari kiri ke kanan: Admin, Bendahara, Ketua.
$urutanRoleTtd = ['admin', 'bendahara', 'ketua'];
$roleMapping = [
    'admin'     => ['label' => 'Juru Bayar',            'nama' => 'EVIN YENTIANA'],
    'bendahara' => ['label' => 'Bendahara<br>Koperasi', 'nama' => 'NANCY FEBI YOLLA'],
    'ketua'     => ['label' => 'Ketua<br>Koperasi',     'nama' => 'YUDI HENDRIAN'],
];

function renderTtdBoxKoperasi($roleKey, $roleMapping, $ttdData)
{
    $mapping = $roleMapping[$roleKey];
    $ttd = $ttdData[$roleKey] ?? null;

    ob_start();
?>
    <div class="ttd-box">
        <div class="ttd-label"><?= $mapping['label'] ?></div>
        <div class="ttd-sig-area">
            <?php if ($ttd): ?>
                <div class="ttd-slot">
                    <img src="../uploads/<?= htmlspecialchars($ttd['signature_path']) ?>" alt="TTD <?= htmlspecialchars($mapping['nama']) ?>">
                </div>
                <?php if (!empty($ttd['signed_at'])): ?>
                    <div class="ttd-timestamp"><?= formatWaktuIndo($ttd['signed_at']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="ttd-name"><?= htmlspecialchars($mapping['nama']) ?></div>
    </div>
<?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Belanja Koperasi - <?= htmlspecialchars($data['tujuan']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
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
            width: 210mm;
            min-height: 297mm;
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
        .judul-laporan-belanja {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: .5px;
            padding: 10px 0;
            background: #fff;
        }

        /* ─── Info Tanggal / Tujuan / Jenis ────────────────────────── */
        .tabel-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            color: #000;
            background: #fff;
        }

        .tabel-info td {
            border: none;
            padding: 5px 6px;
        }

        /* ─── Saldo Masuk / Sisa Saldo ──────────────────────────────── */
        .uang-text {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 0.5px;
            background: #fff;
            padding: 4px 0;
        }

        /* ─── Tabel Data Belanja ──────────────────────────────────── */
        .tabel-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            color: #000;
        }

        .tabel-data th,
        .tabel-data td {
            border: 1px solid #000;
            padding: 6px;
            background: #fff;
        }

        .tabel-data th {
            font-weight: 700;
            text-align: left;
        }

        .tabel-data th.center,
        .tabel-data td.center {
            text-align: center;
        }

        .tabel-data th.right,
        .tabel-data td.right {
            text-align: right;
        }

        .baris-kosong {
            height: 25px;
        }

        /* ─── Tanda Tangan (4 kolom) ──────────────────────────────── */
        .ttd-section {
            margin-top: 45px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            background: #fff;
            padding-bottom: 30px;
        }

        .ttd-box {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .ttd-label {
            font-size: 13px;
            text-decoration: underline;
            line-height: 1.4;
            min-height: 36px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .ttd-sig-area {
            min-height: 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .ttd-slot {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ttd-slot img {
            max-height: 60px;
            max-width: 140px;
            object-fit: contain;
        }

        .ttd-timestamp {
            font-size: 8.5px;
            color: #666;
            margin-top: 2px;
        }

        .ttd-name {
            font-weight: bold;
            font-size: 13px;
        }

        @media print {

            .toolbar,
            .loading-overlay {
                display: none !important;
            }

            body {
                background: white;
            }

            .preview-wrapper {
                margin-top: 0;
                padding: 0;
            }

            .preview-container {
                box-shadow: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <i class="ph ph-file-pdf toolbar-icon"></i>
            <div>
                <div class="toolbar-title">Laporan Belanja Koperasi</div>
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

            <div class="judul-laporan-belanja">LAPORAN BELANJA KOPERASI</div>

            <table class="tabel-info">
                <tr>
                    <td style="width: 10%;">Tanggal</td>
                    <td style="width: 2%; text-align: center;">:</td>
                    <td style="width: 46%;"><?= formatTanggalIndo($data['tanggal']) ?></td>
                    <!-- <td style="width: 15%;">Jenis</td>
                    <td style="width: 2%; text-align: center;">:</td>
                    <td style="width: 25%;"><?= htmlspecialchars(labelJenisKoperasi($data['jenis'])) ?></td> -->
                </tr>
                <!-- <tr>
                    <td>Tujuan</td>
                    <td style="text-align: center;">:</td>
                    <td colspan="4"><?= htmlspecialchars($data['tujuan']) ?></td>
                </tr> -->
            </table>

            <div class="uang-text">SALDO MASUK : <?= $textSaldoMasuk ?></div>

            <table class="tabel-data">
                <thead>
                    <tr>
                        <th class="center" style="width: 7%;">No</th>
                        <th style="width: 38%;">Nama Barang</th>
                        <th class="center" style="width: 10%;">Qty</th>
                        <th class="center" style="width: 12%;">Satuan</th>
                        <th class="right" style="width: 15%;">Harga</th>
                        <th class="right" style="width: 18%;">Sub Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 10; $i++):
                        $it = $items[$i] ?? null;
                    ?>
                        <tr>
                            <td class="center baris-kosong"><?= $i + 1 ?></td>
                            <td><?= $it ? htmlspecialchars($it['keterangan']) : '' ?></td>
                            <td class="center"><?= $it ? rtrim(rtrim(number_format((float) $it['qty'], 2, ',', '.'), '0'), ',') : '' ?></td>
                            <td class="center"><?= $it ? htmlspecialchars($it['satuan']) : '' ?></td>
                            <td class="right"><?= $it ? number_format((float) $it['harga_satuan'], 0, ',', '.') : '' ?></td>
                            <td class="right"><?= $it ? number_format((float) $it['subtotal'], 0, ',', '.') : '' ?></td>
                        </tr>
                    <?php endfor; ?>
                    <tr>
                        <td colspan="4" style="border-right: 1px solid #000;"></td>
                        <td class="center" style="font-weight: bold; vertical-align: middle;">Total<br>Belanja</td>
                        <td class="right" style="vertical-align: middle; font-weight: bold;">
                            <?= $totalBelanja > 0 ? number_format($totalBelanja, 0, ',', '.') : '' ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="uang-text"><?= $labelSisaSaldo ?> : <?= $textSisaSaldo ?></div>

            <div class="ttd-section">
                <?php foreach ($urutanRoleTtd as $roleKey): ?>
                    <?= renderTtdBoxKoperasi($roleKey, $roleMapping, $ttdData) ?>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <script>
        const PENGAJUAN_ID = <?php echo $id; ?>;

        async function downloadPDF() {
            const btn = document.getElementById('downloadBtn');
            const overlay = document.getElementById('loadingOverlay');
            const content = document.getElementById('pdfContent');
            btn.disabled = true;
            overlay.classList.add('active');
            const opt = {
                margin: [8, 8, 8, 8],
                filename: `LAPORAN-BELANJA-KOPERASI-${PENGAJUAN_ID}.pdf`,
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
                    orientation: 'portrait'
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