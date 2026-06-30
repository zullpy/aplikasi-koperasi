<?php
require_once '../database/auth.php';
$pengajuanId = isset($_GET['id']) ? $_GET['id'] : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat Pengajuan Anggaran</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --ink: #111;
            --muted: #555;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Calibri, Arial, system-ui, sans-serif;
            background: #eef0f3;
            margin: 0;
            padding: 0;
            color: var(--ink);
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

        .page-area {
            display: flex;
            justify-content: center;
            padding: 24px 16px 60px;
        }

        .sheet {
            background: #fff;
            width: 100%;
            max-width: 760px;
            min-height: 900px;
            padding: 28px 36px 50px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .08);
            font-size: 13px;
            margin-top: 90px;
        }

        .loading,
        .err-box {
            text-align: center;
            padding: 60px 0;
            color: #888;
            font-size: 13px;
            font-family: system-ui, sans-serif;
        }

        .err-box {
            color: #b91c1c;
        }

        /* ===== KOP SURAT ===== */
        .kop {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 8px;
        }

        .kop .logo-badge {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: contain;
        }

        .kop .org h1 {
            font-size: 16px;
            margin: 0 0 2px;
            letter-spacing: .3px;
        }

        .kop .org p {
            font-size: 11.5px;
            color: var(--muted);
            margin: 0;
            line-height: 1.4;
        }

        .kop-rule {
            border: none;
            border-top: 2px solid var(--ink);
            margin: 6px 0 16px;
        }

        .doc-title {
            text-align: center;
            margin-bottom: 18px;
        }

        .doc-title h2 {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            line-height: 1.4;
            text-transform: uppercase;
        }

        /* ===== META INFO ===== */
        .meta-info {
            margin-bottom: 16px;
            font-size: 13px;
        }

        .meta-info .row {
            display: flex;
            margin-bottom: 4px;
        }

        .meta-info .label {
            width: 190px;
            flex-shrink: 0;
        }

        .meta-info .colon {
            width: 14px;
            flex-shrink: 0;
        }

        .meta-info .value {
            font-weight: 700;
        }

        /* ===== TABEL ===== */
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 0;
        }

        table.items th,
        table.items td {
            border: 1px solid var(--ink);
            padding: 6px 7px;
        }

        table.items th {
            background: #f5f6f8;
            font-weight: 600;
            text-align: center;
        }

        table.items td {
            height: 26px;
        }

        table.items td.num {
            text-align: right;
        }

        table.items td.center {
            text-align: center;
        }

        .total-row td {
            font-weight: 700;
            text-align: center;
        }

        .total-row td.num {
            text-align: right;
        }

        /* ===== OPERASIONAL (tanpa tabel item) ===== */
        .ops-ket {
            margin-top: 16px;
            margin-bottom: 10px;
        }

        .ops-ket .label {
            margin-bottom: 4px;
        }

        .ops-box {
            border: 1px solid var(--ink);
            min-height: 90px;
            padding: 8px 10px;
            font-size: 13px;
        }

        /* ===== STATUS ===== */
        .status-line {
            margin-top: 14px;
            font-size: 12px;
            color: var(--muted);
        }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ===== TANDA TANGAN ===== */
        .sign-area {
            margin-top: 36px;
            font-size: 13px;
        }

        .sign-head {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .sign-head .sign-head-left {
            width: 32%;
            text-align: center;
        }

        .sign-head .sign-head-right {
            width: 64%;
            text-align: center;
        }

        .sign-row {
            display: flex;
            justify-content: space-between;
        }

        .sign-area .col {
            text-align: center;
            width: 32%;
        }

        .sign-area .ttl {
            margin-bottom: 2px;
        }

        .sign-area .role {
            margin-bottom: 50px;
        }

        .sign-area .name {
            font-weight: 700;
            text-decoration: underline;
        }

        .sign-area .ttd-slot {
            height: 60px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 4px;
        }

        .sign-area .ttd-slot img {
            max-height: 60px;
            max-width: 100%;
            object-fit: contain;
        }

        .sign-area .ttd-empty {
            border-bottom: 1px solid var(--ink);
            width: 80%;
            height: 1px;
            margin: 0 auto 4px;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                background: #fff;
            }

            .page-area {
                padding: 0;
            }

            .sheet {
                box-shadow: none;
                max-width: 100%;
                padding: 0;
                min-height: auto;
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

    <div class="page-area">
        <div class="sheet" id="sheet">
            <div class="loading">Memuat data pengajuan...</div>
        </div>
    </div>

    <script>
        const PENGAJUAN_ID = <?php echo json_encode($pengajuanId); ?>;

        function fmtRupiah(n) {
            return Number(n || 0).toLocaleString('id-ID');
        }

        function fmtDate(d) {
            if (!d) return '-';
            const p = String(d).split('-');
            if (p.length < 3) return d;
            const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return p[2] + ' ' + bulan[parseInt(p[1]) - 1] + ' ' + p[0];
        }

        function goBack() {
            if (window.opener) {
                window.close();
            } else {
                window.history.back();
            }
        }

        function downloadPDF() {
            const btn = document.getElementById('downloadBtn');
            const sheet = document.getElementById('sheet');
            const originalLabel = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner"></i> Memproses...';

            html2pdf().set({
                margin: 0,
                filename: 'pengajuan-' + PENGAJUAN_ID + '.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            }).from(sheet).save().finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalLabel;
            });
        }

        function extractArray(json) {
            if (!json) return null;
            if (Array.isArray(json)) return json;
            if (Array.isArray(json.data)) return json.data;
            if (Array.isArray(json.items)) return json.items;
            if (Array.isArray(json.result)) return json.result;
            for (const k of Object.keys(json)) {
                if (Array.isArray(json[k])) return json[k];
            }
            return null;
        }

        function statusBadge(status) {
            if (status === 'approved') return '<span class="badge approved">Disetujui</span>';
            if (status === 'rejected') return '<span class="badge rejected">Ditolak</span>';
            return '<span class="badge pending">Menunggu Persetujuan</span>';
        }

        // ===== KOP SURAT (sama untuk semua jenis) =====
        function renderKop() {
            return `
                <div class="kop">
                    <img src="../assets/logo.png" alt="Logo KBUS" class="logo-badge">
                    <div class="org">
                        <h1>KOPERASI<br>BINA USAHA SAUYUNAN</h1>
                        <p>
                            Panyingkiran - Singaparna<br>
                            Kab. Tasikmalaya<br>
                            email : kop.binausahasauyunan@gmail.com
                        </p>
                    </div>
                </div>
                <hr class="kop-rule">
                <div class="doc-title">
                    <h2>Surat Pengajuan<br>Anggaran Belanja Koperasi</h2>
                </div>
            `;
        }

        // ===== TANDA TANGAN (ambil dari kolom ttd_admin / ttd_bendahara / ttd_ketua) =====
        function ttdSlot(ttdData) {
            if (ttdData) {
                return `<div class="ttd-slot"><img src="${ttdData}" alt="TTD"></div>`;
            }
            return `<div class="ttd-slot"><div class="ttd-empty"></div></div>`;
        }

        function renderSignature(i) {
            return `
                <div class="sign-area">
                    <div class="sign-head">
                        <div class="sign-head-left">Yang mengajukan,</div>
                        <div class="sign-head-right">Mengetahui dan Menyetujui</div>
                    </div>
                    <div class="sign-row">
                        <div class="col">
                            <div class="role">Admin</div>
                            ${ttdSlot(i.ttd_admin)}
                            <div class="name">EVIN YENTIANA</div>
                        </div>
                        <div class="col">
                            <div class="role">Bendahara</div>
                            ${ttdSlot(i.ttd_bendahara)}
                            <div class="name">NANCY FEBI YOLLA</div>
                        </div>
                        <div class="col">
                            <div class="role">Ketua</div>
                            ${ttdSlot(i.ttd_ketua)}
                            <div class="name">YUDI HENDRIAN</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // ===== FORMAT STOK (foto 1) =====
        function renderStok(i) {
            const items = (i.items || []).slice();
            while (items.length < 10) items.push({});
            const rows = items.map((it, idx) => `
                <tr>
                    <td class="center">${idx + 1}</td>
                    <td>${it.keterangan || ''}</td>
                    <td class="center">${it.sisaStok || ''}</td>
                    <td class="center">${it.qty || ''}</td>
                    <td class="center">${it.satuan || ''}</td>
                    <td class="num">${it.harga ? fmtRupiah(it.harga) : ''}</td>
                    <td class="num">${it.subtotal ? fmtRupiah(it.subtotal) : ''}</td>
                </tr>`).join('');

            return `
                ${renderKop()}
                <div class="meta-info">
                    <div class="row"><div class="label">Tanggal</div><div class="colon">:</div><div class="value">${fmtDate(i.tanggal)}</div></div>
                    <div class="row"><div class="label">Jumlah Anggaran yang diajukan</div><div class="colon">:</div><div class="value">Rp ${fmtRupiah(i.jumlah)}</div></div>
                    <div class="row"><div class="label">Tujuan Anggaran</div><div class="colon">:</div><div class="value">Belanja stok barang${i.tujuan ? ' - ' + i.tujuan : ''}</div></div>
                </div>
                <table class="items">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:30px">No</th>
                            <th rowspan="2">Nama Barang</th>
                            <th rowspan="2" style="width:90px">Keterangan sisa stok di gudang (satuan)</th>
                            <th colspan="4">Rencana Belanja</th>
                        </tr>
                        <tr>
                            <th style="width:50px">Qty</th>
                            <th style="width:60px">Satuan</th>
                            <th style="width:95px">Estimasi Harga</th>
                            <th style="width:100px">Sub Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                        <tr class="total-row">
                            <td colspan="6">Total Ajuan Belanja</td>
                            <td class="num">Rp ${fmtRupiah(i.jumlah)}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="status-line">Status: ${statusBadge(i.status)}</div>
                ${renderSignature(i)}
            `;
        }

        // ===== FORMAT PERALATAN (foto 2) =====
        function renderPeralatan(i) {
            const items = (i.items || []).slice();
            while (items.length < 10) items.push({});
            const rows = items.map((it, idx) => `
                <tr>
                    <td class="center">${idx + 1}</td>
                    <td>${it.keterangan || ''}</td>
                    <td class="center">${it.qty || ''}</td>
                    <td class="center">${it.satuan || ''}</td>
                    <td class="num">${it.harga ? fmtRupiah(it.harga) : ''}</td>
                    <td class="num">${it.subtotal ? fmtRupiah(it.subtotal) : ''}</td>
                </tr>`).join('');

            return `
                ${renderKop()}
                <div class="meta-info">
                    <div class="row"><div class="label">Tanggal</div><div class="colon">:</div><div class="value">${fmtDate(i.tanggal)}</div></div>
                    <div class="row"><div class="label">Jumlah Anggaran yang diajukan</div><div class="colon">:</div><div class="value">Rp ${fmtRupiah(i.jumlah)}</div></div>
                    <div class="row"><div class="label">Tujuan Anggaran</div><div class="colon">:</div><div class="value">Belanja Peralatan${i.tujuan ? ' - ' + i.tujuan : ''}</div></div>
                </div>
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:30px">No</th>
                            <th>Nama Barang</th>
                            <th style="width:55px">Qty</th>
                            <th style="width:65px">Satuan</th>
                            <th style="width:95px">Estimasi Harga</th>
                            <th style="width:100px">Sub Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                        <tr class="total-row">
                            <td colspan="5">Total Ajuan Belanja</td>
                            <td class="num">Rp ${fmtRupiah(i.jumlah)}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="status-line">Status: ${statusBadge(i.status)}</div>
                ${renderSignature(i)}
            `;
        }

        // ===== FORMAT OPERASIONAL (foto 3) =====
        function renderOperasional(i) {
            const keterangan = (i.items && i.items[0] && i.items[0].keterangan) || i.catatan || '';
            return `
                ${renderKop()}
                <div class="meta-info">
                    <div class="row"><div class="label">Tanggal</div><div class="colon">:</div><div class="value">${fmtDate(i.tanggal)}</div></div>
                    <div class="row"><div class="label">Jumlah Anggaran yang diajukan</div><div class="colon">:</div><div class="value">Rp ${fmtRupiah(i.jumlah)}</div></div>
                    <div class="row"><div class="label">Tujuan Anggaran</div><div class="colon">:</div><div class="value">${i.tujuan || '-'}</div></div>
                </div>
                <div class="ops-ket">
                    <div class="label">Keterangan :</div>
                    <div class="ops-box">${keterangan}</div>
                </div>
                <div class="status-line">Status: ${statusBadge(i.status)}</div>
                ${renderSignature(i)}
            `;
        }

        function renderSheet(i) {
            let html;
            if (i.jenis === 'stok') html = renderStok(i);
            else if (i.jenis === 'peralatan') html = renderPeralatan(i);
            else html = renderOperasional(i);
            document.getElementById('sheet').innerHTML = html;
        }

        async function load() {
            if (!PENGAJUAN_ID) {
                document.getElementById('sheet').innerHTML = '<div class="err-box">ID pengajuan tidak ditemukan.</div>';
                return;
            }
            try {
                const res = await fetch('../database/get-pengajuan.php');
                const json = await res.json();
                const arr = extractArray(json) || [];
                const found = arr.find(x => String(x.id) === String(PENGAJUAN_ID));
                if (!found) {
                    document.getElementById('sheet').innerHTML = '<div class="err-box">Data pengajuan tidak ditemukan.</div>';
                    return;
                }
                renderSheet(found);
            } catch (e) {
                document.getElementById('sheet').innerHTML = '<div class="err-box">Gagal memuat data: ' + e.message + '</div>';
            }
        }

        load();
    </script>
</body>

</html>