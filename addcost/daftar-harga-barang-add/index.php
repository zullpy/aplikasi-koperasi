<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../../..');
    exit;
}
session_start();
include '../database/koneksi.php';
$query = "SELECT
b.id_barang,
b.nama_barang,
b.kategori,
b.harga_beli,
b.harga_jual,
b.suplier,
b.satuan,
b.alamat,
b.tanggal_terupdate_baru,
COALESCE(MIN(r.harga_beli), b.harga_beli) AS harga_min,
COALESCE(MAX(r.harga_beli), b.harga_beli) AS harga_max
FROM barang b
LEFT JOIN riwayat_harga r
ON b.id_barang = r.id_barang
GROUP BY
b.id_barang,
b.nama_barang,
b.kategori,
b.harga_beli,
b.harga_jual,
b.suplier,
b.satuan,
b.alamat,
b.tanggal_terupdate_baru";
$result = mysqli_query($koneksi, $query);
$barang_list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $barang_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Harga Barang|Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php if (isset($_SESSION['alert'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: '<?php echo $_SESSION['alert']['icon']; ?>',
                    title: '<?php echo $_SESSION['alert']['title']; ?>',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
    <?php unset($_SESSION['alert']);
    endif; ?>
    <?php $activePage = 'daftar-harga-barang';
    include '../components/navbar.php'; ?>
    <main class="container">
        <div class="header-section">
            <h1>Daftar Harga Barang</h1>
            <div class="search-bar">
                <div class="input-group">
                    <input type="text" id="search-bar" placeholder="Cari nama barang...">
                    <i class="ph ph-magnifying-glass"></i>
                </div>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <i class="ph ph-plus-circle"></i>
                Tambah Barang Baru
            </button>
        </div>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga Beli</th>
                        <th>Tanggal Terupdate</th>
                        <th>Keuntungan</th>
                        <th>Harga Jual</th>
                        <th>Satuan</th>
                        <th>Nama Toko</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($barang_list as $row) :
                        $keuntungan = (int)$row['harga_jual'] - (int)$row['harga_beli'];
                    ?>
                        <tr>
                            <td class="nama-barang"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></td>
                            <td><?= htmlspecialchars($row['kategori']) ?></td>
                            <td>
                                <div class="badge">Rp <?= number_format($row['harga_beli'], 0, ',', '.'); ?></div>
                                <?php if ($row['harga_min'] != $row['harga_max']) : ?>
                                    <small style="display:block;color:#666;">
                                        Rp <?= number_format($row['harga_min'], 0, ',', '.') ?> - Rp <?= number_format($row['harga_max'], 0, ',', '.') ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><span><?= date('d-m-Y', strtotime($row['tanggal_terupdate_baru'])); ?></span></td>
                            <td>
                                <span class="badge badge-keuntungan">
                                    + Rp <?= number_format($keuntungan, 0, ',', '.'); ?>
                                </span>
                            </td>
                            <td><span class="badge badge-jual">Rp <?= number_format($row['harga_jual'], 0, ',', '.'); ?></span></td>
                            <td class="suplier"><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></td>
                            <td class="suplier"><?= !empty($row['suplier']) ? htmlspecialchars($row['suplier']) : '-' ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                                        <i class="ph ph-pencil-simple"></i> Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mobile-card">
            <?php foreach ($barang_list as $row) :
                $keuntungan = (int)$row['harga_jual'] - (int)$row['harga_beli'];
            ?>
                <details class="barang-card">
                    <summary>
                        <div class="barang-card-header">
                            <div class="barang-title-section">
                                <span class="barang-name"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></span>
                                <div class="barang-badges">
                                    <?php if (!empty($row['kategori'])) : ?>
                                        <span class="badge-category"><?= htmlspecialchars($row['kategori']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['satuan'])) : ?>
                                        <span class="badge-unit"><?= htmlspecialchars($row['satuan']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="barang-price-section">
                                <span class="barang-price">Rp <?= number_format($row['harga_jual'], 0, ',', '.'); ?></span>
                                <span class="barang-price-label">Harga Jual</span>
                            </div>
                        </div>
                        <i class="ph ph-caret-down barang-card-toggle"></i>
                    </summary>
                    <div class="barang-card-details">
                        <div class="detail-row">
                            <span class="detail-row-label">Harga Beli</span>
                            <span class="detail-row-value price-buy">Rp <?= number_format($row['harga_beli'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Keuntungan</span>
                            <span class="detail-row-value price-profit">+ Rp <?= number_format($keuntungan, 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Harga Max & Min</span>
                            <span class="detail-row-value">Rp <?= number_format($row['harga_max'], 0, ',', '.'); ?> - Rp <?= number_format($row['harga_min'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Tanggal Terupdate</span>
                            <span class="detail-row-value"><?= date('d-m-Y', strtotime($row['tanggal_terupdate_baru'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Nama Toko</span>
                            <span class="detail-row-value"><?= !empty($row['suplier']) ? htmlspecialchars($row['suplier']) : '-' ?></span>
                        </div>
                        <div class="mobile-actions">
                            <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                                <i class="ph ph-pencil-simple"></i> Edit
                            </button>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- MODAL -->
    <div class="modal">
        <div class="modal-content">
            <h2 id="modal-title">Tambah Barang</h2>
            <form id="modal-form" action="../database/add-barang-baru.php" method="post">
                <input type="hidden" id="id_barang" name="id_barang">

                <!-- Section Info Dasar -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="ph ph-package"></i>
                        <span>Informasi Barang</span>
                    </div>
                    <div class="grid">
                        <div class="form-group">
                            <label for="nama_barang">Nama Barang</label>
                            <input type="text" id="nama_barang" name="nama_barang" required>
                        </div>
                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <input type="text" id="kategori" name="kategori" required>
                        </div>
                        <div class="form-group">
                            <label for="satuan">Satuan</label>
                            <input type="text" id="satuan" name="satuan" required placeholder="pcs, kg, liter, dll">
                        </div>
                        <div class="form-group">
                            <label for="suplier">Nama Toko</label>
                            <input type="text" id="suplier" name="suplier" required>
                        </div>
                        <div class="form-group form-group-full">
                            <label for="tanggal_terupdate_baru">Tanggal Terupdate</label>
                            <input type="date" id="tanggal_terupdate_baru" name="tanggal_terupdate_baru" required>
                        </div>
                    </div>
                </div>

                <!-- Section Perhitungan Harga -->
                <div class="form-section price-section">
                    <div class="form-section-title">
                        <i class="ph ph-calculator"></i>
                        <span>Perhitungan Harga</span>
                    </div>
                    <div class="price-grid">
                        <div class="price-input-group">
                            <label for="harga_beli">Harga Beli</label>
                            <div class="price-input-wrapper">
                                <span class="price-prefix">Rp</span>
                                <input type="text" id="harga_beli" name="harga_beli" inputmode="numeric" required placeholder="0">
                            </div>
                        </div>

                        <div class="price-operator">+</div>

                        <div class="price-input-group">
                            <label for="keuntungan">
                                Keuntungan
                                <small class="label-hint">Margin yang diambil</small>
                            </label>
                            <div class="price-input-wrapper profit">
                                <span class="price-prefix">Rp</span>
                                <input type="text" id="keuntungan" name="keuntungan" inputmode="numeric" required placeholder="0">
                            </div>
                        </div>

                        <div class="price-operator">=</div>

                        <div class="price-input-group">
                            <label for="harga_jual">
                                Harga Jual
                                <small class="label-hint">Otomatis terhitung</small>
                            </label>
                            <div class="price-input-wrapper result">
                                <span class="price-prefix">Rp</span>
                                <input type="text" id="harga_jual" name="harga_jual" readonly placeholder="0">
                            </div>
                        </div>
                    </div>
                    <div class="price-info" id="price-info">
                        <i class="ph ph-info"></i>
                        <span>Isi harga beli & keuntungan, harga jual akan terhitung otomatis</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" id="submit-btn">
                        <i class="ph ph-check-circle"></i>
                        <span>Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="script.js"></script>

</html>