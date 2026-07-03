<?php
// ==========================================================
// PENJUALAN SPPG ADDCOST
// Data header & detail dari db_mbg (pengambilan_barang & pengambilan_barang_detail)
// Harga beli diambil dari db_draft_barang.barang (beda database, 1 server)
// ==========================================================

require_once '../database/auth.php';
include '../database/koneksi.php';

$activePage = 'penjualan-sppg-addcost';
include '../components/navbar.php';

// ----------------------------------------------------------
// FUNGSI BANTU
// ----------------------------------------------------------

// Membersihkan harga_beli (varchar) jadi angka murni,
// karena di tabel barang kolomnya bertipe varchar(30)
// (bisa jadi ada "Rp", titik, koma, spasi, dll)
function bersihkanHarga($str)
{
    if ($str === null) return 0;
    $bersih = preg_replace('/[^0-9]/', '', $str);
    return $bersih === '' ? 0 : (float) $bersih;
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

// Format qty: tampilkan tanpa desimal jika bilangan bulat,
// tapi tetap tampilkan desimal (maks 2 digit) jika ada pecahan
function formatQty($angka)
{
    $angka = (float) $angka;
    if ($angka == floor($angka)) {
        return number_format($angka, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($angka, 2, ',', '.'), '0'), ',');
}

// ----------------------------------------------------------
// FILTER (opsional): tanggal awal, tanggal akhir, keyword
// ----------------------------------------------------------
$tanggalAwal  = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$tanggalAkhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';
$keyword      = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// ----------------------------------------------------------
// QUERY UTAMA
// Karena db_mbg & db_draft_barang ada di server yang sama,
// kita bisa JOIN lintas database langsung: db_draft_barang.barang
// Pakai koneksi $koneksi2 (yang sudah terhubung ke db_mbg)
// ----------------------------------------------------------
$sql = "
    SELECT 
        pb.id_pengambilan,
        pb.no_pengambilan,
        pb.nama_pengambil,
        pb.tanggal_pengambilan,
        pb.jam_pengambilan,
        pb.nama_sppg,
        pb.no_kontak,
        pb.lokasi,
        pb.status,
        pbd.id_detail,
        pbd.nama_barang,
        pbd.satuan,
        pbd.qty,
        b.harga_beli
    FROM pengambilan_barang pb
    INNER JOIN pengambilan_barang_detail pbd 
        ON pbd.id_pengambilan = pb.id_pengambilan
    LEFT JOIN db_draft_barang.barang b 
        ON LOWER(TRIM(b.nama_barang)) = LOWER(TRIM(pbd.nama_barang))
    WHERE pbd.jenis = 'addcost'
      AND pb.status = 'verified'
";

$params = [];
$types  = '';

if ($tanggalAwal !== '' && $tanggalAkhir !== '') {
    $sql .= " AND pb.tanggal_pengambilan BETWEEN ? AND ? ";
    $params[] = $tanggalAwal;
    $params[] = $tanggalAkhir;
    $types   .= 'ss';
}

if ($keyword !== '') {
    $sql .= " AND (pb.nama_sppg LIKE ? OR pb.no_pengambilan LIKE ? OR pb.nama_pengambil LIKE ?) ";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$sql .= " ORDER BY pb.tanggal_pengambilan DESC, pb.id_pengambilan DESC, pbd.id_detail ASC";

$stmt = $koneksi2->prepare($sql);

if ($stmt === false) {
    die("Gagal menyiapkan query: " . $koneksi2->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ----------------------------------------------------------
// SUSUN DATA PER TRANSAKSI (GROUP BY id_pengambilan)
// ----------------------------------------------------------
$transaksi = [];

while ($row = $result->fetch_assoc()) {
    $idPengambilan = $row['id_pengambilan'];

    if (!isset($transaksi[$idPengambilan])) {
        $transaksi[$idPengambilan] = [
            'no_pengambilan'      => $row['no_pengambilan'],
            'nama_pengambil'      => $row['nama_pengambil'],
            'tanggal_pengambilan' => $row['tanggal_pengambilan'],
            'jam_pengambilan'     => $row['jam_pengambilan'],
            'nama_sppg'           => $row['nama_sppg'],
            'no_kontak'           => $row['no_kontak'],
            'lokasi'              => $row['lokasi'],
            'status'              => $row['status'],
            'detail'              => [],
            'total'               => 0,
        ];
    }

    $hargaBeli = bersihkanHarga($row['harga_beli']);
    $qty       = (float) $row['qty'];
    $subtotal  = $hargaBeli * $qty;

    $transaksi[$idPengambilan]['detail'][] = [
        'nama_barang' => $row['nama_barang'],
        'satuan'      => $row['satuan'],
        'qty'         => $qty,
        'harga_beli'  => $hargaBeli,
        'subtotal'    => $subtotal,
    ];

    $transaksi[$idPengambilan]['total'] += $subtotal;
}

$stmt->close();

$grandTotal = 0;
foreach ($transaksi as $t) {
    $grandTotal += $t['total'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan SPPG | KBUS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="foodcost-wrap">
        <div class="fc-header">
            <div class="fc-header-left">
                <div class="fc-header-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3h18v4H3z" />
                        <path d="M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7" />
                        <path d="M9 12h6" />
                    </svg>
                </div>
                <div>
                    <h4>Penjualan SPPG Add Cost</h4>
                    <div class="fc-subtitle">Rekap pengambilan barang &amp; estimasi biaya berdasarkan harga beli</div>
                </div>
            </div>
            <div class="fc-header-right">
                <div class="fc-badge-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                    <?= count($transaksi) ?> Transaksi Ditemukan
                </div>
            </div>
        </div>
        <!-- FORM FILTER -->
        <form method="GET" class="fc-filter" id="fcFilterForm">
            <div class="fc-filter-grid">
                <div class="fc-field">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <path d="M16 2v4M8 2v4M3 10h18" />
                        </svg>
                        Tanggal Awal
                    </label>
                    <input type="date" name="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggalAwal) ?>">
                </div>
                <div class="fc-field">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <path d="M16 2v4M8 2v4M3 10h18" />
                        </svg>
                        Tanggal Akhir
                    </label>
                    <input type="date" name="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggalAkhir) ?>">
                </div>
                <div class="fc-field">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        Cari (No. / SPPG / Pengambil)
                    </label>
                    <div class="fc-input-wrap">
                        <svg class="fc-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        <input type="text" name="keyword" class="form-control" placeholder="Ketik untuk mencari..." value="<?= htmlspecialchars($keyword) ?>">
                    </div>
                </div>
                <div class="fc-field fc-filter-actions">
                    <button type="submit" class="fc-btn fc-btn-filter" id="fcSubmitFilter">
                        <span class="fc-spinner"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                        </svg>
                        <span class="fc-btn-label">Terapkan Filter</span>
                    </button>
                    <button type="button" class="fc-btn fc-btn-reset" id="fcResetFilter">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 3-6.7" />
                            <path d="M3 4v5h5" />
                        </svg>
                        Reset
                    </button>
                </div>
            </div>
        </form>
        <?php if (empty($transaksi)): ?>
            <div class="fc-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.3-4.3" />
                </svg>
                <div class="fc-empty-title">Tidak ada data ditemukan</div>
                <div class="fc-empty-desc">Coba ubah rentang tanggal atau kata kunci pencarian.</div>
            </div>
        <?php else: ?>
            <?php foreach ($transaksi as $idPengambilan => $t): ?>
                <div class="fc-card">
                    <div class="fc-card-head" data-toggle="collapse">
                        <div class="fc-card-head-main">
                            <svg class="fc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                            <div>
                                <div class="fc-no">No. <?= htmlspecialchars($t['no_pengambilan']) ?></div>
                                <div class="fc-meta">
                                    <b>Pengambil:</b> <?= htmlspecialchars($t['nama_pengambil']) ?> &middot;
                                    <b>SPPG:</b> <?= htmlspecialchars($t['nama_sppg']) ?>
                                </div>
                                <div class="fc-meta">
                                    <b>Kontak:</b> <?= htmlspecialchars($t['no_kontak']) ?> &middot;
                                    <b>Lokasi:</b> <?= htmlspecialchars($t['lokasi']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="fc-card-head-side">
                            <div class="fc-tanggal">
                                <b><?= htmlspecialchars($t['tanggal_pengambilan']) ?></b> &middot; <?= htmlspecialchars($t['jam_pengambilan']) ?>
                            </div>
                            <?php if ($t['status'] === 'verified'): ?>
                                <span class="fc-pill fc-pill-verified">Verified</span>
                                <a href="cetak-faktur.php?id=<?= (int) $idPengambilan ?>"
                                    target="_blank"
                                    class="fc-btn-cetak"
                                    title="Cetak Faktur">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9V2h12v7" />
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                        <path d="M6 14h12v8H6z" />
                                    </svg>
                                    Cetak Faktur
                                </a>
                            <?php else: ?>
                                <span class="fc-pill fc-pill-pending">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="fc-card-body">
                        <div class="fc-card-body-inner">
                            <div class="fc-table-wrap">
                                <table class="fc-table">
                                    <thead>
                                        <tr>
                                            <th style="width:4%">No</th>
                                            <th>Nama Barang</th>
                                            <th class="fc-text-end" style="width:10%">Qty</th>
                                            <th style="width:12%">Satuan</th>
                                            <th class="fc-text-center" style="width:17%">Harga</th>
                                            <th class="fc-text-end" style="width:17%">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1;
                                        foreach ($t['detail'] as $d): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($d['nama_barang']) ?></td>
                                                <td class="fc-text-end"><?= formatQty($d['qty']) ?></td>
                                                <td><?= htmlspecialchars($d['satuan']) ?></td>
                                                <td class="fc-text-center">
                                                    <?php if ($d['harga_beli'] > 0): ?>
                                                        <?= formatRupiah($d['harga_beli']) ?>
                                                    <?php else: ?>
                                                        <span class="fc-harga-kosong" title="Nama barang tidak ditemukan di tabel barang">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <circle cx="12" cy="12" r="10" />
                                                                <path d="M12 8v5M12 16h.01" />
                                                            </svg>
                                                            Harga tidak ditemukan
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fc-text-end fc-subtotal"><?= formatRupiah($d['subtotal']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="fc-card-foot">
                                <span class="label">Total Transaksi Ini</span>
                                <span class="value"><?= formatRupiah($t['total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="fc-grand-total">
                <div class="fc-gt-left">
                    <div class="fc-gt-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div>
                        <div class="fc-gt-label">Grand Total Keseluruhan</div>
                        <div class="fc-gt-value"><?= formatRupiah($grandTotal) ?></div>
                    </div>
                </div>
                <div class="fc-gt-note"><?= count($transaksi) ?> transaksi dalam tampilan ini</div>
            </div>
        <?php endif; ?>
    </div>
    <button type="button" class="fc-scroll-top" id="fcScrollTop" aria-label="Kembali ke atas">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
    </button>
    <script src="script.js" defer></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>