<?php
session_start();
include '../database/koneksi.php';

$tanggal = date('Ymd');

$q = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi_penjualan
     WHERE DATE(tanggal)=CURDATE()"
);

$data = mysqli_fetch_assoc($q);
$urutan = $data['total'] + 1;

$no_faktur =
    "PJ-" .
    $tanggal .
    "-" .
    str_pad($urutan, 3, "0", STR_PAD_LEFT);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan|Bina Usaha Sauyunan</title>
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
    if(isset($_SESSION['alert'])){
    ?>
    <script>
        Swal.fire({
            icon: '<?= $_SESSION['alert']['icon'] ?>',
            title: '<?= $_SESSION['alert']['title'] ?>',
            text: '<?= $_SESSION['alert']['text'] ?>',
            confirmButtonColor: '#2563a8'
        });
    </script>
    <?php unset($_SESSION['alert']);}?>
    <?php $activePage = 'transaksi-penjualan'; include '../components/navbar.php'; ?>

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
        <div class="stat-card">
            <i class="ph-fill ph-currency-circle-dollar"></i>
            <span>Hari Ini</span>
            <h2>Rp 1.200.000</h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-chart-line-up"></i>
            <span>Minggu Ini</span>
            <h2>Rp 8.400.000</h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-calendar"></i>
            <span>Bulan Ini</span>
            <h2>Rp 35.000.000</h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-receipt"></i>
            <span>Total Faktur</span>
            <h2>125</h2>
        </div>
    </div>

    <div class="filter-box">
        <input type="date">
        <input
            type="text"
            placeholder="Cari nama konsumen..."
        >
    </div>

    <div class="tanggal-section">
        <div class="tanggal-title">
            05 Juni 2026
        </div>
        <div class="transaksi-card">
            <div class="left">
                <h3>PT Maju Jaya</h3>
                <div class="info">
                    <span>3 Barang</span>
                    <span>14:20</span>
                </div>
            </div>
            <div class="right"
                <h3>Rp 450.000</h3>
                <button class="detail-btn">
                    Detail
                </button>
            </div>
        </div>

        <div class="transaksi-card">
            <div class="left">
                <h3>Budi Santoso</h3>
                <div class="info">
                    <span>2 Barang</span>
                    <span>16:40</span>
                </div>
            </div>
            <div class="right">
                <h3>Rp 125.000</h3>
                <button class="detail-btn">
                    Detail
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalTransaksi">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Tambah Transaksi Penjualan</h2>
            <button class="close-btn" onclick="closeModal()">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <form action="../database/add-transaksi-penjualan.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nomor Faktur</label>
                    <input
                        type="text"
                        name="kode_faktur"
                        value="<?= $no_faktur ?>"
                        readonly
                    >
                </div>
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" required>
                </div>
                <div class="form-group full">
                    <label>Nama Konsumen</label>
                    <input
                        type="text"
                        name="nama_konsumen"
                        placeholder="Masukkan nama konsumen"
                        required
                    >
                </div>
                <div class="form-group">
                    <label>No Kontak</label>
                    <input type="number" name="no_kontak" required>
                </div>
                <div class="form-group full">
                    <label>Alamat</label>
                    <textarea name="alamat" rows="3"></textarea>
                </div>
            </div>

            <div class="barang-section">
                <div class="section-header">
                    <h3>Daftar Barang</h3>
                    <button
                        type="button"
                        class="btn-tambah-barang"
                        onclick="tambahBarang()"
                    >
                        + Tambah Barang
                    </button>
                </div>
                <div class="grand-total-box">
                    <span>Total Keseluruhan</span>
                    <h2 id="grandTotal">
                        Rp 0
                    </h2>
                </div>

                <div id="barangContainer">
                    <div class="barang-row">
                        <div class="autocomplete">
                            <input
                                type="text"
                                class="barang-input"
                                placeholder="Cari barang..."
                                autocomplete="off"
                                required
                            >
                            <input
                                type="hidden"
                                name="id_barang[]"
                                class="id-barang"
                            >
                            <div class="suggestions"></div>
                        </div>

                        <input
                            type="number"
                            name="qty[]"
                            class="qty"
                            min="1"
                            value="1"
                            oninput="hitungSubtotal(this)"
                        >
                        <input
                            type="text"
                            name="satuan[]"
                            class="satuan"
                            placeholder="Satuan"
                            readonly
                        >
                        <input
                            type="text"
                            name="harga[]"
                            class="harga"
                            placeholder="Harga"
                            readonly
                        >
                        <input
                            type="text"
                            name="subtotal[]"
                            class="subtotal"
                            placeholder="Sub Total"
                            readonly
                        >
                        <button
                            type="button"
                            class="hapus-barang"
                            onclick="hapusBarang(this)"
                        >
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="btn-primary"
                >
                    Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>
</body>
<script src="script.js"></script>
</html>