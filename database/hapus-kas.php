<?php
session_start();
header('Content-Type: application/json');
require_once 'koneksi.php';

function respond($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Metode tidak diizinkan.');
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    respond(false, 'Akses ditolak. Hanya admin yang dapat menghapus transaksi kas.');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    respond(false, 'ID transaksi tidak valid.');
}

$stmt = $koneksi->prepare('DELETE FROM kas_koperasi WHERE id = ?');
if (!$stmt) {
    respond(false, 'Gagal menyiapkan query hapus: ' . $koneksi->error);
}
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        respond(true, 'Transaksi berhasil dihapus.');
    } else {
        respond(false, 'Transaksi tidak ditemukan.');
    }
} else {
    respond(false, 'Gagal menghapus transaksi: ' . $stmt->error);
}