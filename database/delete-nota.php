<?php
// Anti bocor
while (ob_get_level()) { ob_end_clean(); }
ob_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_USER_DEPRECATED);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();
include 'koneksi.php';
require_once __DIR__ . '/cloudinary_helper.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang berhak menghapus nota.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = intval($data['id'] ?? $_POST['id'] ?? 0);
$file = trim($data['file'] ?? $_POST['file'] ?? '');

if (!$id && empty($file)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID pembelian atau nama file tidak valid.']);
    exit;
}

try {
    // 1. Dapatkan data transaksi pembelian
    $stmt = $koneksi->prepare("SELECT id_pembelian, kode_transaksi, nota FROM transaksi_pembelian WHERE id_pembelian = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception('Data transaksi pembelian tidak ditemukan.');
    }

    $kodeTransaksi = $row['kode_transaksi'];
    $rawNotas = trim($row['nota'] ?? '');
    $currentNotas = !empty($rawNotas) 
        ? preg_split('/,(?=\s*https?:\/\/|\s*[a-zA-Z0-9_.-]+\.(?:jpe?g|png|webp|pdf))/i', $rawNotas)
        : [];
    $updatedNotas = [];
    $fileToDelete = '';

    foreach ($currentNotas as $n) {
        $n = trim($n);
        if (empty($n)) continue;
        // Cek kecocokan nama file atau URL
        if ($n === $file || basename($n) === basename($file) || (str_contains($file, $n) && !empty($n))) {
            $fileToDelete = $n;
        } else {
            $updatedNotas[] = $n;
        }
    }

    // Jika tidak cocok di per-item tapi file diberikan langsung
    if (empty($fileToDelete) && !empty($file)) {
        $fileToDelete = $file;
    }

    // 2. Hapus file fisik secara permanen dari Cloudinary / lokal
    if (!empty($fileToDelete)) {
        delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/nota/');
    }

    // 3. Update database untuk semua item dengan kode_transaksi yang sama
    $newNotaStr = !empty($updatedNotas) ? implode(',', $updatedNotas) : null;
    $stmtUp = $koneksi->prepare("UPDATE transaksi_pembelian SET nota = ? WHERE kode_transaksi = ?");
    $stmtUp->bind_param('ss', $newNotaStr, $kodeTransaksi);
    $stmtUp->execute();
    $stmtUp->close();

    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'message' => 'Nota berhasil dihapus dari sistem dan penyimpanan fisik.',
        'remaining_count' => count($updatedNotas)
    ]);
    exit;
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus nota: ' . $e->getMessage()
    ]);
    exit;
}
