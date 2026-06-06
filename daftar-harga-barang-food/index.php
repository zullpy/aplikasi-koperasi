<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../database/koneksi.php';

$query = "SELECT 
            id_barang,
            nama_barang,
            kategori,
            harga_beli,
            harga_jual,
            suplier,
            satuan
          FROM barang";
$resultDesk = mysqli_query($koneksi, $query);
$resultMobile = mysqli_query($koneksi, $query);
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
    <?php
    if(isset($_SESSION['alert'])): ?>
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
    <?php
    unset($_SESSION['alert']);
    endif;?>
    <?php $activePage = 'daftar-harga-barang'; include '../components/navbar.php'; ?>

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
                    <th>Harga Jual</th>
                    <th>Satuan</th>
                    <th>Nama Toko</th>
                    <th>Alamat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultDesk)) : ?>
                <tr>
                    <td class="nama-barang"><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></td>
                    <td><?= htmlspecialchars($row['kategori']) ?></td>
                    <td>
                        <span class="badge">
                            Rp <?= number_format($row['harga_beli'], 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge">
                            Rp <?= number_format($row['harga_jual'], 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td class="suplier"><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></td>
                    <td class="suplier"><?= !empty($row['suplier']) ? htmlspecialchars($row['suplier']) : '-' ?></td>
                    <td class="suplier"><?= !empty($row['alamat']) ? htmlspecialchars($row['alamat']) : '-' ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                                <i class="ph ph-pencil-simple"></i> Edit
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
                    <span>Kategori</span>
                <strong><?= htmlspecialchars($row['kategori']) ?></strong>
            </div>
            
            <div class="detail-item">
                <span>Harga Beli</span>
                <strong class="badge">
                    <?= htmlspecialchars($row['harga_beli']) ?>
                </strong>
            </div>

            <div class="detail-item">
                <span>Harga Jual</span>
                <strong><?= !empty($row['stok_akhir']) ? htmlspecialchars($row['stok_akhir']) : '-' ?></strong>
            </div>

            <div class="detail-item">
                <span>Satuan</span>
                <strong><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></strong>
            </div>

            <div class="detail-item">
                <span>Nama Toko</span>
                <strong><?= !empty($row['suplier']) ? htmlspecialchars($row['suplier']) : '-' ?></strong>
            </div>

            <div class="detail-item">
                <span>Alamat</span>
                <strong><?= !empty($row['alamat']) ? htmlspecialchars($row['alamat']) : '-' ?></strong>
            </div>

            <div class="detail-item actions-mobile" style="margin-top: 10px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                <span>Aksi</span>
                <div class="action-buttons">
                    <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                        <i class="ph ph-pencil-simple"></i> Edit
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
            <form id="modal-form" action="../database/add-barang-baru.php" method="post" >
                <input type="hidden" id="id_barang" name="id_barang">
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
                    <label for="harga_beli">Harga Beli</label>
                    <input type="text" id="harga_beli" name="harga_beli" required>
                </div>
                <div class="form-group">
                    <label for="satuan">Satuan</label>
                    <input type="text" id="satuan" name="satuan" required>
                </div>
                <div class="form-group">
                    <label for="suplier">Nama Toko</label>
                    <input type="text" id="suplier" name="suplier" required>
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat Toko</label>
                    <input type="text" id="alamat" name="alamat" required>
                </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="submit-btn">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="script.js"></script>
</html>