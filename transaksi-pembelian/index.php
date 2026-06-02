<?php
include '../database/koneksi.php';

$query = "SELECT 
            nama_barang,
            keterangan,
            harga_beli,
            tgl_terupdate,
            satuan,
            stok_akhir
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
        <h1>Transaksi Pembelian</h1>
        <button class="add-btn" onclick="openModal()">
            <i class="ph ph-plus-circle"></i>
            Tambah Transaksi Pembelian
        </button>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Tgl Pembelian</th>
                    <th>Harga Beli</th>
                    <th>Volume</th>
                    <th>Satuan</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
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
        </details>
        <?php endwhile; ?>
    </div>
    </main>

    <div class="modal">
        <div class="modal-content">
            <h2>Tambah Transaksi Pembelian</h2>
            <form action="../database/add-transaksi.php" method="post">
                <div class="form-group">
                    <label for="nama_barang">Nama Barang</label>
                    <input type="text" id="nama_barang" name="nama_barang" required>
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
                <button type="submit">Tambah</button>
                <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
</body>
<script src="script.js"></script>
</html>