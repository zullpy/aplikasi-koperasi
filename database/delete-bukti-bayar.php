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
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang berhak menghapus bukti pembayaran.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = intval($data['id'] ?? $_POST['id'] ?? 0);
$type = trim($data['type'] ?? $_POST['type'] ?? 'penjualan'); // 'penjualan' atau 'pembelian'
$file = trim($data['file'] ?? $_POST['file'] ?? '');

if (!$id && empty($file)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
    exit;
}

try {
    if ($type === 'pembelian') {
        $fileToDelete = $file;
        $cleanName = !empty($file) ? basename(parse_url($file, PHP_URL_PATH)) : '';

        // Dapatkan nama file dari database jika $id diberikan dan $fileToDelete kosong
        if ($id > 0 && empty($fileToDelete)) {
            $stmt = $koneksi->prepare("SELECT bukti_pembayaran FROM riwayat_pembayaran_pembelian WHERE id_riwayat = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $fileToDelete = $row['bukti_pembayaran'] ?? '';
                    $cleanName = !empty($fileToDelete) ? basename(parse_url($fileToDelete, PHP_URL_PATH)) : '';
                }
                $stmt->close();
            }
        }

        // Hapus file fisik (lokal & Cloudinary)
        if (!empty($fileToDelete)) {
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/bukti_pembayaran/');
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/bukti_transfer/');
        }

        // Update / Hapus record di tabel riwayat_pembayaran_pembelian
        if ($id > 0) {
            // Jika bukti berdiri sendiri tanpa nominal bayar (jumlah_bayar = 0), hapus row-nya
            $stmtDel = $koneksi->prepare("DELETE FROM riwayat_pembayaran_pembelian WHERE id_riwayat = ? AND (jumlah_bayar = 0 OR jumlah_bayar IS NULL)");
            if ($stmtDel) {
                $stmtDel->bind_param('i', $id);
                $stmtDel->execute();
                $stmtDel->close();
            }
            // Jika ada nominal bayar yang perlu dipertahankan riwayatnya, set bukti_pembayaran jadi NULL
            $stmtUpd = $koneksi->prepare("UPDATE riwayat_pembayaran_pembelian SET bukti_pembayaran = NULL WHERE id_riwayat = ?");
            if ($stmtUpd) {
                $stmtUpd->bind_param('i', $id);
                $stmtUpd->execute();
                $stmtUpd->close();
            }
        }

        // Pencarian dan pembersihan berdasarkan nama file / URL jika ada
        if (!empty($fileToDelete)) {
            $likeTerm = '%' . $cleanName . '%';
            $stmtDelFile = $koneksi->prepare("DELETE FROM riwayat_pembayaran_pembelian WHERE (bukti_pembayaran = ? OR bukti_pembayaran LIKE ?) AND (jumlah_bayar = 0 OR jumlah_bayar IS NULL)");
            if ($stmtDelFile) {
                $stmtDelFile->bind_param('ss', $fileToDelete, $likeTerm);
                $stmtDelFile->execute();
                $stmtDelFile->close();
            }

            $stmtUpdFile = $koneksi->prepare("UPDATE riwayat_pembayaran_pembelian SET bukti_pembayaran = NULL WHERE bukti_pembayaran = ? OR bukti_pembayaran LIKE ?");
            if ($stmtUpdFile) {
                $stmtUpdFile->bind_param('ss', $fileToDelete, $likeTerm);
                $stmtUpdFile->execute();
                $stmtUpdFile->close();
            }
        }
    } else {
        // Default: Penjualan food / SPPG (tabel pembayaran)
        $fileToDelete = $file;
        if ($id > 0) {
            $stmt = $koneksi->prepare("SELECT * FROM pembayaran WHERE id = ? OR id_pembayaran = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $id, $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $fileToDelete = $row['bukti_bayar'] ?? $row['bukti_transfer'] ?? $file;
                    @$koneksi->query("UPDATE pembayaran SET bukti_bayar = NULL, bukti_transfer = NULL WHERE id = $id OR id_pembayaran = $id");
                }
            }
        }

        if (!empty($fileToDelete)) {
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/bukti-bayar/');
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/bukti_transfer/');
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/tf_penjualan_foodcost/');
            delete_photo_asset($fileToDelete, __DIR__ . '/../uploads/tf_penjualan_addcost/');
        }
    }

    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'message' => 'Bukti pembayaran berhasil dihapus dari sistem dan penyimpanan fisik.'
    ]);
    exit;
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus bukti pembayaran: ' . $e->getMessage()
    ]);
    exit;
}
