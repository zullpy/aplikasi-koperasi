<?php
$activePage = 'laporan-sppg';
include '../database/koneksi.php';
include '../database/tambah-saldo.php';
include '../database/laporan-sppg-func.php';
require_once '../database/auth.php';

// ── Proses aksi dari modal Edit Harga / Tambah Barang / Hapus Barang ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'edit_harga') {
        updateHargaItem($koneksi, $_POST['item_id'], str_replace(['.', ','], '', $_POST['harga_baru']));
        header('Location: index.php?status=harga_updated');
        exit;
    }

    if ($_POST['aksi'] === 'tambah_barang') {
        tambahBarangItem(
            $koneksi,
            $_POST['pengajuan_id_barang'],
            $_POST['nama_barang'],
            $_POST['qty'],
            $_POST['satuan'],
            str_replace(['.', ','], '', $_POST['harga'])
        );
        header('Location: index.php?status=barang_added');
        exit;
    }

    if ($_POST['aksi'] === 'hapus_barang') {
        hapusBarangItem($koneksi, $_POST['item_id']);
        header('Location: index.php?status=barang_deleted');
        exit;
    }

    if ($_POST['aksi'] === 'simpan_ttd') {
        $role_login     = $_SESSION['role'] ?? '';
        $user_id_login  = $_SESSION['id'] ?? null;
        $signature_data = $_POST['signature_data'] ?? '';
        $pengajuan_id   = $_POST['pengajuan_id'] ?? 0;

        if (!empty($signature_data) && strpos($signature_data, 'data:image') === 0) {
            $ok = simpanTandaTanganLaporan($koneksi, $pengajuan_id, $role_login, $user_id_login, $signature_data);
            header('Location: index.php?status=' . ($ok ? 'ttd_saved' : 'ttd_gagal'));
            exit;
        }

        header('Location: index.php?status=ttd_gagal');
        exit;
    }
}

include '../components/navbar.php';

