<?php
session_start();
include '../database/koneksi.php';

$q = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi_penjualan
     WHERE DATE(tanggal)=CURDATE()"
);

$data = mysqli_fetch_assoc($q);
$urutan = $data['total'] + 1;

$tanggal = date('dmY');
$no_faktur = str_pad($urutan, 4, "0", STR_PAD_LEFT) ."-". $tanggal . "-FC";

$query = mysqli_query($koneksi, "
    SELECT
        tp.*,
        p.nama_pelanggan
    FROM transaksi_penjualan tp
    JOIN pelanggan p
        ON tp.id_pelanggan = p.id_pelanggan
    ORDER BY tp.tanggal DESC
");


// per hari
$q_hari = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(total),0) AS total
     FROM transaksi_penjualan
     WHERE DATE(tanggal) = CURDATE()"
);

$hari_ini = mysqli_fetch_assoc($q_hari)['total'];


// per minggu
$q_minggu = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(total),0) AS total
     FROM transaksi_penjualan
     WHERE YEARWEEK(tanggal, 1) = YEARWEEK(CURDATE(), 1)"
);

$minggu_ini = mysqli_fetch_assoc($q_minggu)['total'];


// per bulan
$q_bulan = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(total),0) AS total
     FROM transaksi_penjualan
     WHERE MONTH(tanggal) = MONTH(CURDATE())
     AND YEAR(tanggal) = YEAR(CURDATE())"
);

$bulan_ini = mysqli_fetch_assoc($q_bulan)['total'];


// total faktur 
$q_faktur = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi_penjualan
     WHERE MONTH(tanggal) = MONTH(CURDATE())
     AND YEAR(tanggal) = YEAR(CURDATE())"
);

$total_faktur = mysqli_fetch_assoc($q_faktur)['total'];
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
            <h2>Rp <?= number_format($hari_ini,0,',','.') ?></h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-chart-line-up"></i>
            <span>Minggu Ini</span>
            <h2>Rp <?= number_format($minggu_ini,0,',','.') ?></h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-calendar"></i>
            <span>Bulan Ini</span>
            <h2>Rp <?= number_format($bulan_ini,0,',','.') ?></h2>
        </div>
        <div class="stat-card">
            <i class="ph-fill ph-receipt"></i>
            <span>Total Faktur</span>
            <h2><?= $total_faktur ?: 0 ?></h2>
        </div>
    </div>

    <div class="filter-box">
        <input type="date">
        <input
            type="text"
            placeholder="Cari nama konsumen..."
        >
    </div>
        <?php
            $tanggal_lama = '';

        while ($row = mysqli_fetch_assoc($query)) {        
            $tanggal_baru = date('Y-m-d', strtotime($row['tanggal']));        
            if ($tanggal_baru != $tanggal_lama) {
                if ($tanggal_lama != '') {
                    echo '</div>';
                }            
                echo '
                <div class="tanggal-section">
                    <div class="tanggal-title">'
                    . date('d F Y', strtotime($row['tanggal'])) .
                    '</div>';
                $tanggal_lama = $tanggal_baru;
            }        
            echo '
            <div class="transaksi-card">
                <div class="left">
                    <h3>'.$row['nama_pelanggan'].'</h3>
                    <div class="info">
                        <span>'.$row['no_faktur'].'</span>
                        <span>'.date('H:i', strtotime($row['tanggal'])).'</span>
                    </div>
                </div>        
                <div class="right">
                    <h3>Rp '.number_format($row['total'],0,',','.').'</h3>
                    <button class="detail-btn" onclick="openDetail('.$row['id_transaksi'].')">Detail</button>
                </div>
            </div>';
        }
        if ($tanggal_lama != '') {
            echo '</div>';
        }
        ?>
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
                        name="no_faktur"
                        value="<?= $no_faktur ?>"
                        readonly
                    >
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
                        $query = mysqli_query($koneksi, "SELECT * FROM pelanggan ");

                        while ($pelanggan = mysqli_fetch_assoc($query)) :
                        ?>
                            <option 
                                value="<?= $pelanggan['id_pelanggan'] ?>"
                                data-telepon="<?= $pelanggan['no_telepon'] ?>"
                                data-alamat="<?= $pelanggan['alamat'] ?>"
                            >
                                <?= $pelanggan['nama_pelanggan'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>No Kontak</label>
                    <input type="text" id="no_kontak" readonly>
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <input type="text" id="alamat" readonly></input>
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

<div class="modal-overlay" id="modalDetail">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detail Transaksi</h2>
            <button class="close-btn" onclick="closeDetail()">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div style="padding: 25px;">
            <p><strong>Pelanggan:</strong> <span id="detailNama"></span></p>
            <p><strong>No Faktur:</strong> <span id="detailFaktur"></span></p>
            <p><strong>Tanggal:</strong> <span id="detailTanggal"></span></p>
            <table width="100%" cellpadding="8" style="margin-top:15px; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th>Barang</th><th>Qty</th><th>Harga</th><th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="detailItems"></tbody>
            </table>
            <p style="margin-top:15px; text-align:right; font-size:1.1rem;">
                <strong>Total: <span id="detailTotal"></span></strong>
            </p>
        </div>
    </div>
</div>
</body>
<script src="script.js"></script>
</html>