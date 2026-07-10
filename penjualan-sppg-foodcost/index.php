<?php
// ==========================================================
// PENJUALAN SPPG FOODCOST
// Data header & detail dari db_mbg (pengambilan_barang & pengambilan_barang_detail)
// Harga beli diambil dari db_draft_barang.barang (beda database, 1 server)
// ==========================================================

require_once '../database/auth.php';
include '../database/koneksi.php';

$activePage = 'penjualan-sppg-foodcost';
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
// PROSES SIMPAN PEMBAYARAN (POST) — cash / transfer, bisa cicilan
// ----------------------------------------------------------
$errorBayar = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_bayar') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        die("Akses ditolak: Hanya admin yang dapat menambah pembayaran.");
    }
    $idPengambilanBayar = isset($_POST['id_pengambilan']) ? (int) $_POST['id_pengambilan'] : 0;
    $metodeBayar         = isset($_POST['metode_pembayaran']) ? $_POST['metode_pembayaran'] : '';
    $jumlahBayarMentah   = isset($_POST['jumlah_dibayar']) ? $_POST['jumlah_dibayar'] : '0';
    $jumlahBayar         = (float) preg_replace('/[^0-9]/', '', $jumlahBayarMentah);

    if ($idPengambilanBayar <= 0 || !in_array($metodeBayar, ['cash', 'transfer'], true) || $jumlahBayar <= 0) {
        $errorBayar = 'Data pembayaran tidak lengkap. Pastikan metode dan jumlah pembayaran sudah diisi.';
    } else {
        $buktiPath = null;

        if ($metodeBayar === 'transfer') {
            if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
                $ext        = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];

                if (!in_array($ext, $allowedExt, true)) {
                    $errorBayar = 'Format bukti transfer tidak didukung. Gunakan JPG, PNG, atau PDF.';
                } else {
                    $folderUpload = __DIR__ . '/uploads/bukti-transfer/';
                    if (!is_dir($folderUpload)) {
                        mkdir($folderUpload, 0755, true);
                    }
                    $namaFile = 'bukti_' . $idPengambilanBayar . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $folderUpload . $namaFile)) {
                        compressImage($folderUpload . $namaFile);
                        $buktiPath = 'uploads/bukti-transfer/' . $namaFile;
                    } else {
                        $errorBayar = 'Gagal mengunggah bukti transfer. Silakan coba lagi.';
                    }
                }
            } else {
                $errorBayar = 'Bukti transfer wajib diunggah untuk metode transfer.';
            }
        }

        if ($errorBayar === '') {
            $sqlInsertBayar  = "INSERT INTO pembayaran (id_pengambilan, metode_pembayaran, jumlah_dibayar, bukti_transfer, tanggal_bayar) VALUES (?, ?, ?, ?, NOW())";
            $stmtInsertBayar = $koneksi2->prepare($sqlInsertBayar);

            if ($stmtInsertBayar === false) {
                $errorBayar = 'Gagal menyiapkan penyimpanan pembayaran: ' . $koneksi2->error;
            } else {
                $stmtInsertBayar->bind_param('isds', $idPengambilanBayar, $metodeBayar, $jumlahBayar, $buktiPath);
                $stmtInsertBayar->execute();
                $stmtInsertBayar->close();

                // redirect (Post/Redirect/Get) supaya form tidak submit ulang saat refresh,
                // sambil mempertahankan filter tanggal/keyword yang sedang aktif
                $queryStringSaatIni = $_SERVER['QUERY_STRING'] ?? '';
                $urlRedirect = 'index.php' . ($queryStringSaatIni !== '' ? '?' . $queryStringSaatIni : '');
                $urlRedirect .= (strpos($urlRedirect, '?') !== false ? '&' : '?') . 'bayar_sukses=1#trx-' . $idPengambilanBayar;
                header('Location: ' . $urlRedirect);
                exit;
            }
        }
    }
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
    WHERE pbd.jenis = 'foodcost'
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

