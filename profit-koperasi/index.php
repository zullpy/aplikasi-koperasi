<?php
$activePage = 'profit-koperasi';
require_once '../database/koneksi.php';
require_once '../database/auth.php';

$userRole = $_SESSION['role'] ?? '';

// ── Buat tabel jika belum ada / sesuaikan tipe kolom ──
$koneksi->query("CREATE TABLE IF NOT EXISTS profit_koperasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    profit DECIMAL(15,2) NOT NULL DEFAULT 0,
    pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
    bukti_profit TEXT DEFAULT NULL,
    bukti_pajak TEXT DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

@$koneksi->query("ALTER TABLE profit_koperasi MODIFY COLUMN bukti_profit TEXT DEFAULT NULL");
@$koneksi->query("ALTER TABLE profit_koperasi MODIFY COLUMN bukti_pajak TEXT DEFAULT NULL");

$uploadDir = __DIR__ . '/../uploads/bukti_profit/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Helper: Parse list bukti ──
function parseBuktiList($str) {
    if (empty($str)) return [];
    $decoded = json_decode($str, true);
    if (is_array($decoded)) return array_values(array_filter($decoded));
    if (strpos($str, ',') !== false) {
        return array_values(array_filter(array_map('trim', explode(',', $str))));
    }
    return [trim($str)];
}

// ── Helper: Hapus fisik file ──
function deletePhysicalFiles($str, $uploadDir) {
    $files = parseBuktiList($str);
    foreach ($files as $file) {
        if (!empty($file) && file_exists($uploadDir . $file)) {
            @unlink($uploadDir . $file);
        }
    }
}

// ── Helper: Upload file (Mendukung Multi-File Upload) ──
function uploadBuktiMultiple($fileKey, $uploadDir) {
    if (!isset($_FILES[$fileKey])) return [];
    $file = $_FILES[$fileKey];

    $filesToProcess = [];
    if (is_array($file['name'])) {
        foreach ($file['name'] as $idx => $name) {
            if (isset($file['error'][$idx]) && $file['error'][$idx] === UPLOAD_ERR_OK) {
                $filesToProcess[] = [
                    'name'     => $name,
                    'type'     => $file['type'][$idx],
                    'tmp_name' => $file['tmp_name'][$idx],
                    'error'    => $file['error'][$idx],
                    'size'     => $file['size'][$idx]
                ];
            }
        }
    } else {
        if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $filesToProcess[] = $file;
        }
    }

    if (empty($filesToProcess)) return [];

    $uploaded = [];
    $allowed  = ['jpg','jpeg','png','webp','pdf'];

    foreach ($filesToProcess as $f) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        if ($f['size'] > 5 * 1024 * 1024) continue;

        $newName  = uniqid('bukti_', true) . '.' . $ext;
        $fullPath = $uploadDir . $newName;

        if (move_uploaded_file($f['tmp_name'], $fullPath)) {
            // Kompresi gambar jika GD terinstall
            if (in_array($ext, ['jpg','jpeg','png','webp']) && extension_loaded('gd')) {
                $info = @getimagesize($fullPath);
                if ($info) {
                    [$w, $h, $type] = $info;
                    $maxW    = 1000;
                    $quality = 60;
                    $img     = null;
                    if ($type === IMAGETYPE_JPEG)  $img = @imagecreatefromjpeg($fullPath);
                    elseif ($type === IMAGETYPE_PNG) $img = @imagecreatefrompng($fullPath);
                    elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp'))
                        $img = @imagecreatefromwebp($fullPath);
                    if ($img) {
                        if ($w > $maxW) {
                            $newH    = (int) round($h * ($maxW / $w));
                            $resized = imagecreatetruecolor($maxW, $newH);
                            if ($type === IMAGETYPE_PNG) {
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                            }
                            imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxW, $newH, $w, $h);
                            imagedestroy($img);
                            $img = $resized;
                        }
                        if ($type === IMAGETYPE_JPEG)  imagejpeg($img, $fullPath, $quality);
                        elseif ($type === IMAGETYPE_PNG)  imagepng($img, $fullPath, (int) round(9 - ($quality / 100 * 9)));
                        elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagewebp'))
                            imagewebp($img, $fullPath, $quality);
                        imagedestroy($img);
                    }
                }
            }
            $uploaded[] = $newName;
        }
    }
    return $uploaded;
}

