<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../database/auth.php';
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../');
    exit;
}
include '../database/koneksi.php';
$supplierResult = mysqli_query($koneksi, "SELECT * FROM suplier ORDER BY nama_supplier ASC");
$kategoriResult = mysqli_query($koneksi, "SELECT DISTINCT kategori FROM barang WHERE kategori IS NOT NULL AND kategori <> '' ORDER BY kategori ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Transaksi Pembelian | Bina Usaha Sauyunan</title>
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
    <?php $activePage = 'transaksi-pembelian';
    include '../components/navbar.php'; ?>
    <main class="container">
        <div class="header-section">
            <div class="header-title">
                <h1>Tambah Transaksi Pembelian</h1>
                <p class="header-subtitle">Catat transaksi pembelian baru dari suplier</p>
            </div>
            <a href="index.php" class="add-btn" style="background:#f1f5f9; color:var(--text-main);">
                <i class="ph ph-arrow-left"></i>
                Kembali
            </a>
        </div>

        <div class="modal-content modal-content-tambah" id="tambah-page-card" style="margin:0 auto; max-height:none; overflow:visible; animation:none;">
            <form id="tambah-form" action="../database/add-transaksi.php" method="post" enctype="multipart/form-data">
                <datalist id="kategori-list">
                    <?php
                    if ($kategoriResult) {
                        while ($k = mysqli_fetch_assoc($kategoriResult)) {
                    ?>
                            <option value="<?= htmlspecialchars($k['kategori']); ?>"></option>
                    <?php
                        }
                    }
                    ?>
                </datalist>
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
                <div class="tambah-payment-grid">
                    <div class="form-group">
                        <label>Status Pembayaran</label>
                        <div class="metode-radio-group status-bayar-group">
                            <label class="metode-radio-label status-radio-label" data-status="lunas">
                                <input type="radio" name="status_pembayaran" value="lunas" id="add_status_lunas" checked>
                                <span class="metode-radio-btn"><i class="ph ph-check-circle"></i> Lunas</span>
                            </label>
                            <label class="metode-radio-label status-radio-label" data-status="sebagian">
                                <input type="radio" name="status_pembayaran" value="sebagian" id="add_status_sebagian">
                                <span class="metode-radio-btn"><i class="ph ph-hourglass-medium"></i> Bayar Sebagian</span>
                            </label>
                        </div>
                        <small class="upload-hint">Status berlaku untuk seluruh nota/transaksi ini (1x bayar per nota).</small>
                    </div>
                    <div class="form-group" id="add_jumlah_dibayar_group" style="display:none;">
                        <label for="add_jumlah_dibayar">Jumlah Dibayar Sekarang</label>
                        <input type="text" id="add_jumlah_dibayar" name="jumlah_dibayar" placeholder="Rp 0" value="">
                        <small class="upload-hint" id="sisa-bayar-hint">Sisa akan otomatis dihitung dari Total Keseluruhan.</small>
                    </div>
                </div>
                <div class="nota-upload-wrapper" id="add_bukti_bayar_wrapper" style="display:none;">
                    <p class="nota-upload-heading">
                        <i class="ph ph-receipt"></i> Bukti Pembayaran <span class="optional-tag">opsional</span>
                    </p>
                    <div class="form-group">
                        <label class="upload-dropzone" for="add_bukti_pembayaran" tabindex="0">
                            <i class="ph ph-upload-simple"></i>
                            <span class="upload-text">Pilih atau seret bukti transfer/QRIS di sini</span>
                            <span class="upload-filename"></span>
                        </label>
                        <input type="file" id="add_bukti_pembayaran" name="bukti_pembayaran" accept="image/*,.png,.jpg,.jpeg,.pdf" hidden>
                        <div class="selected-files-list" id="add_bukti_pembayaran-selected-files-list"></div>
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
                    <a href="index.php" class="cancel" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">Batal</a>
                    <button type="submit" id="tambah-submit-btn">
                        <i class="ph ph-paper-plane-tilt"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </main>
    <?php include '../components/made-by.php'; ?>
</body>
<script src="script.js"></script>
<script>
    // Halaman ini berdiri sendiri (bukan modal), jadi langsung inisialisasi form saat load
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof initTambahForm === 'function') initTambahForm();
        if (typeof toggleStatusPembayaran === 'function') toggleStatusPembayaran();
    });
</script>

</html>