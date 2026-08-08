<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

require_once 'koneksi.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$pengajuan_id  = intval($data['pengajuan_id']);
$role_penanda  = $data['role_penanda'];      // 'ketua' | 'bendahara' | 'admin'
$signature_data = $data['signature_data'];  // base64 PNG

if ($role_penanda !== $userRole) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Anda tidak diizinkan menandatangani sebagai role ini.']);
    exit;
}

// Map role → nama kolom
$kolom_map = [
    'ketua'     => 'ttd_ketua',
    'bendahara' => 'ttd_bendahara',
    'admin'     => 'ttd_admin',
];

if (!isset($kolom_map[$role_penanda])) {
    echo json_encode(['success' => false, 'message' => 'Role tidak dikenal']);
    exit;
}

$kolom = $kolom_map[$role_penanda];

$stmt = $koneksi->prepare("UPDATE pengajuan_anggaran SET `$kolom` = ? WHERE id = ?");
$stmt->bind_param("si", $signature_data, $pengajuan_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tanda tangan berhasil disimpan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $koneksi->error]);
}

$stmt->close();
$koneksi->close();
