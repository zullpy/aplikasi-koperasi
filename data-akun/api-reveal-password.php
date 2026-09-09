<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya role admin yang boleh mengakses API ini
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Khusus Administrator.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode HTTP tidak didukung.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$adminPassword = trim($input['admin_password'] ?? '');
$targetAccountId = (int)($input['account_id'] ?? 0);

if (empty($adminPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'Password admin wajib diisi.']);
    exit;
}

if ($targetAccountId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID akun target tidak valid.']);
    exit;
}

require_once __DIR__ . '/../database/koneksi.php';

// 1. Verifikasi password admin yang sedang login
$adminId = (int)$_SESSION['id'];
$stmtAdmin = $koneksi->prepare("SELECT id, password FROM akun WHERE id = ? AND role = 'admin'");
if (!$stmtAdmin) {
    echo json_encode(['status' => 'error', 'message' => 'Query database gagal.']);
    exit;
}
$stmtAdmin->bind_param("i", $adminId);
$stmtAdmin->execute();
$resAdmin = $stmtAdmin->get_result();
$adminData = $resAdmin->fetch_assoc();
$stmtAdmin->close();

if (!$adminData || $adminData['password'] !== $adminPassword) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password admin salah! Verifikasi keamanan gagal.'
    ]);
    exit;
}

// 2. Ambil password akun target
$stmtTarget = $koneksi->prepare("SELECT id, username, password FROM akun WHERE id = ?");
if (!$stmtTarget) {
    echo json_encode(['status' => 'error', 'message' => 'Query database gagal.']);
    exit;
}
$stmtTarget->bind_param("i", $targetAccountId);
$stmtTarget->execute();
$resTarget = $stmtTarget->get_result();
$targetData = $resTarget->fetch_assoc();
$stmtTarget->close();

if (!$targetData) {
    echo json_encode(['status' => 'error', 'message' => 'Akun tidak ditemukan.']);
    exit;
}

// Simpan flag di session bahwa admin sudah terverifikasi (opsional, berlaku 5 menit)
$_SESSION['admin_password_verified_until'] = time() + 300;

echo json_encode([
    'status' => 'success',
    'message' => 'Verifikasi berhasil.',
    'account_id' => (int)$targetData['id'],
    'username' => $targetData['username'],
    'password' => $targetData['password']
]);
