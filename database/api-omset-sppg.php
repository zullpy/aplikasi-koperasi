<?php
require_once 'auth.php';
header('Content-Type: application/json');
require_once 'koneksi.php';

// Ensure column `pajak` exists in `omset_sppg_harian` table
try {
    $cekPajak = $koneksi->query("SHOW COLUMNS FROM omset_sppg_harian LIKE 'pajak'");
    if ($cekPajak && $cekPajak->num_rows === 0) {
        $koneksi->query("ALTER TABLE omset_sppg_harian ADD COLUMN pajak DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER biaya_admin");
    }
} catch (Throwable $e) {
    // abaikan jika sudah ada
}

// ---- KONSTANTA HARGA PORSI ----
const HARGA_PORSI_BESAR = 9950;
const HARGA_PORSI_KECIL = 7950;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_bulan':
            cekRolloverBulan($koneksi);
            echo json_encode(getDataBulanIni($koneksi));
            break;

        case 'cek_tanggal':
            echo json_encode(cekTanggalSudahAda($koneksi));
            break;

        case 'simpan_harian':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(simpanHarian($koneksi));
            break;

        case 'update_kpm':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateKpm($koneksi));
            break;

        case 'update_keuntungan':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateKeuntungan($koneksi));
            break;

        case 'update_belanja_foodcost':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateBelanjaFoodcost($koneksi));
            break;

        case 'update_anggaran_diterima':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateAnggaranDiterima($koneksi));
            break;

        case 'update_pajak':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updatePajak($koneksi));
            break;

        case 'update_pajak_mingguan':
            if (!in_array($_SESSION['role'] ?? '', ['bendahara', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara atau admin yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updatePajakMingguan($koneksi));
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// =========================================================
// FUNGSI-FUNGSI
// =========================================================

/**
 * Ambil semua baris omset harian untuk bulan berjalan (otomatis
 * kosong di tanggal 1 karena belum ada baris untuk bulan itu).
 */
function getDataBulanIni(mysqli $koneksi): array
{
    // Ambil daftar bulan yang tersedia datanya di tabel harian untuk filter dropdown
    $listBulan = [];
    $resBulan = $koneksi->query("SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan_val FROM omset_sppg_harian ORDER BY bulan_val DESC");
    if ($resBulan) {
        while ($rowB = $resBulan->fetch_assoc()) {
            if (!empty($rowB['bulan_val'])) {
                $listBulan[] = $rowB['bulan_val'];
            }
        }
    }
    $bulanIni = date('Y-m');
    if (!in_array($bulanIni, $listBulan, true)) {
        array_unshift($listBulan, $bulanIni);
    }

    $bulanRequested = $_GET['bulan'] ?? $_POST['bulan'] ?? null;
    if ($bulanRequested) {
        $bulanFilter = $bulanRequested;
    } else {
        // Jika tidak ada parameter bulan, utamakan bulan terbaru yang ada datanya
        $bulanFilter = !empty($listBulan) ? $listBulan[0] : $bulanIni;
    }

    $stmt = $koneksi->prepare("
        SELECT * FROM omset_sppg_harian
        WHERE DATE_FORMAT(tanggal, '%Y-%m') = CONVERT(? USING utf8mb4)
        ORDER BY tanggal ASC
    ");
    $stmt->bind_param('s', $bulanFilter);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();

    // hitung total (footer tabel)
    $total = [
        'total_anggaran' => 0,
        'anggaran_diterima' => 0, // sebelumnya 'biaya_admin'
        'pajak' => 0,
        'total_kpm' => 0,
        'pagu_belanja' => 0,
        'nominal_koperasi' => 0,
        'nominal_yayasan' => 0,
        'nominal_helmi' => 0,
        'nominal_management' => 0,
    ];
    foreach ($rows as $r) {
        $total['total_anggaran']      += (float)$r['total_anggaran'];
        $total['anggaran_diterima']   += (float)$r['biaya_admin']; // kolom db tetap biaya_admin
        $total['pajak']               += (float)($r['pajak'] ?? 0);
        $total['total_kpm']           += (int)$r['total_kpm'];
        $total['pagu_belanja']        += (float)$r['pagu_belanja'];
        $total['nominal_koperasi']    += (float)$r['nominal_koperasi'];
        $total['nominal_yayasan']     += (float)$r['nominal_yayasan'];
        $total['nominal_helmi']       += (float)$r['nominal_helmi'];
        $total['nominal_management']  += (float)$r['nominal_management'];
    }

    // Ambil rekap profit mingguan
    $rekapMingguan = [];
    try {
        $queryRekap = "
            SELECT 
                DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY) AS tgl_mulai,
                DATE_ADD(DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY), INTERVAL 6 DAY) AS tgl_selesai,
                SUM(nominal_koperasi) AS koperasi,
                SUM(nominal_yayasan) AS yayasan,
                SUM(nominal_helmi) AS helmi,
                SUM(nominal_management) AS management,
                SUM(pajak) AS pajak
            FROM omset_sppg_harian
            GROUP BY 1, 2
            ORDER BY 1 ASC
        ";
        $resRekap = $koneksi->query($queryRekap);
        if ($resRekap) {
            while ($r = $resRekap->fetch_assoc()) {
                $rekapMingguan[] = $r;
            }
        }
    } catch (Throwable $e) {
        error_log("API Rekap Mingguan error: " . $e->getMessage());
    }

    return [
        'success' => true,
        'bulan' => $bulanFilter,
        'harga_porsi_besar' => HARGA_PORSI_BESAR,
        'harga_porsi_kecil' => HARGA_PORSI_KECIL,
        'rows' => $rows,
        'total' => $total,
        'list_bulan' => $listBulan,
        'rekap_mingguan' => $rekapMingguan,
    ];
}

/**
 * Cek apakah tanggal tertentu (dipilih user di modal input) sudah punya
 * baris data. Dipakai frontend untuk munculkan badge "sudah pernah diinput".
 */
function cekTanggalSudahAda(mysqli $koneksi): array
{
    $tanggal = $_POST['tanggal'] ?? $_GET['tanggal'] ?? '';

    if (!$tanggal || !isTanggalValid($tanggal)) {
        return ['success' => false, 'message' => 'Tanggal tidak valid'];
    }

    $stmt = $koneksi->prepare("SELECT COUNT(*) AS jml FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ['success' => true, 'ada' => ((int)$row['jml']) > 0];
}

/**
 * Validasi format & keabsahan tanggal (Y-m-d).
 */
function isTanggalValid(string $tanggal): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal;
}

/**
 * Simpan input KPM besar & kecil untuk TANGGAL YANG DIPILIH USER.
 * Kalau baris tanggal itu sudah ada, di-update (bukan double insert).
 */
function simpanHarian(mysqli $koneksi): array
{
    $tanggal  = $_POST['tanggal'] ?? '';
    $kpmBesar = (int)($_POST['kpm_besar'] ?? 0);
    $kpmKecil = (int)($_POST['kpm_kecil'] ?? 0);
    $pajakInput = isset($_POST['pajak']) && $_POST['pajak'] !== '' ? (float)$_POST['pajak'] : null;
    $anggaranDiterimaInput = isset($_POST['anggaran_diterima']) && $_POST['anggaran_diterima'] !== '' ? (float)$_POST['anggaran_diterima'] : null;

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }
    if (!isTanggalValid($tanggal)) {
        return ['success' => false, 'message' => 'Format tanggal tidak valid'];
    }
    if ($kpmBesar < 0 || $kpmKecil < 0) {
        return ['success' => false, 'message' => 'KPM tidak boleh negatif'];
    }
    if ($kpmBesar === 0 && $kpmKecil === 0) {
        return ['success' => false, 'message' => 'Isi minimal salah satu KPM'];
    }

    $anggaranBesar = $kpmBesar * HARGA_PORSI_BESAR;
    $anggaranKecil = $kpmKecil * HARGA_PORSI_KECIL;
    $totalAnggaran = $anggaranBesar + $anggaranKecil;
    $totalKpm      = $kpmBesar + $kpmKecil;

    // cek baris tanggal terpilih
    $cek = $koneksi->prepare("SELECT * FROM omset_sppg_harian WHERE tanggal = ?");
    $cek->bind_param('s', $tanggal);
    $cek->execute();
    $existing = $cek->get_result()->fetch_assoc();
    $cek->close();

    if ($existing) {
        $keuntungan = [
            'koperasi'   => (float)$existing['keuntungan_koperasi'],
            'yayasan'    => (float)$existing['keuntungan_yayasan'],
            'helmi'      => (float)$existing['keuntungan_helmi'],
        ];
        $paguBelanja      = (float)$existing['pagu_belanja'];
        $anggaranDiterima = $anggaranDiterimaInput !== null ? $anggaranDiterimaInput : (float)$existing['biaya_admin'];
        $pajak            = $pajakInput !== null ? $pajakInput : (float)($existing['pajak'] ?? 0);
    } else {
        $keuntungan = ['koperasi' => 0, 'yayasan' => 0, 'helmi' => 0];
        $paguBelanja      = 0.0;
        $anggaranDiterima = $anggaranDiterimaInput !== null ? $anggaranDiterimaInput : 0.0;
        $pajak            = $pajakInput !== null ? $pajakInput : 0.0;
    }

    $nominal = hitungNominal($keuntungan, $totalKpm);
    // RUMUS: Anggaran Diterima - Pajak - Belanja Foodcost - (KBUS+Yayasan+Koperasi)
    $nominalManagement = $anggaranDiterima - $pajak - $paguBelanja - array_sum($nominal);

    if ($existing) {
        $stmt = $koneksi->prepare("
            UPDATE omset_sppg_harian SET
                kpm_besar = ?,
                kpm_kecil = ?,
                anggaran_besar = ?,
                anggaran_kecil = ?,
                total_anggaran = ?,
                total_kpm = ?,
                nominal_koperasi = ?,
                nominal_yayasan = ?,
                nominal_helmi = ?,
                nominal_management = ?
            WHERE tanggal = ?
        ");
        $stmt->bind_param(
            'iidddidddds',
            $kpmBesar,
            $kpmKecil,
            $anggaranBesar,
            $anggaranKecil,
            $totalAnggaran,
            $totalKpm,
            $nominal['koperasi'],
            $nominal['yayasan'],
            $nominal['helmi'],
            $nominalManagement,
            $tanggal
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $koneksi->prepare("
            INSERT INTO omset_sppg_harian
                (tanggal, kpm_besar, kpm_kecil, anggaran_besar, anggaran_kecil,
                 total_anggaran, total_kpm,
                 keuntungan_koperasi, nominal_koperasi,
                 keuntungan_yayasan, nominal_yayasan,
                 keuntungan_helmi, nominal_helmi,
                 keuntungan_management, nominal_management,
                 pagu_belanja, biaya_admin, pajak)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $keuntunganManagement = 0.0;
        $stmt->bind_param(
            'siidddiddddddddddd',
            $tanggal,
            $kpmBesar,
            $kpmKecil,
            $anggaranBesar,
            $anggaranKecil,
            $totalAnggaran,
            $totalKpm,
            $keuntungan['koperasi'],
            $nominal['koperasi'],
            $keuntungan['yayasan'],
            $nominal['yayasan'],
            $keuntungan['helmi'],
            $nominal['helmi'],
            $keuntunganManagement,
            $nominalManagement,
            $paguBelanja,
            $anggaranDiterima,
            $pajak
        );
        $stmt->execute();
        $stmt->close();
    }

    return ['success' => true, 'message' => 'Data omset tanggal ' . $tanggal . ' tersimpan'];
}

/**
 * Update KPM (besar atau kecil) untuk baris tanggal tertentu.
 */
function updateKpm(mysqli $koneksi): array
{
    $tanggal = $_POST['tanggal'] ?? '';
    $jenis   = $_POST['jenis'] ?? ''; // 'besar' atau 'kecil'
    $nilai   = (int)($_POST['nilai'] ?? 0);

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }
    if (!in_array($jenis, ['besar', 'kecil'], true)) {
        return ['success' => false, 'message' => 'Jenis KPM tidak valid'];
    }
    if ($nilai < 0) {
        return ['success' => false, 'message' => 'KPM tidak boleh negatif'];
    }

    $stmt = $koneksi->prepare("SELECT * FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $kpmBesar = (int)$row['kpm_besar'];
    $kpmKecil = (int)$row['kpm_kecil'];

    if ($jenis === 'besar') {
        $kpmBesar = $nilai;
    } else {
        $kpmKecil = $nilai;
    }

    $anggaranBesar = $kpmBesar * HARGA_PORSI_BESAR;
    $anggaranKecil = $kpmKecil * HARGA_PORSI_KECIL;
    $totalAnggaran = $anggaranBesar + $anggaranKecil;
    $totalKpm      = $kpmBesar + $kpmKecil;

    $keuntungan = [
        'koperasi'   => (float)$row['keuntungan_koperasi'],
        'yayasan'    => (float)$row['keuntungan_yayasan'],
        'helmi'      => (float)$row['keuntungan_helmi'],
    ];
    $nominal = hitungNominal($keuntungan, $totalKpm);

    $paguBelanja      = (float)$row['pagu_belanja'];
    $anggaranDiterima = (float)$row['biaya_admin'];
    $pajak            = (float)($row['pajak'] ?? 0);
    $nominalManagement = $anggaranDiterima - $pajak - $paguBelanja - array_sum($nominal);

    $stmt = $koneksi->prepare("
        UPDATE omset_sppg_harian SET
            kpm_besar = ?,
            kpm_kecil = ?,
            anggaran_besar = ?,
            anggaran_kecil = ?,
            total_anggaran = ?,
            total_kpm = ?,
            nominal_koperasi = ?,
            nominal_yayasan = ?,
            nominal_helmi = ?,
            nominal_management = ?
        WHERE tanggal = ?
    ");
    $stmt->bind_param(
        'iidddidddds',
        $kpmBesar,
        $kpmKecil,
        $anggaranBesar,
        $anggaranKecil,
        $totalAnggaran,
        $totalKpm,
        $nominal['koperasi'],
        $nominal['yayasan'],
        $nominal['helmi'],
        $nominalManagement,
        $tanggal
    );
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'kpm_besar' => $kpmBesar,
        'kpm_kecil' => $kpmKecil,
        'total_anggaran' => $totalAnggaran,
        'total_kpm' => $totalKpm,
        'nominal_koperasi' => $nominal['koperasi'],
        'nominal_yayasan' => $nominal['yayasan'],
        'nominal_helmi' => $nominal['helmi'],
        'nominal_management' => $nominalManagement,
    ];
}

/**
 * Update keuntungan (rate per-KPM) salah satu dari 3 kategori
 */
function updateKeuntungan(mysqli $koneksi): array
{
    $tanggal  = $_POST['tanggal'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $nilai    = (float)($_POST['nilai'] ?? 0);

    $kategoriValid = ['koperasi', 'yayasan', 'helmi'];
    if (!in_array($kategori, $kategoriValid, true)) {
        return ['success' => false, 'message' => 'Kategori tidak valid'];
    }
    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }

    $stmt = $koneksi->prepare("SELECT * FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $keuntungan = [
        'koperasi'   => (float)$row['keuntungan_koperasi'],
        'yayasan'    => (float)$row['keuntungan_yayasan'],
        'helmi'      => (float)$row['keuntungan_helmi'],
    ];
    $keuntungan[$kategori] = $nilai;

    $totalKpm = (int)$row['total_kpm'];
    $nominal = hitungNominal($keuntungan, $totalKpm);
    $paguBelanja      = (float)$row['pagu_belanja'];
    $anggaranDiterima = (float)$row['biaya_admin'];
    $pajak            = (float)($row['pajak'] ?? 0);
    $nominalManagement = $anggaranDiterima - $pajak - $paguBelanja - array_sum($nominal);

    $stmt = $koneksi->prepare("
        UPDATE omset_sppg_harian SET
            keuntungan_koperasi = ?,
            nominal_koperasi = ?,
            keuntungan_yayasan = ?,
            nominal_yayasan = ?,
            keuntungan_helmi = ?,
            nominal_helmi = ?,
            nominal_management = ?
        WHERE tanggal = ?
    ");
    $stmt->bind_param(
        'ddddddds',
        $keuntungan['koperasi'],
        $nominal['koperasi'],
        $keuntungan['yayasan'],
        $nominal['yayasan'],
        $keuntungan['helmi'],
        $nominal['helmi'],
        $nominalManagement,
        $tanggal
    );
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'nominal' => $nominal[$kategori],
        'nominal_management' => $nominalManagement,
    ];
}

/**
 * Update Belanja Foodcost (pagu_belanja) secara langsung.
 */
function updateBelanjaFoodcost(mysqli $koneksi): array
{
    $tanggal = $_POST['tanggal'] ?? '';
    $belanja = (float)($_POST['belanja'] ?? 0);

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }

    $stmt = $koneksi->prepare("SELECT biaya_admin, pajak, nominal_koperasi, nominal_yayasan, nominal_helmi FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $anggaranDiterima = (float)$row['biaya_admin'];
    $pajak            = (float)($row['pajak'] ?? 0);
    $nominalTiga      = (float)$row['nominal_koperasi'] + (float)$row['nominal_yayasan'] + (float)$row['nominal_helmi'];
    $nominalManagement = $anggaranDiterima - $pajak - $belanja - $nominalTiga;

    $stmt = $koneksi->prepare("UPDATE omset_sppg_harian SET pagu_belanja = ?, nominal_management = ? WHERE tanggal = ?");
    $stmt->bind_param('dds', $belanja, $nominalManagement, $tanggal);
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'belanja' => $belanja,
        'nominal_management' => $nominalManagement
    ];
}

/**
 * Update Anggaran Diterima secara langsung
 */
function updateAnggaranDiterima(mysqli $koneksi): array
{
    $tanggal          = $_POST['tanggal'] ?? '';
    $anggaranDiterima = (float)($_POST['anggaran_diterima'] ?? 0);

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }
    if ($anggaranDiterima < 0) {
        return ['success' => false, 'message' => 'Anggaran diterima tidak boleh negatif'];
    }

    $stmt = $koneksi->prepare("SELECT pagu_belanja, pajak, nominal_koperasi, nominal_yayasan, nominal_helmi FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $paguBelanja = (float)$row['pagu_belanja'];
    $pajak       = (float)($row['pajak'] ?? 0);
    $nominalTiga = (float)$row['nominal_koperasi'] + (float)$row['nominal_yayasan'] + (float)$row['nominal_helmi'];

    $nominalManagement = $anggaranDiterima - $pajak - $paguBelanja - $nominalTiga;

    $stmt = $koneksi->prepare("UPDATE omset_sppg_harian SET biaya_admin = ?, nominal_management = ? WHERE tanggal = ?");
    $stmt->bind_param('dds', $anggaranDiterima, $nominalManagement, $tanggal);
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'anggaran_diterima' => $anggaranDiterima,
        'nominal_management' => $nominalManagement,
    ];
}

/**
 * Update Pajak secara langsung
 */
function updatePajak(mysqli $koneksi): array
{
    $tanggal = $_POST['tanggal'] ?? '';
    $pajak   = (float)($_POST['pajak'] ?? 0);

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }
    if ($pajak < 0) {
        return ['success' => false, 'message' => 'Pajak tidak boleh negatif'];
    }

    $stmt = $koneksi->prepare("SELECT biaya_admin, pagu_belanja, nominal_koperasi, nominal_yayasan, nominal_helmi FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $anggaranDiterima = (float)$row['biaya_admin'];
    $paguBelanja      = (float)$row['pagu_belanja'];
    $nominalTiga      = (float)$row['nominal_koperasi'] + (float)$row['nominal_yayasan'] + (float)$row['nominal_helmi'];

    $nominalManagement = $anggaranDiterima - $pajak - $paguBelanja - $nominalTiga;

    $stmt = $koneksi->prepare("UPDATE omset_sppg_harian SET pajak = ?, nominal_management = ? WHERE tanggal = ?");
    $stmt->bind_param('dds', $pajak, $nominalManagement, $tanggal);
    $stmt->execute();
    $stmt->close();

    return [
        'success' => true,
        'pajak' => $pajak,
        'nominal_management' => $nominalManagement,
    ];
}

/**
 * Update Pajak per minggu (membagi pajak ke hari-hari pada minggu tersebut)
 */
function updatePajakMingguan(mysqli $koneksi): array
{
    $tglMulai   = $_POST['tgl_mulai'] ?? '';
    $tglSelesai = $_POST['tgl_selesai'] ?? '';
    $pajakTotal = (float)($_POST['pajak'] ?? 0);

    if (!$tglMulai || !$tglSelesai) {
        return ['success' => false, 'message' => 'Rentang tanggal tidak valid'];
    }
    if ($pajakTotal < 0) {
        return ['success' => false, 'message' => 'Pajak tidak boleh negatif'];
    }

    $stmt = $koneksi->prepare("
        SELECT tanggal, biaya_admin, pagu_belanja, nominal_koperasi, nominal_yayasan, nominal_helmi 
        FROM omset_sppg_harian 
        WHERE tanggal BETWEEN ? AND ?
        ORDER BY tanggal ASC
    ");
    $stmt->bind_param('ss', $tglMulai, $tglSelesai);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();

    if (empty($rows)) {
        return ['success' => false, 'message' => 'Tidak ada data harian pada rentang minggu ini'];
    }

    $count = count($rows);
    $basePajak = floor($pajakTotal / $count);
    $remainder = $pajakTotal - ($basePajak * $count);

    $stmtUpdate = $koneksi->prepare("UPDATE omset_sppg_harian SET pajak = ?, nominal_management = ? WHERE tanggal = ?");

    foreach ($rows as $i => $row) {
        $pajakHari = $basePajak + ($i === 0 ? $remainder : 0);
        $anggaranDiterima = (float)$row['biaya_admin'];
        $paguBelanja      = (float)$row['pagu_belanja'];
        $nominalTiga      = (float)$row['nominal_koperasi'] + (float)$row['nominal_yayasan'] + (float)$row['nominal_helmi'];

        $nominalManagement = $anggaranDiterima - $pajakHari - $paguBelanja - $nominalTiga;

        $stmtUpdate->bind_param('dds', $pajakHari, $nominalManagement, $row['tanggal']);
        $stmtUpdate->execute();
    }
    $stmtUpdate->close();

    return [
        'success' => true,
        'message' => 'Pajak mingguan berhasil diperbarui'
    ];
}

function hitungNominal(array $keuntungan, int $totalKpm): array
{
    return [
        'koperasi'   => $keuntungan['koperasi']   * $totalKpm,
        'yayasan'    => $keuntungan['yayasan']    * $totalKpm,
        'helmi'      => $keuntungan['helmi']      * $totalKpm,
    ];
}

/**
 * Cek apakah ada bulan lalu yang datanya sudah lengkap tapi
 * belum direkap ke rekap_omset_bulanan. Kalau ada, agregat & simpan.
 */
function cekRolloverBulan(mysqli $koneksi): void
{
    try {
        $bulanIni = $koneksi->real_escape_string(date('Y-m'));

        $result = $koneksi->query("
            SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan
            FROM omset_sppg_harian
            WHERE DATE_FORMAT(tanggal, '%Y-%m') < '$bulanIni'
        ");
        if (!$result) return;

        $bulanLalu = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['bulan'])) {
                $bulanLalu[] = $row['bulan'];
            }
        }

        // Cek apakah tabel rekap_omset_bulanan ada
        $cekTabel = $koneksi->query("SHOW TABLES LIKE 'rekap_omset_bulanan'");
        if (!$cekTabel || $cekTabel->num_rows === 0) {
            return; // jika belum ada tabelnya, skip aman
        }

        foreach ($bulanLalu as $bulan) {
            $cek = $koneksi->prepare("SELECT COUNT(*) AS jml FROM rekap_omset_bulanan WHERE bulan = ?");
            if (!$cek) continue;
            $cek->bind_param('s', $bulan);
            $cek->execute();
            $resCek = $cek->get_result();
            $jml = $resCek ? (int)($resCek->fetch_assoc()['jml'] ?? 0) : 0;
            $cek->close();
            if ($jml > 0) continue; // sudah direkap

            $agg = $koneksi->prepare("
                SELECT
                    COUNT(*) AS jumlah_hari,
                    SUM(total_kpm) AS total_kpm,
                    SUM(total_anggaran) AS total_anggaran,
                    SUM(pagu_belanja) AS total_pagu_belanja,
                    SUM(nominal_koperasi) AS total_nominal_koperasi,
                    SUM(nominal_yayasan) AS total_nominal_yayasan,
                    SUM(nominal_helmi) AS total_nominal_helmi,
                    SUM(nominal_management) AS total_nominal_management
                FROM omset_sppg_harian
                WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?
            ");
            if (!$agg) continue;
            $agg->bind_param('s', $bulan);
            $agg->execute();
            $resAgg = $agg->get_result();
            $data = $resAgg ? $resAgg->fetch_assoc() : null;
            $agg->close();

            if (!$data) continue;

            $insert = $koneksi->prepare("
                INSERT INTO rekap_omset_bulanan
                    (bulan, jumlah_hari,
                     total_nominal_koperasi, total_nominal_yayasan, total_nominal_helmi, total_nominal_management)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");
            if (!$insert) continue;
            $insert->bind_param(
                'sidddd',
                $bulan,
                $data['jumlah_hari'],
                $data['total_nominal_koperasi'],
                $data['total_nominal_yayasan'],
                $data['total_nominal_helmi'],
                $data['total_nominal_management']
            );
            $insert->execute();
            $insert->close();
        }
    } catch (Throwable $e) {
        // abaikan jika terjadi error pada rollover
    }
}