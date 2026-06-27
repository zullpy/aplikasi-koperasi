<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../');
    exit;
}
include '../database/koneksi.php';
$query = "SELECT
p.id_pembelian,
p.kode_transaksi,
p.nama_barang,
p.keterangan,
p.harga,
p.volume,
p.satuan,
p.tanggal_pembelian,
p.nota,
p.metode_pembayaran,
p.biaya_admin,
s.id_supplier,
s.nama_supplier,
s.no_telepon,
s.alamat
FROM transaksi_pembelian p
INNER JOIN suplier s ON p.id_supplier = s.id_supplier
ORDER BY p.tanggal_pembelian DESC, s.nama_supplier ASC, p.id_pembelian DESC";
$result = mysqli_query($koneksi, $query);
$grouped = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tanggal    = $row['tanggal_pembelian'];
    $idSupplier = $row['id_supplier'];
    $kodeTrx    = $row['kode_transaksi'];
    $harga      = (float) preg_replace('/[^0-9]/', '', $row['harga'] ?? '');
    $volume     = (float) preg_replace('/[^0-9]/', '', $row['volume'] ?? '');
    $biayaAdmin = (float) preg_replace('/[^0-9]/', '', $row['biaya_admin'] ?? '');
    $row['jumlah'] = ($harga * $volume);
    $row['biaya_admin_clean'] = $biayaAdmin;
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
            'metode_pembayaran' => $row['metode_pembayaran'] ?? 'cash',
            'items'             => [],
            'nota'              => null,
            'kode_transaksi'    => $kodeTrx,
            'sample_id'         => $row['id_pembelian'],
        ];
    }
    $grouped[$tanggal]['suppliers'][$idSupplier]['items'][] = $row;
    $grouped[$tanggal]['suppliers'][$idSupplier]['subtotal'] += $row['jumlah'];
    $grouped[$tanggal]['suppliers'][$idSupplier]['total_biaya_admin'] += $biayaAdmin;
    if (!empty($row['nota']) && empty($grouped[$tanggal]['suppliers'][$idSupplier]['nota'])) {
        $grouped[$tanggal]['suppliers'][$idSupplier]['nota'] = $row['nota'];
    }
    $grouped[$tanggal]['total'] += $row['jumlah'] + $biayaAdmin;
    $grouped[$tanggal]['item_count'] += 1;
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
    <link rel="stylesheet" href="style.css">
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
    include '../components/navbar.php'; ?>
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
            <button class="add-btn" onclick="openAddModal()">
                <i class="ph ph-plus-circle"></i>
                Tambah Transaksi Pembelian
            </button>
        </div>
        <?php if (empty($grouped)): ?>
            <div class="empty-state">
                <i class="ph ph-receipt"></i>
                <h3>Belum ada transaksi pembelian</h3>
                <p>Mulai catat pembelian dengan menekan tombol "Tambah Transaksi Pembelian" di atas.</p>
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
                                    ?>
                                    <details class="supplier-accordion">
                                        <summary class="supplier-header">
                                            <span class="supplier-icon"><i class="ph ph-storefront"></i></span>
                                            <span class="supplier-info">
                                                <strong><?= htmlspecialchars($supplierData['nama_supplier']) ?></strong>
                                                <small><i class="ph ph-phone"></i> <?= htmlspecialchars($supplierData['no_telepon']) ?></small>
                                                <span class="<?= $badgeMetodeClass ?>" style="margin-top:4px;">
                                                    <i class="ph <?= $iconMetode ?>"></i>
                                                    <?= htmlspecialchars(strtoupper($metode)) ?>
                                                    <?php if ($supplierData['total_biaya_admin'] > 0): ?>
                                                        <span class="badge-admin-fee">+<?= rupiah($supplierData['total_biaya_admin']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                            <span class="supplier-subtotal"><?= rupiah($supplierData['subtotal'] + $supplierData['total_biaya_admin']) ?></span>
                                            <i class="ph ph-caret-down toggle-caret-sm"></i>
                                        </summary>
                                        <div class="item-list">
                                            <?php foreach ($supplierData['items'] as $row): ?>
                                                <div class="item-row" data-nama="<?= htmlspecialchars(strtolower($row['nama_barang'] ?? '')) ?>">
                                                    <div class="item-name-col">
                                                        <span class="item-name"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></span>
                                                        <span class="item-sub"><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></span>
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
                                                        <button type="button" class="edit-btn" data-id="<?= (int) $row['id_pembelian'] ?>">
                                                            <i class="ph ph-pencil-simple"></i> Edit
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="supplier-nota-row">
                                            <div class="supplier-nota-left">
                                                <span class="supplier-nota-label">
                                                    <i class="ph ph-receipt"></i> Bukti Nota
                                                </span>
                                                <?php if ($hasNota): ?>
                                                    <?php
                                                    $notas = explode(',', $supplierData['nota']);
                                                    foreach ($notas as $index => $singleNota):
                                                        $singleNota = trim($singleNota);
                                                        if (empty($singleNota)) continue;
                                                        $notaUrl = '../uploads/nota/' . rawurlencode($singleNota);
                                                        $isPdf = str_ends_with(strtolower($singleNota), '.pdf');
                                                        $notaNum = count($notas) > 1 ? ' ' . ($index + 1) : '';
                                                    ?>
                                                        <button type="button" class="nota-thumb-btn lihat-nota-btn" data-nota="<?= htmlspecialchars($notaUrl) ?>" style="margin-right: 5px; margin-bottom: 5px;">
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
                                            <div class="supplier-nota-actions">
                                                <button type="button" class="<?= $hasNota ? 'ganti-nota-btn' : 'add-nota-btn' ?>"
                                                    data-id="<?= (int) $supplierData['sample_id'] ?>"
                                                    data-supplier="<?= htmlspecialchars($supplierData['nama_supplier']) ?>">
                                                    <i class="ph ph-<?= $hasNota ? 'arrows-clockwise' : 'camera-plus' ?>"></i>
                                                    <?= $hasNota ? 'Ganti Nota' : 'Tambah Nota' ?>
                                                </button>
                                            </div>
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

    <!-- MODAL TAMBAH -->
    <div class="modal" id="tambahModal">
        <div class="modal-content modal-content-tambah">
            <div class="modal-header">
                <h2><i class="ph ph-shopping-cart"></i> Tambah Transaksi Pembelian</h2>
                <button type="button" class="modal-close" onclick="closeTambahModal()" aria-label="Tutup">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <form id="tambah-form" action="../database/add-transaksi.php" method="post" enctype="multipart/form-data">
                <div class="tambah-top-grid">
                    <div class="form-group">
                        <label for="add_id_supplier">Supplier</label>
                        <select name="id_supplier" id="add_id_supplier" required>
                            <option value="">-- Pilih Supplier --</option>
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
                    <div class="form-group">
                        <label for="add_tanggal">Tanggal Pembelian</label>
                        <input type="date" id="add_tanggal" name="tanggal_pembelian" required>
                    </div>
                </div>
                <div class="tambah-payment-grid">
                    <div class="form-group">
                        <label for="add_metode_pembayaran">Metode Pembayaran</label>
                        <div class="metode-radio-group">
                            <label class="metode-radio-label" data-metode="cash">
                                <input type="radio" name="metode_pembayaran" value="cash" id="add_metode_cash" checked>
                                <span class="metode-radio-btn"><i class="ph ph-money"></i> Cash</span>
                            </label>
                            <label class="metode-radio-label" data-metode="qris">
                                <input type="radio" name="metode_pembayaran" value="qris" id="add_metode_qris">
                                <span class="metode-radio-btn"><i class="ph ph-qr-code"></i> QRIS</span>
                            </label>
                            <label class="metode-radio-label" data-metode="transfer">
                                <input type="radio" name="metode_pembayaran" value="transfer" id="add_metode_transfer">
                                <span class="metode-radio-btn"><i class="ph ph-bank"></i> Transfer</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="add_biaya_admin_group" style="display:none;">
                        <label for="add_biaya_admin">Biaya Admin</label>
                        <input type="text" id="add_biaya_admin" name="biaya_admin" placeholder="Rp 0" value="">
                        <small class="upload-hint">Biaya admin QRIS/Transfer (opsional, isi 0 jika tidak ada).</small>
                    </div>
                </div>
                <div class="daftar-barang-section">
                    <div class="daftar-barang-header">
                        <div class="daftar-barang-title">
                            <i class="ph ph-list-bullets"></i>
                            <span>Daftar Barang</span>
                            <span class="item-count-badge" id="item-count-badge">0 item</span>
                        </div>
                        <button type="button" class="tambah-barang-btn" onclick="addItemRow()">
                            <i class="ph ph-plus"></i> Tambah Barang
                        </button>
                    </div>
                    <!-- <div class="item-table-header">
                        <span class="th-nama">Nama Barang</span>
                        <span class="th-harga">Harga Beli</span>
                        <span class="th-ket">Keterangan</span>
                        <span class="th-eceran">Harga Eceran</span>
                        <span class="th-keuntungan">Keuntungan</span>
                        <span class="th-harga-jual">Harga Jual/Dus</span>
                        <span class="th-harga-jual-eceran">Harga Jual Eceran</span>
                        <span class="th-volume">Volume</span>
                        <span class="th-satuan">Satuan</span>
                        <span class="th-subtotal">Sub Total</span>
                        <span class="th-aksi"></span>
                    </div> -->
                    <div id="item-rows-container"></div>
                    <div class="item-empty-state" id="item-empty-state">
                        <i class="ph ph-package"></i>
                        <p>Belum ada barang.<br>Klik <strong>+ Tambah Barang</strong> untuk mulai.</p>
                    </div>
                    <div class="total-row" id="total-row" style="display:none;">
                        <span class="total-label">Total Keseluruhan</span>
                        <span class="total-value" id="grand-total-display">Rp 0</span>
                    </div>
                </div>
                <div class="nota-upload-wrapper">
                    <p class="nota-upload-heading">
                        <i class="ph ph-receipt"></i> Bukti Nota <span class="optional-tag">opsional</span>
                    </p>
                    <div class="nota-upload-grid-add">
                        <div class="form-group camera-only">
                            <label for="add_nota_kamera">Foto Nota (Kamera)</label>
                            <label class="upload-dropzone" for="add_nota_kamera" tabindex="0">
                                <i class="ph ph-camera"></i>
                                <span class="upload-text">Ambil foto nota</span>
                                <span class="upload-filename"></span>
                            </label>
                            <input type="file" id="add_nota_kamera" name="nota_kamera[]" accept="image/*,.png,.jpg,.jpeg,.pdf" capture="environment" multiple hidden>
                            <div class="selected-files-list" id="add_nota_kamera-selected-files-list"></div>
                        </div>
                        <div class="form-group">
                            <label for="add_nota_file">Foto Nota (File)</label>
                            <label class="upload-dropzone" for="add_nota_file" tabindex="0">
                                <i class="ph ph-upload-simple"></i>
                                <span class="upload-text">Pilih atau seret berkas di sini</span>
                                <span class="upload-filename"></span>
                            </label>
                            <input type="file" id="add_nota_file" name="nota_file[]" accept="image/*,.png,.jpg,.jpeg,.pdf" multiple hidden>
                            <small class="upload-hint">Jika nota belum ada, boleh dikosongkan (Bisa pilih/unggah lebih dari 1 file).</small>
                            <div class="selected-files-list" id="add_nota_file-selected-files-list"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeTambahModal()">Batal</button>
                    <button type="submit" id="tambah-submit-btn">
                        <i class="ph ph-paper-plane-tilt"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>

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
                <button type="submit" id="detail-edit-btn">Edit Transaksi</button>
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
            <div class="modal-actions">
                <a href="#" target="_blank" rel="noopener" id="nota-open-new-tab" class="cancel nota-open-link">
                    <i class="ph ph-arrow-square-out"></i> Buka di Tab Baru
                </a>
            </div>
        </div>
    </div>
</body>
<script src="script.js"></script>

</html>