<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Session Guard (API-safe)
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized: silakan login']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';

require_once '/srv/http/aplikasi_kopdes/database/koneksi.php';

$method  = $_SERVER['REQUEST_METHOD'];
$input   = json_decode(file_get_contents('php://input'), true);

// Pastikan hanya admin yang bisa melakukan mutasi data (POST, PUT, DELETE)
if (in_array($method, ['POST', 'PUT', 'DELETE']) && $userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang diperbolehkan mengubah data pelanggan.']);
    exit;
}

switch ($method) {

    // ── GET: ambil semua supplier ──
    case 'GET':
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        if ($search !== '') {
            $stmt = $koneksi->prepare(
                "SELECT id_pelanggan, nama_pelanggan, no_telepon, alamat
                 FROM pelanggan
                 WHERE nama_pelanggan LIKE ? OR no_telepon LIKE ? OR alamat LIKE ?
                 ORDER BY id_pelanggan DESC"
            );
            $like = "%$search%";
            $stmt->bind_param('sss', $like, $like, $like);
        } else {
            $stmt = $koneksi->prepare(
                "SELECT id_pelanggan, nama_pelanggan, no_telepon, alamat
                 FROM pelanggan ORDER BY id_pelanggan DESC"
            );
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ── POST: tambah supplier ──
    case 'POST':
        $nama       = trim($input['nama_pelanggan'] ?? '');
        $no_telepon = trim($input['no_telepon']    ?? '');
        $alamat     = trim($input['alamat']        ?? '');

        if ($nama === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong']);
            break;
        }

        $stmt = $koneksi->prepare(
            "INSERT INTO pelanggan (nama_pelanggan, no_telepon, alamat) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('sss', $nama, $no_telepon, $alamat);
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan',
                'id'      => $koneksi->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data: ' . $koneksi->error]);
        }
        break;

    // ── PUT: edit supplier ──
    case 'PUT':
        $id         = intval($input['id_pelanggan']  ?? 0);
        $nama       = trim($input['nama_pelanggan']  ?? '');
        $no_telepon = trim($input['no_telepon']     ?? '');
        $alamat     = trim($input['alamat']         ?? '');

        if ($id === 0 || $nama === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID atau nama tidak valid']);
            break;
        }

        $stmt = $koneksi->prepare(
            "UPDATE pelanggan SET nama_pelanggan=?, no_telepon=?, alamat=? WHERE id_pelanggan=?"
        );
        $stmt->bind_param('sssi', $nama, $no_telepon, $alamat, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Pelanggan berhasil diperbarui']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . $koneksi->error]);
        }
        break;

    // ── DELETE: hapus supplier ──
    case 'DELETE':
        $id = intval($input['id_pelanggan'] ?? 0);

        if ($id === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            break;
        }

        $stmt = $koneksi->prepare("DELETE FROM pelanggan WHERE id_pelanggan=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Pelanggan berhasil dihapus']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
}

$koneksi->close();