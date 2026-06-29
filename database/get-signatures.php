<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'koneksi.php';

$pengajuan_id = intval($_GET['pengajuan_id'] ?? 0);

if (!$pengajuan_id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid', 'data' => []]);
    exit;
}

$stmt = $koneksi->prepare("SELECT ttd_ketua, ttd_bendahara, ttd_admin FROM pengajuan_anggaran WHERE id = ?");
$stmt->bind_param("i", $pengajuan_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Format jadi array seperti yang diharapkan script.js
$signatures = [];
$map = [
    'ketua'     => 'ttd_ketua',
    'bendahara' => 'ttd_bendahara',
    'admin'     => 'ttd_admin',
];

foreach ($map as $role => $kolom) {
    if (!empty($row[$kolom])) {
        $signatures[] = [
            'role_penanda'   => $role,
            'signature_data' => $row[$kolom],
        ];
    }
}

echo json_encode([
    'success' => true,
    'data'    => $signatures
]);

$stmt->close();
$koneksi->close();
