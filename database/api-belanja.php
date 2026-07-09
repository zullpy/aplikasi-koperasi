<?php
// Anti bocor
if (ob_get_level()) ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// ─── Session Guard (API-safe versi dari auth.php) ──────────────────────────
// TIDAK pakai require_once 'auth.php' langsung karena auth.php melakukan
// header("Location: ../") saat gagal — itu cocok untuk halaman biasa, tapi
// untuk endpoint JSON ini harus balas JSON 401, bukan redirect (redirect
// bikin fetch() di JS menerima HTML, bukan JSON, dan gagal di-parse).
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
$isPurchase = ($userRole === 'purchase');

// ─── Purchase Whitelist Guard ──────────────────────────────────────────────
// PENDEKATAN WHITELIST (bukan blacklist) — lebih aman:
// Definisikan HANYA aksi yang BOLEH dilakukan role purchase.
// Semua aksi di luar daftar ini otomatis ditolak 403, termasuk aksi baru
// yang mungkin ditambahkan di masa depan (save_ttd, get_ttd, dll.).
if ($isPurchase) {
    $purchaseAllowedActions = ['list', 'list_barang', 'update_item_status', 'upload_nota'];
    if (!in_array($action, $purchaseAllowedActions, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Akses ditolak: role purchase hanya dapat melakukan aksi: ' . implode(', ', $purchaseAllowedActions),
        ]);
        exit;
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

                // Ambil detail items + nota urls + status_beli
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
                    // Pecah nota_urls_raw jadi array
                    $d['nota_urls'] = $d['nota_urls_raw']
                        ? explode('||', $d['nota_urls_raw'])
                        : [];
                    unset($d['nota_urls_raw']);

                    // Pastikan status_beli ada (default 'belum')
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

            // Hitung total belanja
            $totalBelanja = 0;
            foreach ($items as $it) {
                $totalBelanja += (floatval($it['harga']) * intval($it['qty']));
            }
            $sisaUang  = $uangMasuk - $totalBelanja;
            $status    = $data['status'] ?? 'pending';
            $createdBy = $data['created_by'] ?? 1;

            $koneksi->begin_transaction();
            try {
                if ($idPengajuan) {
                    // UPDATE
                    $stmt = $koneksi->prepare("
                        UPDATE pengajuan_belanja
                        SET tanggal = ?,
                        nama_menu = ?,
                        jumlah_porsi = ?,
                        uang_masuk = ?,
                        total_belanja = ?,
                        sisa_uang = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare UPDATE error: ' . $koneksi->error);
                    }
                    $stmt->bind_param(
                        "ssidsisi",
                        $tanggal,
                        $namaMenu,
                        $jumlahPorsi,
                        $uangMasuk,
                        $totalBelanja,
                        $sisaUang,
                        $status,
                        $idPengajuan
                    );
                    if (!$stmt->execute()) {
                        throw new Exception('Execute UPDATE error: ' . $stmt->error);
                    }
                    $stmt->close();

                    // Hapus detail lama
                    $koneksi->query("DELETE FROM detail_item_belanja WHERE pengajuan_id = $idPengajuan");
                } else {
                    // INSERT
                    $stmt = $koneksi->prepare("
                        INSERT INTO pengajuan_belanja
                        (tanggal, nama_menu, jumlah_porsi, uang_masuk, total_belanja, sisa_uang, status, created_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare INSERT error: ' . $koneksi->error);
                    }
                    $stmt->bind_param(
                        "ssidsisi",
                        $tanggal,
                        $namaMenu,
                        $jumlahPorsi,
                        $uangMasuk,
                        $totalBelanja,
                        $sisaUang,
                        $status,
                        $createdBy
                    );
                    if (!$stmt->execute()) {
                        throw new Exception('Execute INSERT error: ' . $stmt->error);
                    }
                    $idPengajuan = $koneksi->insert_id;
                    $stmt->close();
                }

                // Insert detail items
                foreach ($items as $it) {
                    $subtotal = floatval($it['harga']) * intval($it['qty']);
                    $idBarang = !empty($it['id_barang']) ? intval($it['id_barang']) : null;
                    $namaBarang = $it['nama_barang'] ?? '';
                    $qty = intval($it['qty'] ?? 0);
                    $satuan = $it['satuan'] ?? '';
                    $harga = floatval($it['harga'] ?? 0);
                    $statusBendahara = 'pending';
                    $statusBeli = 'belum'; // default

                    $stmtD = $koneksi->prepare("
                        INSERT INTO detail_item_belanja
                        (pengajuan_id, id_barang, nama_barang, qty, satuan, harga, subtotal, status_bendahara, status_beli)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmtD) {
                        throw new Exception('Prepare INSERT detail error: ' . $koneksi->error);
                    }
                    $stmtD->bind_param(
                        "iisisisss",
                        $idPengajuan,
                        $idBarang,
                        $namaBarang,
                        $qty,
                        $satuan,
                        $harga,
                        $subtotal,
                        $statusBendahara,
                        $statusBeli
                    );
                    if (!$stmtD->execute()) {
                        throw new Exception('Execute INSERT detail error: ' . $stmtD->error);
                    }
                    $stmtD->close();
                }

                $koneksi->commit();

                // ─── Sync estimasi_harga ──────────────────────────────────────
                // UPDATE harga jika barang sudah ada, INSERT jika barang baru
                foreach ($items as $it) {
                    $namaBarang = trim($it['nama_barang'] ?? '');
                    $hargaBeli  = floatval($it['harga'] ?? 0);
                    $satuan     = trim($it['satuan'] ?? '');
                    if (!$namaBarang || !$hargaBeli) continue;

                    // Cek apakah sudah ada di estimasi_harga (exact match case-insensitive)
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
                            // Sudah ada → UPDATE harga & tanggal
                            $stmtUpd = $koneksi->prepare(
                                "UPDATE estimasi_harga SET harga_beli = ?, satuan = ?, tanggal_terupdate = CURDATE() WHERE id = ?"
                            );
                            if ($stmtUpd) {
                                $stmtUpd->bind_param('dsi', $hargaBeli, $satuan, $rowCek['id']);
                                $stmtUpd->execute();
                                $stmtUpd->close();
                            }
                        } else {
                            // Belum ada → INSERT baru
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

            // Ambil semua file nota yang terkait pengajuan ini
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

            // Hapus record upload_nota
            $koneksi->query("DELETE FROM upload_nota WHERE pengajuan_id = $id");
            // Hapus detail items
            $koneksi->query("DELETE FROM detail_item_belanja WHERE pengajuan_id = $id");
            // Hapus pengajuan
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

            // Cek apakah kolom catatan_bendahara ada
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

            // Pastikan kolom status_beli ada
            $checkCol = $koneksi->query("SHOW COLUMNS FROM detail_item_belanja LIKE 'status_beli'");
            if (!$checkCol || $checkCol->num_rows === 0) {
                // Tambahkan kolom jika belum ada
                $koneksi->query("ALTER TABLE detail_item_belanja ADD COLUMN status_beli ENUM('belum','sudah') DEFAULT 'belum' AFTER status_bendahara");
            }

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

            // ─── GET TTD: Ambil semua tanda tangan berdasarkan IDs pengajuan ─────
        case 'get_ttd':
            $idsRaw = $_GET['ids'] ?? '';
            if (!$idsRaw) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            // Sanitasi: hanya angka dan koma
            $idsClean = preg_replace('/[^0-9,]/', '', $idsRaw);
            $idsArr   = array_filter(array_map('intval', explode(',', $idsClean)));
            if (empty($idsArr)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $inClause = implode(',', $idsArr);

            // Pastikan tabel tanda_tangan_digital ada
            $checkTbl = $koneksi->query("SHOW TABLES LIKE 'tanda_tangan_digital'");
            if (!$checkTbl || $checkTbl->num_rows === 0) {
                // Buat tabel jika belum ada
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
            $userId        = intval($data['user_id'] ?? 0); // OPSIONAL: default 0 jika tidak dikirim

            if (!$pengajuanId) throw new Exception('pengajuan_id tidak valid');
            if (!$rolePenanda) throw new Exception('role_penanda wajib diisi');
            if (!$signatureData) throw new Exception('signature_data kosong');
            // HAPUS VALIDASI KETAT: if (!$userId) throw new Exception('user_id wajib diisi');

            $validRoles = ['bendahara', 'purchase', 'ketua'];
            if (!in_array($rolePenanda, $validRoles)) {
                throw new Exception('role_penanda tidak valid: ' . $rolePenanda);
            }
            // Cegah user menandatangani atas nama role lain (admin dikecualikan)
            if ($userRole !== 'admin' && $rolePenanda !== $userRole) {
                throw new Exception('Anda hanya dapat menandatangani sebagai role Anda sendiri (' . $userRole . ')');
            }

            // Pastikan tabel ada
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

            // INSERT or UPDATE (upsert via ON DUPLICATE KEY)
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

            // ─── APPROVE: Setujui pengajuan + simpan uang masuk + bukti TF ─────
        case 'approve':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $id        = intval($_POST['id'] ?? 0);
            $uangMasuk = floatval($_POST['uang_masuk'] ?? 0);

            if (!$id)        throw new Exception('ID pengajuan tidak valid');
            if (!$uangMasuk) throw new Exception('Saldo / uang masuk wajib diisi');

            // Hitung sisa uang (ambil total_belanja dari DB)
            $rowPb = $koneksi->query("SELECT total_belanja FROM pengajuan_belanja WHERE id = $id");
            if (!$rowPb || $rowPb->num_rows === 0) throw new Exception('Pengajuan tidak ditemukan');
            $pb        = $rowPb->fetch_assoc();
            $totalBelanja = floatval($pb['total_belanja']);
            $sisaUang     = $uangMasuk - $totalBelanja;

            // Upload bukti transfer
            $buktiPath = null;
            if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/bukti_transfer/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

                $file     = $_FILES['bukti_transfer'];
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
                if (!in_array($ext, $allowed)) throw new Exception('Tipe file bukti transfer tidak diizinkan');
                if ($file['size'] > 5 * 1024 * 1024) throw new Exception('Ukuran file melebihi 5 MB');

                $fileName  = 'bukti_' . $id . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Gagal menyimpan file bukti transfer');
                }
                compressImage($targetPath);
                $buktiPath = $fileName;
            }

            // Update pengajuan_belanja
            if ($buktiPath) {
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
                $stmt->bind_param('ddsi', $uangMasuk, $sisaUang, $buktiPath, $id);
            } else {
                // Approve tanpa file (bukti_transfer opsional)
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
                'bukti_transfer' => $buktiPath,
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