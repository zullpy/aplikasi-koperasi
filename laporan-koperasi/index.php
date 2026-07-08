<?php
$activePage = 'laporan-koperasi';
include '../database/koneksi.php';
include '../database/laporan-koperasi-func.php';
require_once '../database/auth.php';

// ── Proses aksi dari modal: Edit Harga / Tambah Barang / Hapus Barang / Tambah Saldo ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {

    if ($_POST['aksi'] === 'edit_harga_item') {
        $ok = updateHargaItemKoperasi($koneksi, $_POST['item_id'], str_replace(['.', ','], '', $_POST['harga_baru']));
        header('Location: index.php?status=' . ($ok ? 'harga_updated' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'edit_jumlah_pengajuan') {
        $ok = updateJumlahPengajuanKoperasi($koneksi, $_POST['pengajuan_id'], str_replace(['.', ','], '', $_POST['jumlah_baru']));
        header('Location: index.php?status=' . ($ok ? 'harga_updated' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'tambah_barang') {
        $res = tambahBarangItemKoperasi(
            $koneksi,
            $_POST['pengajuan_id_barang'],
            $_POST['nama_barang'],
            $_POST['qty'],
            $_POST['satuan'],
            str_replace(['.', ','], '', $_POST['harga']),
            $_FILES['nota'] ?? null
        );
        header('Location: index.php?status=' . ($res['success'] ? 'barang_added' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'hapus_barang') {
        $ok = hapusBarangItemKoperasi($koneksi, $_POST['item_id']);
        header('Location: index.php?status=' . ($ok ? 'barang_deleted' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'tambah_nota_item') {
        $res = tambahNotaItemKoperasi(
            $koneksi,
            $_POST['item_id_nota'],
            $_FILES['nota_tambahan'] ?? null
        );
        header('Location: index.php?status=' . ($res['success'] ? 'nota_added' : 'gagal'));
        exit;
    }

    // ── Upload Kwitansi/Nota — level pengajuan (jenis selain 'stok') ──
    if ($_POST['aksi'] === 'tambah_kwitansi') {
        $res = tambahKwitansiKoperasi(
            $koneksi,
            $_POST['pengajuan_id_kwitansi'],
            $_FILES['kwitansi'] ?? null
        );
        header('Location: index.php?status=' . ($res['success'] ? 'kwitansi_added' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'tambah_saldo') {
        $res = tambahSaldoKoperasi(
            $koneksi,
            $_POST['pengajuan_id_saldo'],
            str_replace(['.', ','], '', $_POST['tambah_saldo']),
            $_FILES['bukti_transfer'] ?? null,
            date('Y-m-d'),
            $_POST['keterangan_saldo'] ?? null
        );
        header('Location: index.php?status=' . ($res['success'] ? 'saldo_added' : 'gagal'));
        exit;
    }

    if ($_POST['aksi'] === 'kembalikan_saldo') {
        $res = kembalikanSaldoKoperasi(
            $koneksi,
            $_POST['pengajuan_id_kembali'],
            str_replace(['.', ','], '', $_POST['jumlah_kembali']),
            $_FILES['bukti_kembali'] ?? null,
            date('Y-m-d'),
            $_POST['keterangan_kembali'] ?? null
        );
        header('Location: index.php?status=' . ($res['success'] ? 'saldo_returned' : 'gagal'));
        exit;
    }

    // ── Simpan / Ganti Tanda Tangan ──
    // Role diambil dari SESSION (bukan dari form), supaya user tidak bisa
    // "menandatangani sebagai" role lain lewat manipulasi request.
    if ($_POST['aksi'] === 'simpan_ttd') {
        $roleAktif  = $_SESSION['role'] ?? '';
        $rolesValid = array_keys(daftarRoleTtdKoperasi());

        if (!in_array($roleAktif, $rolesValid, true)) {
            header('Location: index.php?status=gagal');
            exit;
        }

        $signedBy = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Tidak diketahui';
        $res = simpanTtdKoperasi(
            $koneksi,
            $_POST['pengajuan_id'],
            $roleAktif,
            $signedBy,
            $_POST['signature_data'] ?? ''
        );
        header('Location: index.php?status=' . ($res['success'] ? 'ttd_saved' : 'gagal'));
        exit;
    }

    // ── Hapus Tanda Tangan (role sendiri saja) ──
    if ($_POST['aksi'] === 'hapus_ttd') {
        $roleAktif  = $_SESSION['role'] ?? '';
        $rolesValid = array_keys(daftarRoleTtdKoperasi());

        if (!in_array($roleAktif, $rolesValid, true)) {
            header('Location: index.php?status=gagal');
            exit;
        }

        $ok = hapusTtdKoperasi($koneksi, $_POST['pengajuan_id'], $roleAktif);
        header('Location: index.php?status=' . ($ok ? 'ttd_deleted' : 'gagal'));
        exit;
    }
}

include '../components/navbar.php';

$rows  = getDataLaporanKoperasi($koneksi);
$total = getTotalRingkasanKoperasi($rows);

// ── Data Tanda Tangan (TTD) ──
$ttdSemua      = getTtdSemuaPengajuanKoperasi($koneksi); // [pengajuan_id => [role => row]]
$daftarRoleTtd = daftarRoleTtdKoperasi();                // [role_key => Label]
$roleLoginSaatIni = $_SESSION['role'] ?? '';
$bisaTtd          = array_key_exists($roleLoginSaatIni, $daftarRoleTtd);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Koperasi | KBUS</title>
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style-koperasi.css">
</head>

<body>

    <div class="page">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header__title">
                <div class="page-header__icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18" />
                        <path d="M5 21V7l7-4 7 4v14" />
                        <path d="M9 21v-6h6v6" />
                    </svg>
                </div>
                <div>
                    <h1>Laporan Koperasi</h1>
                    <p>Rekap saldo masuk, belanja, dan sisa saldo pengajuan koperasi (approved)</p>
                </div>
            </div>
        </div>

        <!-- Alert -->
        <?php if (isset($_GET['status']) && in_array($_GET['status'], ['harga_updated', 'barang_added', 'barang_deleted', 'saldo_added', 'saldo_returned', 'nota_added', 'kwitansi_added', 'ttd_saved', 'ttd_deleted', 'gagal'])):
            $pesan = [
                'harga_updated'  => 'Harga / nominal berhasil diperbarui.',
                'barang_added'   => 'Barang baru berhasil ditambahkan.',
                'barang_deleted' => 'Barang berhasil dihapus.',
                'saldo_added'    => 'Saldo berhasil ditambahkan.',
                'saldo_returned' => 'Sisa saldo berhasil dikembalikan.',
                'nota_added'     => 'Nota berhasil ditambahkan.',
                'kwitansi_added' => 'Kwitansi/nota berhasil ditambahkan.',
                'ttd_saved'      => 'Tanda tangan berhasil disimpan.',
                'ttd_deleted'    => 'Tanda tangan berhasil dihapus.',
                'gagal'          => 'Terjadi kesalahan, silakan coba lagi.',
            ][$_GET['status']];
            $isError = $_GET['status'] === 'gagal';
        ?>
            <div class="alert <?= $isError ? 'alert--error' : 'alert--success' ?>" id="alertSuccess">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                <?= $pesan ?>
                <button class="alert__close" onclick="this.closest('.alert').remove()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E3A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                        <path d="M2 17l10 5 10-5" />
                        <path d="M2 12l10 5 10-5" />
                    </svg>
                </div>
                <div class="summary-card__label">Total Saldo Masuk</div>
                <div class="summary-card__value"><?= rupiah($total['saldo_masuk']) ?></div>
                <div class="summary-card__sub">Akumulasi seluruh pengajuan approved</div>
            </div>

            <div class="summary-card <?= $total['sisa_saldo'] < 0 ? 'summary-card--minus' : '' ?>">
                <div class="summary-card__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $total['sisa_saldo'] < 0 ? '#DC2626' : '#059669' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                    </svg>
                </div>
                <div class="summary-card__label">Total Sisa Saldo</div>
                <div class="summary-card__value <?= $total['sisa_saldo'] < 0 ? 'summary-card__value--minus' : 'summary-card__value--plus' ?>">
                    <?= rupiah($total['sisa_saldo']) ?>
                </div>
                <div class="summary-card__sub"><?= $total['sisa_saldo'] < 0 ? 'Saldo defisit' : 'Saldo tersisa' ?></div>
            </div>

            <div class="summary-card">
                <div class="summary-card__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E3A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                    </svg>
                </div>
                <div class="summary-card__label">Jumlah Pengajuan</div>
                <div class="summary-card__value"><?= count($rows) ?></div>
                <div class="summary-card__sub">Data dengan status approved</div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-card__header">
                <div class="table-card__title">Rincian Laporan Koperasi</div>
                <div class="table-card__badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <?= count($rows) ?> Pengajuan
                </div>
            </div>

            <div class="table-wrap">
                <table class="laporan">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Tujuan</th>
                            <th class="center">Jenis</th>
                            <th class="right">Saldo Masuk</th>
                            <th class="right">Total Belanja</th>
                            <th class="center">Sisa Saldo</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state__icon">
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="9" x2="21" y2="9" />
                                                <line x1="9" y1="21" x2="9" y2="9" />
                                            </svg>
                                        </div>
                                        <div class="empty-state__title">Belum ada data</div>
                                        <div class="empty-state__desc">Tidak ada pengajuan koperasi dengan status approved.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $no => $row):
                                $sisa       = (float) $row['sisa_saldo'];
                                $punyaItem  = $row['jenis'] !== 'operasional';
                                // Cetak PDF + Tanda Tangan cuma untuk jenis 'stok'.
                                // Jenis lain (peralatan/operasional) pakai upload kwitansi/nota manual.
                                $jenisStok  = $row['jenis'] === 'stok';
                            ?>
                                <tr class="data-row">
                                    <td><span class="no-badge"><?= $no + 1 ?></span></td>
                                    <td style="white-space:nowrap; color:var(--muted)">
                                        <?= date('d M Y', strtotime($row['tanggal'])) ?>
                                    </td>
                                    <td style="font-weight:500"><?= htmlspecialchars($row['tujuan']) ?></td>
                                    <td class="center">
                                        <span class="jenis-badge jenis-badge--<?= htmlspecialchars($row['jenis']) ?>">
                                            <?= htmlspecialchars(labelJenisKoperasi($row['jenis'])) ?>
                                        </span>
                                    </td>
                                    <td class="right mono"><?= rupiah($row['saldo_masuk']) ?></td>
                                    <td class="right mono"><?= rupiah($row['total_belanja']) ?></td>
                                    <td class="center">
                                        <span class="saldo-pill <?= $sisa < 0 ? 'saldo-pill--minus' : 'saldo-pill--plus' ?>">
                                            <?php if ($sisa < 0): ?>
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <line x1="12" y1="5" x2="12" y2="19" />
                                                    <polyline points="19 12 12 19 5 12" />
                                                </svg>
                                            <?php else: ?>
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <line x1="12" y1="19" x2="12" y2="5" />
                                                    <polyline points="5 12 12 5 19 12" />
                                                </svg>
                                            <?php endif; ?>
                                            <?= rupiah(abs($sisa)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn--outline"
                                                onclick="toggleDetail('detail<?= $row['id'] ?>', this)">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <line x1="8" y1="6" x2="21" y2="6" />
                                                    <line x1="8" y1="12" x2="21" y2="12" />
                                                    <line x1="8" y1="18" x2="21" y2="18" />
                                                    <line x1="3" y1="6" x2="3.01" y2="6" />
                                                    <line x1="3" y1="12" x2="3.01" y2="12" />
                                                    <line x1="3" y1="18" x2="3.01" y2="18" />
                                                </svg>
                                                Detail
                                            </button>
                                            <button class="btn btn--success"
                                                onclick="bukaTambahSaldo(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['tujuan'])) ?>')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="8" x2="12" y2="16" />
                                                    <line x1="8" y1="12" x2="16" y2="12" />
                                                </svg>
                                                Saldo
                                            </button>
                                            <?php if ($sisa > 0): ?>
                                                <button class="btn btn--kembali"
                                                    onclick="bukaKembalikanSaldo(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['tujuan'])) ?>', <?= $sisa ?>)">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="9 14 4 19 9 24" />
                                                        <path d="M20 4v7a4 4 0 0 1-4 4H4" />
                                                    </svg>
                                                    Kembalikan Saldo
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($jenisStok): ?>
                                                <?php if ($bisaTtd):
                                                    $ttdRoleIni = $ttdSemua[$row['id']][$roleLoginSaatIni] ?? null;
                                                ?>
                                                    <button type="button" class="btn <?= $ttdRoleIni ? 'btn--outline' : 'btn--ttd' ?>"
                                                        onclick='bukaTtdKoperasi(<?= $row['id'] ?>, "<?= htmlspecialchars(addslashes($row['tujuan'])) ?>", "<?= htmlspecialchars($daftarRoleTtd[$roleLoginSaatIni]) ?>", <?= $ttdRoleIni ? json_encode([
                                                                                                                                                                                                                                'signature_path' => $ttdRoleIni['signature_path'],
                                                                                                                                                                                                                                'signed_by'      => $ttdRoleIni['signed_by'],
                                                                                                                                                                                                                                'signed_at'      => $ttdRoleIni['signed_at'],
                                                                                                                                                                                                                            ]) : 'null' ?>)'>
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M12 19l7-7 3 3-7 7-3-3z" />
                                                            <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" />
                                                            <path d="M2 2l7.586 7.586" />
                                                            <circle cx="11" cy="11" r="2" />
                                                        </svg>
                                                        <?= $ttdRoleIni ? 'Lihat TTD' : 'Tanda Tangan' ?>
                                                    </button>
                                                <?php endif; ?>
                                                <a class="btn btn--outline"
                                                    href="cetak-laporan-koperasi.php?id=<?= $row['id'] ?>"
                                                    target="_blank" title="Ekspor / Cetak PDF Laporan Belanja">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <polyline points="14 2 14 8 20 8" />
                                                        <line x1="9" y1="15" x2="15" y2="15" />
                                                        <line x1="9" y1="11" x2="12" y2="11" />
                                                    </svg>
                                                    Cetak
                                                </a>
                                            <?php else: ?>
                                                <?php if (!empty($row['kwitansi'])): ?>
                                                    <button type="button" class="btn btn--outline btn-nota"
                                                        onclick='bukaGaleriNota(<?= json_encode(array_values($row['kwitansi'])) ?>, "Kwitansi/Nota — <?= htmlspecialchars(addslashes($row['tujuan'])) ?>")'>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                        Lihat Kwitansi<?= count($row['kwitansi']) > 1 ? ' (' . count($row['kwitansi']) . ')' : '' ?>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn--outline"
                                                    onclick="bukaUploadKwitansi(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['tujuan'])) ?>')"
                                                    title="Upload Kwitansi / Nota Belanja">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                        <polyline points="17 8 12 3 7 8" />
                                                        <line x1="12" y1="3" x2="12" y2="15" />
                                                    </svg>
                                                    <?= empty($row['kwitansi']) ? 'Upload Kwitansi/Nota' : '+ Kwitansi/Nota' ?>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!$punyaItem): ?>
                                                <button class="btn btn--outline"
                                                    onclick="bukaEditHarga('pengajuan', <?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['tujuan'])) ?>', <?= (float) $row['jumlah'] ?>)">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z" />
                                                    </svg>
                                                    Edit Nominal
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Detail Row -->
                                <tr class="detail-row" id="detail<?= $row['id'] ?>">
                                    <td colspan="8">
                                        <div class="detail-inner">

                                            <?php if ($punyaItem): ?>
                                                <div class="detail-inner__title" style="justify-content:space-between">
                                                    <span style="display:flex;align-items:center;gap:6px">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--navy)" stroke-width="2.5">
                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                            <polyline points="14 2 14 8 20 8" />
                                                        </svg>
                                                        Rincian Barang — <?= htmlspecialchars($row['tujuan']) ?>
                                                    </span>
                                                    <button type="button" class="btn btn--success" onclick="bukaTambahBarang(<?= $row['id'] ?>)">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <line x1="12" y1="8" x2="12" y2="16" />
                                                            <line x1="8" y1="12" x2="16" y2="12" />
                                                        </svg>
                                                        Tambah Barang
                                                    </button>
                                                </div>
                                                <div style="overflow-x:auto; margin-bottom:18px">
                                                    <table class="detail-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Nama Barang</th>
                                                                <th>Qty</th>
                                                                <th>Satuan</th>
                                                                <th>Estimasi Harga</th>
                                                                <th>Subtotal</th>
                                                                <th>Nota</th>
                                                                <th>Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $items = getDetailItemKoperasi($koneksi, $row['id']);
                                                            if (empty($items)): ?>
                                                                <tr>
                                                                    <td colspan="7" style="text-align:center;color:var(--muted);padding:16px">Belum ada barang.</td>
                                                                </tr>
                                                                <?php else: foreach ($items as $item):
                                                                    // Dukung dua bentuk data: array multi-nota ($item['notas']) atau kolom lama tunggal ($item['nota_path'])
                                                                    $notaList = [];
                                                                    if (!empty($item['notas']) && is_array($item['notas'])) {
                                                                        $notaList = $item['notas'];
                                                                    } elseif (!empty($item['nota_path'])) {
                                                                        $notaList = [$item['nota_path']];
                                                                    }
                                                                ?>
                                                                    <tr>
                                                                        <td style="font-weight:500"><?= htmlspecialchars($item['keterangan']) ?></td>
                                                                        <td><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',') ?></td>
                                                                        <td style="color:var(--muted)"><?= htmlspecialchars($item['satuan']) ?></td>
                                                                        <td style="font-variant-numeric:tabular-nums"><?= rupiah($item['harga_satuan']) ?></td>
                                                                        <td style="font-variant-numeric:tabular-nums;font-weight:600"><?= rupiah($item['subtotal']) ?></td>
                                                                        <td class="center">
                                                                            <?php if (!empty($notaList)): ?>
                                                                                <button type="button" class="btn btn--outline btn-nota"
                                                                                    onclick='bukaGaleriNota(<?= json_encode(array_values($notaList)) ?>, "Nota Belanja — <?= htmlspecialchars(addslashes($item['keterangan'])) ?>")'>
                                                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                                                        <circle cx="12" cy="12" r="3" />
                                                                                    </svg>
                                                                                    Lihat<?= count($notaList) > 1 ? ' (' . count($notaList) . ')' : '' ?>
                                                                                </button>
                                                                            <?php else: ?>
                                                                                <span style="color:var(--muted)">—</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <div class="actions">
                                                                                <button type="button" class="btn btn--outline"
                                                                                    onclick="bukaUploadNota(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['keterangan'])) ?>')">
                                                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                                                        <polyline points="17 8 12 3 7 8" />
                                                                                        <line x1="12" y1="3" x2="12" y2="15" />
                                                                                    </svg>
                                                                                    + Nota
                                                                                </button>
                                                                                <button type="button" class="btn btn--outline"
                                                                                    onclick="bukaEditHarga('item', <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['keterangan'])) ?>', <?= (float) $item['harga_satuan'] ?>)">
                                                                                    Edit
                                                                                </button>
                                                                                <form method="POST" style="display:inline"
                                                                                    onsubmit="return confirm('Hapus barang \'<?= htmlspecialchars(addslashes($item['keterangan'])) ?>\'? Tindakan ini tidak bisa dibatalkan.');">
                                                                                    <input type="hidden" name="aksi" value="hapus_barang">
                                                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                                                    <button type="submit" class="btn btn--outline" style="color:var(--minus);border-color:#FCA5A5">
                                                                                        Hapus
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                            <?php endforeach;
                                                            endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="detail-inner__title">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--navy)" stroke-width="2.5">
                                                        <circle cx="12" cy="12" r="10" />
                                                        <line x1="12" y1="16" x2="12" y2="12" />
                                                        <line x1="12" y1="8" x2="12.01" y2="8" />
                                                    </svg>
                                                    Pengajuan operasional tidak memiliki rincian barang. Gunakan tombol "Edit Nominal" untuk mengubah jumlahnya.
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($jenisStok): ?>
                                                <!-- ─── Status Tanda Tangan Persetujuan (4 role) — hanya jenis Stok ─── -->
                                                <div class="detail-inner__title" style="margin-top:18px">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--navy)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 19l7-7 3 3-7 7-3-3z" />
                                                        <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" />
                                                    </svg>
                                                    Status Tanda Tangan
                                                </div>
                                                <div class="ttd-status-grid">
                                                    <?php foreach ($daftarRoleTtd as $roleKey => $roleLabel):
                                                        $ttdItem = $ttdSemua[$row['id']][$roleKey] ?? null;
                                                    ?>
                                                        <div class="ttd-status-item <?= $ttdItem ? 'ttd-status-item--signed' : '' ?>">
                                                            <div class="ttd-status-item__role"><?= htmlspecialchars($roleLabel) ?></div>
                                                            <?php if ($ttdItem): ?>
                                                                <img src="../uploads/<?= htmlspecialchars($ttdItem['signature_path']) ?>"
                                                                    alt="Tanda tangan <?= htmlspecialchars($roleLabel) ?>" class="ttd-status-item__img"
                                                                    onclick="bukaGambar(this.src, 'Tanda Tangan — <?= htmlspecialchars(addslashes($roleLabel)) ?>')">
                                                                <div class="ttd-status-item__meta">
                                                                    <?= htmlspecialchars($ttdItem['signed_by']) ?><br>
                                                                    <?= date('d M Y, H:i', strtotime($ttdItem['signed_at'])) ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="ttd-status-item__empty">Belum tanda tangan</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <!-- ─── Kwitansi / Nota — jenis Peralatan & Operasional ─────── -->
                                                <div class="detail-inner__title" style="margin-top:18px;justify-content:space-between">
                                                    <span style="display:flex;align-items:center;gap:6px">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--navy)" stroke-width="2.5">
                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                            <polyline points="14 2 14 8 20 8" />
                                                        </svg>
                                                        Kwitansi / Nota Belanja
                                                    </span>
                                                    <button type="button" class="btn btn--success"
                                                        onclick="bukaUploadKwitansi(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['tujuan'])) ?>')">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <line x1="12" y1="8" x2="12" y2="16" />
                                                            <line x1="8" y1="12" x2="16" y2="12" />
                                                        </svg>
                                                        Tambah Kwitansi/Nota
                                                    </button>
                                                </div>
                                                <?php if (empty($row['kwitansi'])): ?>
                                                    <div class="detail-inner__title" style="color:var(--muted);font-weight:400">
                                                        Belum ada kwitansi/nota yang diunggah.
                                                    </div>
                                                <?php else: ?>
                                                    <div class="nota-galeri-grid">
                                                        <?php foreach ($row['kwitansi'] as $idx => $kwitansiPath):
                                                            $isPdf = preg_match('/\.pdf(\?.*)?$/i', $kwitansiPath);
                                                        ?>
                                                            <div class="nota-galeri-item">
                                                                <?php if ($isPdf): ?>
                                                                    <a href="../uploads/<?= htmlspecialchars($kwitansiPath) ?>" target="_blank" rel="noopener" class="nota-galeri-pdf">
                                                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                                            <polyline points="14 2 14 8 20 8" />
                                                                        </svg>
                                                                        <span>Kwitansi <?= $idx + 1 ?> (PDF) — buka di tab baru</span>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <img src="../uploads/<?= htmlspecialchars($kwitansiPath) ?>" alt="Kwitansi <?= $idx + 1 ?>" loading="lazy" title="Klik untuk perbesar"
                                                                        onclick="bukaGambar('../uploads/<?= htmlspecialchars($kwitansiPath) ?>', 'Kwitansi/Nota — <?= htmlspecialchars(addslashes($row['tujuan'])) ?> (<?= $idx + 1 ?>)')">
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /page -->

    <!-- ─── Modal Tambah Saldo ───────────────────────────────────────── -->
    <div class="modal-overlay" id="modalSaldo" onclick="tutupModal('modalSaldo', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleSaldo">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal__header">
                    <div class="modal__title" id="titleSaldo">Tambah Saldo Masuk</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalSaldo')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="tambah_saldo">
                    <input type="hidden" name="pengajuan_id_saldo" id="inputIdPengajuanSaldo">

                    <div class="form-group">
                        <label class="form-label">Pengajuan</label>
                        <input type="text" class="form-control" id="inputNamaPengajuanSaldo" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Saldo Tambahan (Rp)</label>
                        <input type="text" inputmode="numeric" name="tambah_saldo" class="form-control" required placeholder="Contoh: 50.000" oninput="formatRibuan(this)">
                        <div class="form-hint">Titik ribuan otomatis mengikuti saat kamu mengetik.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Bukti Transfer</label>
                        <input type="file" name="bukti_transfer" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                        <div class="form-hint">Format JPG, PNG, atau PDF. Maksimal 5MB.</div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalSaldo')">Batal</button>
                    <button type="submit" class="btn btn--success">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Kembalikan Saldo ─────────────────────────────────── -->
    <div class="modal-overlay" id="modalKembali" onclick="tutupModal('modalKembali', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleKembali">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal__header">
                    <div class="modal__title" id="titleKembali">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px">
                            <polyline points="9 14 4 19 9 24" />
                            <path d="M20 4v7a4 4 0 0 1-4 4H4" />
                        </svg>
                        Kembalikan Sisa Saldo
                    </div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalKembali')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="kembalikan_saldo">
                    <input type="hidden" name="pengajuan_id_kembali" id="inputIdPengajuanKembali">

                    <div class="form-group">
                        <label class="form-label">Pengajuan</label>
                        <input type="text" class="form-control" id="inputNamaPengajuanKembali" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sisa Saldo Saat Ini</label>
                        <input type="text" class="form-control" id="inputSisaSaldoKembali" disabled style="font-weight:600;color:var(--success,#059669)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Yang Dikembalikan (Rp)</label>
                        <input type="text" inputmode="numeric" name="jumlah_kembali" id="inputJumlahKembali" class="form-control" required
                            placeholder="Contoh: 50.000" oninput="formatRibuan(this)">
                        <div class="form-hint">Titik ribuan otomatis mengikuti saat kamu mengetik.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keterangan <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
                        <input type="text" name="keterangan_kembali" class="form-control" placeholder="Contoh: Sisa dana dikembalikan ke kas koperasi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Bukti Transfer</label>
                        <input type="file" name="bukti_kembali" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                        <div class="form-hint">Foto/scan bukti transfer pengembalian. Format JPG, PNG, atau PDF. Maksimal 5MB.</div>
                    </div>

                    <div class="form-group" style="background:var(--warning-bg,#FEF3C7);border:1px solid #FDE68A;border-radius:var(--radius-sm,8px);padding:10px 14px;margin-bottom:0">
                        <div style="display:flex;gap:8px;align-items:flex-start">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span style="font-size:12.5px;color:#92400E;line-height:1.5">
                                Pengembalian saldo akan mengurangi <strong>Saldo Masuk</strong> pengajuan ini. Tindakan tidak bisa dibatalkan secara otomatis.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalKembali')">Batal</button>
                    <button type="submit" class="btn btn--kembali">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 14 4 19 9 24" />
                            <path d="M20 4v7a4 4 0 0 1-4 4H4" />
                        </svg>
                        Simpan Pengembalian
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Tanda Tangan (TTD) ────────────────────────────────── -->
    <div class="modal-overlay" id="modalTtd" onclick="tutupModal('modalTtd', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleTtd">
            <form method="POST" id="formTtd" onsubmit="return submitTtdKoperasi(event)">
                <div class="modal__header">
                    <div class="modal__title" id="titleTtd">Tanda Tangan</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalTtd')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="simpan_ttd" id="ttdAksiInput">
                    <input type="hidden" name="pengajuan_id" id="ttdPengajuanId">
                    <input type="hidden" name="signature_data" id="ttdSignatureData">

                    <div class="form-group">
                        <label class="form-label">Pengajuan</label>
                        <input type="text" class="form-control" id="ttdNamaPengajuan" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Menandatangani Sebagai</label>
                        <input type="text" class="form-control" id="ttdRoleLabel" disabled>
                    </div>

                    <!-- Mode LIHAT: tanda tangan untuk role ini sudah pernah tersimpan -->
                    <div id="ttdViewMode" style="display:none">
                        <div class="form-group">
                            <label class="form-label">Tanda Tangan Tersimpan</label>
                            <div class="signature-pad-wrap" style="display:flex;align-items:center;justify-content:center;height:180px">
                                <img id="ttdExistingImg" src="" alt="Tanda tangan tersimpan"
                                    style="max-height:170px;max-width:100%;object-fit:contain">
                            </div>
                            <div class="form-hint" id="ttdInfoText"></div>
                        </div>
                        <div class="actions">
                            <button type="button" class="btn btn--outline" onclick="gantiTtdKoperasi()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z" />
                                </svg>
                                Ganti Tanda Tangan
                            </button>
                            <button type="button" class="btn btn--outline" style="color:var(--minus);border-color:#FCA5A5"
                                onclick="hapusTtdKoperasiClick()">
                                Hapus Tanda Tangan
                            </button>
                        </div>
                    </div>

                    <!-- Mode GAMBAR: canvas kosong untuk tanda tangan baru / pengganti -->
                    <div id="ttdDrawMode" style="display:none">
                        <div class="form-group">
                            <label class="form-label">Gambar Tanda Tangan</label>
                            <div class="signature-pad-wrap">
                                <canvas id="canvasTTD"></canvas>
                                <div class="signature-pad-placeholder" id="signaturePlaceholder">Tanda tangan di sini</div>
                            </div>
                            <div class="form-hint">Gunakan jari (di HP) atau mouse untuk menandatangani di dalam kotak.</div>
                        </div>
                        <button type="button" class="btn btn--outline" onclick="hapusTandaTangan()">
                            Hapus Coretan
                        </button>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalTtd')">Batal</button>
                    <button type="submit" class="btn btn--ttd" id="ttdSubmitBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Simpan Tanda Tangan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Tambah Barang ─────────────────────────────────────── -->
    <div class="modal-overlay" id="modalTambahBarang" onclick="tutupModal('modalTambahBarang', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleTambahBarang">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal__header">
                    <div class="modal__title" id="titleTambahBarang">Tambah Barang</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalTambahBarang')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="tambah_barang">
                    <input type="hidden" name="pengajuan_id_barang" id="tambahBarangPengajuanId">

                    <div class="form-group">
                        <label class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" name="nama_barang" required placeholder="Contoh: Buku Tulis">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qty</label>
                        <input type="number" min="0" step="any" class="form-control" name="qty" id="tambahBarangQty" required oninput="hitungSubtotalTambah()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan</label>
                        <input type="text" class="form-control" name="satuan" required placeholder="Contoh: Pcs / Lusin / Rim">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Satuan (Rp)</label>
                        <input type="text" inputmode="numeric" class="form-control" name="harga" id="tambahBarangHarga" required placeholder="Contoh: 15.000" oninput="formatRibuan(this); hitungSubtotalTambah()">
                        <div class="form-hint">Titik ribuan otomatis mengikuti saat kamu mengetik.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subtotal (otomatis)</label>
                        <input type="text" class="form-control" id="tambahBarangSubtotal" disabled value="Rp 0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Nota</label>

                        <div class="nota-upload-actions">
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('tambahBarangNotaFileInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                                Pilih File
                            </button>
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('tambahBarangNotaCameraInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                                Ambil Foto
                            </button>
                        </div>

                        <!-- Input asli untuk pilih file dari galeri/file manager. Tidak diberi atribut
                             "name" supaya tidak ikut terkirim langsung — hasil pilihan digabung dulu
                             lewat JS ke input tersembunyi di bawah (biar bisa akumulasi + bisa dihapus satuan). -->
                        <input type="file" id="tambahBarangNotaFileInput" class="visually-hidden"
                            accept="image/jpeg,image/png,application/pdf" multiple
                            onchange="handleNotaFilesAdded('tambahBarang', this.files); this.value='';">

                        <!-- Input khusus kamera HP (capture="environment" = kamera belakang). -->
                        <input type="file" id="tambahBarangNotaCameraInput" class="visually-hidden"
                            accept="image/*" capture="environment"
                            onchange="handleNotaFilesAdded('tambahBarang', this.files); this.value='';">

                        <!-- Input sebenarnya yang dikirim ke server (name="nota[]"), disinkron via JS. -->
                        <input type="file" name="nota[]" id="tambahBarangNotaSubmit" class="visually-hidden" multiple>

                        <div class="form-hint">Bisa pilih lebih dari 1 file dari galeri, atau ambil langsung dari kamera HP (JPG, PNG, atau PDF). Maksimal 5MB per file. (opsional, disarankan diisi)</div>
                        <div class="nota-preview-list" id="tambahBarangNotaPreview"></div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalTambahBarang')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Upload Nota (untuk barang yang sudah ada) ────────────── -->
    <div class="modal-overlay" id="modalUploadNota" onclick="tutupModal('modalUploadNota', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleUploadNota">
            <form method="POST" enctype="multipart/form-data" onsubmit="return validasiUploadNota()">
                <div class="modal__header">
                    <div class="modal__title" id="titleUploadNota">Tambah Nota</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalUploadNota')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="tambah_nota_item">
                    <input type="hidden" name="item_id_nota" id="uploadNotaItemId">

                    <div class="form-group">
                        <label class="form-label">Barang</label>
                        <input type="text" class="form-control" id="uploadNotaNama" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nota Baru</label>

                        <div class="nota-upload-actions">
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('uploadNotaFileInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                                Pilih File
                            </button>
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('uploadNotaCameraInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                                Ambil Foto
                            </button>
                        </div>

                        <input type="file" id="uploadNotaFileInput" class="visually-hidden"
                            accept="image/jpeg,image/png,application/pdf" multiple
                            onchange="handleNotaFilesAdded('uploadNota', this.files); this.value='';">

                        <input type="file" id="uploadNotaCameraInput" class="visually-hidden"
                            accept="image/*" capture="environment"
                            onchange="handleNotaFilesAdded('uploadNota', this.files); this.value='';">

                        <input type="file" name="nota_tambahan[]" id="uploadNotaSubmit" class="visually-hidden" multiple>

                        <div class="form-hint">Nota yang sudah ada sebelumnya tidak akan hilang — file baru cuma ditambahkan.</div>
                        <div class="nota-preview-list" id="uploadNotaPreview"></div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalUploadNota')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Upload Kwitansi/Nota (level pengajuan — jenis Peralatan & Operasional) ─── -->
    <div class="modal-overlay" id="modalUploadKwitansi" onclick="tutupModal('modalUploadKwitansi', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleUploadKwitansi">
            <form method="POST" enctype="multipart/form-data" onsubmit="return validasiUploadKwitansi()">
                <div class="modal__header">
                    <div class="modal__title" id="titleUploadKwitansi">Upload Kwitansi / Nota</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalUploadKwitansi')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="tambah_kwitansi">
                    <input type="hidden" name="pengajuan_id_kwitansi" id="uploadKwitansiPengajuanId">

                    <div class="form-group">
                        <label class="form-label">Pengajuan</label>
                        <input type="text" class="form-control" id="uploadKwitansiNama" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Foto Kwitansi / Nota</label>

                        <div class="nota-upload-actions">
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('uploadKwitansiFileInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                                Pilih File
                            </button>
                            <button type="button" class="btn btn--outline" onclick="document.getElementById('uploadKwitansiCameraInput').click()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                                Ambil Foto
                            </button>
                        </div>

                        <input type="file" id="uploadKwitansiFileInput" class="visually-hidden"
                            accept="image/jpeg,image/png,application/pdf" multiple
                            onchange="handleNotaFilesAdded('uploadKwitansi', this.files); this.value='';">

                        <input type="file" id="uploadKwitansiCameraInput" class="visually-hidden"
                            accept="image/*" capture="environment"
                            onchange="handleNotaFilesAdded('uploadKwitansi', this.files); this.value='';">

                        <input type="file" name="kwitansi[]" id="uploadKwitansiSubmit" class="visually-hidden" multiple>

                        <div class="form-hint">Bisa pilih lebih dari 1 file dari galeri, atau ambil langsung dari kamera HP (JPG, PNG, atau PDF). Maksimal 5MB per file. Kwitansi lama tidak akan hilang — file baru cuma ditambahkan.</div>
                        <div class="nota-preview-list" id="uploadKwitansiPreview"></div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalUploadKwitansi')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Edit Harga / Nominal (unified: item atau pengajuan) ─── -->
    <div class="modal-overlay" id="modalEditHarga" onclick="tutupModal('modalEditHarga', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleEditHarga">
            <form method="POST" id="formEditHarga">
                <div class="modal__header">
                    <div class="modal__title" id="titleEditHarga">Edit Harga</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalEditHarga')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" id="editHargaAksi" value="edit_harga_item">
                    <input type="hidden" name="item_id" id="editHargaItemId">
                    <input type="hidden" name="pengajuan_id" id="editHargaPengajuanId">

                    <div class="form-group">
                        <label class="form-label" id="editHargaLabelNama">Nama Barang</label>
                        <input type="text" class="form-control" id="editHargaNama" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="editHargaLabelInput">Harga Baru (Rp)</label>
                        <input type="text" inputmode="numeric" id="editHargaInput" class="form-control" required oninput="formatRibuan(this)">
                        <div class="form-hint" id="editHargaHint">Subtotal akan dihitung ulang otomatis (qty × harga).</div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalEditHarga')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Lihat Gambar ───────────────────────────────────────── -->
    <div class="modal-overlay" id="modalGambar" onclick="tutupModal('modalGambar', event)">
        <div class="modal modal--img" role="dialog" aria-modal="true" aria-labelledby="titleGambar">
            <div class="modal__header">
                <div class="modal__title" id="titleGambar">Lihat Gambar</div>
                <button type="button" class="modal__close" onclick="tutupModalById('modalGambar')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal__body" style="text-align:center">
                <img id="gambarBesar" src="" alt="gambar" style="max-width:100%; border-radius:var(--radius-sm); max-height:70vh; object-fit:contain">
            </div>
        </div>
    </div>

    <!-- ─── Modal Galeri Nota (full preview, bisa lebih dari 1 file) ──── -->
    <div class="modal-overlay" id="modalGaleriNota" onclick="tutupModal('modalGaleriNota', event)">
        <div class="modal modal--img" role="dialog" aria-modal="true" aria-labelledby="titleGaleriNota">
            <div class="modal__header">
                <div class="modal__title" id="titleGaleriNota">Nota Belanja</div>
                <button type="button" class="modal__close" onclick="tutupModalById('modalGaleriNota')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal__body" id="galeriNotaBody"></div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="script-koperasi.js"></script>

    <?php include '../components/made-by.php'; ?>
</body>

</html>