// ── POST handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // Input Data (Profit + Pajak sekaligus)
    if ($aksi === 'input_data') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $profit  = (float) str_replace(['.', ','], ['', '.'], $_POST['profit'] ?? 0);
        $pajak   = (float) str_replace(['.', ','], ['', '.'], $_POST['pajak_data'] ?? 0);
        $ket     = trim($_POST['keterangan'] ?? '');

        $uploadedProfit = uploadBuktiMultiple('bukti_profit', $uploadDir);
        $namaProfit     = !empty($uploadedProfit) ? json_encode($uploadedProfit) : null;

        $uploadedPajak = uploadBuktiMultiple('bukti_pajak_data', $uploadDir);
        $namaPajak     = !empty($uploadedPajak) ? json_encode($uploadedPajak) : null;

        $stmt = $koneksi->prepare("INSERT INTO profit_koperasi (tanggal, profit, pajak, bukti_profit, bukti_pajak, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sddsss', $tanggal, $profit, $pajak, $namaProfit, $namaPajak, $ket);
        $stmt->execute();
        header('Location: index.php?status=data_added');
        exit;
    }

    // Edit Data (Hanya Angka, Tanggal, & Keterangan)
    if ($aksi === 'edit_data') {
        $id      = (int) ($_POST['record_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $profit  = (float) str_replace(['.', ','], ['', '.'], $_POST['profit'] ?? 0);
        $pajak   = (float) str_replace(['.', ','], ['', '.'], $_POST['pajak_data'] ?? 0);
        $ket     = trim($_POST['keterangan'] ?? '');

        if ($id > 0) {
            $stmt = $koneksi->prepare("UPDATE profit_koperasi SET tanggal=?, profit=?, pajak=?, keterangan=? WHERE id=?");
            $stmt->bind_param('sddsi', $tanggal, $profit, $pajak, $ket, $id);
            $stmt->execute();
        }
        header('Location: index.php?status=data_updated');
        exit;
    }

    // Upload / Ganti Bukti (Mengganti foto bukti & hapus file fisik lama)
    if ($aksi === 'upload_bukti') {
        $id      = (int) ($_POST['record_id'] ?? 0);
        $jenis   = $_POST['jenis_bukti'] ?? 'profit';
        $kolom   = $jenis === 'pajak' ? 'bukti_pajak' : 'bukti_profit';
        $fileKey = $jenis === 'pajak' ? 'bukti_pajak' : 'bukti_profit';

        $uploaded = uploadBuktiMultiple($fileKey, $uploadDir);
        if ($id > 0 && !empty($uploaded)) {
            $oldRow = $koneksi->query("SELECT {$kolom} FROM profit_koperasi WHERE id=$id")->fetch_assoc();
            if ($oldRow && !empty($oldRow[$kolom])) {
                deletePhysicalFiles($oldRow[$kolom], $uploadDir);
            }
            $nama = json_encode($uploaded);
            $stmt = $koneksi->prepare("UPDATE profit_koperasi SET {$kolom}=? WHERE id=?");
            $stmt->bind_param('si', $nama, $id);
            $stmt->execute();
        }
        header('Location: index.php?status=bukti_uploaded');
        exit;
    }

    // Hapus record (otomatis hapus file fisik)
    if ($aksi === 'hapus' && $userRole === 'admin') {
        $id = (int) ($_POST['record_id'] ?? 0);
        if ($id > 0) {
            $row = $koneksi->query("SELECT bukti_profit, bukti_pajak FROM profit_koperasi WHERE id=$id")->fetch_assoc();
            if ($row) {
                if (!empty($row['bukti_profit'])) deletePhysicalFiles($row['bukti_profit'], $uploadDir);
                if (!empty($row['bukti_pajak']))  deletePhysicalFiles($row['bukti_pajak'], $uploadDir);
                $koneksi->query("DELETE FROM profit_koperasi WHERE id=$id");
            }
        }
        header('Location: index.php?status=deleted');
        exit;
    }
}

// ── Filter ──
$tahunSekarang = (int) date('Y');
$tahunDipilih  = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $tahunSekarang;
$bulanDipilih  = isset($_GET['bulan']) ? (int) $_GET['bulan'] : 0;
if ($bulanDipilih < 0 || $bulanDipilih > 12) $bulanDipilih = 0;

// Daftar tahun
$tahunList = [$tahunSekarang];
$resThn = $koneksi->query("SELECT DISTINCT YEAR(tanggal) AS thn FROM profit_koperasi ORDER BY thn DESC");
if ($resThn) {
    while ($r = $resThn->fetch_assoc()) {
        if (!in_array((int)$r['thn'], $tahunList)) $tahunList[] = (int)$r['thn'];
    }
}
rsort($tahunList);

// Query data
if ($bulanDipilih > 0) {
    $stmt = $koneksi->prepare("SELECT * FROM profit_koperasi WHERE YEAR(tanggal)=? AND MONTH(tanggal)=? ORDER BY tanggal DESC, id DESC");
    $stmt->bind_param('ii', $tahunDipilih, $bulanDipilih);
} else {
    $stmt = $koneksi->prepare("SELECT * FROM profit_koperasi WHERE YEAR(tanggal)=? ORDER BY tanggal DESC, id DESC");
    $stmt->bind_param('i', $tahunDipilih);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
$totalProfit = 0;
$totalPajak  = 0;
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $totalProfit += (float)$r['profit'];
    $totalPajak  += (float)$r['pajak'];
}

$status = $_GET['status'] ?? '';

include '../components/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Koperasi – Bina Usaha Sauyunan</title>
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>

<!-- ── TOAST ── -->
<?php if ($status): ?>
<div class="toast" id="toast">
    <?php
    $icons = [
        'data_added'     => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        'data_updated'   => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'pajak_updated'  => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        'bukti_uploaded' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        'bukti_deleted'  => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
        'deleted'        => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
    ];
    $msgs = [
        'data_added'     => 'Data profit & pajak berhasil ditambahkan.',
        'data_updated'   => 'Data profit & pajak berhasil diperbarui.',
        'pajak_updated'  => 'Data pajak berhasil diperbarui.',
        'bukti_uploaded' => 'Bukti transfer berhasil diunggah.',
        'bukti_deleted'  => 'Foto bukti berhasil dihapus.',
        'deleted'        => 'Data berhasil dihapus.',
    ];
    $icon = $icons[$status] ?? '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    $msg  = $msgs[$status] ?? 'Operasi berhasil.';
    echo $icon . ' ' . htmlspecialchars($msg);
    ?>
</div>
<?php endif; ?>

<main class="pk-main">

    <!-- ── HEADER ── -->
    <div class="pk-header">
        <div class="pk-header-left">
            <h1 class="pk-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Profit Koperasi
            </h1>
            <p class="pk-subtitle">Kelola data profit dan pajak koperasi</p>
        </div>
        <div class="pk-header-actions">
            <button class="btn-primary" id="btnInputData">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Input Data
            </button>
        </div>
    </div>

    <!-- ── SUMMARY CARDS ── -->
    <div class="pk-cards">
        <div class="pk-card pk-card-profit">
            <div class="pk-card-icon">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
            <div class="pk-card-body">
                <span class="pk-card-label">Total Profit</span>
                <span class="pk-card-value">Rp <?= number_format($totalProfit, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="pk-card pk-card-pajak">
            <div class="pk-card-icon">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
            </div>
            <div class="pk-card-body">
                <span class="pk-card-label">Total Pajak</span>
                <span class="pk-card-value">Rp <?= number_format($totalPajak, 0, ',', '.') ?></span>
            </div>
        </div>
        <!-- <div class="pk-card pk-card-net">
            <div class="pk-card-icon">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
            </div>
            <div class="pk-card-body">
                <span class="pk-card-label">Profit Bersih</span>
                <span class="pk-card-value">Rp <?= number_format($totalProfit - $totalPajak, 0, ',', '.') ?></span>
            </div>
        </div> -->
    </div>

    <!-- ── FILTER ── -->
    <div class="pk-filter-bar">
        <form method="GET" class="pk-filter-form">
            <div class="filter-group">
                <label>Tahun</label>
                <select name="tahun" id="filterTahun">
                    <?php foreach ($tahunList as $thn): ?>
                    <option value="<?= $thn ?>" <?= $thn == $tahunDipilih ? 'selected' : '' ?>><?= $thn ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Bulan</label>
                <select name="bulan" id="filterBulan">
                    <option value="0" <?= $bulanDipilih == 0 ? 'selected' : '' ?>>Semua Bulan</option>
                    <?php
                    $namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                    for ($m = 1; $m <= 12; $m++):
                    ?>
                    <option value="<?= $m ?>" <?= $bulanDipilih == $m ? 'selected' : '' ?>><?= $namaBulan[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">Terapkan</button>
            <a href="index.php" class="btn-reset">Reset</a>
        </form>
    </div>

    <!-- ── TABEL ── -->
    <div class="pk-table-wrap">
        <table class="pk-table" id="tabelProfit">
            <thead>
                <tr>
                    <th class="th-no">#</th>
                    <th class="th-date">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Tanggal
                        </span>
                    </th>
                    <th class="th-profit">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            Profit (Rp)
                        </span>
                    </th>
                    <th class="th-bukti">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Bukti Profit
                        </span>
                    </th>
                    <th class="th-pajak">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                            Pajak (Rp)
                        </span>
                    </th>
                    <th class="th-bukti">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Bukti Pajak
                        </span>
                    </th>
                    <th class="th-ket">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="13" y1="18" x2="3" y2="18"/></svg>
                            Keterangan
                        </span>
                    </th>
                    <th class="th-aksi">
                        <span class="th-inner">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            Aksi
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8" class="pk-empty">
                        <div class="pk-empty-inner">
                            <div class="pk-empty-icon">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <line x1="18" y1="20" x2="18" y2="10"/>
                                    <line x1="12" y1="20" x2="12" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                    <line x1="3" y1="20" x2="21" y2="20"/>
                                </svg>
                            </div>
                            <p class="pk-empty-title">Belum ada data profit</p>
                            <p class="pk-empty-sub">Klik tombol di atas untuk mulai menambahkan data profit atau pajak koperasi.</p>
                            <!-- <div class="pk-empty-actions">
                                <button class="btn-empty-cta btn-empty-profit" onclick="openModal('modalProfit')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                    Input Profit
                                </button>
                                <button class="btn-empty-cta btn-empty-pajak" onclick="openModal('modalPajakBaru')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                    Input Pajak
                                </button>
                            </div> -->
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <?php
                    $listProfit = parseBuktiList($row['bukti_profit']);
                    $listPajak  = parseBuktiList($row['bukti_pajak']);
                ?>
                <tr>
                    <td class="pk-no"><?= $i + 1 ?></td>
                    <td class="pk-date"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                    <td class="pk-amount pk-profit-val">Rp <?= number_format((float)$row['profit'], 0, ',', '.') ?></td>
                    <td class="pk-bukti">
                        <?php if (!empty($listProfit)): ?>
                            <div class="bukti-btns">
                                <button type="button" class="btn-lihat-bukti" onclick='openPreviewBukti(<?= htmlspecialchars(json_encode($listProfit), ENT_QUOTES, "UTF-8") ?>, "Bukti Profit Transfer")'>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Lihat <?= count($listProfit) > 1 ? '(' . count($listProfit) . ')' : '' ?>
                                </button>
                                <button type="button" class="btn-upload-bukti btn-ganti-bukti" onclick="openUploadBukti(<?= $row['id'] ?>, 'profit', true)" title="Ganti bukti profit">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Ganti
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn-upload-bukti" onclick="openUploadBukti(<?= $row['id'] ?>, 'profit', false)" title="Upload bukti profit">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Upload
                            </button>
                        <?php endif; ?>
                    </td>
                    <td class="pk-amount pk-pajak-val">Rp <?= number_format((float)$row['pajak'], 0, ',', '.') ?></td>
                    <td class="pk-bukti">
                        <?php if (!empty($listPajak)): ?>
                            <div class="bukti-btns">
                                <button type="button" class="btn-lihat-bukti btn-lihat-bukti-pajak" onclick='openPreviewBukti(<?= htmlspecialchars(json_encode($listPajak), ENT_QUOTES, "UTF-8") ?>, "Bukti Pajak Transfer")'>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Lihat <?= count($listPajak) > 1 ? '(' . count($listPajak) . ')' : '' ?>
                                </button>
                                <button type="button" class="btn-upload-bukti btn-upload-pajak btn-ganti-bukti" onclick="openUploadBukti(<?= $row['id'] ?>, 'pajak', true)" title="Ganti bukti pajak">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Ganti
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn-upload-bukti btn-upload-pajak" onclick="openUploadBukti(<?= $row['id'] ?>, 'pajak', false)" title="Upload bukti pajak">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Upload
                            </button>
                        <?php endif; ?>
                    </td>
                    <td class="pk-ket"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td class="pk-actions">
                        <button type="button" class="btn-icon btn-edit" onclick='openModalEdit(<?= htmlspecialchars(json_encode([
                            "id" => (int)$row["id"],
                            "tanggal" => $row["tanggal"],
                            "profit" => (float)$row["profit"],
                            "pajak" => (float)$row["pajak"],
                            "keterangan" => $row["keterangan"] ?? ""
                        ]), ENT_QUOTES, "UTF-8") ?>)' title="Edit Data">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php if ($userRole === 'admin'): ?>
                        <button class="btn-icon btn-hapus" onclick="konfirmasiHapus(<?= $row['id'] ?>)" title="Hapus">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot>
                <tr class="pk-tfoot">
                    <td colspan="2"><strong>TOTAL</strong></td>
                    <td class="pk-amount"><strong>Rp <?= number_format($totalProfit, 0, ',', '.') ?></strong></td>
                    <td></td>
                    <td class="pk-amount"><strong>Rp <?= number_format($totalPajak, 0, ',', '.') ?></strong></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</main>

