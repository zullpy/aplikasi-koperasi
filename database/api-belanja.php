<?php
// Anti bocor
if (ob_get_level()) ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// ─── Session Guard (API-safe versi dari auth.php) ──────────────────────────
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: sesi tidak valid, silakan login ulang']);
    exit;
}

require_once 'koneksi.php';

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─── Role Guard ───────────────────────────────────────────────────────────
$userRole = $_SESSION['role'] ?? null;
if (!$userRole) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: role tidak ditemukan di sesi']);
    exit;
}

$approvalActions = ['approve', 'update_status', 'upload_bukti'];
if (in_array($action, $approvalActions, true)) {
    if ($userRole !== 'bendahara' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya bendahara yang dapat melakukan aksi ini']);
        exit;
    }
}

$isPurchase = ($userRole === 'purchase' || $userRole === 'purchase_stok');

if ($isPurchase) {
    if ($userRole === 'purchase_stok') {
        $purchaseAllowedActions = ['list', 'list_barang'];
    } else {
        $purchaseAllowedActions = ['list', 'list_barang', 'update_item_status', 'upload_nota', 'delete_nota'];
    }
    if (!in_array($action, $purchaseAllowedActions, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Akses ditolak: role purchase hanya dapat melakukan aksi: ' . implode(', ', $purchaseAllowedActions),
        ]);
        exit;
    }
}

// ─── Helper: Bukti Transfer (Multi-File) ───────────────────────────────────
function decodeBuktiList($raw)
{
    if ($raw === null) return [];
    $trimmed = trim((string)$raw);
    if ($trimmed === '') return [];

    if ($trimmed[0] === '[') {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
    }
    if (strpos($trimmed, ',') !== false) {
        return array_values(array_filter(array_map('trim', explode(',', $trimmed))));
    }
    return [$trimmed];
}

function encodeBuktiList($arr)
{
    $clean = array_values(array_filter($arr, function ($v) {
        return $v !== null && $v !== '';
    }));
    return json_encode($clean);
}

function normalizeUploadedFiles($filesField)
{
    if (!is_array($filesField) || !isset($filesField['name'])) return [];

    if (is_array($filesField['name'])) {
        $out = [];
        $count = count($filesField['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name'     => $filesField['name'][$i],
                'type'     => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error'    => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $filesField['size'][$i] ?? 0,
            ];
        }
        return $out;
    }

    if (($filesField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
    return [$filesField];
}

function uploadOneBuktiFile($file, $id, $index, $uploadDir)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Gagal upload file "' . ($file['name'] ?? '') . '" (kode error: ' . ($file['error'] ?? '?') . ')');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    if (!in_array($ext, $allowed)) {
        throw new Exception('Tipe file bukti transfer tidak diizinkan: ' . $file['name']);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file melebihi 5 MB: ' . $file['name']);
    }

    $fileName   = 'bukti_' . $id . '_' . time() . '_' . $index . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Gagal menyimpan file bukti transfer: ' . $file['name']);
    }
    compressImage($targetPath);
    return $fileName;
}

function ensureBuktiTransferColumnIsText($koneksi)
{
    $checkCol = $koneksi->query("SHOW COLUMNS FROM pengajuan_belanja LIKE 'bukti_transfer'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $colInfo = $checkCol->fetch_assoc();
        if (stripos($colInfo['Type'], 'text') === false) {
            @$koneksi->query("ALTER TABLE pengajuan_belanja MODIFY bukti_transfer TEXT NULL");
        }
    }
}

/**
 * Pastikan kolom biaya_admin ada di tabel detail_item_belanja. Kolom ini
 * sekarang disimpan PER BARANG (bukan per transaksi/header lagi), supaya
 * setiap item bisa punya biaya admin sendiri-sendiri. total biaya_admin
 * yang ditampilkan di header pengajuan_belanja dihitung sebagai SUM dari
 * seluruh item pada saat SAVE.
 */
function ensureBiayaAdminColumnExists($koneksi)
{
    $checkCol = $koneksi->query("SHOW COLUMNS FROM detail_item_belanja LIKE 'biaya_admin'");
    if (!$checkCol || $checkCol->num_rows === 0) {
        @$koneksi->query("ALTER TABLE detail_item_belanja ADD COLUMN biaya_admin DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER harga");
    }
}