$rows  = getDataLaporan($koneksi);
$total = getTotalRingkasan($rows);
$role_login_saat_ini = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan SPPG | KBUS</title>
    <link rel="icon" href="../assets/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="page">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header__title">
                <div class="page-header__icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                </div>
                <div>
                    <h1>Laporan SPPG</h1>
                    <p>Rekap saldo masuk, belanja, dan sisa saldo pengajuan approved</p>
                </div>
            </div>
        </div>

        <!-- Alert -->
        <?php if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'harga_updated', 'barang_added', 'barang_deleted', 'ttd_saved', 'ttd_gagal'])):
            $pesan = [
                'success'        => 'Saldo masuk berhasil diperbarui.',
                'harga_updated'  => 'Harga barang berhasil diperbarui.',
                'barang_added'   => 'Barang baru berhasil ditambahkan.',
                'barang_deleted' => 'Barang berhasil dihapus.',
                'ttd_saved'      => 'Tanda tangan berhasil disimpan.',
                'ttd_gagal'      => 'Gagal menyimpan tanda tangan, silakan coba lagi.',
            ][$_GET['status']];
            $isError = $_GET['status'] === 'ttd_gagal';
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
                <div class="summary-card__sub">Akumulasi seluruh Laporan</div>
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
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                </div>
                <div class="summary-card__label">Jumlah Laporan</div>
                <div class="summary-card__value"><?= count($rows) ?></div>
                <div class="summary-card__sub">Data dengan status approved</div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-card__header">
                <div class="table-card__title">Rincian Laporan</div>
                <div class="table-card__badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <?= count($rows) ?> Laporan
                </div>
            </div>

            <div class="table-wrap">
                <table class="laporan">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Nama Menu</th>
                            <th class="center">Porsi</th>
                            <th class="right">Saldo Masuk</th>
                            <th class="right">Total Belanja</th>
                            <th class="center">Sisa Saldo</th>
                            <th class="center">Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-state__icon">
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="9" x2="21" y2="9" />
                                                <line x1="9" y1="21" x2="9" y2="9" />
                                            </svg>
                                        </div>
                                        <div class="empty-state__title">Belum ada data</div>
                                        <div class="empty-state__desc">Tidak ada pengajuan dengan status approved.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $no => $row):
                                $sisa = (float) $row['sisa_uang'];
                                $ttdMasuk = getTandaTanganLaporan($koneksi, $row['id']);
                                $ttdSaya  = $ttdMasuk[$role_login_saat_ini] ?? null;
                            ?>
                                <tr class="data-row">
                                    <td><span class="no-badge"><?= $no + 1 ?></span></td>
                                    <td style="white-space:nowrap; color:var(--muted)">
                                        <?= date('d M Y', strtotime($row['tanggal'])) ?>
                                    </td>
                                    <td style="font-weight:500"><?= htmlspecialchars($row['nama_menu']) ?></td>
                                    <td class="center">
                                        <span style="display:inline-flex;align-items:center;gap:4px;font-weight:600">
                                            <?= (int) $row['jumlah_porsi'] ?>
                                            <span style="font-size:11px;color:var(--muted);font-weight:400">porsi</span>
                                        </span>
                                    </td>
                                    <td class="right mono"><?= rupiah($row['uang_masuk']) ?></td>
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
                                    <td class="center">
                                        <?php if (!empty($row['bukti_transfer'])): ?>
                                            <img src="../uploads/bukti_transfer/<?= htmlspecialchars($row['bukti_transfer']) ?>"
                                                class="thumb"
                                                onclick="bukaGambar('../uploads/bukti_transfer/<?= htmlspecialchars($row['bukti_transfer']) ?>', 'Bukti Transfer')"
                                                alt="bukti transfer">
                                        <?php else: ?>
                                            <span style="color:var(--muted); font-size:13px">—</span>
                                        <?php endif; ?>
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
                                                onclick="bukaSaldo(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_menu'])) ?>')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="8" x2="12" y2="16" />
                                                    <line x1="8" y1="12" x2="16" y2="12" />
                                                </svg>
                                                Saldo
                                            </button>
                                            <?php if ($ttdSaya): ?>
                                                <img src="<?= htmlspecialchars($ttdSaya['signature_data']) ?>"
                                                    class="thumb" style="background:#fff"
                                                    onclick="bukaGambar('<?= htmlspecialchars($ttdSaya['signature_data']) ?>', 'Tanda Tangan <?= htmlspecialchars(labelRole($role_login_saat_ini)) ?>')"
                                                    alt="ttd tersimpan" title="Sudah ditandatangani — klik untuk lihat">
                                                <button class="btn btn--outline" onclick="bukaTandaTangan(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_menu'])) ?>')">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <path d="M3 17l6-6 4 4 8-8" />
                                                        <path d="M14 6l3-3 4 4-3 3" />
                                                    </svg>
                                                    Ganti
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn--ttd" onclick="bukaTandaTangan(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_menu'])) ?>')">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <path d="M3 17l6-6 4 4 8-8" />
                                                        <path d="M14 6l3-3 4 4-3 3" />
                                                        <path d="M2 21c2-1 3-1 5 0s3 1 5 0 3-1 5 0 3 1 5 0" />
                                                    </svg>
                                                    TTD
                                                </button>
                                            <?php endif; ?>
                                            <a class="btn btn--outline"
                                                href="cetak-laporan-belanja.php?id=<?= $row['id'] ?>"
                                                target="_blank" title="Ekspor / Cetak PDF Laporan Belanja">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                    <line x1="9" y1="15" x2="15" y2="15" />
                                                    <line x1="9" y1="11" x2="12" y2="11" />
                                                </svg>
                                                Cetak
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Detail Row -->
                                <tr class="detail-row" id="detail<?= $row['id'] ?>">
                                    <td colspan="9">
                                        <div class="detail-inner">
                                            <div class="detail-inner__title" style="justify-content:space-between">
                                                <span style="display:flex;align-items:center;gap:6px">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--navy)" stroke-width="2.5">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <polyline points="14 2 14 8 20 8" />
                                                    </svg>
                                                    Detail Belanja — <?= htmlspecialchars($row['nama_menu']) ?>
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
                                            <div style="overflow-x:auto">
                                                <table class="detail-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Nama Barang</th>
                                                            <th>Qty</th>
                                                            <th>Satuan</th>
                                                            <th>Harga</th>
                                                            <th>Subtotal</th>
                                                            <th>Nota</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $items = getDetailItem($koneksi, $row['id']);
                                                        if (empty($items)): ?>
                                                            <tr>
                                                                <td colspan="7" class="text-muted text-center" style="text-align:center;color:var(--muted);padding:16px">Tidak ada detail item.</td>
                                                            </tr>
                                                            <?php else: foreach ($items as $item): ?>
                                                                <tr>
                                                                    <td style="font-weight:500"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                                                    <td><?= (int) $item['qty'] ?></td>
                                                                    <td style="color:var(--muted)"><?= htmlspecialchars($item['satuan']) ?></td>
                                                                    <td style="font-variant-numeric:tabular-nums" id="hargaTampil<?= $item['id'] ?>"><?= rupiah($item['harga']) ?></td>
                                                                    <td style="font-variant-numeric:tabular-nums;font-weight:600"><?= rupiah($item['subtotal']) ?></td>
                                                                    <td>
                                                                        <?php if (!empty($item['file_path'])): ?>
                                                                            <img src="<?= htmlspecialchars($item['file_path']) ?>"
                                                                                class="thumb"
                                                                                onclick="bukaGambar('<?= htmlspecialchars($item['file_path']) ?>', 'Nota Belanja')"
                                                                                alt="nota" style="width:32px;height:32px"
                                                                                onerror="this.replaceWith(Object.assign(document.createElement('span'),{style:'color:var(--minus);font-size:11px',textContent:'File tidak ditemukan'}))">
                                                                        <?php else: ?>
                                                                            <span style="color:var(--muted)">—</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <div class="actions">
                                                                            <button type="button" class="btn btn--outline"
                                                                                onclick="bukaEditHarga(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['nama_barang'])) ?>', <?= (float) $item['harga'] ?>)">
                                                                                Edit
                                                                            </button>
                                                                            <form method="POST" style="display:inline"
                                                                                onsubmit="return confirm('Hapus barang \'<?= htmlspecialchars(addslashes($item['nama_barang'])) ?>\'? Tindakan ini tidak bisa dibatalkan.');">
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
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($rows)): ?>
                        <tfoot>
                            <!-- <tr>
                                <td colspan="4" style="text-align:right; color:var(--navy); letter-spacing:.3px; font-size:12px; text-transform:uppercase">Grand Total</td>
                                <td style="text-align:right"><?= rupiah($total['saldo_masuk']) ?></td>
                                <td style="text-align:right"><?= rupiah($total['belanja']) ?></td>
                                <td style="text-align:center">
                                    <span class="saldo-pill <?= $total['sisa_saldo'] < 0 ? 'saldo-pill--minus' : 'saldo-pill--plus' ?>">
                                        <?= rupiah($total['sisa_saldo']) ?>
                                    </span>
                                </td>
                                <td colspan="2"></td>
                            </tr> -->
                        </tfoot>
                    <?php endif; ?>
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
                    <input type="hidden" name="id_pengajuan" id="inputIdPengajuan">

                    <div class="form-group">
                        <label class="form-label">Menu</label>
                        <input type="text" class="form-control" id="inputNamaMenu" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Saldo Tambahan (Rp)</label>
                        <input type="text" inputmode="numeric" name="tambah_saldo" class="form-control" required placeholder="Contoh: 50.000" oninput="formatRibuan(this)">
                        <div class="form-hint">Titik ribuan otomatis mengikuti saat kamu mengetik.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Bukti Transfer</label>
                        <input type="file" name="bukti_transfer" id="inputBuktiTransfer" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
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

    <!-- ─── Modal Tanda Tangan Digital ──────────────────────────────── -->
    <div class="modal-overlay" id="modalTandaTangan" onclick="tutupModal('modalTandaTangan', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleTTD">
            <form method="POST" id="formTandaTangan" onsubmit="return submitTandaTangan(event)">
                <div class="modal__header">
                    <div class="modal__title" id="titleTTD">Tanda Tangan Digital</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalTandaTangan')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="simpan_ttd">
                    <input type="hidden" name="signature_data" id="inputSignatureData">
                    <input type="hidden" name="pengajuan_id" id="ttdPengajuanId">

                    <div class="form-group">
                        <label class="form-label">Laporan</label>
                        <input type="text" class="form-control" id="ttdNamaMenu" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Penanda Tangan</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(labelRole($_SESSION['role'] ?? '')) ?> (<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)" disabled>
                        <div class="form-hint">Otomatis sesuai akun yang sedang login.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gambar Tanda Tangan di Area Bawah</label>
                        <div class="signature-pad-wrap">
                            <canvas id="canvasTTD" width="400" height="180"></canvas>
                            <div class="signature-pad-placeholder" id="signaturePlaceholder">Tanda tangan di sini</div>
                        </div>
                        <div class="form-hint">Gunakan mouse, trackpad, atau jari (di perangkat sentuh). Jika role ini sudah pernah tanda tangan di laporan ini, menyimpan akan menggantikan tanda tangan yang lama.</div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="hapusTandaTangan()">Hapus</button>
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalTandaTangan')">Batal</button>
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

    <!-- ─── Modal Edit Harga ─────────────────────────────────────────── -->
    <div class="modal-overlay" id="modalEditHarga" onclick="tutupModal('modalEditHarga', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleEditHarga">
            <form method="POST">
                <div class="modal__header">
                    <div class="modal__title" id="titleEditHarga">Edit Harga Barang</div>
                    <button type="button" class="modal__close" onclick="tutupModalById('modalEditHarga')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="modal__body">
                    <input type="hidden" name="aksi" value="edit_harga">
                    <input type="hidden" name="item_id" id="editHargaItemId">

                    <div class="form-group">
                        <label class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="editHargaNamaBarang" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Baru (Rp)</label>
                        <input type="text" inputmode="numeric" name="harga_baru" id="editHargaInput" class="form-control" required oninput="formatRibuan(this)">
                        <div class="form-hint">Subtotal akan dihitung ulang otomatis (qty × harga).</div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalEditHarga')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Modal Tambah Barang ─────────────────────────────────────── -->
    <div class="modal-overlay" id="modalTambahBarang" onclick="tutupModal('modalTambahBarang', event)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titleTambahBarang">
            <form method="POST">
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
                        <input type="text" class="form-control" name="nama_barang" required placeholder="Contoh: Minyak Goreng">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qty</label>
                        <input type="number" min="0" step="any" class="form-control" name="qty" id="tambahBarangQty" required oninput="hitungSubtotalTambah()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan</label>
                        <input type="text" class="form-control" name="satuan" required placeholder="Contoh: Liter / Kg / Pcs">
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
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="tutupModalById('modalTambahBarang')">Batal</button>
                    <button type="submit" class="btn btn--success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ─── Detail Toggle ─────────────────────────────────────────── */
        function toggleDetail(id, btn) {
            const row = document.getElementById(id);
            const isOpen = row.classList.contains('open');
            row.classList.toggle('open', !isOpen);
            btn.style.background = !isOpen ? 'var(--navy)' : '';
            btn.style.color = !isOpen ? '#fff' : '';
            btn.style.borderColor = !isOpen ? 'var(--navy)' : '';
        }

        /* ─── Modal Helpers ─────────────────────────────────────────── */
        function bukaModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function tutupModalById(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }

        function tutupModal(id, e) {
            if (e.target === document.getElementById(id)) tutupModalById(id);
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
                    m.classList.remove('open');
                });
                document.body.style.overflow = '';
            }
        });

        /* ─── Saldo Modal ───────────────────────────────────────────── */
        function bukaSaldo(id, nama) {
            document.getElementById('inputIdPengajuan').value = id;
            document.getElementById('inputNamaMenu').value = nama;
            bukaModal('modalSaldo');
        }

        /* ─── Gambar Modal ──────────────────────────────────────────── */
        function bukaGambar(src, judul) {
            document.getElementById('gambarBesar').src = src;
            document.getElementById('titleGambar').textContent = judul;
            bukaModal('modalGambar');
        }

        /* ─── Edit Harga Modal ──────────────────────────────────────── */
        function bukaEditHarga(itemId, namaBarang, hargaSaatIni) {
            document.getElementById('editHargaItemId').value = itemId;
            document.getElementById('editHargaNamaBarang').value = namaBarang;
            document.getElementById('editHargaInput').value = Math.round(hargaSaatIni).toLocaleString('id-ID');
            bukaModal('modalEditHarga');
        }

        /* ─── Tambah Barang Modal ───────────────────────────────────── */
        function bukaTambahBarang(pengajuanId) {
            document.getElementById('tambahBarangPengajuanId').value = pengajuanId;
            document.getElementById('tambahBarangQty').value = '';
            document.getElementById('tambahBarangHarga').value = '';
            document.getElementById('tambahBarangSubtotal').value = 'Rp 0';
            bukaModal('modalTambahBarang');
        }

        function hitungSubtotalTambah() {
            const qty = parseFloat(document.getElementById('tambahBarangQty').value) || 0;
            const hargaInput = document.getElementById('tambahBarangHarga').value;
            const harga = parseFloat(angkaBersih(hargaInput)) || 0;
            const subtotal = qty * harga;
            document.getElementById('tambahBarangSubtotal').value =
                'Rp ' + subtotal.toLocaleString('id-ID');
        }

        /* ─── Tanda Tangan Modal ─────────────────────────────────────── */
        function bukaTandaTangan(pengajuanId, namaMenu) {
            document.getElementById('ttdPengajuanId').value = pengajuanId;
            document.getElementById('ttdNamaMenu').value = namaMenu || '';
            hapusTandaTangan();
            bukaModal('modalTandaTangan');
            // canvas baru bisa di-resize dengan benar setelah modal benar-benar terlihat
            setTimeout(initCanvasTTD, 50);
        }

        function submitTandaTangan(e) {
            if (!ttdSudahMenggambar) {
                e.preventDefault();
                alert('Silakan gambar tanda tangan terlebih dahulu sebelum menyimpan.');
                return false;
            }
            const canvas = document.getElementById('canvasTTD');
            document.getElementById('inputSignatureData').value = canvas.toDataURL('image/png');
            return true;
        }
    </script>
    <script src="script.js"></script>

</body>

</html>