<!-- ═══════════════════════════════════════════════
     MODAL: INPUT DATA (Profit + Pajak — 2 kolom)
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalInputData">
    <div class="modal-box modal-box-wide">
        <div class="modal-header">
            <h2>
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Input Data Koperasi
            </h2>
            <button class="modal-close" onclick="closeModal('modalInputData')">&times;</button>
        </div>
        <form method="POST" action="index.php" enctype="multipart/form-data" id="formInputData">
            <input type="hidden" name="aksi" value="input_data">
            <div class="modal-body">

                <!-- Tanggal + Keterangan (full width) -->
                <div class="modal-row-2">
                    <div class="form-group">
                        <label for="input_tanggal">Tanggal <span class="req">*</span></label>
                        <input type="date" id="input_tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="input_keterangan">Keterangan</label>
                        <input type="text" id="input_keterangan" name="keterangan" placeholder="Opsional...">
                    </div>
                </div>

                <!-- Dua kolom: Profit | Pajak -->
                <div class="modal-cols">

                    <!-- Kolom Profit -->
                    <div class="modal-col modal-col-profit">
                        <div class="modal-col-header">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            Profit
                        </div>
                        <div class="form-group">
                            <label for="profit_nominal">Nominal Profit (Rp) <span class="req">*</span></label>
                            <div class="input-rp">
                                <span>Rp</span>
                                <input type="text" id="profit_nominal" name="profit" placeholder="0" required inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Bukti Transfer Profit</label>
                            <div class="upload-area upload-area-sm" id="uploadAreaProfit" onclick="document.getElementById('fileProfit').click()">
                                <svg width="26" height="26" fill="none" stroke="#a0aec0" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <p id="uploadTextProfit">Klik atau seret<br><small>JPG, PNG, WEBP, PDF</small></p>
                                <img id="previewProfit" class="upload-preview" src="" alt="preview">
                            </div>
                            <input type="file" id="fileProfit" name="bukti_profit[]" accept="image/*,application/pdf" multiple style="display:none" onchange="previewFile(this,'previewProfit','uploadTextProfit')">
                        </div>
                    </div>

                    <!-- Pemisah -->
                    <div class="modal-col-divider"></div>

                    <!-- Kolom Pajak -->
                    <div class="modal-col modal-col-pajak">
                        <div class="modal-col-header">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                            Pajak
                        </div>
                        <div class="form-group">
                            <label for="pajak_data_nominal">Nominal Pajak (Rp)</label>
                            <div class="input-rp">
                                <span>Rp</span>
                                <input type="text" id="pajak_data_nominal" name="pajak_data" placeholder="0" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Bukti Transfer Pajak</label>
                            <div class="upload-area upload-area-sm" id="uploadAreaPajakData" onclick="document.getElementById('filePajakData').click()">
                                <svg width="26" height="26" fill="none" stroke="#a0aec0" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <p id="uploadTextPajakData">Klik atau seret<br><small>JPG, PNG, WEBP, PDF (Bisa >1 file)</small></p>
                                <img id="previewPajakData" class="upload-preview" src="" alt="preview">
                            </div>
                            <input type="file" id="filePajakData" name="bukti_pajak_data[]" accept="image/*,application/pdf" multiple style="display:none" onchange="previewFile(this,'previewPajakData','uploadTextPajakData')">
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalInputData')">Batal</button>
                <button type="submit" class="btn-submit">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MODAL: EDIT DATA (Hanya Angka & Teks)
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEditData">
    <div class="modal-box">
        <div class="modal-header">
            <h2>
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Data Koperasi
            </h2>
            <button class="modal-close" onclick="closeModal('modalEditData')">&times;</button>
        </div>
        <form method="POST" action="index.php" id="formEditData">
            <input type="hidden" name="aksi" value="edit_data">
            <input type="hidden" name="record_id" id="editRecordId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_tanggal">Tanggal <span class="req">*</span></label>
                    <input type="date" id="edit_tanggal" name="tanggal" required>
                </div>
                <div class="modal-row-2">
                    <div class="form-group">
                        <label for="edit_profit_nominal">Nominal Profit (Rp) <span class="req">*</span></label>
                        <div class="input-rp">
                            <span>Rp</span>
                            <input type="text" id="edit_profit_nominal" name="profit" placeholder="0" required inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_pajak_nominal">Nominal Pajak (Rp)</label>
                        <div class="input-rp">
                            <span>Rp</span>
                            <input type="text" id="edit_pajak_nominal" name="pajak_data" placeholder="0" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_keterangan">Keterangan</label>
                    <input type="text" id="edit_keterangan" name="keterangan" placeholder="Opsional...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditData')">Batal</button>
                <button type="submit" class="btn-submit">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MODAL: UPLOAD BUKTI
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalUploadBukti">
    <div class="modal-box modal-box-sm">
        <div class="modal-header">
            <h2 id="uploadBuktiTitle">Upload Bukti Transfer</h2>
            <button class="modal-close" onclick="closeModal('modalUploadBukti')">&times;</button>
        </div>
        <form method="POST" action="index.php" enctype="multipart/form-data" id="formUploadBukti">
            <input type="hidden" name="aksi" value="upload_bukti">
            <input type="hidden" name="record_id" id="uploadBuktiRecordId">
            <input type="hidden" name="jenis_bukti" id="uploadBuktiJenis">
            <input type="hidden" name="mode_bukti" id="uploadBuktiMode" value="tambah">
            <div class="modal-body">
                <div class="form-group">
                    <label>File Bukti Transfer</label>
                    <div class="upload-area" id="uploadAreaExtra" onclick="document.getElementById('fileExtra').click()">
                        <svg width="32" height="32" fill="none" stroke="#a0aec0" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p id="uploadTextExtra">Klik atau seret file ke sini<br><small>JPG, PNG, WEBP, PDF — maks 5MB/file</small></p>
                        <img id="previewExtra" class="upload-preview" src="" alt="preview">
                    </div>
                    <input type="file" id="fileExtra" name="bukti_profit[]" accept="image/*,application/pdf" multiple style="display:none" onchange="previewFile(this,'previewExtra','uploadTextExtra')">
                    <input type="file" id="fileExtraPajak" name="bukti_pajak[]" accept="image/*,application/pdf" multiple style="display:none" onchange="previewFile(this,'previewExtra','uploadTextExtra')">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalUploadBukti')">Batal</button>
                <button type="submit" class="btn-submit" id="btnSubmitUploadBukti">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MODAL: PRATINJAU BUKTI
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPreviewBukti">
    <div class="modal-box modal-box-wide">
        <div class="modal-header">
            <h2 id="modalPreviewTitle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Pratinjau Bukti Transfer
            </h2>
            <button class="modal-close" onclick="closeModal('modalPreviewBukti')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-gallery" id="previewBuktiGallery"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('modalPreviewBukti')">Tutup</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MODAL: KONFIRMASI HAPUS
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal-box modal-box-sm">
        <div class="modal-header">
            <h2>Konfirmasi Hapus</h2>
            <button class="modal-close" onclick="closeModal('modalHapus')">&times;</button>
        </div>
        <form method="POST" action="index.php" id="formHapus">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="record_id" id="hapusRecordId">
            <div class="modal-body">
                <p style="color:#64748b;font-size:15px;">Apakah kamu yakin ingin menghapus data profit ini? Data yang dihapus tidak bisa dikembalikan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
                <button type="submit" class="btn-submit btn-submit-hapus">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Ya, Hapus
                </button>
            </div>
        </form>
    </div>
</div>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
