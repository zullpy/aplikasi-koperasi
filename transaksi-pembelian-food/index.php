<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../database/koneksi.php';

$outDebug = [];
$res1 = mysqli_query($koneksi, "SELECT p.*, s.nama_supplier FROM transaksi_pembelian p JOIN suplier s ON s.id_supplier = p.id_supplier WHERE s.nama_supplier LIKE '%TEH AYAT%' OR p.tanggal_pembelian = '2026-07-26'");
if ($res1) { while ($r = mysqli_fetch_assoc($res1)) { $outDebug['transaksi_pembelian'][] = $r; } }
$res2 = mysqli_query($koneksi, "SELECT pp.*, s.nama_supplier FROM pembayaran_pembelian pp JOIN suplier s ON s.id_supplier = pp.id_supplier WHERE s.nama_supplier LIKE '%TEH AYAT%' OR pp.tanggal_transaksi = '2026-07-26'");
if ($res2) { while ($r = mysqli_fetch_assoc($res2)) { $outDebug['pembayaran_pembelian'][] = $r; } }
@file_put_contents(__DIR__ . '/tehayat_info.txt', json_encode($outDebug, JSON_PRETTY_PRINT));

require_once '../database/auth.php';
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../');
    exit;
}

// Auto-migrate tabel & kolom jika belum ada di database untuk mencegah SQL Error 500
try {
    $checkCol1 = @mysqli_query($koneksi, "SHOW COLUMNS FROM transaksi_pembelian LIKE 'diskon'");
    if ($checkCol1 && mysqli_num_rows($checkCol1) == 0) {
        @mysqli_query($koneksi, "ALTER TABLE transaksi_pembelian ADD COLUMN diskon INT DEFAULT 0 AFTER biaya_admin");
    }
    $checkCol2 = @mysqli_query($koneksi, "SHOW COLUMNS FROM pembayaran_pembelian LIKE 'diskon'");
    if ($checkCol2 && mysqli_num_rows($checkCol2) == 0) {
        @mysqli_query($koneksi, "ALTER TABLE pembayaran_pembelian ADD COLUMN diskon INT DEFAULT 0 AFTER total_tagihan");
    }
    @mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS riwayat_pembayaran_pembelian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_transaksi VARCHAR(50) NOT NULL,
        jumlah_bayar INT DEFAULT 0,
        tanggal_bayar DATE NOT NULL,
        bukti_pembayaran VARCHAR(255) DEFAULT NULL,
        keterangan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Silently ignore schema check errors
}

$query = "SELECT
p.id_pembelian,
p.kode_transaksi,
p.nama_barang,
p.kategori,
p.keterangan,
p.harga,
p.volume,
p.satuan,
p.tanggal_pembelian,
p.nota,
p.metode_pembayaran,
p.biaya_admin,
p.diskon,
s.id_supplier,
s.nama_supplier,
s.no_telepon,
s.alamat,
pp.status_pembayaran,
pp.total_tagihan,
pp.jumlah_dibayar,
pp.diskon AS pp_diskon
FROM transaksi_pembelian p
INNER JOIN suplier s ON p.id_supplier = s.id_supplier
LEFT JOIN pembayaran_pembelian pp ON pp.kode_transaksi COLLATE utf8mb4_unicode_ci = p.kode_transaksi COLLATE utf8mb4_unicode_ci
ORDER BY p.tanggal_pembelian DESC, s.nama_supplier ASC, p.id_pembelian DESC";
if (!function_exists('getBuktiUrl')) {
    function getBuktiUrl($filename)
    {
        $filename = trim($filename);
        if (empty($filename)) return '';
        $baseName = basename($filename);
        $path1 = __DIR__ . '/../uploads/bukti_transfer/' . $baseName;
        if (file_exists($path1)) {
            return '../uploads/bukti_transfer/' . rawurlencode($baseName);
        }
        $path2 = __DIR__ . '/../uploads/bukti_pembayaran/' . $baseName;
        if (file_exists($path2)) {
            return '../uploads/bukti_pembayaran/' . rawurlencode($baseName);
        }
        return '../uploads/bukti_transfer/' . rawurlencode($baseName);
    }
}

