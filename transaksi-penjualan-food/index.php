<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ../');
    exit;
}
include '../database/koneksi.php';

$q = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi_penjualan WHERE DATE(tanggal)=CURDATE()");
$data = mysqli_fetch_assoc($q);
$urutan = $data['total'] + 1;
$tanggal = date('dmY');
$no_faktur = str_pad($urutan, 4, "0", STR_PAD_LEFT) . "-" . $tanggal . "-FC";

$query = mysqli_query($koneksi, "
    SELECT tp.*, p.nama_pelanggan
    FROM transaksi_penjualan tp
    JOIN pelanggan p ON tp.id_pelanggan = p.id_pelanggan
    ORDER BY tp.tanggal DESC
");

$q_hari = mysqli_query($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi_penjualan WHERE DATE(tanggal) = CURDATE()");
$hari_ini = mysqli_fetch_assoc($q_hari)['total'];
$q_minggu = mysqli_query($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi_penjualan WHERE YEARWEEK(tanggal, 1) = YEARWEEK(CURDATE(), 1)");
$minggu_ini = mysqli_fetch_assoc($q_minggu)['total'];
$q_bulan = mysqli_query($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi_penjualan WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$bulan_ini = mysqli_fetch_assoc($q_bulan)['total'];
$q_faktur = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi_penjualan WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$total_faktur = mysqli_fetch_assoc($q_faktur)['total'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan | Bina Usaha Sauyunan</title>
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
    <?php if (isset($_SESSION['alert'])) { ?>
        <script>
            Swal.fire({
                icon: '<?= $_SESSION['alert']['icon'] ?>',
                title: '<?= $_SESSION['alert']['title'] ?>',
                text: '<?= $_SESSION['alert']['text'] ?>',
                confirmButtonColor: '#2563a8'
            });
        </script>
    <?php unset($_SESSION['alert']);
    } ?>
    <?php $activePage = 'transaksi-penjualan';
    include '../components/navbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>Transaksi Penjualan</h1>
                <p>Kelola seluruh transaksi penjualan</p>
            </div>
            <button class="btn-primary" onclick="openModal()">
                <i class="ph ph-plus-circle"></i>
                Transaksi Baru
            </button>
        </div>

        <div class="stats">
            <div class="stat-card"><i class="ph-fill ph-currency-circle-dollar"></i><span>Hari Ini</span>
                <h2>Rp <?= number_format($hari_ini, 0, ',', '.') ?></h2>
            </div>
            <div class="stat-card"><i class="ph-fill ph-chart-line-up"></i><span>Minggu Ini</span>
                <h2>Rp <?= number_format($minggu_ini, 0, ',', '.') ?></h2>
            </div>
            <div class="stat-card"><i class="ph-fill ph-calendar"></i><span>Bulan Ini</span>
                <h2>Rp <?= number_format($bulan_ini, 0, ',', '.') ?></h2>
            </div>
            <div class="stat-card"><i class="ph-fill ph-receipt"></i><span>Total Faktur</span>
                <h2><?= $total_faktur ?: 0 ?></h2>
            </div>
        </div>

        <div class="filter-box">
            <input type="date">
            <input type="text" placeholder="Cari nama konsumen...">
        </div>

        <?php
        $tanggal_lama = '';
        while ($row = mysqli_fetch_assoc($query)) {
            $tanggal_baru = date('Y-m-d', strtotime($row['tanggal']));
            if ($tanggal_baru != $tanggal_lama) {
                if ($tanggal_lama != '') echo '</div>';
                echo '<div class="tanggal-section"><div class="tanggal-title">' . date('d F Y', strtotime($row['tanggal'])) . '</div>';
                $tanggal_lama = $tanggal_baru;
            }

            // Status badge
            $status = $row['status_pembayaran'] ?? 'lunas';
            $badge_class = '';
            $badge_text = '';
            if ($status == 'lunas') {
                $badge_class = 'badge-lunas';
                $badge_text = 'LUNAS';
            } elseif ($status == 'sebagian') {
                $badge_class = 'badge-sebagian';
                $badge_text = 'SEBAGIAN';
            } else {
                $badge_class = 'badge-belum';
                $badge_text = 'BELUM LUNAS';
            }

            $sisa = $row['total'] - $row['total_bayar'];

            echo '
        <div class="transaksi-card">
            <div class="left">
                <h3>' . $row['nama_pelanggan'] . '</h3>
                <div class="info">
                    <span>' . $row['no_faktur'] . '</span>
                    <span>' . date('H:i', strtotime($row['tanggal'])) . '</span>
                    <span class="status-badge ' . $badge_class . '">' . $badge_text . '</span>
                </div>
                ' . ($status != 'lunas' ? '<div class="info" style="margin-top:5px;"><span style="color:#ef4444;font-weight:600;">Sisa: Rp ' . number_format($sisa, 0, ',', '.') . '</span></div>' : '') . '
            </div>
            <div class="right">
                <h3>Rp ' . number_format($row['total'], 0, ',', '.') . '</h3>
                <div class="action-buttons">
                    <button class="detail-btn" onclick="openDetail(' . $row['id_transaksi'] . ')"><i class="ph ph-eye"></i> Detail</button>
                    <button class="detail-btn btn-edit" onclick="openEdit(' . $row['id_transaksi'] . ')"><i class="ph ph-pencil-simple"></i> Edit</button>
                    ' . ($status != 'lunas' ? '<button class="detail-btn btn-bayar" onclick="openBayar(' . $row['id_transaksi'] . ', ' . $row['total'] . ', ' . $row['total_bayar'] . ')"><i class="ph ph-money"></i> Bayar</button>' : '') . '
                    <button class="detail-btn" onclick="window.open(\'cetak-faktur.php?id=' . $row['id_transaksi'] . '\',\'_blank\')"><i class="ph ph-printer"></i> Cetak</button>
                </div>
            </div>
        </div>';
        }
        if ($tanggal_lama != '') echo '</div>';
        ?>
    </div>

    <!-- MODAL TAMBAH TRANSAKSI -->
    <div class="modal-overlay" id="modalTransaksi">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tambah Transaksi Penjualan</h2>
                <button class="close-btn" onclick="closeModal()"><i class="ph ph-x"></i></button>
            </div>
            <form action="../database/add-transaksi-penjualan.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nomor Faktur</label>
                        <input type="text" name="no_faktur" value="<?= $no_faktur ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group full">
                        <label>Pelanggan</label>
                        <select name="id_pelanggan" id="id_pelanggan" onchange="isiDataPelanggan()" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php
                            $qp = mysqli_query($koneksi, "SELECT * FROM pelanggan");
                            while ($p = mysqli_fetch_assoc($qp)) :
                            ?>
                                <option value="<?= $p['id_pelanggan'] ?>" data-telepon="<?= $p['no_telepon'] ?>" data-alamat="<?= $p['alamat'] ?>"><?= $p['nama_pelanggan'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>No Kontak</label><input type="text" id="no_kontak" readonly></div>
                    <div class="form-group"><label>Alamat</label><input type="text" id="alamat" readonly></div>
                </div>
                <div class="barang-section">
                    <div class="section-header">
                        <h3>Daftar Barang</h3>
                        <button type="button" class="btn-tambah-barang" onclick="tambahBarang()">+ Tambah Barang</button>
                    </div>
                    <div class="grand-total-box">
                        <span>Total Keseluruhan</span>
                        <h2 id="grandTotal">Rp 0</h2>
                    </div>
                    <div id="barangContainer">
                        <div class="barang-row">
                            <div class="autocomplete">
                                <input type="text" class="barang-input" placeholder="Cari barang..." autocomplete="off" required>
                                <input type="hidden" name="id_barang[]" class="id-barang">
                                <div class="suggestions"></div>
                            </div>
                            <input type="number" name="qty[]" class="qty" min="1" value="1" oninput="hitungSubtotal(this)">
                            <input type="text" name="satuan[]" class="satuan" placeholder="Satuan" readonly>
                            <input type="text" name="harga[]" class="harga" placeholder="Harga" readonly>
                            <input type="text" name="subtotal[]" class="subtotal" placeholder="Sub Total" readonly>
                            <button type="button" class="hapus-barang" onclick="hapusBarang(this)"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-primary">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detail Transaksi</h2>
                <button class="close-btn" onclick="closeDetail()"><i class="ph ph-x"></i></button>
            </div>
            <div style="padding: 25px;">
                <p><strong>Pelanggan:</strong> <span id="detailNama"></span></p>
                <p><strong>No Faktur:</strong> <span id="detailFaktur"></span></p>
                <p><strong>Tanggal:</strong> <span id="detailTanggal"></span></p>
                <div id="detailStatusBox" style="margin:10px 0;"></div>
                <table width="100%" cellpadding="8" style="margin-top:15px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th>Barang</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detailItems"></tbody>
                </table>
                <p style="margin-top:15px; text-align:right; font-size:1.1rem;">
                    <strong>Total: <span id="detailTotal"></span></strong>
                </p>
                <div id="detailPembayaranBox" style="margin-top:20px;"></div>
            </div>
        </div>
    </div>

    <!-- MODAL BAYAR -->
    <div class="modal-overlay" id="modalBayar">
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2><i class="ph ph-money" style="color:var(--primary);"></i> Pembayaran</h2>
                <button class="close-btn" onclick="closeBayar()"><i class="ph ph-x"></i></button>
            </div>
            <form action="../database/add-pembayaran.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_transaksi" id="bayar_id_transaksi">
                <div style="padding:25px;">
                    <div class="payment-summary">
                        <div class="pay-row">
                            <span>Total Transaksi</span>
                            <strong id="bayar_total_display">Rp 0</strong>
                        </div>
                        <div class="pay-row">
                            <span>Sudah Dibayar</span>
                            <strong id="bayar_sudah_display" style="color:#10b981;">Rp 0</strong>
                        </div>
                        <div class="pay-row highlight">
                            <span>Sisa Pembayaran</span>
                            <strong id="bayar_sisa_display" style="color:#ef4444;">Rp 0</strong>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <label>Jumlah Bayar <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="input_jumlah_bayar" name="jumlah_bayar" placeholder="Masukkan nominal..." required oninput="formatRupiahInput(this); hitungSisaSetelahBayar();">
                        <div class="quick-amount">
                            <button type="button" onclick="setQuickAmount(50)">50%</button>
                            <button type="button" onclick="setQuickAmount(100)">Lunas</button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label>Sisa Setelah Bayar</label>
                        <input type="text" id="input_sisa_setelah" readonly style="background:#f0f6fc;font-weight:700;color:var(--primary);">
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label>Bukti Pembayaran (Foto/File)</label>
                        <div class="upload-area" onclick="document.getElementById('bukti_bayar').click()">
                            <i class="ph ph-upload-simple"></i>
                            <span id="upload_label">Klik untuk upload bukti (JPG/PNG/PDF)</span>
                            <input type="file" name="bukti_bayar" id="bukti_bayar" accept="image/*,application/pdf" style="display:none;" onchange="previewBukti(this)">
                        </div>
                        <div id="bukti_preview" class="bukti-preview"></div>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label>Keterangan (opsional)</label>
                        <textarea name="keterangan" rows="2" placeholder="Misal: Transfer BCA, Cash, dll..." style="padding:12px 15px;border:1px solid #ddd;border-radius:12px;font-family:var(--font-main);width:100%;resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeBayar()">Batal</button>
                    <button type="submit" class="btn-primary"><i class="ph ph-check-circle"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT -->
    <div class="modal-overlay" id="modalEdit">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="ph ph-pencil-simple" style="color:var(--primary);"></i> Edit Transaksi</h2>
                <button class="close-btn" onclick="closeEdit()"><i class="ph ph-x"></i></button>
            </div>
            <form action="../database/update-transaksi.php" method="POST" id="formEdit">
                <input type="hidden" name="id_transaksi" id="edit_id_transaksi">
                <div style="padding:25px;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nomor Faktur</label>
                            <input type="text" name="no_faktur" id="edit_no_faktur" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" id="edit_tanggal" required>
                        </div>
                        <div class="form-group full">
                            <label>Pelanggan</label>
                            <select name="id_pelanggan" id="edit_id_pelanggan" required>
                                <?php
                                mysqli_data_seek($query, 0);
                                $qp2 = mysqli_query($koneksi, "SELECT * FROM pelanggan");
                                while ($p = mysqli_fetch_assoc($qp2)) :
                                ?>
                                    <option value="<?= $p['id_pelanggan'] ?>"><?= $p['nama_pelanggan'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <h3 style="margin-top:25px;color:var(--primary);">Daftar Barang</h3>
                    <div id="editBarangContainer" style="margin-top:15px;"></div>
                    <button type="button" class="btn-tambah-barang" onclick="tambahBarangEdit()" style="margin-top:10px;">+ Tambah Barang</button>

                    <div class="grand-total-box" style="margin-top:20px;">
                        <span>Total Keseluruhan</span>
                        <h2 id="editGrandTotal">Rp 0</h2>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEdit()">Batal</button>
                    <button type="submit" class="btn-primary"><i class="ph ph-check-circle"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

</body>
<script src="script.js"></script>

</html>