<?php
$activePage = 'daftar-aset-koperasi';
require_once '../database/koneksi.php';
require_once '../database/auth.php';

$userRole = $_SESSION['role'] ?? null;

// Batasi akses halaman hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

// ===================== PROSES TAMBAH ASET =====================
if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    if ($userRole !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Hanya admin yang dapat menambah aset']);
        exit;
    }
    $nama_aset    = trim($_POST['nama_aset']);
    $jumlah       = (int) $_POST['jumlah'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $kondisi      = $_POST['kondisi'];

    $stmt = $koneksi->prepare("INSERT INTO aset_koperasi (nama_aset, jumlah, tanggal_beli, kondisi) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('siss', $nama_aset, $jumlah, $tanggal_beli, $kondisi);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Aset berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan aset']);
    }
    $stmt->close();
    exit;
}

// ===================== PROSES EDIT ASET =====================
if (isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    if ($userRole !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Hanya admin yang dapat mengubah aset']);
        exit;
    }
    $id           = (int) $_POST['id'];
    $nama_aset    = trim($_POST['nama_aset']);
    $jumlah       = (int) $_POST['jumlah'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $kondisi      = $_POST['kondisi'];

    $stmt = $koneksi->prepare("UPDATE aset_koperasi SET nama_aset = ?, jumlah = ?, tanggal_beli = ?, kondisi = ? WHERE id = ?");
    $stmt->bind_param('sissi', $nama_aset, $jumlah, $tanggal_beli, $kondisi, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Aset berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui aset']);
    }
    $stmt->close();
    exit;
}

// ===================== PROSES UPDATE PENGECEKAN =====================
if (isset($_POST['aksi']) && $_POST['aksi'] === 'update_pengecekan') {
    $id                 = (int) $_POST['id'];
    $tanggal_pengecekan = $_POST['tanggal_pengecekan'];
    $kondisi            = $_POST['kondisi_cek'];

    $stmt = $koneksi->prepare("UPDATE aset_koperasi SET tanggal_pengecekan = ?, kondisi = ? WHERE id = ?");
    $stmt->bind_param('ssi', $tanggal_pengecekan, $kondisi, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Pengecekan aset berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui pengecekan aset']);
    }
    $stmt->close();
    exit;
}

// ===================== PROSES HAPUS ASET =====================
if (isset($_POST['aksi']) && $_POST['aksi'] === 'hapus') {
    if ($userRole !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Hanya admin yang dapat menghapus aset']);
        exit;
    }
    $id = (int) $_POST['id'];

    $stmt = $koneksi->prepare("DELETE FROM aset_koperasi WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Aset berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus aset']);
    }
    $stmt->close();
    exit;
}

// ===================== AMBIL DATA UNTUK EDIT (AJAX) =====================
if (isset($_GET['aksi']) && $_GET['aksi'] === 'detail') {
    $id = (int) $_GET['id'];
    $stmt = $koneksi->prepare("SELECT * FROM aset_koperasi WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());
    $stmt->close();
    exit;
}

// ===================== AMBIL SEMUA DATA ASET =====================
$dataAset = $koneksi->query("SELECT * FROM aset_koperasi ORDER BY created_at DESC");

include '../components/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Aset Koperasi</title>

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
</head>

<body>

    <div class="container-aset">

        <div class="card-header-aset">
            <h2><i class="ph ph-package"></i> Daftar Aset Koperasi</h2>
            <div style="display: flex; gap: 10px;">
                <button class="btn-tambah no-print" onclick="window.print()" style="background: #475569;">
                    <i class="ph ph-printer"></i> Cetak
                </button>
                <?php if ($userRole === 'admin'): ?>
                <button class="btn-tambah no-print" onclick="bukaModalTambah()">
                    <i class="ph ph-plus"></i> Tambah Aset
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-table">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Aset</th>
                        <th>Jumlah</th>
                        <th>Tanggal Beli</th>
                        <th>Kondisi</th>
                        <th>Tanggal Pengecekan</th>
                        <?php if ($userRole === 'admin'): ?>
                        <th class="no-print">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dataAset->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $dataAset->fetch_assoc()): ?>
                            <?php
                            $badgeClass = match ($row['kondisi']) {
                                'Baik' => 'badge-baik',
                                'Rusak Ringan' => 'badge-ringan',
                                'Rusak Berat' => 'badge-berat',
                                default => 'badge-baik'
                            };
                            ?>
                            <?php
                            $tglCek = $row['tanggal_pengecekan'];
                            $tglCekLabel = $tglCek ? date('d M Y', strtotime($tglCek)) : '<span class="badge-cek-belum">Belum dicek</span>';
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($row['nama_aset']); ?></td>
                                <td><?= htmlspecialchars($row['jumlah']); ?></td>
                                <td><?= date('d M Y', strtotime($row['tanggal_beli'])); ?></td>
                                <td><span class="badge <?= $badgeClass; ?>"><?= $row['kondisi']; ?></span></td>
                                <td><?= $tglCekLabel; ?></td>
                                <?php if ($userRole === 'admin'): ?>
                                <td class="no-print">
                                    <button class="aksi-btn btn-edit" onclick="bukaModalEdit(<?= $row['id']; ?>)" title="Edit">
                                        <i class="ph ph-pencil-simple"></i>
                                    </button>
                                    <button class="aksi-btn btn-cek" onclick="bukaModalPengecekan(<?= $row['id']; ?>, '<?= htmlspecialchars($row['nama_aset']); ?>', '<?= $tglCek; ?>', '<?= htmlspecialchars($row['kondisi']); ?>')" title="Update Pengecekan">
                                        <i class="ph ph-clipboard-text"></i>
                                    </button>
                                    <button class="aksi-btn btn-hapus" onclick="hapusAset(<?= $row['id']; ?>)" title="Hapus">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $userRole === 'admin' ? 7 : 6; ?>" class="empty-state">
                                <i class="ph ph-package" style="font-size: 32px;"></i>
                                <p>Belum ada data aset koperasi</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <div class="modal-overlay" id="modalAset">
        <div class="modal-box">
            <h3 id="modalTitle"><i class="ph ph-plus-circle"></i> Tambah Aset</h3>
            <form id="formAset">
                <input type="hidden" id="aset_id" name="id">
                <input type="hidden" id="aksi_form" name="aksi" value="tambah">

                <div class="form-group">
                    <label>Nama Aset</label>
                    <input type="text" id="nama_aset" name="nama_aset" placeholder="Contoh: Laptop, Meja, Printer" required>
                </div>

                <div class="form-group">
                    <label>Jumlah</label>
                    <input type="number" id="jumlah" name="jumlah" min="1" value="1" required>
                </div>

                <div class="form-group">
                    <label>Tanggal Beli</label>
                    <input type="date" id="tanggal_beli" name="tanggal_beli" required>
                </div>

                <div class="form-group">
                    <label>Kondisi</label>
                    <select id="kondisi" name="kondisi" required>
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Berat">Rusak Berat</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-batal" onclick="tutupModal()">Batal</button>
                    <button type="submit" class="btn-simpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Update Pengecekan -->
    <div class="modal-overlay" id="modalPengecekan">
        <div class="modal-box">
            <h3 id="modalPengecekanTitle"><i class="ph ph-clipboard-text"></i> Update Pengecekan</h3>
            <form id="formPengecekan">
                <input type="hidden" id="pengecekan_id" name="id">
                <input type="hidden" name="aksi" value="update_pengecekan">

                <div class="form-group">
                    <label>Nama Aset</label>
                    <input type="text" id="pengecekan_nama" readonly style="background:#f1f5f9; cursor:not-allowed;">
                </div>

                <div class="form-group">
                    <label>Tanggal Pengecekan</label>
                    <input type="date" id="tanggal_pengecekan" name="tanggal_pengecekan" required>
                </div>

                <div class="form-group">
                    <label>Kondisi Aset</label>
                    <select id="kondisi_cek" name="kondisi_cek" required>
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Berat">Rusak Berat</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-batal" onclick="tutupModalPengecekan()">Batal</button>
                    <button type="submit" class="btn-simpan btn-cek-submit"><i class="ph ph-check"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>