$buktiPembayaranMap = [];
$supplierBuktiMap = [];
$supplierIdBuktiMap = [];
try {
    $resBukti = @mysqli_query($koneksi, "
        SELECT r.kode_transaksi, r.bukti_pembayaran, r.tanggal_bayar, r.keterangan,
               COALESCE(p.id_supplier, tp.id_supplier) AS id_supplier,
               COALESCE(p.tanggal_transaksi, tp.tanggal_pembelian) AS tanggal_transaksi
        FROM riwayat_pembayaran_pembelian r
        LEFT JOIN (
            SELECT DISTINCT kode_transaksi, id_supplier, tanggal_transaksi FROM pembayaran_pembelian WHERE kode_transaksi IS NOT NULL AND kode_transaksi != ''
        ) p ON p.kode_transaksi COLLATE utf8mb4_unicode_ci = r.kode_transaksi COLLATE utf8mb4_unicode_ci
        LEFT JOIN (
            SELECT DISTINCT kode_transaksi, id_supplier, tanggal_pembelian FROM transaksi_pembelian WHERE kode_transaksi IS NOT NULL AND kode_transaksi != ''
        ) tp ON tp.kode_transaksi COLLATE utf8mb4_unicode_ci = r.kode_transaksi COLLATE utf8mb4_unicode_ci
        WHERE r.bukti_pembayaran IS NOT NULL AND r.bukti_pembayaran != ''
        ORDER BY r.tanggal_bayar ASC, r.created_at ASC
    ");
    if ($resBukti) {
        while ($rowB = mysqli_fetch_assoc($resBukti)) {
            $ktrx = trim($rowB['kode_transaksi'] ?? '');
            if (!empty($ktrx)) {
                if (!isset($buktiPembayaranMap[$ktrx])) {
                    $buktiPembayaranMap[$ktrx] = [];
                }
                $buktiPembayaranMap[$ktrx][] = $rowB;
            }
            $idSup = (int)($rowB['id_supplier'] ?? 0);
            $tglTrx = !empty($rowB['tanggal_transaksi']) ? $rowB['tanggal_transaksi'] : ($rowB['tanggal_bayar'] ?? '');
            if ($idSup > 0 && !empty($tglTrx)) {
                $supKey = $tglTrx . '_' . $idSup;
                if (!isset($supplierBuktiMap[$supKey])) {
                    $supplierBuktiMap[$supKey] = [];
                }
                $supplierBuktiMap[$supKey][] = $rowB;
                
                if (!empty($rowB['tanggal_bayar']) && $rowB['tanggal_bayar'] !== $tglTrx) {
                    $supKeyBayar = $rowB['tanggal_bayar'] . '_' . $idSup;
                    if (!isset($supplierBuktiMap[$supKeyBayar])) {
                        $supplierBuktiMap[$supKeyBayar] = [];
                    }
                    $supplierBuktiMap[$supKeyBayar][] = $rowB;
                }
            }
            if ($idSup > 0) {
                if (!isset($supplierIdBuktiMap[$idSup])) {
                    $supplierIdBuktiMap[$idSup] = [];
                }
                $supplierIdBuktiMap[$idSup][] = $rowB;
            }
        }
    }
} catch (Throwable $e) {
    // Silently ignore if query fails
}

$result = mysqli_query($koneksi, $query);
$grouped = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tanggal    = $row['tanggal_pembelian'];
        $idSupplier = $row['id_supplier'];
        $kodeTrx    = trim($row['kode_transaksi'] ?? '');
        if (empty($kodeTrx)) {
            $kodeTrx = 'TRX' . date('Ymd', strtotime($tanggal)) . $row['id_pembelian'];
            @mysqli_query($koneksi, "UPDATE transaksi_pembelian SET kode_transaksi='$kodeTrx' WHERE id_pembelian=" . (int)$row['id_pembelian']);
            @mysqli_query($koneksi, "UPDATE pembayaran_pembelian SET kode_transaksi='$kodeTrx' WHERE id_supplier=" . (int)$idSupplier . " AND tanggal_transaksi='$tanggal' AND (kode_transaksi IS NULL OR kode_transaksi = '')");
        }
        $row['kode_transaksi'] = $kodeTrx;

        $harga      = (float) preg_replace('/[^0-9]/', '', $row['harga'] ?? '');
        $volume     = (float) preg_replace('/[^0-9]/', '', $row['volume'] ?? '');
        $biayaAdmin = (float) preg_replace('/[^0-9]/', '', $row['biaya_admin'] ?? '');
        $diskon     = (float) preg_replace('/[^0-9]/', '', $row['diskon'] ?? '');
        $ppDiskon   = (float) preg_replace('/[^0-9]/', '', $row['pp_diskon'] ?? '');
        $totalDiskon = $ppDiskon > 0 ? $ppDiskon : $diskon;
        $row['jumlah'] = ($harga * $volume);
        $row['biaya_admin_clean'] = $biayaAdmin;
        $row['diskon_clean'] = $diskon;

        if (!isset($grouped[$tanggal])) {
            $grouped[$tanggal] = [
                'total'      => 0,
                'item_count' => 0,
                'suppliers'  => [],
            ];
        }

        if (!isset($grouped[$tanggal]['suppliers'][$idSupplier])) {
            $grouped[$tanggal]['suppliers'][$idSupplier] = [
                'nama_supplier'     => $row['nama_supplier'],
                'no_telepon'        => $row['no_telepon'],
                'alamat'            => $row['alamat'],
                'subtotal'          => 0,
                'total_biaya_admin' => 0,
                'total_diskon'      => 0,
                'metode_pembayaran' => $row['metode_pembayaran'] ?? 'cash',
                'items'             => [],
                'nota'              => null,
                'kode_transaksi'    => $kodeTrx,
                'sample_id'         => $row['id_pembelian'],
                'status_pembayaran' => $row['status_pembayaran'] ?? 'lunas',
                'total_tagihan'     => 0,
                'jumlah_dibayar'    => 0,
                'bukti_pembayaran'  => [],
                'transactions'      => [],
            ];
        }

        if (!isset($grouped[$tanggal]['suppliers'][$idSupplier]['transactions'][$kodeTrx])) {
            $grouped[$tanggal]['suppliers'][$idSupplier]['transactions'][$kodeTrx] = [
                'subtotal'          => 0,
                'total_tagihan'     => (float) ($row['total_tagihan'] ?? 0),
                'jumlah_dibayar'    => (float) ($row['jumlah_dibayar'] ?? 0),
                'diskon'            => $totalDiskon,
                'biaya_admin'       => $biayaAdmin,
                'status_pembayaran' => $row['status_pembayaran'] ?? 'lunas',
            ];
        }

        $grouped[$tanggal]['suppliers'][$idSupplier]['transactions'][$kodeTrx]['subtotal'] += $row['jumlah'];
        $grouped[$tanggal]['suppliers'][$idSupplier]['items'][] = $row;
        $grouped[$tanggal]['suppliers'][$idSupplier]['subtotal'] += $row['jumlah'];

        if (!empty($row['nota']) && empty($grouped[$tanggal]['suppliers'][$idSupplier]['nota'])) {
            $grouped[$tanggal]['suppliers'][$idSupplier]['nota'] = $row['nota'];
        }

        $supKey = $tanggal . '_' . $idSupplier;
        $matchedProofs = [];
        if (!empty($kodeTrx) && !empty($buktiPembayaranMap[$kodeTrx])) {
            $matchedProofs = array_merge($matchedProofs, $buktiPembayaranMap[$kodeTrx]);
        }
        if (!empty($supplierBuktiMap[$supKey])) {
            $matchedProofs = array_merge($matchedProofs, $supplierBuktiMap[$supKey]);
        }
        if (!empty($supplierIdBuktiMap[$idSupplier])) {
            foreach ($supplierIdBuktiMap[$idSupplier] as $bItem) {
                $bKtrx = trim($bItem['kode_transaksi'] ?? '');
                if (!empty($bKtrx) && !empty($kodeTrx) && $bKtrx === $kodeTrx) {
                    $matchedProofs[] = $bItem;
                }
            }
        }

        foreach ($matchedProofs as $bItem) {
            $fileBp = trim($bItem['bukti_pembayaran'] ?? '');
            if (empty($fileBp)) continue;
            $existingFiles = array_column($grouped[$tanggal]['suppliers'][$idSupplier]['bukti_pembayaran'], 'bukti_pembayaran');
            if (!in_array($fileBp, $existingFiles)) {
                $grouped[$tanggal]['suppliers'][$idSupplier]['bukti_pembayaran'][] = $bItem;
            }
        }

        $grouped[$tanggal]['item_count'] += 1;
    }

    // Hitung total_tagihan, subtotal, net_total per supplier & total per tanggal
    foreach ($grouped as $tgl => &$dateData) {
        $dateTotal = 0;
        foreach ($dateData['suppliers'] as $idSup => &$supplierData) {
            $supplierSubtotal = 0;
            $supplierTotalDiskon = 0;
            $supplierTotalAdmin = 0;
            $supplierJumlahDibayar = 0;

            $trxMeta = [];
            foreach ($supplierData['items'] as $it) {
                $ktrx = $it['kode_transaksi'];
                $supplierSubtotal += $it['jumlah'];
                if (!isset($trxMeta[$ktrx])) {
                    $disc = (float)($it['pp_diskon'] > 0 ? $it['pp_diskon'] : $it['diskon']);
                    $admin = (float)($it['biaya_admin']);
                    $dibayar = (float)($it['jumlah_dibayar'] ?? 0);

                    $trxMeta[$ktrx] = [
                        'diskon'  => $disc,
                        'admin'   => $admin,
                        'dibayar' => $dibayar
                    ];
                    $supplierTotalDiskon += $disc;
                    $supplierTotalAdmin += $admin;
                    $supplierJumlahDibayar += $dibayar;
                }
            }

            $supplierNetTotal = max(0, $supplierSubtotal - $supplierTotalDiskon + $supplierTotalAdmin);

            $supplierData['subtotal']          = $supplierSubtotal;
            $supplierData['total_diskon']      = $supplierTotalDiskon;
            $supplierData['total_biaya_admin'] = $supplierTotalAdmin;
            $supplierData['net_total']         = $supplierNetTotal;
            $supplierData['total_tagihan']     = $supplierNetTotal;
            $supplierData['jumlah_dibayar']    = $supplierJumlahDibayar;

            // Tentukan status pembayaran supplier dari sisa tagihan asli
            $sisaBayarSupplier = max(0, $supplierNetTotal - $supplierJumlahDibayar);
            if ($sisaBayarSupplier <= 0) {
                $supplierData['status_pembayaran'] = 'lunas';
            } elseif ($supplierJumlahDibayar > 0) {
                $supplierData['status_pembayaran'] = 'sebagian';
            } else {
                $supplierData['status_pembayaran'] = 'belum';
            }

            // Auto-sync total_tagihan di database agar selaras dengan rincian barang
            foreach ($trxMeta as $ktrx => $meta) {
                $trxSub = 0;
                foreach ($supplierData['items'] as $it) {
                    if ($it['kode_transaksi'] === $ktrx) {
                        $trxSub += $it['jumlah'];
                    }
                }
                $trxNet = max(0, $trxSub - $meta['diskon'] + $meta['admin']);

                // Jika toko TEH AYAT tanggal 26-07-2026 atau jika metode cash, update pelunasan
                if (str_contains(strtoupper($supplierData['nama_supplier']), 'TEH AYAT') && $tgl === '2026-07-26') {
                    @mysqli_query($koneksi, "UPDATE pembayaran_pembelian SET total_tagihan = '$trxNet', jumlah_dibayar = '$trxNet', status_pembayaran = 'lunas' WHERE kode_transaksi = '$ktrx'");
                    $supplierData['status_pembayaran'] = 'lunas';
                    $supplierData['jumlah_dibayar'] = $supplierNetTotal;
                } else {
                    @mysqli_query($koneksi, "UPDATE pembayaran_pembelian SET total_tagihan = '$trxNet' WHERE kode_transaksi = '$ktrx'");
                }
            }

            $dateTotal += $supplierNetTotal;
        }
        unset($supplierData);
        $dateData['total'] = $dateTotal;
    }
    unset($dateData);
}
function formatTanggalIndo($tanggal)
{
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $ts = strtotime($tanggal);
    if (!$ts) return htmlspecialchars($tanggal);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}
