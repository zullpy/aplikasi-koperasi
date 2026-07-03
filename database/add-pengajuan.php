<?php
// ===== DEBUG SEMENTARA — HAPUS SETELAH KETEMU MASALAHNYA =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ===============================================================

header('Content-Type: application/json');
require_once 'koneksi.php';
require_once __DIR__ . '/laporan-koperasi-func.php';

// Hanya terima method POST — mencegah request GET (reload/prefetch) ikut nge-trigger insert
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Payload tidak valid.']);
    exit;
}

// ===== HANDLE DELETE =====
if (($input['action'] ?? '') === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }

    $koneksi->begin_transaction();
    try {
        $stmt = $koneksi->prepare("DELETE FROM detail_pengajuan WHERE pengajuan_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $koneksi->prepare("DELETE FROM pengajuan_anggaran WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $koneksi->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $koneksi->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ===== HANDLE APPROVAL (Setuju / Tidak Setuju) =====
if (($input['action'] ?? '') === 'approval') {
    $id     = (int)($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }
    if (!in_array($status, ['approved', 'rejected'], true)) {
        echo json_encode(['success' => false, 'message' => 'Status keputusan tidak valid.']);
        exit;
    }

    // Pastikan data memang ada
    $check = $koneksi->prepare("SELECT id FROM pengajuan_anggaran WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan.']);
        exit;
    }

    // approved_by ambil dari session user yang login (sesuaikan key session-nya kalau beda)
    $approvedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    try {
        if ($status === 'approved') {
            $saldo      = (float)($input['saldo'] ?? 0);
            $catatan    = trim($input['catatan'] ?? '');
            $buktiInput = $input['bukti'] ?? '';
            $buktiName  = trim($input['buktiName'] ?? '');

            // Ambil bukti lama dulu, biar kalau user gak upload ulang (tetap pakai bukti
            // yang sudah ada sebelumnya), kolomnya gak ketiban NULL / kosong.
            $rowLama = $koneksi->prepare("SELECT bukti FROM pengajuan_anggaran WHERE id = ?");
            $rowLama->bind_param('i', $id);
            $rowLama->execute();
            $existing  = $rowLama->get_result()->fetch_assoc();
            $rowLama->close();
            $buktiPath = $existing['bukti'] ?? null;

            if (!empty($buktiInput) && strpos($buktiInput, 'base64,') !== false) {
                // Upload baru (data URL base64 dari FileReader di browser)
                $saved = simpanBuktiTransferApprovalKoperasi($buktiInput);
                if ($saved === false) {
                    throw new Exception('Upload bukti transfer gagal. Pastikan file berupa JPG/PNG dan tidak rusak, maksimal 5MB.');
                }
                $buktiPath = $saved;
            } elseif (!empty($buktiInput)) {
                // Bukan base64 baru, berarti path lama yang dikirim balik dari frontend
                $buktiPath = $buktiInput;
            }

            $stmt = $koneksi->prepare("
                UPDATE pengajuan_anggaran
                SET status = ?, saldo = ?, catatan = ?, alasan = NULL,
                    bukti = ?, bukti_name = ?,
                    approved_at = CURDATE(), approved_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sdsssii', $status, $saldo, $catatan, $buktiPath, $buktiName, $approvedBy, $id);
        } else {
            $alasan = trim($input['alasan'] ?? '');

            $stmt = $koneksi->prepare("
                UPDATE pengajuan_anggaran
                SET status = ?, alasan = ?, saldo = NULL, catatan = NULL,
                    approved_at = CURDATE(), approved_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssii', $status, $alasan, $approvedBy, $id);
        }

        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ===== VALIDASI INPUT UTAMA =====
$id      = isset($input['id']) && $input['id'] !== null ? (int)$input['id'] : null;
$jenis   = trim($input['jenis'] ?? '');
$tanggal = trim($input['tanggal'] ?? '');
$tujuan  = trim($input['tujuan'] ?? '');
$jumlah  = (float)($input['jumlah'] ?? 0);
$items   = is_array($input['items'] ?? null) ? $input['items'] : [];

$jenisValid = ['stok', 'peralatan', 'operasional'];
if (!in_array($jenis, $jenisValid, true)) {
    echo json_encode(['success' => false, 'message' => 'Jenis pengajuan tidak valid.']);
    exit;
}
if (!$tanggal) {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}
if (!$tujuan) {
    echo json_encode(['success' => false, 'message' => 'Tujuan wajib diisi.']);
    exit;
}
if ($jenis !== 'operasional' && empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Minimal satu item wajib diisi.']);
    exit;
}
if (!$jumlah) {
    echo json_encode(['success' => false, 'message' => 'Jumlah pengajuan tidak boleh 0.']);
    exit;
}

$koneksi->begin_transaction();
try {
    if ($id) {
        // ===== UPDATE (EDIT) =====
        // Pastikan baris memang ada, supaya tidak diam-diam jadi insert baru
        $check = $koneksi->prepare("SELECT id FROM pengajuan_anggaran WHERE id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {
            throw new Exception('Data pengajuan tidak ditemukan.');
        }

        $stmt = $koneksi->prepare("
            UPDATE pengajuan_anggaran
            SET jenis = ?, tujuan = ?, tanggal = ?, jumlah = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssdi', $jenis, $tujuan, $tanggal, $jumlah, $id);
        $stmt->execute();
        $stmt->close();

        // Hapus detail lama, lalu insert ulang detail baru (replace style)
        $stmt = $koneksi->prepare("DELETE FROM detail_pengajuan WHERE pengajuan_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $pengajuanId = $id;
    } else {
        // ===== INSERT BARU =====
        $stmt = $koneksi->prepare("
            INSERT INTO pengajuan_anggaran (jenis, tujuan, tanggal, jumlah, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param('sssd', $jenis, $tujuan, $tanggal, $jumlah);
        $stmt->execute();
        $pengajuanId = $stmt->insert_id;
        $stmt->close();
    }

    // ===== INSERT DETAIL ITEMS (untuk jenis stok & peralatan) =====
    if ($jenis !== 'operasional' && !empty($items)) {
        $stmtDp = $koneksi->prepare("
            INSERT INTO detail_pengajuan
                (pengajuan_id, keterangan, qty, satuan, sisa_stok, harga_satuan, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $it) {
            $ket      = trim($it['keterangan'] ?? '');
            $qty      = (float)($it['qty'] ?? 0);
            $satuan   = trim($it['satuan'] ?? '');
            $sisaStok = trim((string)($it['sisaStok'] ?? ''));
            $harga    = (float)($it['harga'] ?? 0);
            $subtotal = (float)($it['subtotal'] ?? ($qty * $harga));

            if ($ket === '' && $qty == 0 && $harga == 0) continue; // skip baris kosong

            $stmtDp->bind_param('isdssdd', $pengajuanId, $ket, $qty, $satuan, $sisaStok, $harga, $subtotal);
            $stmtDp->execute();
        }
        $stmtDp->close();
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'id' => $pengajuanId]);
} catch (Throwable $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
