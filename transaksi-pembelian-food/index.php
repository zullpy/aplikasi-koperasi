<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../database/koneksi.php';

    if(isset($_SESSION['alert'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: '<?php echo $_SESSION['alert']['icon']; ?>',
    title: '<?php echo $_SESSION['alert']['title']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['alert']); ?>
<?php endif;

$query = "SELECT
    p.id_pembelian,
    p.nama_barang,
    p.keterangan,
    p.harga,
    p.volume,
    p.satuan,
    p.tanggal_pembelian,
    p.nota,

    s.id_supplier,
    s.nama_supplier,
    s.no_telepon,
    s.alamat

FROM transaksi_pembelian p
INNER JOIN suplier s
ON p.id_supplier = s.id_supplier
ORDER BY p.id_pembelian DESC";

$resultDesk = mysqli_query($koneksi, $query);
$resultMobile = mysqli_query($koneksi, $query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pembelian|Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css"
    />
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css"
    />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <?php $activePage = 'transaksi-pembelian'; include '../components/navbar.php'; ?>

    <main class="container">
    <div class="header-section">
        <h1>Transaksi Pembelian</h1>
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

    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Keterangan</th>
                    <th>Harga Beli</th>
                    <th>Volume</th>
                    <th>Satuan</th>
                    <th>Jumlah</th>
                    <th>Tanggal Pembelian</th>
                    <th>Nama Toko</th>
                    <th>Alamat Toko</th>
                    <th>Bukti Nota</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultDesk)) : ?>
                <tr>
                    <td class="nama-barang"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></td>
                    <td><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></td>
                    <td>
                        <span class="badge">
                            <?= $row['harga']; ?>
                        </span>
                    </td>
                    <td><?= !empty($row['volume']) ? htmlspecialchars($row['volume']) : '-' ?></td>
                    <td><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></td>
                    <td>
                        <?php
                        $harga = (float) preg_replace('/[^0-9]/', '', $row['harga'] ?? "");
                        $volume = (float) preg_replace('/[^0-9]/', '', $row['volume'] ?? "");
                        $jumlah = $harga * $volume;
                        ?>
                        <span class="badge">
                            Rp <?= number_format($jumlah, 0, ',', '.') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['tanggal_pembelian']) ?></td>
                    <td><?= $row['nama_supplier']; ?></td>
                    <td><?= $row['alamat']; ?></td>
                    <td>
                        <?php if (!empty($row['nota'])): ?>
                            <a href="../uploads/nota/<?= htmlspecialchars($row['nota']) ?>" target="_blank" class="nota-link">
                                <i class="ph ph-file-text"></i> Lihat Nota
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="edit-btn" data-id="<?= $row['id_pembelian'] ?>">
                                <i class="ph ph-pencil-simple"></i> Edit
                            </button>
                            <button class="delete-btn" data-id="<?= $row['id_pembelian'] ?>">
                                <i class="ph ph-trash"></i> Hapus
                            </button>
                            <button class="add-nota-btn" data-id="<?= $row['id_pembelian'] ?>">
                                <i class="ph ph-camera-plus"></i> Nota
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-card">
        <?php while($row = mysqli_fetch_assoc($resultMobile)) : ?>
        <details class="barang-card">
            <summary>
                <?= htmlspecialchars($row['nama_barang']) ?>
                <i class="ph ph-caret-up"></i>
            </summary>

            <div class="detail-item">
                <span>Tgl Pembelian</span>
                <strong><?= htmlspecialchars($row['tgl_terupdate']) ?></strong>
            </div>

            <div class="detail-item">
                <span>Harga Beli</span>
                <strong class="badge">
                    <?= htmlspecialchars($row['harga_beli']) ?>
                </strong>
            </div>

            <div class="detail-item">
                <span>Volume</span>
                <strong><?= !empty($row['stok_akhir']) ? htmlspecialchars($row['stok_akhir']) : '-' ?></strong>
            </div>

            <div class="detail-item">
                <span>Satuan</span>
                <strong><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></strong>
            </div>

            <div class="detail-item">
                <span>Keterangan</span>
                <strong><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></strong>
            </div>

            <div class="detail-item total">
                <span>Jumlah</span>
                <?php
                    $harga = (float) preg_replace('/[^0-9]/', '', $row['harga_beli']);
                    $stok  = (float) preg_replace('/[^0-9]/', '', $row['stok_akhir']);
                    $jumlah = $harga * $stok;
                ?>
                <strong class="badge">
                    Rp <?= number_format($jumlah, 0, ',', '.') ?>
                </strong>
            </div>

            <div class="detail-item">
                <span>Bukti Nota</span>
                <strong>
                    <?php if (!empty($row['nota'])): ?>
                        <a href="../uploads/nota/<?= htmlspecialchars($row['nota']) ?>" target="_blank" class="nota-link-mobile">
                            <i class="ph ph-file-text"></i> Lihat Nota
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </strong>
            </div>

            <div class="detail-item actions-mobile" style="margin-top: 10px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                <span>Aksi</span>
                <div class="action-buttons">
                    <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                        <i class="ph ph-pencil-simple"></i> Edit
                    </button>
                    <button class="delete-btn" data-id="<?= $row['id_barang'] ?>">
                        <i class="ph ph-trash"></i> Hapus
                    </button>
                    <button class="add-nota-btn" data-id="<?= $row['id_barang'] ?>">
                        <i class="ph ph-camera-plus"></i> Nota
                    </button>
                </div>
            </div>
        </details>
        <?php endwhile; ?>
    </div>
    </main>

    <div class="modal">
        <div class="modal-content">
            <h2 id="modal-title">Tambah Transaksi Pembelian</h2>
            <form id="modal-form" action="../database/add-transaksi.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="id_barang" name="id_barang">
                <div class="grid">
                <div class="form-group autocomplete-wrapper">
                    <label for="nama_barang">Nama Barang</label>
                    <input
                        type="text"
                        id="nama_barang"
                        name="nama_barang"
                        autocomplete="off"
                        required
                    >
                    <div id="suggestions" ></div>

                    <small id="info-barang"></small>
                </div>
                <div class="form-group">
                    <label for="tanggal_pembelian">Tanggal Pembelian</label>
                    <input type="date" id="tanggal_pembelian" name="tanggal_pembelian" required>
                </div>
                <div class="form-group">
                    <label for="harga">Harga Beli</label>
                    <input type="text" id="harga" name="harga" required>
                </div>
                <div class="form-group">
                    <label for="volume">Volume</label>
                    <input type="text" id="volume" name="volume" required>
                </div>
                <div class="form-group">
                    <label for="satuan">Satuan</label>
                    <input type="text" id="satuan" name="satuan" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" required>
                </div>
                <div class="form-group">
                    <label for="id_supplier">Supplier</label>
                    <select name="id_supplier" id="id_supplier" required>
                        <option value="">Pilih Supplier</option>
                        <?php
                        $q = mysqli_query($koneksi, "SELECT * FROM suplier");
                        while($s = mysqli_fetch_assoc($q)){
                        ?>
                            <option value="<?= $s['id_supplier']; ?>">
                                <?= $s['nama_supplier']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group camera-only">
                    <label for="nota_kamera">Foto Nota (Kamera)</label>
                    <input type="file" id="nota_kamera" name="nota_kamera" accept="image/*,.png,.jpg,.jpeg,.pdf" capture="environment">
                </div>
                <div class="form-group file-input-group">
                    <label for="nota_file" id="nota_file_label">Foto Nota (File)</label>
                    <input type="file" id="nota_file" name="nota_file" accept="image/*,.png,.jpg,.jpeg,.pdf">
                </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="submit-btn">Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="notaModal">
        <div class="modal-content" style="max-width: 500px;">
            <h2>Unggah Bukti Nota</h2>
            <form id="nota-form" action="../database/add-nota.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="nota_id_barang" name="id_barang">
                <div class="grid" style="grid-template-columns: 1fr;">
                    <div class="form-group camera-only">
                        <label for="nota_kamera_only">Foto Nota (Kamera)</label>
                        <input type="file" id="nota_kamera_only" name="nota_kamera" accept="image/*,.png,.jpg,.jpeg,.pdf" capture="environment">
                    </div>
                    <div class="form-group file-input-group" style="width: 100%;">
                        <label for="nota_file_only">Foto Nota (File)</label>
                        <input type="file" id="nota_file_only" name="nota_file" accept="image/*,.png,.jpg,.jpeg,.pdf">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeNotaModal()">Cancel</button>
                    <button type="submit">Unggah</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="script.js"></script>
</html>