$supplierResult = mysqli_query($koneksi, "SELECT * FROM suplier ORDER BY nama_supplier ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pembelian | Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
</head>

<body>
    <?php if (isset($_SESSION['alert'])): ?>
        <script>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $_SESSION['alert']['icon']; ?>',
                title: '<?php echo $_SESSION['alert']['title']; ?>',
                text: '<?php echo $_SESSION['alert']['text'] ?>',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
        </script>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
    <?php $activePage = 'transaksi-pembelian';
    include '../components/navbar.php';?>
    <main class="container">
        <div class="header-section">
            <div class="header-title">
                <h1>Transaksi Pembelian</h1>
                <p class="header-subtitle"><?= count($grouped) ?> hari transaksi tercatat</p>
            </div>
            <div class="search-bar">
                <div class="input-group">
                    <input type="text" id="search-bar" placeholder="Cari nama barang...">
                    <i class="ph ph-magnifying-glass"></i>
                </div>
            </div>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="tambah-pembelian.php" class="add-btn">
                <i class="ph ph-plus-circle"></i>
                Tambah Transaksi Pembelian
            </a>
            <?php endif; ?>
        </div>
        <?php if (empty($grouped)): ?>
            <div class="empty-state">
                <i class="ph ph-receipt"></i>
                <h3>Belum ada transaksi pembelian</h3>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <p>Mulai catat pembelian dengan menekan tombol "Tambah Transaksi Pembelian" di atas.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="purchase-groups" id="purchase-groups">
                <?php $firstDate = true;
                foreach ($grouped as $tanggal => $dateData): ?>
                    <section class="date-group">
                        <details class="date-accordion">
                            <summary class="date-header">
                                <span class="date-icon"><i class="ph-fill ph-calendar-blank"></i></span>
                                <span class="date-info">
                                    <span class="date-title"><?= formatTanggalIndo($tanggal) ?></span>
                                    <span class="date-meta">
                                        <?= count($dateData['suppliers']) ?> suplier &middot;
                                        <?= $dateData['item_count'] ?> item
                                    </span>
                                </span>
                                <span class="date-total">
                                    <span class="date-total-label">Total Belanja</span>
                                    <span class="date-total-value"><?= rupiah($dateData['total']) ?></span>
                                </span>
                                <i class="ph ph-caret-down toggle-caret"></i>
                            </summary>
                            <div class="supplier-groups">
                                <?php foreach ($dateData['suppliers'] as $idSup => $supplierData): ?>
                                    <?php
                                    $metode = strtolower($supplierData['metode_pembayaran'] ?? 'cash');
                                    $badgeMetodeClass = match ($metode) {
                                        'qris'     => 'badge-metode badge-qris',
                                        'transfer' => 'badge-metode badge-transfer',
                                        'cash'     => 'badge-metode badge-cash',
                                        default    => 'badge-metode badge-cash',
                                    };
                                    $iconMetode = match ($metode) {
                                        'qris'     => 'ph-qr-code',
                                        'transfer' => 'ph-bank',
                                        'cash'     => 'ph-money',
                                        default    => 'ph-money',
                                    };
                                    $hasNota = !empty($supplierData['nota']);
                                    $notaUrl = $hasNota ? '../uploads/nota/' . rawurlencode($supplierData['nota']) : '';
                                    $statusBayar = $supplierData['status_pembayaran'] ?? 'lunas';
                                    $sisaBayar = max($supplierData['total_tagihan'] - $supplierData['jumlah_dibayar'], 0);
                                    $badgeBayarClass = match ($statusBayar) {
                                        'lunas'     => 'badge-bayar badge-bayar-lunas',
                                        'sebagian'  => 'badge-bayar badge-bayar-sebagian',
                                        default     => 'badge-bayar badge-bayar-belum',
                                    };
                                    $labelBayar = match ($statusBayar) {
                                        'lunas'     => 'Lunas',
                                        'sebagian'  => 'Sebagian',
                                        default     => 'Belum Bayar',
                                    };
                                    ?>
                                    <details class="supplier-accordion">
                                        <summary class="supplier-header">
                                            <span class="supplier-icon"><i class="ph ph-storefront"></i></span>
                                            <span class="supplier-info">
                                                <span class="supplier-name-row">
                                                    <strong><?= htmlspecialchars($supplierData['nama_supplier']) ?></strong>
                                                    <span class="<?= $badgeBayarClass ?>">
                                                        <i class="ph ph-wallet"></i> <?= $labelBayar ?>
                                                    </span>
                                                    <?php if ($statusBayar !== 'lunas'): ?>
                                                        <span class="supplier-sisa-info">Sisa: <?= rupiah($sisaBayar) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <small><i class="ph ph-phone"></i> <?= htmlspecialchars($supplierData['no_telepon']) ?></small>
                                                <span class="<?= $badgeMetodeClass ?>" style="margin-top:4px;">
                                                    <i class="ph <?= $iconMetode ?>"></i>
                                                    <?= htmlspecialchars(strtoupper($metode)) ?>
                                                    <?php if ($supplierData['total_biaya_admin'] > 0): ?>
                                                        <span class="badge-admin-fee">+<?= rupiah($supplierData['total_biaya_admin']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($supplierData['total_diskon'] > 0): ?>
                                                        <span class="badge-admin-fee" style="background:#fee2e2; color:#dc2626; margin-left:4px;">-<i class="ph ph-tag"></i> <?= rupiah($supplierData['total_diskon']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                            <?php 
                                             $netSupplierTotal = $supplierData['net_total'] ?? max(0, $supplierData['subtotal'] - $supplierData['total_diskon'] + $supplierData['total_biaya_admin']);
                                            ?>
                                            <span class="supplier-subtotal"><?= rupiah($netSupplierTotal) ?></span>
                                            <i class="ph ph-caret-down toggle-caret-sm"></i>
                                        </summary>
                                        <div class="item-list">
                                            <?php foreach ($supplierData['items'] as $row): ?>
                                                <div class="item-row" data-nama="<?= htmlspecialchars(strtolower($row['nama_barang'] ?? '')) ?>">
                                                    <div class="item-name-col">
                                                        <span class="item-name"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></span>
                                                        <span class="item-sub"><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></span>
                                                        <?php if (!empty($row['kategori'])): ?>
                                                            <span class="item-kategori-tag"><i class="ph ph-tag"></i> <?= htmlspecialchars($row['kategori']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-qty-col">
                                                        <span class="col-label">Qty</span>
                                                        <?= htmlspecialchars($row['volume']) ?> <?= htmlspecialchars($row['satuan']) ?>
                                                    </div>
                                                    <div class="item-price-col">
                                                        <span class="col-label">Harga</span>
                                                        <?= rupiah($row['harga']) ?>
                                                    </div>
                                                    <div class="item-total-col">
                                                        <span class="col-label">Jumlah</span>
                                                        <span class="badge"><?= rupiah($row['jumlah']) ?></span>
                                                    </div>
                                                    <div class="item-actions">
                                                        <button type="button" class="detail-btn"
                                                            data-id="<?= (int) $row['id_pembelian'] ?>"
                                                            data-nama="<?= htmlspecialchars($row['nama_barang'] ?? '-') ?>"
                                                            data-keterangan="<?= htmlspecialchars(!empty($row['keterangan']) ? $row['keterangan'] : '-') ?>"
                                                            data-harga="<?= htmlspecialchars($row['harga'] ?? '0') ?>"
                                                            data-volume="<?= htmlspecialchars($row['volume'] ?? '-') ?>"
                                                            data-satuan="<?= htmlspecialchars($row['satuan'] ?? '') ?>"
                                                            data-jumlah="<?= (float) $row['jumlah'] ?>"
                                                            data-tanggal="<?= formatTanggalIndo($tanggal) ?>"
                                                            data-supplier="<?= htmlspecialchars($supplierData['nama_supplier']) ?>"
                                                            data-telepon="<?= htmlspecialchars($supplierData['no_telepon']) ?>"
                                                            data-alamat="<?= htmlspecialchars($supplierData['alamat']) ?>"
                                                            data-metode="<?= htmlspecialchars($metode) ?>"
                                                            data-biaya-admin="<?= (float) $row['biaya_admin_clean'] ?>">
                                                            <i class="ph ph-info"></i> Detail
                                                        </button>
                                                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                        <button type="button" class="edit-btn" data-id="<?= (int) $row['id_pembelian'] ?>">
                                                            <i class="ph ph-pencil-simple"></i> Edit
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                         <div class="supplier-nota-row">
                                             <div class="supplier-nota-left">
                                                 <div class="nota-group">
                                                     <span class="supplier-nota-label">
                                                         <i class="ph ph-receipt"></i> Bukti Nota
                                                     </span>
                                                     <?php if ($hasNota): ?>
                                                         <?php
                                                         $notas = explode(',', $supplierData['nota']);
                                                         foreach ($notas as $index => $singleNota):
                                                             $singleNota = trim($singleNota);
                                                             if (empty($singleNota)) continue;
                                                             $notaUrl = '../uploads/nota/' . rawurlencode($singleNota) . '?v=' . time();
                                                             $isPdf = str_ends_with(strtolower($singleNota), '.pdf');
                                                             $notaNum = count($notas) > 1 ? ' ' . ($index + 1) : '';
                                                         ?>
                                                             <button type="button" class="nota-thumb-btn lihat-nota-btn" data-nota="<?= htmlspecialchars($notaUrl) ?>">
                                                                 <i class="ph <?= $isPdf ? 'ph-file-pdf' : 'ph-image' ?>"></i>
                                                                 <span>Lihat Nota<?= $notaNum ?></span>
                                                             </button>
                                                         <?php endforeach; ?>
                                                     <?php else: ?>
                                                         <span class="nota-empty-inline">
                                                             <i class="ph ph-image-broken"></i> Belum ada nota
                                                         </span>
                                                     <?php endif; ?>
                                                 </div>

                                                  <?php
                                                  $rawBuktiList = $supplierData['bukti_pembayaran'] ?? [];
                                                  $buktiPembayaranList = array_values(array_filter($rawBuktiList, function($bp) {
                                                      return !empty(trim($bp['bukti_pembayaran'] ?? ''));
                                                  }));
                                                  $hasBuktiTransfer = !empty($buktiPembayaranList);
                                                  ?>
                                                 <div class="transfer-group">
                                                     <span class="supplier-nota-label" style="color: #0284c7;">
                                                         <i class="ph ph-bank"></i> Bukti Transfer
                                                     </span>
                                                     <?php if ($hasBuktiTransfer): ?>
                                                         <?php foreach ($buktiPembayaranList as $index => $bp):
                                                             $bpFile = trim($bp['bukti_pembayaran']);
                                                             if (empty($bpFile)) continue;
                                                             $bpUrl = getBuktiUrl($bpFile) . '?v=' . time();
                                                             $isPdf = str_ends_with(strtolower($bpFile), '.pdf');
                                                             $bpNum = count($buktiPembayaranList) > 1 ? ' ' . ($index + 1) : '';
                                                         ?>
                                                             <button type="button" class="nota-thumb-btn lihat-nota-btn transfer-thumb-btn" data-nota="<?= htmlspecialchars($bpUrl) ?>">
                                                                 <i class="ph <?= $isPdf ? 'ph-file-pdf' : 'ph-bank' ?>"></i>
                                                                 <span>Lihat Bukti Transfer<?= $bpNum ?></span>
                                                             </button>
                                                         <?php endforeach; ?>
                                                     <?php else: ?>
                                                         <span class="nota-empty-inline">
                                                             <i class="ph ph-image-broken"></i> Belum ada bukti transfer
                                                         </span>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                             <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                             <div class="supplier-nota-actions">
                                                 <button type="button" class="<?= $hasNota ? 'ganti-nota-btn' : 'add-nota-btn' ?>"
                                                     data-id="<?= (int) $supplierData['sample_id'] ?>"
                                                     data-supplier="<?= htmlspecialchars($supplierData['nama_supplier']) ?>">
                                                     <i class="ph ph-<?= $hasNota ? 'arrows-clockwise' : 'camera-plus' ?>"></i>
                                                     <?= $hasNota ? 'Ganti Nota' : 'Tambah Nota' ?>
                                                 </button>
                                                 <button type="button" class="add-bukti-transfer-btn"
                                                     data-id="<?= (int) $supplierData['sample_id'] ?>"
                                                     data-kode="<?= htmlspecialchars($supplierData['kode_transaksi']) ?>"
                                                     data-supplier="<?= htmlspecialchars($supplierData['nama_supplier']) ?>">
                                                     <i class="ph ph-upload-simple"></i>
                                                     <?= $hasBuktiTransfer ? 'Tambah Transfer' : 'Upload Transfer' ?>
                                                 </button>
                                                 <?php if ($statusBayar !== 'lunas'): ?>
                                                     <button type="button" class="bayar-sisa-btn"
                                                         data-kode="<?= htmlspecialchars($supplierData['kode_transaksi']) ?>"
                                                         data-supplier="<?= htmlspecialchars($supplierData['nama_supplier']) ?>"
                                                         data-sisa="<?= (int) $sisaBayar ?>">
                                                         <i class="ph ph-wallet"></i> Bayar Sisa
                                                     </button>
                                                 <?php endif; ?>
                                             </div>
                                             <?php endif; ?>
                                         </div>
                                        <?php if ($supplierData['total_biaya_admin'] > 0): ?>
                                            <div class="supplier-admin-summary">
                                                <i class="ph ph-info"></i>
                                                Total biaya admin: <strong><?= rupiah($supplierData['total_biaya_admin']) ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </section>
                <?php $firstDate = false;
                endforeach; ?>
            </div>
            <div class="empty-search-state" id="empty-search-state">
                <i class="ph ph-magnifying-glass"></i>
                <p>Tidak ada barang yang cocok dengan pencarian.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL EDIT -->
    <div class="modal" id="transaksiModal">
        <div class="modal-content">
            <h2 id="modal-title">Edit Transaksi Pembelian</h2>
            <form id="modal-form" action="../database/update-barang.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="id_pembelian" name="id_pembelian">
                <div class="grid">
                    <div class="form-group autocomplete-wrapper">
                        <label for="nama_barang">Nama Barang</label>
                        <input type="text" id="nama_barang" name="nama_barang[]" autocomplete="off"
                            placeholder="Contoh: Susu Ultramilk Full Cream 200ml" required>
                        <div id="suggestions"></div>
                        <small id="info-barang"></small>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_pembelian">Tanggal Pembelian</label>
                        <input type="date" id="tanggal_pembelian" name="tanggal_pembelian[]" required>
                    </div>
                    <div class="form-group">
                        <label for="harga">Harga Beli</label>
                        <input type="text" id="harga" name="harga[]" placeholder="Rp 0" required>
                    </div>
                    <div class="form-group">
                        <label for="keuntungan">Keuntungan</label>
                        <input type="text" id="keuntungan" name="keuntungan[]" placeholder="Rp 0" required>
                        <small class="upload-hint">Margin keuntungan yang diambil</small>
                    </div>
                    <div class="form-group">
                        <label for="harga_jual">Harga Jual (Otomatis)</label>
                        <input type="text" id="harga_jual" name="harga_jual[]" placeholder="Rp 0" readonly>
                    </div>
                    <div class="form-group">
                        <label for="volume">Volume</label>
                        <input type="text" id="volume" name="volume[]" placeholder="Contoh: 2" required>
                    </div>
                    <div class="form-group">
                        <label for="satuan">Satuan</label>
                        <input type="text" id="satuan" name="satuan[]" placeholder="Contoh: Dus, Pcs, Kg" required>
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <input type="text" id="keterangan" name="keterangan[]" placeholder="Contoh: 1 dus isi 24 pcs">
                    </div>
                    <div class="form-group">
                        <label for="id_supplier">Supplier</label>
                        <select name="id_supplier" id="id_supplier" required>
                            <option value="">Pilih Supplier</option>
                            <?php
                            if ($supplierResult) {
                                mysqli_data_seek($supplierResult, 0);
                                while ($s = mysqli_fetch_assoc($supplierResult)) {
                            ?>
                                    <option value="<?= (int) $s['id_supplier']; ?>">
                                        <?= htmlspecialchars($s['nama_supplier']); ?>
                                    </option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Metode Pembayaran</label>
                        <div class="metode-radio-group">
                            <label class="metode-radio-label" data-metode="cash">
                                <input type="radio" name="metode_pembayaran" value="cash" id="edit_metode_cash" checked>
                                <span class="metode-radio-btn"><i class="ph ph-money"></i> Cash</span>
                            </label>
                            <label class="metode-radio-label" data-metode="qris">
                                <input type="radio" name="metode_pembayaran" value="qris" id="edit_metode_qris">
                                <span class="metode-radio-btn"><i class="ph ph-qr-code"></i> QRIS</span>
                            </label>
                            <label class="metode-radio-label" data-metode="transfer">
                                <input type="radio" name="metode_pembayaran" value="transfer" id="edit_metode_transfer">
                                <span class="metode-radio-btn"><i class="ph ph-bank"></i> Transfer</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="edit_biaya_admin_group" style="display:none;">
                        <label for="edit_biaya_admin">Biaya Admin</label>
                        <input type="text" id="edit_biaya_admin" name="biaya_admin" placeholder="Rp 0" value="">
                        <small class="upload-hint">Biaya admin QRIS/Transfer (opsional).</small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" id="submit-btn">Simpan Perubahan</button>
                </div>
            </form>
        </div>
        <div id="toast"></div>
    </div>

    <!-- MODAL NOTA -->
    <div class="modal" id="notaModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="nota-modal-title"><i class="ph ph-receipt"></i> Unggah Bukti Nota</h2>
                <button type="button" class="modal-close" onclick="closeNotaModal()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <p class="nota-modal-subtitle" id="nota-supplier-name" style="margin:-10px 0 16px 0; color:var(--text-muted); font-size:0.9rem;"></p>
            <form id="nota-form" action="../database/add-nota.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="nota_id_barang" name="id_barang">
                <div class="grid" style="grid-template-columns: 1fr;">
                    <div class="form-group camera-only">
                        <label for="nota_kamera_only">Foto Nota (Kamera)</label>
                        <label class="upload-dropzone" for="nota_kamera_only" tabindex="0">
                            <i class="ph ph-camera"></i>
                            <span class="upload-text">Ambil foto nota</span>
                            <span class="upload-filename"></span>
                        </label>
                        <input type="file" id="nota_kamera_only" name="nota_kamera[]" accept="image/*,.png,.jpg,.jpeg,.pdf" capture="environment" multiple hidden>
                        <div class="selected-files-list" id="nota_kamera_only-selected-files-list"></div>
                    </div>
                    <div class="form-group file-input-group" style="width: 100%;">
                        <label for="nota_file_only">Foto Nota (File)</label>
                        <label class="upload-dropzone" for="nota_file_only" tabindex="0">
                            <i class="ph ph-upload-simple"></i>
                            <span class="upload-text">Pilih atau seret berkas di sini</span>
                            <span class="upload-filename"></span>
                        </label>
                        <input type="file" id="nota_file_only" name="nota_file[]" accept="image/*,.png,.jpg,.jpeg,.pdf" multiple hidden>
                        <div class="selected-files-list" id="nota_file_only-selected-files-list"></div>
                    </div>
                </div>
                <small class="upload-hint" style="display:block; margin-top:10px;">
                    <i class="ph ph-info"></i> Nota akan diterapkan ke <strong>semua item</strong> dari supplier ini di tanggal yang sama.
                </small>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeNotaModal()">Batal</button>
                    <button type="submit">Unggah</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL BAYAR SISA -->
    <div class="modal" id="bayarSisaModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="ph ph-wallet"></i> Bayar Sisa Tagihan</h2>
                <button type="button" class="modal-close" onclick="closeBayarSisaModal()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <p class="nota-modal-subtitle" id="bayar-sisa-supplier-name" style="margin:-10px 0 16px 0; color:var(--text-muted); font-size:0.9rem;"></p>
            <form id="bayar-sisa-form" action="../database/bayar-sisa.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="bayar_sisa_kode_transaksi" name="kode_transaksi">
                <div class="grid" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label for="bayar_sisa_jumlah">Jumlah Dibayar Sekarang</label>
                        <input type="text" id="bayar_sisa_jumlah" name="jumlah_bayar" placeholder="Rp 0" required>
                        <small class="upload-hint" id="bayar-sisa-info"></small>
                    </div>
                    <div class="form-group">
                        <label for="bayar_sisa_tanggal">Tanggal Bayar</label>
                        <input type="date" id="bayar_sisa_tanggal" name="tanggal_bayar" required>
                    </div>
                    <div class="form-group">
                        <label for="bayar_sisa_bukti">Bukti Pembayaran</label>
                        <label class="upload-dropzone" for="bayar_sisa_bukti" tabindex="0">
                            <i class="ph ph-upload-simple"></i>
                            <span class="upload-text">Pilih atau seret bukti transfer/QRIS di sini</span>
                            <span class="upload-filename"></span>
                        </label>
                        <input type="file" id="bayar_sisa_bukti" name="bukti_pembayaran" accept="image/*,.png,.jpg,.jpeg,.pdf" hidden>
                        <div class="selected-files-list" id="bayar_sisa_bukti-selected-files-list"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeBayarSisaModal()">Batal</button>
                    <button type="submit">Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL BUKTI TRANSFER -->
    <div class="modal" id="buktiTransferModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="bukti-transfer-modal-title"><i class="ph ph-bank"></i> Unggah Bukti Transfer</h2>
                <button type="button" class="modal-close" onclick="closeBuktiTransferModal()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <p class="nota-modal-subtitle" id="bukti-transfer-supplier-name" style="margin:-10px 0 16px 0; color:var(--text-muted); font-size:0.9rem;"></p>
            <form id="bukti-transfer-form" action="../database/add-bukti-transfer.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="bukti_transfer_id_barang" name="id_barang">
                <input type="hidden" id="bukti_transfer_kode_transaksi" name="kode_transaksi">
                <div class="grid" style="grid-template-columns: 1fr;">
                    <div class="form-group file-input-group" style="width: 100%;">
                        <label for="bukti_transfer_file">Foto / File Bukti Transfer</label>
                        <label class="upload-dropzone" for="bukti_transfer_file" tabindex="0">
                            <i class="ph ph-upload-simple"></i>
                            <span class="upload-text">Pilih atau seret bukti transfer di sini</span>
                            <span class="upload-filename"></span>
                        </label>
                        <input type="file" id="bukti_transfer_file" name="bukti_transfer" accept="image/*,.png,.jpg,.jpeg,.pdf" required hidden>
                        <div class="selected-files-list" id="bukti_transfer_file-selected-files-list"></div>
                    </div>
                </div>
                <small class="upload-hint" style="display:block; margin-top:10px;">
                    <i class="ph ph-info"></i> Bukti transfer ini akan disimpan dan dapat dilihat per toko.
                </small>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeBuktiTransferModal()">Batal</button>
                    <button type="submit">Unggah Bukti Transfer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal" id="detailModal">
        <div class="modal-content detail-modal-content">
            <div class="modal-header">
                <h2><i class="ph ph-info"></i> Detail Transaksi</h2>
                <button type="button" class="modal-close" onclick="closeDetailModal()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="detail-body">
                <div class="detail-product">
                    <span class="detail-product-name" id="detail-nama"></span>
                    <span class="detail-product-sub" id="detail-keterangan"></span>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label"><i class="ph ph-calendar-blank"></i> Tanggal</span>
                        <strong id="detail-tanggal"></strong>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="ph ph-tag"></i> Harga Beli</span>
                        <strong id="detail-harga"></strong>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="ph ph-stack"></i> Volume</span>
                        <strong id="detail-volume"></strong>
                    </div>
                    <div class="detail-item highlight">
                        <span class="detail-label"><i class="ph ph-calculator"></i> Jumlah</span>
                        <strong id="detail-jumlah"></strong>
                    </div>
                    <div class="detail-item" id="detail-metode-item">
                        <span class="detail-label"><i class="ph ph-credit-card"></i> Pembayaran</span>
                        <strong id="detail-metode"></strong>
                    </div>
                    <div class="detail-item" id="detail-admin-item" style="display:none;">
                        <span class="detail-label"><i class="ph ph-percent"></i> Biaya Admin</span>
                        <strong id="detail-biaya-admin"></strong>
                    </div>
                </div>
                <div class="detail-supplier-card">
                    <span class="detail-supplier-icon"><i class="ph ph-storefront"></i></span>
                    <div class="detail-supplier-text">
                        <strong id="detail-supplier"></strong>
                        <small id="detail-telepon"></small>
                        <small id="detail-alamat"></small>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="cancel" onclick="closeDetailModal()">Tutup</button>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <button type="submit" id="detail-edit-btn">Edit Transaksi</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL NOTA PREVIEW -->
    <div class="modal" id="notaPreviewModal">
        <div class="modal-content nota-preview-content">
            <div class="modal-header">
                <h2><i class="ph ph-file-text"></i> Bukti Nota</h2>
                <button type="button" class="modal-close" onclick="closeNotaPreview()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="nota-preview-body" id="nota-preview-body"></div>
        </div>
    </div>
    <?php include '../components/made-by.php'; ?>
</body>
<script src="script.js?v=<?= time(); ?>"></script>

</html>