// ----------------------------------------------------------
// AMBIL DATA PEMBAYARAN UNTUK SETIAP TRANSAKSI (bisa cicilan / >1 baris)
// ----------------------------------------------------------
$idPengambilanList = array_keys($transaksi);

if (!empty($idPengambilanList)) {
    $placeholder = implode(',', array_fill(0, count($idPengambilanList), '?'));
    $typesBayar  = str_repeat('i', count($idPengambilanList));

    $sqlBayar = "
        SELECT id_pembayaran, id_pengambilan, metode_pembayaran, jumlah_dibayar, bukti_transfer, tanggal_bayar
        FROM pembayaran
        WHERE id_pengambilan IN ($placeholder)
        ORDER BY tanggal_bayar ASC, id_pembayaran ASC
    ";
    $stmtBayar = $koneksi2->prepare($sqlBayar);

    if ($stmtBayar !== false) {
        $stmtBayar->bind_param($typesBayar, ...$idPengambilanList);
        $stmtBayar->execute();
        $resultBayar = $stmtBayar->get_result();

        while ($rowBayar = $resultBayar->fetch_assoc()) {
            $transaksi[$rowBayar['id_pengambilan']]['pembayaran'][] = $rowBayar;
        }
        $stmtBayar->close();
    }
}

// ----------------------------------------------------------
// HITUNG TOTAL DIBAYAR, SISA, STATUS & BADGE METODE PER TRANSAKSI
// ----------------------------------------------------------
foreach ($transaksi as $idT => &$t) {
    if (!isset($t['pembayaran'])) {
        $t['pembayaran'] = [];
    }

    $totalDibayar = 0;
    $metodeDipakai = [];

    foreach ($t['pembayaran'] as $p) {
        $totalDibayar += (float) $p['jumlah_dibayar'];
        $metodeDipakai[$p['metode_pembayaran']] = true;
    }

    $t['total_dibayar'] = $totalDibayar;
    $t['sisa_bayar']    = max($t['total'] - $totalDibayar, 0);

    if ($t['total'] > 0 && $totalDibayar >= $t['total']) {
        $t['status_bayar'] = 'lunas';
    } elseif ($totalDibayar > 0) {
        $t['status_bayar'] = 'cicilan';
    } else {
        $t['status_bayar'] = 'belum_bayar';
    }

    $labelMetode = array_map(function ($m) {
        return $m === 'cash' ? 'Cash' : 'Transfer';
    }, array_keys($metodeDipakai));

    $t['metode_badge'] = empty($labelMetode) ? '-' : implode(' + ', $labelMetode);
}
unset($t);

