<?php
session_start();
header('Content-Type: application/json');
require_once 'koneksi.php'; // expose $koneksi (mysqli) -> db_draft_barang. Sesuaikan path kalau beda.

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

        case 'simpan_harian':
            if (($_SESSION['role'] ?? '') !== 'bendahara') {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara yang dapat mengubah data']);
                exit;
            }
            echo json_encode(simpanHarian($koneksi));
            break;

        case 'update_keuntungan':
            if (($_SESSION['role'] ?? '') !== 'bendahara') {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateKeuntungan($koneksi));
            break;



        case 'update_belanja_foodcost':
            if (($_SESSION['role'] ?? '') !== 'bendahara') {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara yang dapat mengubah data']);
                exit;
            }
            echo json_encode(updateBelanjaFoodcost($koneksi));
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
    }
} catch (Throwable $e) {
    http_response_code(500);
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
    $bulanFilter = $_GET['bulan'] ?? $_POST['bulan'] ?? date('Y-m');

    $stmt = $koneksi->prepare("
        SELECT * FROM omset_sppg_harian
        WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?
        ORDER BY tanggal ASC
    ");
    $stmt->bind_param('s', $bulanFilter);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // hitung total (footer tabel)
    $total = [
        'total_anggaran' => 0,
        'total_kpm' => 0,
        'pagu_belanja' => 0,
        'nominal_koperasi' => 0,
        'nominal_yayasan' => 0,
        'nominal_helmi' => 0,
        'nominal_management' => 0,
    ];
    foreach ($rows as $r) {
        $total['total_anggaran']      += (float)$r['total_anggaran'];
        $total['total_kpm']           += (int)$r['total_kpm'];
        $total['pagu_belanja']        += (float)$r['pagu_belanja'];
        $total['nominal_koperasi']    += (float)$r['nominal_koperasi'];
        $total['nominal_yayasan']     += (float)$r['nominal_yayasan'];
        $total['nominal_helmi']       += (float)$r['nominal_helmi'];
        $total['nominal_management']  += (float)$r['nominal_management'];
    }

    // Ambil daftar bulan yang tersedia datanya di tabel harian untuk filter dropdown
    $listBulan = [];
    $resBulan = $koneksi->query("SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') AS bulan_val FROM omset_sppg_harian ORDER BY bulan_val DESC");
    if ($resBulan) {
        $listBulan = array_column($resBulan->fetch_all(MYSQLI_ASSOC), 'bulan_val');
    }
    // Pastikan bulan berjalan selalu masuk di list_bulan sekalipun datanya belum diinput hari ini
    $bulanIni = date('Y-m');
    if (!in_array($bulanIni, $listBulan, true)) {
        array_unshift($listBulan, $bulanIni);
    }

    return [
        'success' => true,
        'bulan' => $bulanFilter,
        'harga_porsi_besar' => HARGA_PORSI_BESAR,
        'harga_porsi_kecil' => HARGA_PORSI_KECIL,
        'rows' => $rows,
        'total' => $total,
        'list_bulan' => $listBulan,
        'sudah_input_hari_ini' => cekSudahInputHariIni($koneksi),
    ];
}

function cekSudahInputHariIni(mysqli $koneksi): bool
{
    $result = $koneksi->query("SELECT COUNT(*) AS jml FROM omset_sppg_harian WHERE tanggal = CURDATE()");
    $row = $result->fetch_assoc();
    return ((int)$row['jml']) > 0;
}

/**
 * Simpan input KPM besar & kecil untuk HARI INI.
 * Kalau baris hari ini sudah ada, di-update (bukan double insert).
 */
function simpanHarian(mysqli $koneksi): array
{
    $kpmBesar = (int)($_POST['kpm_besar'] ?? 0);
    $kpmKecil = (int)($_POST['kpm_kecil'] ?? 0);

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

    // cek baris hari ini, kalau sudah ada -> ambil keuntungan yg sudah diinput sebelumnya
    // supaya nominal 4 kategori ikut recompute mengikuti KPM baru
    $result = $koneksi->query("SELECT * FROM omset_sppg_harian WHERE tanggal = CURDATE()");
    $existing = $result->fetch_assoc();

    if ($existing) {
        $keuntungan = [
            'koperasi'   => (float)$existing['keuntungan_koperasi'],
            'yayasan'    => (float)$existing['keuntungan_yayasan'],
            'helmi'      => (float)$existing['keuntungan_helmi'],
        ];
        $paguBelanja = (float)$existing['pagu_belanja'];
    } else {
        $keuntungan = ['koperasi' => 0, 'yayasan' => 0, 'helmi' => 0];
        $paguBelanja = 0.0;
    }

    $nominal = hitungNominal($keuntungan, $totalKpm);
    $nominalManagement = $totalAnggaran - $paguBelanja - array_sum($nominal);

    $stmt = $koneksi->prepare("
        INSERT INTO omset_sppg_harian
            (tanggal, kpm_besar, kpm_kecil, anggaran_besar, anggaran_kecil,
             total_anggaran, total_kpm,
             keuntungan_koperasi, nominal_koperasi,
             keuntungan_yayasan, nominal_yayasan,
             keuntungan_helmi, nominal_helmi,
             keuntungan_management, nominal_management,
             pagu_belanja)
        VALUES
            (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            kpm_besar = VALUES(kpm_besar),
            kpm_kecil = VALUES(kpm_kecil),
            anggaran_besar = VALUES(anggaran_besar),
            anggaran_kecil = VALUES(anggaran_kecil),
            total_anggaran = VALUES(total_anggaran),
            total_kpm = VALUES(total_kpm),
            nominal_koperasi = VALUES(nominal_koperasi),
            nominal_yayasan = VALUES(nominal_yayasan),
            nominal_helmi = VALUES(nominal_helmi),
            nominal_management = VALUES(nominal_management),
            pagu_belanja = VALUES(pagu_belanja)
    ");

    $keuntunganManagement = 0.0;

    $stmt->bind_param(
        'iidddiddddddddd',
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
        $paguBelanja
    );
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Data omset hari ini tersimpan'];
}

/**
 * Update keuntungan (rate per-KPM) salah satu dari 4 kategori
 * untuk satu baris tanggal tertentu, lalu recompute nominal & pagu belanja.
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
    $paguBelanja = (float)$row['pagu_belanja'];
    $nominalManagement = (float)$row['total_anggaran'] - $paguBelanja - array_sum($nominal);

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
 * Update Belanja Foodcost (pagu_belanja) secara langsung dan hitung otomatis nominal management.
 */
function updateBelanjaFoodcost(mysqli $koneksi): array
{
    $tanggal = $_POST['tanggal'] ?? '';
    $belanja = (float)($_POST['belanja'] ?? 0);

    if (!$tanggal) {
        return ['success' => false, 'message' => 'Tanggal wajib diisi'];
    }

    $stmt = $koneksi->prepare("SELECT total_anggaran, nominal_koperasi, nominal_yayasan, nominal_helmi FROM omset_sppg_harian WHERE tanggal = ?");
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Data tanggal tersebut tidak ditemukan'];
    }

    $nominalManagement = (float)$row['total_anggaran'] - $belanja - ((float)$row['nominal_koperasi'] + (float)$row['nominal_yayasan'] + (float)$row['nominal_helmi']);

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

function hitungNominal(array $keuntungan, int $totalKpm): array
{
    return [
        'koperasi'   => $keuntungan['koperasi']   * $totalKpm,
        'yayasan'    => $keuntungan['yayasan']    * $totalKpm,
        'helmi'      => $keuntungan['helmi']      * $totalKpm,
        // management TIDAK dihitung di sini — diinput langsung
    ];
}

/**
 * Cek apakah ada bulan lalu yang datanya sudah lengkap tapi
 * belum direkap ke rekap_omset_bulanan. Kalau ada, agregat & simpan.
 * Dipanggil setiap kali halaman dibuka (get_bulan) sehingga rollover
 * terjadi otomatis begitu bulan baru mulai berjalan.
 */
function cekRolloverBulan(mysqli $koneksi): void
{
    $bulanIni = $koneksi->real_escape_string(date('Y-m'));

    $result = $koneksi->query("
        SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan
        FROM omset_sppg_harian
        GROUP BY bulan
        HAVING bulan < '$bulanIni'
    ");
    $bulanLalu = array_column($result->fetch_all(MYSQLI_ASSOC), 'bulan');

    foreach ($bulanLalu as $bulan) {
        $cek = $koneksi->prepare("SELECT COUNT(*) AS jml FROM rekap_omset_bulanan WHERE bulan = ?");
        $cek->bind_param('s', $bulan);
        $cek->execute();
        $jml = (int)$cek->get_result()->fetch_assoc()['jml'];
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
        $agg->bind_param('s', $bulan);
        $agg->execute();
        $data = $agg->get_result()->fetch_assoc();
        $agg->close();

        $insert = $koneksi->prepare("
            INSERT INTO rekap_omset_bulanan
                (bulan, jumlah_hari,
                 total_nominal_koperasi, total_nominal_yayasan, total_nominal_helmi, total_nominal_management)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");
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
}