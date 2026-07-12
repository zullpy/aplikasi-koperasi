<?php
require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/auth.php';
$activePage = 'laporan-barang';
include __DIR__ . '/../components/navbar.php';

$userRole = $_SESSION['role'] ?? null;
// Batasi akses hanya untuk admin, bendahara, dan ketua
if (!in_array($userRole, ['admin', 'bendahara', 'ketua'])) {
    header("Location: ../");
    exit;
}

// 1. Hitung Total Pembelian Barang
$qPembelian = "SELECT SUM(harga * volume + biaya_admin) AS total FROM db_draft_barang.transaksi_pembelian";
$resPembelian = $koneksi->query($qPembelian);
$totalPembelian = $resPembelian ? (float)$resPembelian->fetch_assoc()['total'] : 0;

// 2. Hitung Total Penjualan SPPG (Foodcost + Addcost)
$qPenjualan = "
    SELECT 
        SUM(pbd.qty * COALESCE(CAST(REPLACE(REPLACE(REPLACE(b.harga_beli, 'Rp', ''), '.', ''), ' ', '') AS UNSIGNED), 0)) AS total
    FROM db_mbg.pengambilan_barang pb
    INNER JOIN db_mbg.pengambilan_barang_detail pbd ON pbd.id_pengambilan = pb.id_pengambilan
    LEFT JOIN db_draft_barang.barang b ON LOWER(TRIM(b.nama_barang)) = LOWER(TRIM(pbd.nama_barang))
    WHERE pb.status = 'verified'
";
$resPenjualan = $koneksi2->query($qPenjualan);
$totalPenjualan = $resPenjualan ? (float)$resPenjualan->fetch_assoc()['total'] : 0;

// 3. Ambil Rincian Aset Barang di Pusat & Transit
$qBarang = "SELECT id_barang, nama_barang, satuan, satuan_eceran, isi_per_satuan,
              harga_beli, harga_eceran, stok_akhir
              FROM db_draft_barang.barang 
              ORDER BY nama_barang ASC";
$resMaster = $koneksi->query($qBarang);

$items = [];
if ($resMaster) {
    while ($r = $resMaster->fetch_assoc()) {
        $id  = (int)$r['id_barang'];
        $isi = (isset($r['isi_per_satuan']) && (float)$r['isi_per_satuan'] > 0) ? (float)$r['isi_per_satuan'] : null;
        
        $hargaBeliClean = (float)preg_replace('/[^0-9]/', '', $r['harga_beli'] ?? '0');
        $hargaEceranRaw = (float)$r['harga_eceran'];
        
        if ($hargaEceranRaw > 0) {
            $hargaEceran = $hargaEceranRaw;
        } elseif ($isi) {
            $hargaEceran = $hargaBeliClean / $isi;
        } else {
            $hargaEceran = $hargaBeliClean;
        }
        
        $satuanGrosir  = trim($r['satuan'] ?? '') ?: '-';
        $satuanEceran  = trim($r['satuan_eceran'] ?? '') ?: $satuanGrosir;
        
        $stokGrosirPusat = (int)$r['stok_akhir'];
        $stokEceranPusat = $isi ? ($stokGrosirPusat * $isi) : $stokGrosirPusat;
        
        $items[$id] = [
            'id_barang'      => $id,
            'nama'           => $r['nama_barang'],
            'nama_key'       => strtolower(trim($r['nama_barang'])),
            'satuan'         => $satuanGrosir,
            'satuan_eceran'  => $satuanEceran,
            'isi_per_satuan' => $isi,
            'harga_beli'     => $hargaBeliClean,
            'harga_beli_raw' => $r['harga_beli'],
            'harga_eceran'   => $hargaEceran,
            'pusat'          => [
                'stok_grosir'  => $stokGrosirPusat,
                'stok_eceran'  => $stokEceranPusat
            ],
            'sodong'         => ['stok_grosir' => 0, 'stok_eceran' => 0],
            'sariwangi'      => ['stok_grosir' => 0, 'stok_eceran' => 0],
            'manonjaya'      => ['stok_grosir' => 0, 'stok_eceran' => 0],
        ];
    }
}