$grandTotal = 0;
$grandDibayar = 0;
foreach ($transaksi as $t) {
    $grandTotal   += $t['total'];
    $grandDibayar += $t['total_dibayar'];
}
$grandSisa = max($grandTotal - $grandDibayar, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan SPPG | KBUS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
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
                    <h4>Penjualan SPPG Foodcost</h4>
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
                <div class="fc-card" id="trx-<?= (int) $idPengambilan ?>">
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
                            <div class="fc-pill-row">
                                <?php if ($t['status'] === 'verified'): ?>
                                    <span class="fc-pill fc-pill-verified">Verified</span>
                                <?php else: ?>
                                    <span class="fc-pill fc-pill-pending">Pending</span>
                                <?php endif; ?>

                                <?php if ($t['status_bayar'] === 'lunas'): ?>
                                    <span class="fc-pill fc-pill-lunas">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6L9 17l-5-5" />
                                        </svg>
                                        Lunas
                                    </span>
                                <?php elseif ($t['status_bayar'] === 'cicilan'): ?>
                                    <span class="fc-pill fc-pill-cicilan">Cicilan &middot; Belum Lunas</span>
                                <?php else: ?>
                                    <span class="fc-pill fc-pill-belum-bayar">Belum Lunas</span>
                                <?php endif; ?>

                                <?php if ($t['metode_badge'] !== '-'): ?>
                                    <span class="fc-pill fc-pill-metode">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="5" width="20" height="14" rx="2" />
                                            <path d="M2 10h20" />
                                        </svg>
                                        <?= htmlspecialchars($t['metode_badge']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($t['status'] === 'verified' && ($_SESSION['role'] ?? '') === 'admin'): ?>
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
                            <div class="fc-pay-section">
                                <div class="fc-pay-section-head">
                                    <span class="fc-pay-section-title">Riwayat Pembayaran</span>
                                    <?php if ($t['sisa_bayar'] > 0 && ($_SESSION['role'] ?? '') === 'admin'): ?>
                                        <button type="button" class="fc-btn-bayar"
                                            data-id="<?= (int) $idPengambilan ?>"
                                            data-no="<?= htmlspecialchars($t['no_pengambilan']) ?>"
                                            data-sisa="<?= (int) $t['sisa_bayar'] ?>"
                                            data-sisa-format="<?= htmlspecialchars(formatRupiah($t['sisa_bayar'])) ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                            Tambah Pembayaran
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($t['pembayaran'])): ?>
                                    <div class="fc-pay-empty">Belum ada pembayaran untuk transaksi ini.</div>
                                <?php else: ?>
                                    <div class="fc-pay-list">
                                        <?php foreach ($t['pembayaran'] as $p): ?>
                                            <div class="fc-pay-item">
                                                <div class="fc-pay-item-left">
                                                    <span class="fc-pill fc-pill-metode fc-pill-metode-sm">
                                                        <?= $p['metode_pembayaran'] === 'cash' ? 'Cash' : 'Transfer' ?>
                                                    </span>
                                                    <span class="fc-pay-item-date"><?= htmlspecialchars(date('d M Y, H:i', strtotime($p['tanggal_bayar']))) ?></span>
                                                </div>
                                                <div class="fc-pay-item-right">
                                                    <?php if (!empty($p['bukti_transfer'])): ?>
                                                        <a href="<?= htmlspecialchars($p['bukti_transfer']) ?>" target="_blank" class="fc-pay-bukti-link" title="Lihat Bukti Transfer">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                                <path d="M7 10l5 5 5-5M12 15V3" />
                                                            </svg>
                                                            Bukti
                                                        </a>
                                                    <?php endif; ?>
                                                    <span class="fc-pay-item-amount"><?= formatRupiah($p['jumlah_dibayar']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="fc-card-foot">
                                <div class="fc-card-foot-row">
                                    <span class="label">Total Transaksi Ini</span>
                                    <span class="value"><?= formatRupiah($t['total']) ?></span>
                                </div>
                                <div class="fc-card-foot-row">
                                    <span class="label label-sm">Sudah Dibayar</span>
                                    <span class="value value-sm value-hijau"><?= formatRupiah($t['total_dibayar']) ?></span>
                                </div>
                                <div class="fc-card-foot-row">
                                    <span class="label label-sm">Sisa / Hutang</span>
                                    <span class="value value-sm <?= $t['sisa_bayar'] > 0 ? 'value-merah' : 'value-hijau' ?>"><?= formatRupiah($t['sisa_bayar']) ?></span>
                                </div>
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
                        <div class="fc-gt-note"><?= count($transaksi) ?> transaksi dalam tampilan ini</div>
                    </div>
                </div>
                <div class="fc-gt-right">
                    <div class="fc-gt-mini">
                        <span class="fc-gt-mini-label">Sudah Dibayar</span>
                        <span class="fc-gt-mini-value"><?= formatRupiah($grandDibayar) ?></span>
                    </div>
                    <div class="fc-gt-mini">
                        <span class="fc-gt-mini-label">Sisa / Hutang</span>
                        <span class="fc-gt-mini-value"><?= formatRupiah($grandSisa) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <button type="button" class="fc-scroll-top" id="fcScrollTop" aria-label="Kembali ke atas">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
    </button>

    <?php if (isset($_GET['bayar_sukses'])): ?>
        <div class="fc-toast" id="fcToastSukses">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6L9 17l-5-5" />
            </svg>
            Pembayaran berhasil disimpan.
        </div>
    <?php endif; ?>

    <!-- MODAL TAMBAH PEMBAYARAN -->
    <div class="fc-modal-overlay<?= $errorBayar !== '' ? ' is-open' : '' ?>" id="fcPayModalOverlay">
        <div class="fc-modal">
            <div class="fc-modal-head">
                <div class="fc-modal-head-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
                <div class="fc-modal-head-text">
                    <h5>Tambah Pembayaran</h5>
                    <p>Catat pembayaran cash atau transfer</p>
                </div>
                <button type="button" class="fc-modal-close" id="fcPayModalClose" aria-label="Tutup">&times;</button>
            </div>
            <form method="POST" action="index.php<?= isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" enctype="multipart/form-data" id="fcPayForm">
                <input type="hidden" name="aksi" value="tambah_bayar">
                <input type="hidden" name="id_pengambilan" id="fcPayIdPengambilan" value="<?= $errorBayar !== '' ? (int) $idPengambilanBayar : '' ?>">
                <div class="fc-modal-body">
                    <div class="fc-modal-info">
                        <div>
                            <span>No. Transaksi</span>
                            <b id="fcPayNoTrx"><?= $errorBayar !== '' && isset($transaksi[$idPengambilanBayar]) ? htmlspecialchars($transaksi[$idPengambilanBayar]['no_pengambilan']) : '-' ?></b>
                        </div>
                        <div>
                            <span>Sisa Tagihan</span>
                            <b id="fcPaySisa"><?= $errorBayar !== '' && isset($transaksi[$idPengambilanBayar]) ? formatRupiah($transaksi[$idPengambilanBayar]['sisa_bayar']) : 'Rp 0' ?></b>
                        </div>
                    </div>

                    <div class="fc-field">
                        <label>Metode Pembayaran</label>
                        <div class="fc-metode-toggle">
                            <label class="fc-metode-opt">
                                <input type="radio" name="metode_pembayaran" value="cash" <?= $errorBayar === '' || $metodeBayar === 'cash' ? 'checked' : '' ?>>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="6" width="20" height="12" rx="2" />
                                        <circle cx="12" cy="12" r="2" />
                                    </svg>
                                    Cash
                                </span>
                            </label>
                            <label class="fc-metode-opt">
                                <input type="radio" name="metode_pembayaran" value="transfer" <?= $errorBayar !== '' && $metodeBayar === 'transfer' ? 'checked' : '' ?>>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 2l4 4-4 4" />
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                                        <path d="M7 22l-4-4 4-4" />
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                                    </svg>
                                    Transfer
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="fc-field">
                        <label>Jumlah Dibayar</label>
                        <div class="fc-input-wrap fc-input-rp">
                            <input type="text" name="jumlah_dibayar" id="fcPayJumlah" class="form-control" placeholder="0" inputmode="numeric" autocomplete="off" value="<?= $errorBayar !== '' && $jumlahBayar > 0 ? number_format($jumlahBayar, 0, ',', '.') : '' ?>">
                        </div>
                        <div class="fc-field-hint" id="fcPayHint"></div>
                    </div>

                    <div class="fc-field" id="fcPayBuktiWrap" style="display:<?= $errorBayar !== '' && $metodeBayar === 'transfer' ? 'block' : 'none' ?>">
                        <label>Bukti Transfer (JPG / PNG / PDF)</label>
                        <input type="file" name="bukti_transfer" id="fcPayBukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <?php if ($errorBayar !== ''): ?>
                        <div class="fc-pay-error">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="flex-shrink:0">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8v5M12 16h.01" />
                            </svg>
                            <span><?= htmlspecialchars($errorBayar) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="fc-modal-foot">
                    <button type="button" class="fc-btn fc-btn-reset" id="fcPayCancel">Batal</button>
                    <button type="submit" class="fc-btn fc-btn-filter" id="fcPaySubmit">
                        <span class="fc-spinner"></span>
                        <span class="fc-btn-label">Simpan Pembayaran</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js" defer></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>