try {
    switch ($action) {
        // ─── LIST: Ambil semua data belanja dengan detailnya ────────────────
        case 'list':
            $res = $koneksi->query("
                SELECT pb.*, a.username as created_by_name
                FROM pengajuan_belanja pb
                LEFT JOIN akun a ON pb.created_by = a.id
                ORDER BY pb.tanggal DESC, pb.id DESC
            ");
            if (!$res) {
                throw new Exception('Query error: ' . $koneksi->error);
            }
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $id = $row['id'];

                $row['bukti_transfer'] = decodeBuktiList($row['bukti_transfer'] ?? null);

                $stmtD = $koneksi->prepare("
                    SELECT d.*,
                    GROUP_CONCAT(n.file_path ORDER BY n.id ASC SEPARATOR '||') AS nota_urls_raw
                    FROM detail_item_belanja d
                    LEFT JOIN upload_nota n ON n.item_id = d.id AND n.pengajuan_id = d.pengajuan_id
                    WHERE d.pengajuan_id = ?
                    GROUP BY d.id
                    ORDER BY d.id ASC
                ");
                if (!$stmtD) {
                    throw new Exception('Prepare error: ' . $koneksi->error);
                }
                $stmtD->bind_param("i", $id);
                $stmtD->execute();
                $det = $stmtD->get_result();
                $items = [];
                while ($d = $det->fetch_assoc()) {
                    $d['nota_urls'] = $d['nota_urls_raw']
                        ? explode('||', $d['nota_urls_raw'])
                        : [];
                    unset($d['nota_urls_raw']);

                    if (!isset($d['status_beli']) || $d['status_beli'] === null) {
                        $d['status_beli'] = 'belum';
                    }
                    $items[] = $d;
                }
                $stmtD->close();
                $row['items'] = $items;
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

            // ─── LIST BARANG: Ambil estimasi harga ────────────────────────────
        case 'list_barang':
            // ✅ Auto-sync: Import barang yang ada di tabel `barang` tetapi belum masuk ke `estimasi_harga`
            @$koneksi->query("
                INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate)
                SELECT 
                    TRIM(b.nama_barang),
                    b.harga_beli,
                    COALESCE(NULLIF(TRIM(b.satuan), ''), 'Pcs'),
                    COALESCE(b.tanggal_terupdate_baru, CURDATE())
                FROM barang b
                LEFT JOIN estimasi_harga e ON LOWER(TRIM(e.nama_barang)) = LOWER(TRIM(b.nama_barang))
                WHERE e.id IS NULL AND b.nama_barang IS NOT NULL AND TRIM(b.nama_barang) != ''
            ");

            $res = $koneksi->query("
                SELECT id AS id_barang, nama_barang, harga_beli, satuan, tanggal_terupdate
                FROM estimasi_harga
                ORDER BY nama_barang ASC
            ");
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

            // ─── SAVE: Tambah atau Update ───────────────────────────────────────
        case 'save':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Akses ditolak: Hanya admin yang dapat membuat atau mengedit data belanja.'
                ]);
                exit;
            }
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) {
                throw new Exception('Data tidak valid: ' . json_last_error_msg());
            }

            ensureBiayaAdminColumnExists($koneksi);

            $idPengajuan = $data['id'] ?? null;
            $tanggal     = $data['tanggal'];
            $namaMenu    = trim($data['nama_menu']);
            $jumlahPorsi = isset($data['jumlah_porsi']) ? intval($data['jumlah_porsi']) : (isset($data['porsi']) ? intval($data['porsi']) : 0);
            $uangMasuk   = isset($data['uang_masuk']) ? floatval($data['uang_masuk']) : 0;
            $items       = $data['items'] ?? [];

            if (!$tanggal || !$namaMenu) {
                throw new Exception('Tanggal dan nama menu wajib diisi');
            }
            if (empty($items)) {
                throw new Exception('Minimal 1 barang harus ditambahkan');
            }

            // Hitung total belanja (rincian barang SAJA) dan total biaya
            // admin (SUM dari biaya_admin tiap item — biaya admin sekarang
            // diinput PER BARANG, bukan satu nilai global per transaksi lagi).
            // Biaya admin TETAP tidak dijumlahkan ke Total Estimasi/total_belanja,
            // hanya ditampilkan terpisah sebagai info.
            $totalBelanja = 0;
            $biayaAdminTotal = 0;
            foreach ($items as $it) {
                $totalBelanja += (floatval($it['harga']) * floatval($it['qty'])) + floatval($it['biaya_admin'] ?? 0);
                $biayaAdminTotal += floatval($it['biaya_admin'] ?? 0);
            }
            $sisaUang  = $uangMasuk - $totalBelanja;
            $status    = $data['status'] ?? 'pending';
            $createdBy = $data['created_by'] ?? 1;
            $keterangan = isset($data['keterangan']) ? trim($data['keterangan']) : null;

            $koneksi->begin_transaction();
            try {
                if ($idPengajuan) {
                    // UPDATE header pengajuan
                    $stmt = $koneksi->prepare("
                        UPDATE pengajuan_belanja
                        SET tanggal = ?,
                        nama_menu = ?,
                        jumlah_porsi = ?,
                        uang_masuk = ?,
                        total_belanja = ?,
                        biaya_admin = ?,
                        sisa_uang = ?,
                        status = ?,
                        keterangan = ?,
                        updated_at = NOW()
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare UPDATE error: ' . $koneksi->error);
                    }
                    $stmt->bind_param(
                        "ssiddddssi",
                        $tanggal,
                        $namaMenu,
                        $jumlahPorsi,
                        $uangMasuk,
                        $totalBelanja,
                        $biayaAdminTotal,
                        $sisaUang,
                        $status,
                        $keterangan,
                        $idPengajuan
                    );
                    if (!$stmt->execute()) {
                        throw new Exception('Execute UPDATE error: ' . $stmt->error);
                    }
                    $stmt->close();

                    $existingIds = [];
                    $resExisting = $koneksi->query("SELECT id FROM detail_item_belanja WHERE pengajuan_id = " . intval($idPengajuan));
                    if ($resExisting) {
                        while ($r = $resExisting->fetch_assoc()) {
                            $existingIds[] = intval($r['id']);
                        }
                    }

                    $keepIds = [];
                    foreach ($items as $it) {
                        $idDetail = !empty($it['id_detail']) ? intval($it['id_detail']) : null;
                        if ($idDetail && in_array($idDetail, $existingIds, true)) {
                            $keepIds[] = $idDetail;
                        }
                    }

                    $idsToDelete = array_diff($existingIds, $keepIds);
                    if (!empty($idsToDelete)) {
                        $idsToDeleteStr = implode(',', array_map('intval', $idsToDelete));

                        $resNotaHapus = $koneksi->query("SELECT file_path FROM upload_nota WHERE item_id IN ($idsToDeleteStr)");
                        if ($resNotaHapus) {
                            while ($n = $resNotaHapus->fetch_assoc()) {
                                $fp = $n['file_path'];
                                $abs = __DIR__ . '/' . ltrim($fp, './');
                                if (file_exists($abs)) @unlink($abs);
                                if (file_exists($fp)) @unlink($fp);
                            }
                        }
                        $koneksi->query("DELETE FROM upload_nota WHERE item_id IN ($idsToDeleteStr)");
                        $koneksi->query("DELETE FROM detail_item_belanja WHERE id IN ($idsToDeleteStr)");
                    }
                } else {
                    // INSERT header pengajuan baru
                    $stmt = $koneksi->prepare("
                        INSERT INTO pengajuan_belanja
                        (tanggal, nama_menu, jumlah_porsi, uang_masuk, total_belanja, biaya_admin, sisa_uang, status, keterangan, created_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare INSERT error: ' . $koneksi->error);
                    }
                    $stmt->bind_param(
                        "ssiddddssi",
                        $tanggal,
                        $namaMenu,
                        $jumlahPorsi,
                        $uangMasuk,
                        $totalBelanja,
                        $biayaAdminTotal,
                        $sisaUang,
                        $status,
                        $keterangan,
                        $createdBy
                    );
                    if (!$stmt->execute()) {
                        throw new Exception('Execute INSERT error: ' . $stmt->error);
                    }
                    $idPengajuan = $koneksi->insert_id;
                    $stmt->close();
                }

                // ── Upsert detail items: UPDATE baris lama (id_detail ada), INSERT baris baru ──
                foreach ($items as $it) {
                    $qty = floatval($it['qty'] ?? 0);
                    $biayaAdminItem = floatval($it['biaya_admin'] ?? 0);
                    $subtotal = (floatval($it['harga']) * $qty) + $biayaAdminItem;
                    $idBarang = !empty($it['id_barang']) ? intval($it['id_barang']) : null;
                    $namaBarang = $it['nama_barang'] ?? '';
                    $satuan = $it['satuan'] ?? '';
                    $harga = floatval($it['harga'] ?? 0);
                    $idDetail = !empty($it['id_detail']) ? intval($it['id_detail']) : null;

                    if ($idDetail && $idPengajuan) {
                        // Baris lama → UPDATE di tempat (id tetap sama, link nota tetap utuh)
                        $stmtD = $koneksi->prepare("
                            UPDATE detail_item_belanja
                            SET id_barang = ?, nama_barang = ?, qty = ?, satuan = ?, harga = ?, subtotal = ?, biaya_admin = ?
                            WHERE id = ? AND pengajuan_id = ?
                        ");
                        if (!$stmtD) {
                            throw new Exception('Prepare UPDATE detail error: ' . $koneksi->error);
                        }
                        $stmtD->bind_param(
                            "isdsddiii",
                            $idBarang,
                            $namaBarang,
                            $qty,
                            $satuan,
                            $harga,
                            $subtotal,
                            $biayaAdminItem,
                            $idDetail,
                            $idPengajuan
                        );
                        if (!$stmtD->execute()) {
                            throw new Exception('Execute UPDATE detail error: ' . $stmtD->error);
                        }
                        $stmtD->close();
                    } else {
                        // Baris baru → INSERT
                        $statusBendahara = 'pending';
                        $statusBeli = 'belum'; // default

                        $stmtD = $koneksi->prepare("
                            INSERT INTO detail_item_belanja
                            (pengajuan_id, id_barang, nama_barang, qty, satuan, harga, subtotal, biaya_admin, status_bendahara, status_beli)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if (!$stmtD) {
                            throw new Exception('Prepare INSERT detail error: ' . $koneksi->error);
                        }
                        $stmtD->bind_param(
                            "iisdsidsss",
                            $idPengajuan,
                            $idBarang,
                            $namaBarang,
                            $qty,
                            $satuan,
                            $harga,
                            $subtotal,
                            $biayaAdminItem,
                            $statusBendahara,
                            $statusBeli
                        );
                        if (!$stmtD->execute()) {
                            throw new Exception('Execute INSERT detail error: ' . $stmtD->error);
                        }
                        $stmtD->close();
                    }
                }

                $koneksi->commit();

                // ─── Sync estimasi_harga ──────────────────────────────────────
                foreach ($items as $it) {
                    $namaBarang = trim($it['nama_barang'] ?? '');
                    $hargaBeli  = floatval($it['harga'] ?? 0);
                    $satuan     = trim($it['satuan'] ?? '');
                    if (!$namaBarang || !$hargaBeli) continue;

                    $stmtCek = $koneksi->prepare(
                        "SELECT id FROM estimasi_harga WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM(?)) LIMIT 1"
                    );
                    if ($stmtCek) {
                        $stmtCek->bind_param('s', $namaBarang);
                        $stmtCek->execute();
                        $resCek = $stmtCek->get_result();
                        $rowCek = $resCek->fetch_assoc();
                        $stmtCek->close();

                        if ($rowCek) {
                            $stmtUpd = $koneksi->prepare(
                                "UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = CURDATE() WHERE id = ?"
                            );
                            if ($stmtUpd) {
                                $stmtUpd->bind_param('dsi', $hargaBeli, $satuan, $rowCek['id']);
                                $stmtUpd->execute();
                                $stmtUpd->close();
                            }
                        } else {
                            $stmtIns = $koneksi->prepare(
                                "INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES (?, ?, ?, CURDATE())"
                            );
                            if ($stmtIns) {
                                $stmtIns->bind_param('sds', $namaBarang, $hargaBeli, $satuan);
                                $stmtIns->execute();
                                $stmtIns->close();
                            }
                        }
                    }
                }
                // ─────────────────────────────────────────────────────────────

                echo json_encode([
                    'success' => true,
                    'message' => $idPengajuan ? 'Data berhasil diperbarui' : 'Data berhasil ditambahkan',
                    'id' => $idPengajuan
                ]);
            } catch (Exception $e) {
                $koneksi->rollback();
                throw $e;
            }
            exit;

        // ─── SAVE HEADER: Edit Informasi Transaksi Saja ──────────────────────
        case 'save_header':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya admin yang dapat mengedit informasi transaksi.']);
                exit;
            }
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception('Data tidak valid');

            $idPengajuan = intval($data['id'] ?? 0);
            $tanggal     = $data['tanggal'] ?? '';
            $namaMenu    = trim($data['nama_menu'] ?? '');
            $jumlahPorsi = isset($data['jumlah_porsi']) ? intval($data['jumlah_porsi']) : 0;
            $uangMasuk   = isset($data['uang_masuk']) ? floatval($data['uang_masuk']) : 0;
            $keterangan  = isset($data['keterangan']) ? trim($data['keterangan']) : null;

            if (!$idPengajuan || !$tanggal || !$namaMenu) {
                throw new Exception('Data tidak lengkap (ID, tanggal, dan nama menu wajib)');
            }

            $resSums = $koneksi->query("SELECT COALESCE(SUM((qty * harga) + biaya_admin), 0) AS total_belanja, COALESCE(SUM(biaya_admin), 0) AS biaya_admin FROM detail_item_belanja WHERE pengajuan_id = $idPengajuan");
            $sums = $resSums ? $resSums->fetch_assoc() : ['total_belanja' => 0, 'biaya_admin' => 0];
            $totalBelanja = floatval($sums['total_belanja']);
            $biayaAdminTotal = floatval($sums['biaya_admin']);
            $sisaUang = $uangMasuk - $totalBelanja;

            $stmt = $koneksi->prepare("
                UPDATE pengajuan_belanja
                SET tanggal = ?,
                    nama_menu = ?,
                    jumlah_porsi = ?,
                    uang_masuk = ?,
                    total_belanja = ?,
                    biaya_admin = ?,
                    sisa_uang = ?,
                    keterangan = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) throw new Exception('Prepare error: ' . $koneksi->error);
            $stmt->bind_param("ssiddddsi", $tanggal, $namaMenu, $jumlahPorsi, $uangMasuk, $totalBelanja, $biayaAdminTotal, $sisaUang, $keterangan, $idPengajuan);
            if (!$stmt->execute()) throw new Exception('Execute error: ' . $stmt->error);
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Informasi transaksi berhasil diperbarui']);
            exit;

        // ─── SAVE SINGLE ITEM: Tambah / Edit Barang Per Item ──────────────
        case 'save_single_item':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya admin yang dapat mengubah barang.']);
                exit;
            }
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception('Data tidak valid');

            ensureBiayaAdminColumnExists($koneksi);

            $idPengajuan = intval($data['pengajuan_id'] ?? 0);
            $idDetail    = !empty($data['id_detail']) ? intval($data['id_detail']) : null;
            $idBarang    = !empty($data['id_barang']) ? intval($data['id_barang']) : null;
            $namaBarang  = trim($data['nama_barang'] ?? '');
            $qty         = floatval($data['qty'] ?? 0);
            $satuan      = trim($data['satuan'] ?? '');
            $harga       = floatval($data['harga'] ?? 0);
            $biayaAdminItem = floatval($data['biaya_admin'] ?? 0);

            if (!$idPengajuan || !$namaBarang) {
                throw new Exception('Pengajuan ID dan nama barang wajib diisi');
            }

            $subtotal = ($harga * $qty) + $biayaAdminItem;

            $koneksi->begin_transaction();
            try {
                if ($idDetail) {
                    $stmt = $koneksi->prepare("
                        UPDATE detail_item_belanja
                        SET id_barang = ?, nama_barang = ?, qty = ?, satuan = ?, harga = ?, subtotal = ?, biaya_admin = ?
                        WHERE id = ? AND pengajuan_id = ?
                    ");
                    if (!$stmt) throw new Exception('Prepare UPDATE detail error: ' . $koneksi->error);
                    $stmt->bind_param("isdsddiii", $idBarang, $namaBarang, $qty, $satuan, $harga, $subtotal, $biayaAdminItem, $idDetail, $idPengajuan);
                    if (!$stmt->execute()) throw new Exception('Execute UPDATE detail error: ' . $stmt->error);
                    $stmt->close();
                } else {
                    $statusBendahara = 'pending';
                    $statusBeli = 'belum';
                    $stmt = $koneksi->prepare("
                        INSERT INTO detail_item_belanja
                        (pengajuan_id, id_barang, nama_barang, qty, satuan, harga, subtotal, biaya_admin, status_bendahara, status_beli)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmt) throw new Exception('Prepare INSERT detail error: ' . $koneksi->error);
                    $stmt->bind_param("iisdsidsss", $idPengajuan, $idBarang, $namaBarang, $qty, $satuan, $harga, $subtotal, $biayaAdminItem, $statusBendahara, $statusBeli);
                    if (!$stmt->execute()) throw new Exception('Execute INSERT detail error: ' . $stmt->error);
                    $stmt->close();
                }

                if (!empty($namaBarang)) {
                    if ($idBarang) {
                        $stmtUp = $koneksi->prepare("UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = NOW() WHERE id = ?");
                        if ($stmtUp) {
                            $stmtUp->bind_param('dsi', $harga, $satuan, $idBarang);
                            $stmtUp->execute();
                            $stmtUp->close();
                        }
                    } else {
                        $chkE = $koneksi->prepare("SELECT id FROM estimasi_harga WHERE LOWER(nama_barang) = LOWER(?) LIMIT 1");
                        if ($chkE) {
                            $chkE->bind_param('s', $namaBarang);
                            $chkE->execute();
                            $resE = $chkE->get_result();
                            if ($resE && $resE->num_rows > 0) {
                                $eRow = $resE->fetch_assoc();
                                $eId = intval($eRow['id']);
                                $chkE->close();
                                $stmtUp = $koneksi->prepare("UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = NOW() WHERE id = ?");
                                if ($stmtUp) {
                                    $stmtUp->bind_param('dsi', $harga, $satuan, $eId);
                                    $stmtUp->execute();
                                    $stmtUp->close();
                                }
                            } else {
                                $chkE->close();
                                $stmtIns = $koneksi->prepare("INSERT INTO estimasi_harga (nama_barang, harga_beli, satuan, tanggal_terupdate) VALUES (?, ?, ?, NOW())");
                                if ($stmtIns) {
                                    $stmtIns->bind_param('sds', $namaBarang, $harga, $satuan);
                                    $stmtIns->execute();
                                    $stmtIns->close();
                                }
                            }
                        }
                    }
                }

                $resSums = $koneksi->query("SELECT COALESCE(SUM((qty * harga) + biaya_admin), 0) AS total_belanja, COALESCE(SUM(biaya_admin), 0) AS biaya_admin FROM detail_item_belanja WHERE pengajuan_id = $idPengajuan");
                $sums = $resSums ? $resSums->fetch_assoc() : ['total_belanja' => 0, 'biaya_admin' => 0];
                $totalBelanja = floatval($sums['total_belanja']);
                $biayaAdminTotal = floatval($sums['biaya_admin']);

                $resH = $koneksi->query("SELECT uang_masuk FROM pengajuan_belanja WHERE id = $idPengajuan");
                $hRow = $resH ? $resH->fetch_assoc() : null;
                $uangMasuk = $hRow ? floatval($hRow['uang_masuk']) : 0;
                $sisaUang = $uangMasuk - $totalBelanja;

                $stmtH = $koneksi->prepare("UPDATE pengajuan_belanja SET total_belanja = ?, biaya_admin = ?, sisa_uang = ?, updated_at = NOW() WHERE id = ?");
                if ($stmtH) {
                    $stmtH->bind_param("dddi", $totalBelanja, $biayaAdminTotal, $sisaUang, $idPengajuan);
                    $stmtH->execute();
                    $stmtH->close();
                }

                $koneksi->commit();
                echo json_encode(['success' => true, 'message' => $idDetail ? 'Barang berhasil diperbarui' : 'Barang berhasil ditambahkan']);
            } catch (Exception $e) {
                $koneksi->rollback();
                throw $e;
            }
            exit;

        // ─── DELETE SINGLE ITEM: Hapus Barang Per Item ─────────────────────
        case 'delete_single_item':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya admin yang dapat menghapus barang.']);
                exit;
            }
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception('Data tidak valid');

            $idDetail = intval($data['id_detail'] ?? 0);
            $idPengajuan = intval($data['pengajuan_id'] ?? 0);

            if (!$idDetail || !$idPengajuan) {
                throw new Exception('ID barang dan ID pengajuan wajib diisi');
            }

            $koneksi->begin_transaction();
            try {
                $resNota = $koneksi->query("SELECT file_path FROM upload_nota WHERE item_id = $idDetail");
                if ($resNota) {
                    while ($nota = $resNota->fetch_assoc()) {
                        $filePath = $nota['file_path'];
                        $absPath = __DIR__ . '/' . ltrim($filePath, './');
                        if (file_exists($absPath)) @unlink($absPath);
                        if (file_exists($filePath)) @unlink($filePath);
                    }
                }
                $koneksi->query("DELETE FROM upload_nota WHERE item_id = $idDetail");
                $koneksi->query("DELETE FROM detail_item_belanja WHERE id = $idDetail AND pengajuan_id = $idPengajuan");

                $resSums = $koneksi->query("SELECT COALESCE(SUM((qty * harga) + biaya_admin), 0) AS total_belanja, COALESCE(SUM(biaya_admin), 0) AS biaya_admin FROM detail_item_belanja WHERE pengajuan_id = $idPengajuan");
                $sums = $resSums ? $resSums->fetch_assoc() : ['total_belanja' => 0, 'biaya_admin' => 0];
                $totalBelanja = floatval($sums['total_belanja']);
                $biayaAdminTotal = floatval($sums['biaya_admin']);

                $resH = $koneksi->query("SELECT uang_masuk FROM pengajuan_belanja WHERE id = $idPengajuan");
                $hRow = $resH ? $resH->fetch_assoc() : null;
                $uangMasuk = $hRow ? floatval($hRow['uang_masuk']) : 0;
                $sisaUang = $uangMasuk - $totalBelanja;

                $stmtH = $koneksi->prepare("UPDATE pengajuan_belanja SET total_belanja = ?, biaya_admin = ?, sisa_uang = ?, updated_at = NOW() WHERE id = ?");
                if ($stmtH) {
                    $stmtH->bind_param("dddi", $totalBelanja, $biayaAdminTotal, $sisaUang, $idPengajuan);
                    $stmtH->execute();
                    $stmtH->close();
                }

                $koneksi->commit();
                echo json_encode(['success' => true, 'message' => 'Barang berhasil dihapus']);
            } catch (Exception $e) {
                $koneksi->rollback();
                throw $e;
            }
            exit;

            // ─── DELETE: Hapus pengajuan beserta detail ─────────────────────────
        case 'delete':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Akses ditolak: Hanya admin yang dapat menghapus data belanja.'
                ]);
                exit;
            }
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $id = intval($data['id_pengajuan'] ?? $data['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID tidak valid');
            }

            $resNota = $koneksi->query("SELECT file_path FROM upload_nota WHERE pengajuan_id = $id");
            if ($resNota) {
                while ($nota = $resNota->fetch_assoc()) {
                    $filePath = $nota['file_path'];
                    $absPath = __DIR__ . '/' . ltrim($filePath, './');
                    if (file_exists($absPath)) {
                        unlink($absPath);
                    }
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            $koneksi->query("DELETE FROM upload_nota WHERE pengajuan_id = $id");
            $koneksi->query("DELETE FROM detail_item_belanja WHERE pengajuan_id = $id");
            $koneksi->query("DELETE FROM pengajuan_belanja WHERE id = $id");

            echo json_encode(['success' => true, 'message' => 'Data dihapus']);
            exit;

            // ─── UPDATE STATUS: Approval bendahara (per pengajuan) ──────────────
        case 'update_status':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $id      = $data['id'] ?? 0;
            $status  = $data['status'] ?? 'pending';
            $catatan = $data['catatan_bendahara'] ?? '';

            $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Status tidak valid');
            }

            $checkCol = $koneksi->query("SHOW COLUMNS FROM pengajuan_belanja LIKE 'catatan_bendahara'");
            if ($checkCol && $checkCol->num_rows > 0) {
                $stmt = $koneksi->prepare("
                    UPDATE pengajuan_belanja
                    SET status = ?, catatan_bendahara = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ssi", $status, $catatan, $id);
            } else {
                $stmt = $koneksi->prepare("
                    UPDATE pengajuan_belanja
                    SET status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $status, $id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate']);
            } else {
                throw new Exception('Gagal update: ' . $stmt->error);
            }
            $stmt->close();
            exit;

            // ─── UPDATE ITEM STATUS: Tombol "Sudah Dibeli" per item (BARU) ──────
        case 'update_item_status':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $id         = intval($data['id'] ?? 0);
            $statusBeli = $data['status_beli'] ?? 'sudah';

            if (!$id) {
                throw new Exception('Item ID tidak valid');
            }

            $validStatusBeli = ['belum', 'sudah'];
            if (!in_array($statusBeli, $validStatusBeli)) {
                throw new Exception('Status beli tidak valid');
            }

            $checkCol = $koneksi->query("SHOW COLUMNS FROM detail_item_belanja LIKE 'status_beli'");
            if (!$checkCol || $checkCol->num_rows === 0) {
                $koneksi->query("ALTER TABLE detail_item_belanja ADD COLUMN status_beli ENUM('belum','sudah') DEFAULT 'belum' AFTER status_bendahara");
            }

            $stmtItem = $koneksi->prepare("
                SELECT d.id_barang, d.nama_barang, d.qty, d.satuan, d.harga, d.status_beli, p.tanggal
                FROM detail_item_belanja d
                JOIN pengajuan_belanja p ON d.pengajuan_id = p.id
                WHERE d.id = ?
            ");
            if (!$stmtItem) {
                throw new Exception('Prepare select item error: ' . $koneksi->error);
            }
            $stmtItem->bind_param("i", $id);
            if (!$stmtItem->execute()) {
                throw new Exception('Execute select item error: ' . $stmtItem->error);
            }
            $resItem = $stmtItem->get_result();
            $itemRow = $resItem->fetch_assoc();
            $stmtItem->close();

            if (!$itemRow) {
                throw new Exception('Item tidak ditemukan');
            }

            $oldStatusBeli = $itemRow['status_beli'] ?? 'belum';

            $stmt = $koneksi->prepare("
                UPDATE detail_item_belanja
                SET status_beli = ?
                WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception('Prepare error: ' . $koneksi->error);
            }
            $stmt->bind_param("si", $statusBeli, $id);

            if ($stmt->execute()) {
                if ($userRole === 'purchase_stok' && $oldStatusBeli !== $statusBeli) {
                    $qty = floatval($itemRow['qty']);
                    $namaBarang = $itemRow['nama_barang'];
                    $satuan = $itemRow['satuan'];
                    $harga = floatval($itemRow['harga']);
                    $tanggal = $itemRow['tanggal'];

                    $namaBarangEsc = $koneksi->real_escape_string(trim($namaBarang));

                    $barangQuery = $koneksi->query("SELECT id_barang, stok_akhir FROM barang WHERE LOWER(TRIM(nama_barang)) = LOWER('$namaBarangEsc') LIMIT 1");

                    if ($statusBeli === 'sudah') {
                        if ($barangQuery && $barangQuery->num_rows > 0) {
                            $barangRow = $barangQuery->fetch_assoc();
                            $idBarang = intval($barangRow['id_barang']);
                            $stok_lama = floatval($barangRow['stok_akhir']);
                            $stok_baru = $stok_lama + $qty;

                            $stmtUpdStok = $koneksi->prepare("UPDATE barang SET stok_akhir = ? WHERE id_barang = ?");
                            $stmtUpdStok->bind_param("di", $stok_baru, $idBarang);
                            $stmtUpdStok->execute();
                            $stmtUpdStok->close();

                            $ket_mutasi = 'Belanja Harian (Sudah Dibeli)';
                            $stmtMutasi = $koneksi->prepare("
                                INSERT INTO mutasi_stok (id_barang, tanggal, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
                                VALUES (?, NOW(), 'masuk', ?, ?, ?, ?)
                            ");
                            $stmtMutasi->bind_param("iddds", $idBarang, $qty, $stok_lama, $stok_baru, $ket_mutasi);
                            $stmtMutasi->execute();
                            $stmtMutasi->close();
                        } else {
                            $insertBarangQuery = "
                                INSERT INTO barang (
                                    nama_barang, stok_akhir, harga_beli, satuan, tanggal_terupdate_baru
                                ) VALUES (
                                    ?, ?, ?, ?, ?
                                )
                            ";
                            $stmtInsBarang = $koneksi->prepare($insertBarangQuery);
                            $stmtInsBarang->bind_param("sddss", $namaBarang, $qty, $harga, $satuan, $tanggal);
                            $stmtInsBarang->execute();
                            $idBarang = $stmtInsBarang->insert_id;
                            $stmtInsBarang->close();

                            $ket_mutasi = 'Belanja Harian (Barang Baru)';
                            $stmtMutasi = $koneksi->prepare("
                                INSERT INTO mutasi_stok (id_barang, tanggal, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
                                VALUES (?, NOW(), 'masuk', ?, 0, ?, ?)
                            ");
                            $stmtMutasi->bind_param("idds", $idBarang, $qty, $qty, $ket_mutasi);
                            $stmtMutasi->execute();
                            $stmtMutasi->close();
                        }
                    } else if ($statusBeli === 'belum' && $oldStatusBeli === 'sudah') {
                        if ($barangQuery && $barangQuery->num_rows > 0) {
                            $barangRow = $barangQuery->fetch_assoc();
                            $idBarang = intval($barangRow['id_barang']);
                            $stok_lama = floatval($barangRow['stok_akhir']);
                            $stok_baru = max(0, $stok_lama - $qty);

                            $stmtUpdStok = $koneksi->prepare("UPDATE barang SET stok_akhir = ? WHERE id_barang = ?");
                            $stmtUpdStok->bind_param("di", $stok_baru, $idBarang);
                            $stmtUpdStok->execute();
                            $stmtUpdStok->close();

                            $ket_mutasi = 'Batal Belanja Harian (Batal Dibeli)';
                            $stmtMutasi = $koneksi->prepare("
                                INSERT INTO mutasi_stok (id_barang, tanggal, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
                                VALUES (?, NOW(), 'keluar', ?, ?, ?, ?)
                            ");
                            $stmtMutasi->bind_param("iddds", $idBarang, $qty, $stok_lama, $stok_baru, $ket_mutasi);
                            $stmtMutasi->execute();
                            $stmtMutasi->close();
                        }
                    }
                }

                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status beli berhasil diupdate'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Tidak ada perubahan (mungkin sudah pada status yang sama)'
                    ]);
                }
            } else {
                throw new Exception('Gagal update status beli: ' . $stmt->error);
            }
            $stmt->close();
            exit;

            // ─── UPDATE SALDO: Simpan/update saldo masuk per pengajuan ──────
        case 'update_saldo':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya admin yang dapat memperbarui saldo masuk']);
                exit;
            }
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $id        = intval($data['id'] ?? 0);
            $uangMasuk = floatval($data['uang_masuk'] ?? 0);

            if (!$id) throw new Exception('ID pengajuan tidak valid');

            $rowPb = $koneksi->query("SELECT total_belanja FROM pengajuan_belanja WHERE id = " . intval($id));
            if (!$rowPb || $rowPb->num_rows === 0) throw new Exception('Pengajuan tidak ditemukan');
            $pb = $rowPb->fetch_assoc();
            $totalBelanja = floatval($pb['total_belanja']);
            $sisaUang     = $uangMasuk - $totalBelanja;

            $stmt = $koneksi->prepare("
                UPDATE pengajuan_belanja
                SET uang_masuk = ?, sisa_uang = ?, updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) throw new Exception('Prepare update error: ' . $koneksi->error);
            $stmt->bind_param('ddi', $uangMasuk, $sisaUang, $id);
            if (!$stmt->execute()) throw new Exception('Execute update error: ' . $stmt->error);
            $stmt->close();

            echo json_encode([
                'success'    => true,
                'message'    => 'Saldo masuk berhasil diperbarui',
                'uang_masuk' => $uangMasuk,
                'sisa_uang'  => $sisaUang
            ]);
            exit;

            // ─── UPLOAD NOTA ────────────────────────────────────────────────────
        case 'upload_nota':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $itemId      = $_POST['item_id'] ?? null;
            $pengajuanId = $_POST['pengajuan_id'] ?? null;

            if (!$itemId || !$pengajuanId) {
                throw new Exception('Item ID dan Pengajuan ID wajib diisi');
            }
            if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
                throw new Exception('Tidak ada file yang diupload');
            }

            $uploadDir = '../uploads/nota/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadedFiles = [];
            $files = $_FILES['files'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error upload file: ' . $files['error'][$i]);
                }

                $fileName   = time() . '_' . $i . '_' . basename($files['name'][$i]);
                $targetPath = $uploadDir . $fileName;
                $fileType   = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, dan PDF');
                }
                if ($files['size'][$i] > 5 * 1024 * 1024) {
                    throw new Exception('Ukuran file melebihi 5MB');
                }

                if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                    compressImage($targetPath);
                    $filePath = $uploadDir . $fileName;
                    $stmt = $koneksi->prepare("
                        INSERT INTO upload_nota
                        (pengajuan_id, item_id, file_path, uploaded_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare INSERT error: ' . $koneksi->error);
                    }
                    $stmt->bind_param("iis", $pengajuanId, $itemId, $filePath);
                    if (!$stmt->execute()) {
                        throw new Exception('Execute INSERT error: ' . $stmt->error);
                    }
                    $stmt->close();

                    $uploadedFiles[] = [
                        'file_path' => $filePath,
                        'file_name' => $files['name'][$i]
                    ];
                } else {
                    throw new Exception('Gagal mengupload file');
                }
            }

            echo json_encode([
                'success' => true,
                'message' => count($uploadedFiles) . ' nota berhasil diunggah',
                'files'   => $uploadedFiles
            ]);
            exit;

        // ─── DELETE NOTA: Hapus nota fisik dan data nota ────────────────────
        case 'delete_nota':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $filePath = trim($data['file_path'] ?? '');

            if (!$filePath) {
                throw new Exception('File path nota tidak boleh kosong');
            }

            // Normalisasi & hapus file fisik dari filesystem
            $absPath1 = __DIR__ . '/' . ltrim($filePath, './');
            $absPath2 = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($filePath, './');
            $absPath3 = $filePath;
            $realPath = realpath(__DIR__ . '/' . $filePath);

            if (file_exists($absPath1)) @unlink($absPath1);
            if (file_exists($absPath2)) @unlink($absPath2);
            if (file_exists($absPath3)) @unlink($absPath3);
            if ($realPath && file_exists($realPath)) @unlink($realPath);

            // Hapus dari tabel upload_nota
            $stmt = $koneksi->prepare("DELETE FROM upload_nota WHERE file_path = ?");
            if (!$stmt) {
                throw new Exception('Prepare DELETE error: ' . $koneksi->error);
            }
            $stmt->bind_param("s", $filePath);
            if (!$stmt->execute()) {
                throw new Exception('Execute DELETE error: ' . $stmt->error);
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Nota berhasil dihapus'
            ]);
            exit;

            // ─── GET TTD: Ambil semua tanda tangan berdasarkan IDs pengajuan ─────
        case 'get_ttd':
            $idsRaw = $_GET['ids'] ?? '';
            if (!$idsRaw) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $idsClean = preg_replace('/[^0-9,]/', '', $idsRaw);
            $idsArr   = array_filter(array_map('intval', explode(',', $idsClean)));
            if (empty($idsArr)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $inClause = implode(',', $idsArr);

            $checkTbl = $koneksi->query("SHOW TABLES LIKE 'tanda_tangan_digital'");
            if (!$checkTbl || $checkTbl->num_rows === 0) {
                $koneksi->query("
                    CREATE TABLE tanda_tangan_digital (
                        id            INT AUTO_INCREMENT PRIMARY KEY,
                        pengajuan_id  INT NOT NULL,
                        role_penanda  ENUM('bendahara','purchase','ketua') NOT NULL,
                        user_id       INT DEFAULT 0,
                        signature_data LONGTEXT NOT NULL,
                        nama          VARCHAR(100) DEFAULT NULL,
                        timestamp     DATETIME DEFAULT CURRENT_TIMESTAMP,
                        update_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_pengajuan_role (pengajuan_id, role_penanda)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }

            $res = $koneksi->query("
                SELECT pengajuan_id, role_penanda, signature_data,
                timestamp
                FROM tanda_tangan_digital
                WHERE pengajuan_id IN ($inClause)
                ORDER BY pengajuan_id, timestamp DESC
            ");
            if (!$res) throw new Exception('Query get_ttd error: ' . $koneksi->error);

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

            // ─── SAVE TTD: Simpan / update tanda tangan ke DB ──────────────────
        case 'save_ttd':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception('Data tidak valid: ' . json_last_error_msg());

            $pengajuanId   = intval($data['pengajuan_id'] ?? 0);
            $rolePenanda   = $data['role_penanda'] ?? '';
            $signatureData = $data['signature_data'] ?? '';
            $nama          = $data['nama'] ?? null;
            $userId        = intval($data['user_id'] ?? 0);

            if (!$pengajuanId) throw new Exception('pengajuan_id tidak valid');
            if (!$rolePenanda) throw new Exception('role_penanda wajib diisi');
            if (!$signatureData) throw new Exception('signature_data kosong');

            $validRoles = ['bendahara', 'purchase', 'ketua'];
            if (!in_array($rolePenanda, $validRoles)) {
                throw new Exception('role_penanda tidak valid: ' . $rolePenanda);
            }
            $actualRole = ($userRole === 'purchase_stok') ? 'purchase' : $userRole;
            if ($userRole !== 'admin' && $rolePenanda !== $actualRole) {
                throw new Exception('Anda hanya dapat menandatangani sebagai role Anda sendiri (' . $userRole . ')');
            }

            $checkTbl = $koneksi->query("SHOW TABLES LIKE 'tanda_tangan_digital'");
            if (!$checkTbl || $checkTbl->num_rows === 0) {
                $koneksi->query("
                    CREATE TABLE tanda_tangan_digital (
                        id            INT AUTO_INCREMENT PRIMARY KEY,
                        pengajuan_id  INT NOT NULL,
                        role_penanda  ENUM('bendahara','purchase','ketua') NOT NULL,
                        user_id       INT DEFAULT 0,
                        signature_data LONGTEXT NOT NULL,
                        nama          VARCHAR(100) DEFAULT NULL,
                        timestamp     DATETIME DEFAULT CURRENT_TIMESTAMP,
                        update_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_pengajuan_role (pengajuan_id, role_penanda)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }

            $stmt = $koneksi->prepare("
                INSERT INTO tanda_tangan_digital
                (pengajuan_id, role_penanda, user_id, signature_data)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                signature_data = VALUES(signature_data),
                user_id        = VALUES(user_id)
            ");
            if (!$stmt) throw new Exception('Prepare save_ttd error: ' . $koneksi->error);
            $stmt->bind_param('isis', $pengajuanId, $rolePenanda, $userId, $signatureData);
            if (!$stmt->execute()) throw new Exception('Execute save_ttd error: ' . $stmt->error);
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'pengajuan_id' => $pengajuanId,
                'role_penanda' => $rolePenanda,
            ]);
            exit;

            // ─── UPLOAD BUKTI: Unggah bukti transfer langsung dari card ───────
        case 'upload_bukti':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID pengajuan tidak valid');

            $filesToUpload = normalizeUploadedFiles($_FILES['bukti_transfer'] ?? null);
            if (empty($filesToUpload)) {
                throw new Exception('File bukti transfer wajib diunggah');
            }

            ensureBuktiTransferColumnIsText($koneksi);

            $uploadDir = '../uploads/bukti_transfer/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

            $rowPb = $koneksi->query("SELECT bukti_transfer FROM pengajuan_belanja WHERE id = " . intval($id));
            if (!$rowPb || $rowPb->num_rows === 0) throw new Exception('Pengajuan tidak ditemukan');
            $pbRow = $rowPb->fetch_assoc();
            $existingList = decodeBuktiList($pbRow['bukti_transfer'] ?? null);

            $newFileNames = [];
            foreach ($filesToUpload as $idx => $file) {
                $newFileNames[] = uploadOneBuktiFile($file, $id, $idx, $uploadDir);
            }

            $finalList = array_merge($existingList, $newFileNames);
            $buktiJson = encodeBuktiList($finalList);

            $stmt = $koneksi->prepare("UPDATE pengajuan_belanja SET bukti_transfer = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $buktiJson, $id);
            if (!$stmt->execute()) throw new Exception('Gagal update bukti transfer: ' . $stmt->error);
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => count($newFileNames) . ' bukti transfer berhasil diunggah',
                'bukti_transfer' => $finalList
            ]);
            exit;

            // ─── APPROVE: Setujui pengajuan + simpan uang masuk + bukti TF ─────
        case 'approve':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $id        = intval($_POST['id'] ?? 0);
            $uangMasuk = floatval($_POST['uang_masuk'] ?? 0);

            if (!$id)        throw new Exception('ID pengajuan tidak valid');
            if (!$uangMasuk) throw new Exception('Saldo / uang masuk wajib diisi');

            ensureBuktiTransferColumnIsText($koneksi);

            $rowPb = $koneksi->query("SELECT total_belanja, bukti_transfer FROM pengajuan_belanja WHERE id = " . intval($id));
            if (!$rowPb || $rowPb->num_rows === 0) throw new Exception('Pengajuan tidak ditemukan');
            $pb        = $rowPb->fetch_assoc();
            $totalBelanja = floatval($pb['total_belanja']);
            $sisaUang     = $uangMasuk - $totalBelanja;
            $existingList = decodeBuktiList($pb['bukti_transfer'] ?? null);

            $filesToUpload = normalizeUploadedFiles($_FILES['bukti_transfer'] ?? null);
            $newFileNames = [];
            if (!empty($filesToUpload)) {
                $uploadDir = '../uploads/bukti_transfer/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                foreach ($filesToUpload as $idx => $file) {
                    $newFileNames[] = uploadOneBuktiFile($file, $id, $idx, $uploadDir);
                }
            }

            $finalList = array_merge($existingList, $newFileNames);
            $buktiJson = !empty($finalList) ? encodeBuktiList($finalList) : null;

            if ($buktiJson !== null) {
                $stmt = $koneksi->prepare("
                    UPDATE pengajuan_belanja
                    SET status           = 'approved',
                    uang_masuk       = ?,
                    sisa_uang        = ?,
                    bukti_transfer   = ?,
                    updated_at       = NOW()
                    WHERE id = ?
                ");
                if (!$stmt) throw new Exception('Prepare approve error: ' . $koneksi->error);
                $stmt->bind_param('ddsi', $uangMasuk, $sisaUang, $buktiJson, $id);
            } else {
                $stmt = $koneksi->prepare("
                    UPDATE pengajuan_belanja
                    SET status     = 'approved',
                    uang_masuk = ?,
                    sisa_uang  = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                if (!$stmt) throw new Exception('Prepare approve (no file) error: ' . $koneksi->error);
                $stmt->bind_param('ddi', $uangMasuk, $sisaUang, $id);
            }

            if (!$stmt->execute()) throw new Exception('Execute approve error: ' . $stmt->error);
            $stmt->close();

            echo json_encode([
                'success'        => true,
                'message'        => 'Pengajuan berhasil disetujui',
                'uang_masuk'     => $uangMasuk,
                'sisa_uang'      => $sisaUang,
                'bukti_transfer' => $finalList,
            ]);
            exit;

        default:
            throw new Exception('Action tidak dikenali: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
    exit;
}