$gudangList = ['sodong', 'sariwangi', 'manonjaya'];

foreach ($gudangList as $gudang) {
    $sqlStok = "
        SELECT LOWER(TRIM(nama_barang)) AS nama_key,
               qty_grosir,
               qty_eceran
        FROM db_mbg.stok_barang
        WHERE lokasi = ?
    ";
    
    $stmt = $koneksi2->prepare($sqlStok);
    if ($stmt) {
        $stmt->bind_param('s', $gudang);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $stokMap = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $stokMap[$r['nama_key']] = [
                    'grosir' => (float)($r['qty_grosir'] ?? 0),
                    'eceran' => (float)($r['qty_eceran'] ?? 0)
                ];
            }
        }
        $stmt->close();
        
        foreach ($items as $id => &$it) {
            $key = $it['nama_key'];
            if (isset($stokMap[$key])) {
                $it[$gudang]['stok_grosir'] = $stokMap[$key]['grosir'];
                $it[$gudang]['stok_eceran'] = $stokMap[$key]['eceran'];
            }
        }
        unset($it);
    }
}

$totalNilaiBarang = 0;
$dataBarang = [];
foreach ($items as $it) {
    $stokTransitGrosir = $it['sodong']['stok_grosir'] + $it['sariwangi']['stok_grosir'] + $it['manonjaya']['stok_grosir'];
    $stokTransitEceran = $it['sodong']['stok_eceran'] + $it['sariwangi']['stok_eceran'] + $it['manonjaya']['stok_eceran'];
    
    $totalQtyEceran = $it['pusat']['stok_eceran'] + $stokTransitEceran;
    $nilaiAset = $totalQtyEceran * $it['harga_eceran'];
    $totalNilaiBarang += $nilaiAset;
    
    $it['stok_transit_grosir'] = $stokTransitGrosir;
    $it['stok_transit_eceran'] = $stokTransitEceran;
    $it['total_qty_eceran'] = $totalQtyEceran;
    $it['nilai_aset'] = $nilaiAset;
    
    $dataBarang[] = $it;
}

// 4. Ambil Log Barang Reject
$resReject = $koneksi->query("SELECT * FROM db_draft_barang.barang_reject ORDER BY tanggal DESC");
$rejectLogs = [];
if ($resReject) {
    while ($r = $resReject->fetch_assoc()) {
        $rejectLogs[] = $r;
    }
}

// 5. Hitung Total Nilai Reject untuk Summary Card
$qRejectSum = "SELECT SUM(total) AS total FROM db_draft_barang.barang_reject";
$resRejectSum = $koneksi->query($qRejectSum);
$totalReject = $resRejectSum ? (float)$resRejectSum->fetch_assoc()['total'] : 0;

