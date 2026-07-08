<?php
// cetak-laporan-sppg.php - Preview & Cetak Laporan Belanja
require_once '../database/koneksi.php';
require_once '../database/auth.php';

// Check jika tidak ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID pengajuan tidak ditemukan');
}

$id = intval($_GET['id']);

// ── AMBIL TANDA TANGAN DARI DATABASE ─────────────────────────────
$ttdData = [];
try {
    $stmt = $koneksi->prepare("
        SELECT role_penanda, signature_data, timestamp
        FROM tanda_tangan_digital
        WHERE pengajuan_id = ?
        AND role_penanda IN ('bendahara', 'ketua')
        ORDER BY role_penanda ASC
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ttdData[$row['role_penanda']] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $ttdData = [];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Pengajuan Rencana Anggaran Belanja - KBUS</title>
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
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

        .error-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .error-icon {
            font-size: 64px;
            color: #ef4444;
            margin-bottom: 20px;
        }

        .error-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .kop-surat {
            display: flex;
            align-items: center;
            padding-bottom: 5px;
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
            line-height: 1.2;
        }

        .kop-text-address {
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.3;
        }

        .garis-ganda {
            border-top: 3px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-bottom: 15px;
        }

        .judul-laporan {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .tabel-info,
        .tabel-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            color: #000;
        }

        .tabel-info td {
            border: none;
            padding: 4px 6px;
        }

        .tabel-data th,
        .tabel-data td {
            border: 1px solid #000;
            padding: 6px;
        }

        .tabel-info {
            margin-bottom: 10px;
        }

        .tabel-data th {
            background-color: transparent;
            font-weight: normal;
            text-align: center;
        }

        .tabel-data td.center {
            text-align: center;
        }

        .tabel-data td.right {
            text-align: right;
        }

        .baris-kosong {
            height: 25px;
        }

        .uang-text {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 12px 0;
            letter-spacing: 0.5px;
        }

        .summary-box {
            margin-top: 15px;
            padding: 10px 15px;
            border: 1px solid #000;
            background: #fafafa;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }

        .summary-row.total-pengajuan {
            border-bottom: 1px dashed #999;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .summary-row.total-disetujui {
            font-weight: bold;
        }

        .summary-label {
            color: #333;
        }

        .summary-value {
            font-weight: bold;
            color: #000;
        }

        .ttd-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }

        .ttd-box {
            flex: 1;
            text-align: center;
        }

        .ttd-label {
            font-size: 12px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .ttd-img-wrap {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }

        .ttd-img-wrap img {
            max-height: 150px;
            max-width: 300px;
            object-fit: contain;
        }

        .ttd-timestamp {
            font-size: 9px;
            color: #666;
            margin-bottom: 8px;
        }

        .ttd-underline {
            border-bottom: 1px solid #000;
            margin: 0 20px 8px;
        }

        .ttd-name {
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }

        .ttd-placeholder {
            color: #999;
            font-size: 11px;
            font-style: italic;
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
                <div class="toolbar-title">Surat Pengajuan Rencana Anggaran Belanja</div>
                <div class="toolbar-subtitle">Koperasi Bina Usaha Sauyunan</div>
            </div>
        </div>
        <div class="toolbar-buttons">
            <!-- <button class="btn btn-back" onclick="window.history.back()">
                <i class="ph ph-arrow-left"></i> Kembali
            </button> -->
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
            <div id="contentLoader" style="text-align: center; padding: 40px;">
                <div class="spinner" style="margin: 0 auto 20px;"></div>
                <div style="color: #666;">Memuat data...</div>
            </div>
        </div>
    </div>
    <script>
        const PENGATURAN_ID = <?php echo $id; ?>;
        const TTD_DATA = <?php echo json_encode($ttdData); ?>;

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function formatDateShort(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        async function loadData() {
            try {
                const res = await fetch('../database/api-belanja.php?action=list');
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Gagal memuat data');
                const item = result.data.find(d => d.id == PENGATURAN_ID);
                if (!item) throw new Error('Data tidak ditemukan');
                renderContent(item);
            } catch (err) {
                showError(err.message);
            }
        }

        function renderContent(item) {
            const items = item.detail_items || item.items || [];
            const totalBelanja = parseFloat(item.total_belanja) || 0;
            const uangMasuk = parseFloat(item.uang_masuk) || 0;
            const sisaUang = uangMasuk - totalBelanja;

            let labelSisa = "SISA UANG";
            let nominalSisa = sisaUang;
            if (sisaUang < 0) {
                labelSisa = "MINUS SISA UANG";
                nominalSisa = Math.abs(sisaUang);
            }

            const textUangMasuk = uangMasuk > 0 ? formatRupiah(uangMasuk) : '.............';
            const textSisaUang = (uangMasuk > 0 || totalBelanja > 0) ? formatRupiah(nominalSisa) : '.............';

            let rowsHtml = '';
            for (let i = 0; i < 10; i++) {
                const it = items[i];
                const subtotal = it ? (parseFloat(it.harga) || 0) * (parseInt(it.qty) || 0) : null;
                rowsHtml += `<tr>
            <td class="center baris-kosong">${i + 1}</td>
            <td>${it ? it.nama_barang : ''}</td>
            <td class="center">${it ? it.qty : ''}</td>
            <td class="center">${it ? it.satuan : ''}</td>
            <td class="right">${it ? formatRupiah(it.harga) : ''}</td>
            <td class="right">${it && subtotal ? formatRupiah(subtotal) : ''}</td>
        </tr>`;
            }

            const roleMapping = {
                'bendahara': {
                    label: 'Menyetujui,<br>Bendahara Koperasi',
                    nama: 'NANCY FEBI YOLLA'
                },
                'ketua': {
                    label: 'Mengetahui dan Menyetujui,<br>Ketua Koperasi',
                    nama: 'YUDI HENDRIAN'
                }
            };

            function renderTtdBox(roleKey) {
                const mapping = roleMapping[roleKey];
                const ttd = TTD_DATA[roleKey] || null;

                const imgHtml = ttd ?
                    `<div class="ttd-img-wrap"><img src="${ttd.signature_data}" alt="TTD ${mapping.nama}"></div>
                    <div class="ttd-timestamp">${formatDateTime(ttd.timestamp)}</div>` :
                    `<div class="ttd-placeholder">(Belum ditandatangani)</div>`;

                return `
            <div class="ttd-box">
                <div class="ttd-label">${mapping.label}</div>
                ${imgHtml}
                <div class="ttd-underline"></div>
                <div class="ttd-name">${mapping.nama}</div>
            </div>
        `;
            }

            const html = `
        <div class="kop-surat">
            <div class="kop-logo"><img src="../assets/logo.png" alt="Logo KBUS"></div>
            <div class="kop-text">
                <div class="kop-text-title">KOPERASI<br>BINA USAHA SAUYUNAN</div>
                <div class="kop-text-address">Panyingkiran - Singaparna<br>Kab. Tasikmalaya<br>email : kop.binausahasauyunan@gmail.com</div>
            </div>
        </div>
        <div class="garis-ganda"></div>
        <div class="judul-laporan">SURAT PENGAJUAN</div>
        <div class="judul-laporan">RENCANA ANGGARAN BELANJA PELAYANAN SPPG</div>
        <table class="tabel-info">
            <tr>
                <td style="width: 12%;">Tanggal</td>
                <td style="width: 2%; text-align: center;">:</td>
                <td style="width: 46%;">${formatDateShort(item.tanggal)}</td>
                <td style="width: 15%;">Jumlah Porsi</td>
                <td style="width: 2%; text-align: center;">:</td>
                <td style="width: 23%;">${item.jumlah_porsi || ''}</td>
            </tr>
            <tr>
                <td>Menu</td>
                <td style="text-align: center;">:</td>
                <td colspan="4">${item.nama_menu || ''}</td>
            </tr>
        </table>
        <table class="tabel-data">
            <thead>
                <tr>
                    <th style="width: 6%;">No</th>
                    <th style="width: 36%;">Nama Barang</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 13%;">Satuan</th>
                    <th style="width: 17%;">Harga</th>
                    <th style="width: 18%;">Sub Total</th>
                </tr>
            </thead>
            <tbody>${rowsHtml}
                <tr>
                    <td colspan="4" style="border-right: 1px solid #000;"></td>
                    <td class="center" style="font-weight: bold; vertical-align: middle;">Total<br>Belanja</td>
                    <td class="right" style="vertical-align: middle;">${totalBelanja > 0 ? formatRupiah(totalBelanja) : ''}</td>
                </tr>
            </tbody>
        </table>
        <div class="summary-box">
            <div class="summary-row total-pengajuan">
                <span class="summary-label">Total Pengajuan</span>
                <span class="summary-value">${formatRupiah(totalBelanja)}</span>
            </div>
            <div class="summary-row total-disetujui">
                <span class="summary-label">Total Pengajuan yang Disetujui</span>
                <span class="summary-value">${uangMasuk > 0 ? formatRupiah(uangMasuk) : '-'}</span>
            </div>
        </div>
        ${item.catatan_bendahara ? `<div style="font-size: 11px; margin-top: 10px; color: #444;">Catatan: ${item.catatan_bendahara}</div>` : ''}
        <div class="ttd-section">
            ${renderTtdBox('bendahara')}
            ${renderTtdBox('ketua')}
        </div>
    `;

            document.getElementById('pdfContent').innerHTML = html;
            document.title = `Surat Pengajuan Rencana Anggaran Belanja - ${formatDateShort(item.tanggal)}`;
        }

        function showError(message) {
            document.getElementById('pdfContent').innerHTML = `
        <div class="error-state">
            <i class="ph ph-warning-circle error-icon"></i>
            <div class="error-title">Gagal Memuat Data</div>
            <div>${message}</div>
            <button class="btn btn-back" onclick="window.history.back()" style="margin-top: 20px; display: inline-flex;">
                <i class="ph ph-arrow-left"></i> Kembali
            </button>
        </div>
    `;
        }

        async function downloadPDF() {
            const btn = document.getElementById('downloadBtn');
            const overlay = document.getElementById('loadingOverlay');
            const content = document.getElementById('pdfContent');
            btn.disabled = true;
            overlay.classList.add('active');
            const opt = {
                margin: [8, 8, 8, 8],
                filename: `SPRAB-${PENGATURAN_ID}.pdf`,
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

        window.addEventListener('DOMContentLoaded', loadData);
    </script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>