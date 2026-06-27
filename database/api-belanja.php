<?php
// Anti bocor
if (ob_get_level()) ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Nyalakan error untuk debug

header('Content-Type: application/json; charset=utf-8');

require_once 'koneksi.php';

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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

                // Ambil detail items + nota urls
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
                    // Pecah nota_urls_raw jadi array, kirim sebagai nota_urls
                    $d['nota_urls'] = $d['nota_urls_raw']
                        ? explode('||', $d['nota_urls_raw'])
                        : [];
                    unset($d['nota_urls_raw']);
                    $items[] = $d;
                }
                $stmtD->close();

                $row['items'] = $items;
                $data[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $data]);
            exit;

            // ─── LIST BARANG: Ambil master barang ───────────────────────────────
        case 'list_barang':
            $res = $koneksi->query("
                SELECT id_barang, nama_barang, harga_beli, satuan 
                FROM barang 
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

                // Insert detail items - PERBAIKAN DI SINI
                foreach ($items as $it) {
                    $subtotal = floatval($it['harga']) * intval($it['qty']);
                    $idBarang = !empty($it['id_barang']) ? intval($it['id_barang']) : null;
                    $namaBarang = $it['nama_barang'] ?? '';
                    $qty = intval($it['qty'] ?? 0);
                    $satuan = $it['satuan'] ?? '';
                    $harga = floatval($it['harga'] ?? 0);
                    $statusBendahara = 'pending';

                    $stmtD = $koneksi->prepare("
                        INSERT INTO detail_item_belanja 
                        (pengajuan_id, id_barang, nama_barang, qty, satuan, harga, subtotal, status_bendahara) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmtD) {
                        throw new Exception('Prepare INSERT detail error: ' . $koneksi->error);
                    }

                    $stmtD->bind_param(
                        "iisisiss",
                        $idPengajuan,
                        $idBarang,
                        $namaBarang,
                        $qty,
                        $satuan,
                        $harga,
                        $subtotal,
                        $statusBendahara
                    );

                    if (!$stmtD->execute()) {
                        throw new Exception('Execute INSERT detail error: ' . $stmtD->error);
                    }
                    $stmtD->close();
                }

                $koneksi->commit();

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
                    // Coba hapus file fisik (path relatif dari lokasi api ini)
                    $absPath = __DIR__ . '/' . ltrim($filePath, './');
                    if (file_exists($absPath)) {
                        unlink($absPath);
                    }
                    // Fallback: coba path apa adanya
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

            // ─── UPDATE STATUS: Approval bendahara ──────────────────────────────
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

            // ─── UPLOAD NOTA ──────────────────────────────────────────────
        case 'upload_nota':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $itemId = $_POST['item_id'] ?? null;
            $pengajuanId = $_POST['pengajuan_id'] ?? null;

            if (!$itemId || !$pengajuanId) {
                throw new Exception('Item ID dan Pengajuan ID wajib diisi');
            }

            // Handle file upload
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

                $fileName = time() . '_' . basename($files['name'][$i]);
                $targetPath = $uploadDir . $fileName;
                $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

                // Validasi tipe file
                $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, dan PDF yang diperbolehkan');
                }

                // Validasi ukuran (max 5MB)
                if ($files['size'][$i] > 5 * 1024 * 1024) {
                    throw new Exception('Ukuran file melebihi 5MB');
                }

                if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                    // Simpan ke database
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
                'files' => $uploadedFiles
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
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
}