// 6. Filter & Buat data suggestion barang yang hanya ada stok nya
$suggestedBarang = [];
foreach ($dataBarang as $b) {
    $stockInfo = [];
    if ($b['pusat']['stok_grosir'] > 0 || $b['pusat']['stok_eceran'] > 0) {
        $stockInfo[] = "Pusat: " . number_format($b['pusat']['stok_grosir'], 0, ',', '.') . " " . $b['satuan'] . " (" . number_format($b['pusat']['stok_eceran'], 0, ',', '.') . " " . $b['satuan_eceran'] . ")";
    }
    if ($b['sodong']['stok_grosir'] > 0 || $b['sodong']['stok_eceran'] > 0) {
        $stockInfo[] = "Sodong: " . number_format($b['sodong']['stok_grosir'], 0, ',', '.') . " " . $b['satuan'] . " (" . number_format($b['sodong']['stok_eceran'], 0, ',', '.') . " " . $b['satuan_eceran'] . ")";
    }
    if ($b['sariwangi']['stok_grosir'] > 0 || $b['sariwangi']['stok_eceran'] > 0) {
        $stockInfo[] = "Sariwangi: " . number_format($b['sariwangi']['stok_grosir'], 0, ',', '.') . " " . $b['satuan'] . " (" . number_format($b['sariwangi']['stok_eceran'], 0, ',', '.') . " " . $b['satuan_eceran'] . ")";
    }
    if ($b['manonjaya']['stok_grosir'] > 0 || $b['manonjaya']['stok_eceran'] > 0) {
        $stockInfo[] = "Manonjaya: " . number_format($b['manonjaya']['stok_grosir'], 0, ',', '.') . " " . $b['satuan'] . " (" . number_format($b['manonjaya']['stok_eceran'], 0, ',', '.') . " " . $b['satuan_eceran'] . ")";
    }

    if (!empty($stockInfo)) {
        $b['stock_label'] = implode(', ', $stockInfo);
        $suggestedBarang[] = $b;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Barang</title>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h1><i class="ph ph-package"></i> Laporan Barang</h1>
    <div class="subtitle">Laporan ringkasan total nilai aset barang, total pembelian, dan total penjualan SPPG</div>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <div class="summary-card info">
            <div class="card-icon">
                <i class="ph ph-shopping-cart"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Pembelian Barang</span>
                <span class="value">Rp <?= number_format($totalPembelian, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="summary-card success">
            <div class="card-icon">
                <i class="ph ph-currency-dollar"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Penjualan SPPG</span>
                <span class="value">Rp <?= number_format($totalPenjualan, 0, ',', '.'); ?></span>
            </div>
        </div>
        <div class="summary-card primary">
            <div class="card-icon">
                <i class="ph ph-archive"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Nilai Barang (Aset Gudang)</span>
                <span class="value">Rp <?= number_format($totalNilaiBarang, 0, ',', '.'); ?></span>
            </div>
        </div>
        <div class="summary-card danger">
            <div class="card-icon" style="background: rgba(220, 38, 38, 0.1); color: var(--danger);">
                <i class="ph ph-trash"></i>
            </div>
            <div class="card-content">
                <span class="label">Total Nilai Barang Reject</span>
                <span class="value">Rp <?= number_format($totalReject, 0, ',', '.'); ?></span>
            </div>
        </div>

    </div>

    <!-- LOG BARANG REJECT -->
    <div style="margin-top: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                <i class="ph ph-trash" style="color: #ef4444; font-size: 22px;"></i>
                Log Barang Reject
            </h2>
            <button type="button" onclick="openRejectModal()" class="btn-reject" style="display: inline-flex; align-items: center; gap: 6px; background: #3b82f6; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 500; transition: background 0.2s;">
                <i class="ph ph-plus-circle" style="font-size: 16px;"></i>
                Tambah Barang Reject
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600;">
                        <th style="padding: 12px 16px;">Tanggal</th>
                        <th style="padding: 12px 16px;">Nama Barang</th>
                        <th style="padding: 12px 16px;">Gudang</th>
                        <th style="padding: 12px 16px;">Tipe</th>
                        <th style="padding: 12px 16px; text-align: right;">Qty</th>
                        <th style="padding: 12px 16px; text-align: right;">Harga Beli Eceran</th>
                        <th style="padding: 12px 16px; text-align: right;">Total Nilai</th>
                        <th style="padding: 12px 16px;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rejectLogs)): ?>
                        <tr>
                            <td colspan="8" style="padding: 24px; text-align: center; color: #64748b; font-style: italic;">Belum ada log barang reject.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rejectLogs as $log): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                                <td style="padding: 12px 16px; white-space: nowrap;"><?= date('d-m-Y H:i', strtotime($log['tanggal'])); ?></td>
                                <td style="padding: 12px 16px; font-weight: 500;"><?= htmlspecialchars($log['nama_barang']); ?></td>
                                <td style="padding: 12px 16px; text-transform: capitalize;"><?= htmlspecialchars($log['gudang']); ?></td>
                                <td style="padding: 12px 16px; text-transform: capitalize;"><?= htmlspecialchars($log['tipe']); ?></td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600;"><?= number_format($log['qty'], 2, ',', '.'); ?></td>
                                <td style="padding: 12px 16px; text-align: right;">Rp <?= number_format($log['harga_beli_eceran'], 0, ',', '.'); ?></td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #b91c1c;">Rp <?= number_format($log['total'], 0, ',', '.'); ?></td>
                                <td style="padding: 12px 16px;"><?= htmlspecialchars($log['keterangan'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL OVERLAY -->
<div id="rejectModalOverlay" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal" style="background:#fff; border-radius:12px; width:480px; box-shadow:0 8px 30px rgba(0,0,0,0.15); display:flex; flex-direction:column; overflow:hidden;">
        <div class="modal-header" style="background: linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0;">
            <span style="font-weight:600; font-size:16px;">Tambah Barang Reject</span>
            <button type="button" onclick="closeRejectModal()" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer; font-family:inherit;">&times;</button>
        </div>
        <form action="../database/add-reject.php" method="POST" style="padding:20px; display:flex; flex-direction:column; gap:12px; margin:0;">
            <div class="form-group" style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:600; font-size:13px; color:#475569;">Nama Barang</label>
                <div class="searchable-dropdown" style="position: relative;">
                    <input type="text" name="nama_barang" id="reject_nama_barang" placeholder="Ketik nama barang..." required autocomplete="off" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;" />
                    <div id="reject_dropdown_list" class="searchable-dropdown-list" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); max-height: 200px; overflow-y: auto; z-index: 1000; display: none; margin-top: 4px;">
                        <?php foreach($suggestedBarang as $b): ?>
                            <div class="dropdown-item" data-name="<?= htmlspecialchars($b['nama']); ?>" data-harga="<?= $b['harga_eceran']; ?>" data-isi="<?= $b['isi_per_satuan'] ? $b['isi_per_satuan'] : 1; ?>" style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.15s;">
                                <div class="dropdown-item-name" style="font-size: 13px; font-weight: 600; color: #1e293b; text-align: left;"><?= htmlspecialchars($b['nama']); ?></div>
                                <div class="dropdown-item-meta" style="font-size: 11px; color: #64748b; margin-top: 2px; text-align: left;">
                                    Harga: Rp <?= number_format($b['harga_eceran'], 0, ',', '.'); ?> / eceran <br/>
                                    <span style="color: #0284c7; font-weight: 500;">Stok - <?= htmlspecialchars($b['stock_label']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group" style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:600; font-size:13px; color:#475569;">Gudang</label>
                <select name="gudang" required style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;">
                    <option value="">-- Pilih Gudang --</option>
                    <option value="pusat">Gudang Pusat</option>
                    <option value="sodong">Gudang Sodong</option>
                    <option value="sariwangi">Gudang Sariwangi</option>
                    <option value="manonjaya">Gudang Manonjaya</option>
                </select>
            </div>
            <div class="form-row" style="display:flex; gap:12px;">
                <div class="form-group" style="flex:1; display:flex; flex-direction:column; gap:4px;">
                    <label style="font-weight:600; font-size:13px; color:#475569;">Tipe Unit</label>
                    <select name="tipe" id="reject_tipe" required onchange="calculateTotal()" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;">
                        <option value="eceran" selected>Eceran</option>
                        <option value="grosir">Grosir</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1; display:flex; flex-direction:column; gap:4px;">
                    <label style="font-weight:600; font-size:13px; color:#475569;">Qty</label>
                    <input type="number" name="qty" id="reject_qty" required min="0.01" step="0.01" oninput="calculateTotal()" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;" />
                </div>
            </div>
            <div class="form-row" style="display:flex; gap:12px;">
                <div class="form-group" style="flex:1; display:flex; flex-direction:column; gap:4px;">
                    <label style="font-weight:600; font-size:13px; color:#475569;">Harga Beli Eceran (Rp)</label>
                    <input type="text" name="harga_beli_eceran" id="reject_harga" required oninput="calculateTotal()" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;" />
                </div>
                <div class="form-group" style="flex:1; display:flex; flex-direction:column; gap:4px;">
                    <label style="font-weight:600; font-size:13px; color:#475569;">Total Nilai</label>
                    <input type="text" id="reject_total" readonly style="width:100%; height:38px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:6px; padding:0 8px; font-family:inherit; font-size:14px; box-sizing:border-box;" />
                </div>
            </div>
            <div class="form-group" style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-weight:600; font-size:13px; color:#475569;">Keterangan</label>
                <textarea name="keterangan" id="reject_keterangan" rows="3" placeholder="Contoh: Expired, Pecah, Bocor" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:8px; font-family:inherit; font-size:14px; box-sizing:border-box; resize:vertical;"></textarea>
            </div>
            <div class="modal-footer" style="padding-top:8px; display:flex; justify-content:end; gap:8px;">
                <button type="button" onclick="closeRejectModal()" style="height:38px; padding:0 16px; border:1px solid #cbd5e1; background:#fff; color:#475569; border-radius:6px; font-family:inherit; cursor:pointer; font-weight:500; font-size:14px;">Batal</button>
                <button type="submit" style="height:38px; padding:0 16px; border:none; background:#3b82f6; color:#fff; border-radius:6px; font-family:inherit; cursor:pointer; font-weight:500; font-size:14px;">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_SESSION['alert'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['icon']; ?>',
        title: '<?= $_SESSION['alert']['title']; ?>',
        text: '<?= $_SESSION['alert']['text']; ?>'
    });
</script>
<?php unset($_SESSION['alert']); endif; ?>

<script>
    const masterBarang = <?= json_encode($suggestedBarang); ?>;

    function openRejectModal() {
        document.getElementById('rejectModalOverlay').style.display = 'flex';
        // Reset fields
        document.getElementById('reject_nama_barang').value = '';
        document.getElementById('reject_qty').value = '';
        document.getElementById('reject_harga').value = '';
        document.getElementById('reject_total').value = 'Rp 0';
        document.getElementById('reject_keterangan').value = '';
        document.getElementById('reject_dropdown_list').style.display = 'none';
    }

    function closeRejectModal() {
        document.getElementById('rejectModalOverlay').style.display = 'none';
    }

    const searchInput = document.getElementById('reject_nama_barang');
    const dropdownList = document.getElementById('reject_dropdown_list');

    searchInput.addEventListener('focus', () => {
        filterDropdown(searchInput.value);
        dropdownList.style.display = 'block';
    });

    searchInput.addEventListener('input', () => {
        filterDropdown(searchInput.value);
        dropdownList.style.display = 'block';
    });

    document.addEventListener('click', (e) => {
        const wrapper = document.querySelector('.searchable-dropdown');
        if (wrapper && !wrapper.contains(e.target)) {
            dropdownList.style.display = 'none';
        }
    });

    dropdownList.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            searchInput.value = item.dataset.name;
            const price = parseFloat(item.dataset.harga) || 0;
            document.getElementById('reject_harga').value = Math.round(price).toLocaleString('id-ID');
            dropdownList.style.display = 'none';
            calculateTotal();
        });
    });

    function filterDropdown(query) {
        const cleanQuery = query.trim().toLowerCase();
        const items = dropdownList.querySelectorAll('.dropdown-item');
        items.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            if (name.includes(cleanQuery)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function calculateTotal() {
        const qty = parseFloat(document.getElementById('reject_qty').value) || 0;
        const hargaText = document.getElementById('reject_harga').value || '';
        const harga = parseFloat(hargaText.replace(/\./g, '').replace(/[^0-9]/g, '')) || 0;
        const tipe = document.getElementById('reject_tipe').value;
        
        const selectedItemName = document.getElementById('reject_nama_barang').value.trim().toLowerCase();
        const match = masterBarang.find(b => b.nama.trim().toLowerCase() === selectedItemName);
        const isi = match ? parseFloat(match.isi_per_satuan) || 1 : 1;

        let total = 0;
        if (tipe === 'grosir') {
            total = qty * isi * harga;
        } else {
            total = qty * harga;
        }

        document.getElementById('reject_total').value = 'Rp ' + Math.round(total).toLocaleString('id-ID');
    }

    // Format harga input as typing
    document.getElementById('reject_harga').addEventListener('input', function(e) {
        let value = this.value.replace(/\./g, '').replace(/[^0-9]/g, '');
        if (value !== '') {
            this.value = parseInt(value).toLocaleString('id-ID');
        } else {
            this.value = '';
        }
        calculateTotal();
    });
</script>

<?php include __DIR__ . '/../components/made-by.php'; ?>

</body>
</html>
