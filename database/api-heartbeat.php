<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/ip_helper.php';

$userId = (int)$_SESSION['id'];
$ip = getClientIP();
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$stmt = @$koneksi->prepare("UPDATE akun SET last_activity = NOW(), is_online = 1, ip_address = ?, user_agent = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ssi", $ip, $ua, $userId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['last_activity_update'] = time();
    echo json_encode(['status' => 'success', 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
