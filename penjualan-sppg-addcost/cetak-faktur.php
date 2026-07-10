<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../database/auth.php';
include '../database/koneksi.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Akses ditolak: Hanya admin yang dapat mencetak faktur.");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("ID transaksi tidak valid");
}

/* ==========================================================
   AMBIL DATA HEADER TRANSAKSI (pengambilan_barang)
   Catatan: koneksi ke db_mbg dipakai lewat $koneksi2,
   sama seperti di index.php
========================================================== */
$stmtHead = $koneksi2->prepare("
    SELECT id_pengambilan, no_pengambilan, no_faktur, nama_pengambil,
           tanggal_pengambilan, jam_pengambilan, nama_sppg, no_kontak,
           lokasi, status
    FROM pengambilan_barang
    WHERE id_pengambilan = ?
");
$stmtHead->bind_param('i', $id);
$stmtHead->execute();
$trx = $stmtHead->get_result()->fetch_assoc();
$stmtHead->close();

if (!$trx) {
    die("Data transaksi tidak ditemukan");
}

/* ==========================================================
   NOMOR FAKTUR OTOMATIS
   Format   : 0001/FC/07/2026
   Aturan   : setiap tanggal 1 / awal bulan baru, penomoran
              kembali mulai dari 0001, lalu naik terus mengikuti
              urutan faktur foodcost yang dicetak di bulan itu.
   Kolom "no_faktur" pada tabel pengambilan_barang dipakai untuk
   menyimpan nomor secara permanen, supaya kalau faktur yang sama
   dicetak ulang nomornya tidak berubah-ubah.

   Perlu 2 hal disiapkan lebih dulu di database (lihat catatan di
   bawah kode ini untuk skrip SQL-nya):
   1. Kolom `no_faktur` VARCHAR di tabel `pengambilan_barang`
   2. Tabel bantu `faktur_counter` untuk menyimpan angka berjalan
========================================================== */
function generateNoFakturAddcost($koneksi2, $id_pengambilan, $tanggalTransaksi)
{
    $bulan = (int) date('n', strtotime($tanggalTransaksi));
    $tahun = (int) date('Y', strtotime($tanggalTransaksi));

    $koneksi2->begin_transaction();

    try {
        // Baris counter bulan berjalan dikunci (FOR UPDATE) supaya
        // aman kalau ada 2 faktur dicetak bersamaan di waktu yang sama.
        $stmt = $koneksi2->prepare("
            SELECT counter FROM faktur_counter
            WHERE jenis = 'addcost' AND bulan = ? AND tahun = ?
            FOR UPDATE
        ");
        $stmt->bind_param('ii', $bulan, $tahun);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $counter = (int) $row['counter'] + 1;

            $upd = $koneksi2->prepare("
                UPDATE faktur_counter
                SET counter = ?
                WHERE jenis = 'addcost' AND bulan = ? AND tahun = ?
            ");
            $upd->bind_param('iii', $counter, $bulan, $tahun);
            $upd->execute();
            $upd->close();
        } else {
            // Belum ada baris counter untuk bulan ini -> otomatis
            // mulai dari 0001 (inilah yang bikin reset tiap bulan baru)
            $counter = 1;

            $ins = $koneksi2->prepare("
                INSERT INTO faktur_counter (jenis, bulan, tahun, counter)
                VALUES ('addcost', ?, ?, 1)
            ");
            $ins->bind_param('ii', $bulan, $tahun);
            $ins->execute();
            $ins->close();
        }

        $noFaktur = sprintf('%04d/AC-%02d-%d', $counter, $bulan, $tahun);

        $updTrx = $koneksi2->prepare("
            UPDATE pengambilan_barang SET no_faktur = ? WHERE id_pengambilan = ?
        ");
        $updTrx->bind_param('si', $noFaktur, $id_pengambilan);
        $updTrx->execute();
        $updTrx->close();

        $koneksi2->commit();

        return $noFaktur;
    } catch (Exception $e) {
        $koneksi2->rollback();
        die("Gagal membuat nomor faktur: " . $e->getMessage());
    }
}

if (empty($trx['no_faktur'])) {
    $trx['no_faktur'] = generateNoFakturAddcost($koneksi2, $id, $trx['tanggal_pengambilan']);
}
$displayNoFaktur = str_replace('/FC/', '/AC/', $trx['no_faktur']);

/* ==========================================================
   AMBIL DETAIL BARANG (khusus jenis = 'foodcost')
   Sama seperti logika harga di index.php
========================================================== */
function bersihkanHarga($str)
{
    if ($str === null) return 0;
    $bersih = preg_replace('/[^0-9]/', '', $str);
    return $bersih === '' ? 0 : (float) $bersih;
}

function formatQty($angka)
{
    $angka = (float) $angka;
    if ($angka == floor($angka)) {
        return number_format($angka, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($angka, 2, ',', '.'), '0'), ',');
}

$stmtDetail = $koneksi2->prepare("
    SELECT pbd.nama_barang, pbd.satuan, pbd.qty, b.harga_beli
    FROM pengambilan_barang_detail pbd
    LEFT JOIN db_draft_barang.barang b
        ON LOWER(TRIM(b.nama_barang)) = LOWER(TRIM(pbd.nama_barang))
    WHERE pbd.id_pengambilan = ? AND pbd.jenis = 'addcost'
    ORDER BY pbd.id_detail ASC
");
$stmtDetail->bind_param('i', $id);
$stmtDetail->execute();
$resultDetail = $stmtDetail->get_result();

$items = [];
$total = 0;

while ($row = $resultDetail->fetch_assoc()) {
    $harga    = bersihkanHarga($row['harga_beli']);
    $qty      = (float) $row['qty'];
    $subtotal = $harga * $qty;
    $total   += $subtotal;

    $items[] = [
        'nama_barang' => $row['nama_barang'],
        'satuan'      => $row['satuan'],
        'qty'         => $qty,
        'harga'       => $harga,
        'subtotal'    => $subtotal,
    ];
}
$stmtDetail->close();

if (empty($items)) {
    die("Tidak ada item addcost untuk transaksi ini");
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Faktur <?= htmlspecialchars($displayNoFaktur) ?></title>
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
    <style>
        /* ===== PAGE SETUP A5 ===== */
        @page {
            size: A5 portrait;
            margin: 6mm 7mm 6mm 7mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        /* ===== HEADER KOP SURAT ===== */
        .header-table {
            width: 100%;
            border-collapse: separate;
            border: none;
            border-bottom: 1.5px double #000;
            margin-bottom: 5px;
        }

        .header-table td {
            padding: 4px 5px;
            vertical-align: middle;
        }

        .col-logo {
            width: 75px;
            text-align: center;
            vertical-align: middle;
        }

        .col-logo img {
            width: 65px;
            height: auto;
        }

        .col-kop {
            text-align: center;
            vertical-align: middle;
            padding: 4px 0;
        }

        .col-kop .label-koperasi {
            font-size: 9pt;
            font-weight: bold;
            color: #6b3fa0;
            margin: 0;
            line-height: 1.3;
            letter-spacing: 1px;
        }

        .col-kop .nama-koperasi {
            font-size: 15pt;
            font-weight: bold;
            color: #6b3fa0;
            margin: 0;
            line-height: 1.2;
        }

        .col-kop .tagline {
            color: #b8860b;
            font-style: italic;
            font-weight: bold;
            font-size: 8pt;
            margin: 2px 0 1px;
        }

        .col-kop .alamat {
            font-size: 8pt;
            line-height: 1.5;
        }

        .col-logo-kanan {
            width: 75px;
            text-align: center;
            vertical-align: middle;
        }

        .col-logo-kanan img {
            width: 55px;
            height: auto;
        }

        /* ===== INFO KONSUMEN ===== */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        .info-table td {
            border: none;
            padding: 4px 6px;
            font-size: 8.5pt;
            line-height: 1.4;
        }

        .info-table .label {
            width: 110px;
            white-space: nowrap;
        }

        .info-table .value {
            min-width: 100px;
        }

        .info-table .label-right {
            width: 90px;
            white-space: nowrap;
        }

        .info-table .value-right {
            width: 130px;
        }

        /* ===== JUDUL FAKTUR ===== */
        .judul {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin: 6px 0 1px;
            letter-spacing: 1px;
        }

        .sub-judul {
            text-align: center;
            font-size: 8.5pt;
            margin: 0 0 5px;
            color: #444;
        }

        /* ===== TABEL BARANG ===== */
        .barang {
            width: 100%;
            border-collapse: collapse;
        }

        .barang th {
            border: 1.5px solid #000;
            padding: 4px 5px;
            text-align: center;
            font-size: 8.5pt;
            background: #fff;
            font-weight: bold;
        }

        .barang td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 8.5pt;
            height: 18px;
        }

        .col-qty {
            width: 40px;
        }

        .col-satuan {
            width: 55px;
        }

        .col-harga {
            width: 80px;
        }

        .col-sub {
            width: 85px;
        }

        .barang td.center {
            text-align: center;
        }

        .barang td.right {
            text-align: right;
        }

        /* Row total */
        .row-total td {
            border: 1px solid #000;
            padding: 4px 5px;
            font-size: 8.5pt;
            font-weight: bold;
        }

        /* ===== CATATAN ===== */
        .catatan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .catatan-table td {
            font-size: 8pt;
            padding: 1px 2px;
            vertical-align: top;
        }

        .catatan-label {
            width: 55px;
            font-style: italic;
            text-decoration: underline;
            white-space: nowrap;
        }

        .catatan-isi {
            font-style: italic;
            line-height: 1.6;
        }

        /* ===== TTD ===== */
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .ttd-table td {
            font-size: 8.5pt;
            vertical-align: top;
            padding: 0 4px;
        }

        .ttd-kiri {
            text-align: left;
            width: 50%;
        }

        .ttd-kanan {
            text-align: right;
            width: 50%;
        }

        .ttd-gap {
            height: 55px;
            display: block;
        }

        .ttd-gap-img {
            height: 55px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
        }

        .ttd-line {
            display: block;
            margin-top: 2px;
        }

        .cap-img {
            width: 65px;
            height: auto;
            opacity: 0.88;
        }

        /* ===== PRINT ===== */
        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <!-- ===== KOP SURAT ===== -->
    <table class="header-table">
        <tr>
            <td class="col-logo">
                <img src="../assets/logo.png" alt="Logo KBUS">
            </td>

            <td class="col-kop">
                <div class="label-koperasi">KOPERASI</div>
                <div class="nama-koperasi">BINA USAHA SAUYUNAN</div>
                <div class="tagline">"Bersama Membangun Usaha, Bersatu Meraih Sejahtera"</div>
                <div class="alamat">Kp. Panyingkiran - Singaparna - Kab. Tasikmalaya</div>
                <div class="alamat">email : kop.binausahasauyunan@gmail.com</div>
            </td>

            <td class="col-logo-kanan">
                <img src="../assets/logo-kbus.png" alt="Logo KBUS Kanan">
            </td>
        </tr>
    </table>

    <!-- ===== INFO SPPG ===== -->
    <table class="info-table">
        <tr>
            <td class="label">Nama SPPG</td>
            <td class="value">: <?= htmlspecialchars($trx['nama_sppg']) ?></td>
            <td class="label-right">Tanggal</td>
            <td class="value-right">: <?= date('d-m-Y', strtotime($trx['tanggal_pengambilan'])) ?></td>
        </tr>
        <tr>
            <td class="label">No Kontak</td>
            <td class="value">: <?= htmlspecialchars($trx['no_kontak']) ?></td>
            <td class="label-right">No Faktur</td>
            <td class="value-right">: <?= htmlspecialchars($displayNoFaktur) ?></td>
        </tr>
        <tr>
            <td class="label">Lokasi</td>
            <td class="value">: <?= htmlspecialchars($trx['lokasi']) ?></td>
            <td class="label-right">Pengambil</td>
            <td class="value-right">: <?= htmlspecialchars($trx['nama_pengambil']) ?></td>
        </tr>
    </table>

    <!-- ===== JUDUL ===== -->
    <div class="judul">FAKTUR PENJUALAN ADDCOST</div>

    <!-- ===== TABEL BARANG ===== -->
    <table class="barang">
        <thead>
            <tr>
                <th class="col-qty">QTY</th>
                <th class="col-satuan">SATUAN</th>
                <th class="col-nama">NAMA BARANG</th>
                <th class="col-harga">HARGA</th>
                <th class="col-sub">SUB TOTAL</th>
            </tr>
        </thead>
        <tbody>

            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="center"><?= formatQty($item['qty']) ?></td>
                    <td class="center"><?= htmlspecialchars($item['satuan']) ?></td>
                    <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                    <td class="right"><?= number_format($item['harga'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Baris Total -->
            <tr class="row-total">
                <td colspan="4" class="right">TOTAL :</td>
                <td class="right"><?= number_format($total, 0, ',', '.') ?></td>
            </tr>

        </tbody>
    </table>

    <!-- ===== CATATAN ===== -->
    <table class="catatan-table">
        <tr>
            <td class="catatan-label">Catatan :</td>
            <td class="catatan-isi">
                Faktur ini merupakan bukti pengambilan barang addcost SPPG<br>
                Mohon di cek dengan teliti barang yang sudah diambil
            </td>
        </tr>
    </table>

    <!-- ===== TANDA TANGAN ===== -->
    <table class="ttd-table">
        <tr>
            <td class="ttd-kiri">
                Penerima / SPPG
                <span class="ttd-gap"></span>
                <span class="ttd-line">...................................</span>
            </td>

            <td class="ttd-kanan">
                Hormat Kami,
                <span class="ttd-gap-img">
                    <img src="../assets/logo-kbus.png" class="cap-img" alt="Cap KBUS">
                </span>
                <span class="ttd-line">...................................</span>
            </td>
        </tr>
    </table>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>

    <?php include '../components/made-by.php'; ?>
</body>

</html>