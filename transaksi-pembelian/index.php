<?php
include '../database/koneksi.php';

$query = "SELECT 
            id_barang,
            nama_barang,
            keterangan,
            harga_beli,
            tgl_terupdate,
            satuan,
            stok_akhir,
            nota
          FROM barang";
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
            <button class="add-btn" onclick="openModal()">
                <i class="ph ph-plus-circle"></i>
                Tambah Transaksi Pembelian
            </button>
        </div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Tanggal Pembelian</th>
                    <th>Harga Beli</th>
                    <th>Volume</th>
                    <th>Satuan</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                    <th>Bukti Nota</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultDesk)) : ?>
                <tr>
                    <td><?= !empty($row['nama_barang']) ? htmlspecialchars($row['nama_barang']) : '-' ?></td>
                    <td><?= htmlspecialchars($row['tgl_terupdate']) ?></td>
                    <td>
                        <span class="badge">
                            <?= $row['harga_beli']; ?>
                        </span>
                    </td>
                    <td><?= !empty($row['stok_akhir']) ? htmlspecialchars($row['stok_akhir']) : '-' ?></td>
                    <td><?= !empty($row['satuan']) ? htmlspecialchars($row['satuan']) : '-' ?></td>
                    <td><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></td>
                    <td>
                        <?php
                        $harga = (float) preg_replace('/[^0-9]/', '', $row['harga_beli'] ?? "");
                        $stok  = (float) preg_replace('/[^0-9]/', '', $row['stok_akhir'] ?? "");
                        $jumlah = $harga * $stok;
                        ?>
                        <span class="badge">
                            Rp <?= number_format($jumlah, 0, ',', '.') ?>
                        </span>
                    </td>
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
                            <button class="edit-btn" data-id="<?= $row['id_barang'] ?>">
                                <i class="ph ph-pencil-simple"></i> Edit
                            </button>
                            <button class="delete-btn" data-id="<?= $row['id_barang'] ?>">
                                <i class="ph ph-trash"></i> Hapus
                            </button>
                            <button class="add-nota-btn">
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
                    <button class="add-nota-btn">
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
            <h2>Tambah Transaksi Pembelian</h2>
            <form action="../database/add-transaksi.php" method="post" enctype="multipart/form-data">
                <div class="grid">
                <div class="form-group">
                    <label for="nama_barang">Nama Barang</label>
                    <input type="text" id="nama_barang" name="nama_barang" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Pembelian</label>
                    <input type="date" id="tanggal" name="tanggal" required>
                </div>
                <div class="form-group">
                    <label for="harga_beli">Harga Beli</label>
                    <input type="text" id="harga_beli" name="harga_beli" required>
                </div>
                <div class="form-group">
                    <label for="stok_akhir">Volume</label>
                    <input type="text" id="stok_akhir" name="stok_akhir" required>
                </div>
                <div class="form-group">
                    <label for="satuan">Satuan</label>
                    <input type="text" id="satuan" name="satuan" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" required>
                </div>
                <div class="form-group camera-only">
                    <label for="nota_kamera">Foto Nota (Kamera)</label>
                    <input type="file" id="nota_kamera" name="nota_kamera" accept="image/*" capture="environment">
                </div>
                <div class="form-group file-input-group">
                    <label for="nota_file">Foto Nota (File)</label>
                    <input type="file" id="nota_file" name="nota_file" accept="image/*">
                </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="script.js"></script>
</html>