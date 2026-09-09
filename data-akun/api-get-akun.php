<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya role admin yang boleh mengakses API data akun
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Khusus Administrator.']);
    exit;
}

require_once __DIR__ . '/../database/koneksi.php';
require_once __DIR__ . '/../database/ip_helper.php';
initAppTimezone($koneksi);

function formatRelativeTime($datetimeStr, $secDiff) {
    if (empty($datetimeStr)) {
        return 'Belum pernah aktif';
    }
    if ($secDiff === null || $secDiff < 0) {
        $secDiff = 0;
    }
    if ($secDiff < 60) {
        return 'Sedang aktif sekarang';
    }
    if ($secDiff < 3600) {
        $min = floor($secDiff / 60);
        return $min . ' menit yang lalu';
    }
    if ($secDiff < 86400) {
        $hours = floor($secDiff / 3600);
        return $hours . ' jam yang lalu';
    }
    if ($secDiff < 172800) {
        $time = date('H:i', strtotime($datetimeStr));
        return 'Kemarin, ' . $time . ' WIB';
    }
    $bulanIndo = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $ts = strtotime($datetimeStr);
    $d = date('j', $ts);
    $m = $bulanIndo[(int)date('n', $ts)] ?? date('M', $ts);
    $y = date('Y', $ts);
    $time = date('H:i', $ts);
    return "$d $m $y, $time WIB";
}

function formatIndoDateTime($datetimeStr) {
    if (empty($datetimeStr)) {
        return '-';
    }
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts = strtotime($datetimeStr);
    $d = date('j', $ts);
    $m = $bulanIndo[(int)date('n', $ts)] ?? date('F', $ts);
    $y = date('Y', $ts);
    $time = date('H:i:s', $ts);
    return "$d $m $y, $time WIB";
}

function parseDevice($ua) {
    if (empty($ua)) return 'Tidak diketahui';
    $os = 'Perangkat';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $browser = 'Browser';
    if (stripos($ua, 'Edg') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';
    elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR') !== false) $browser = 'Opera';

    return "$browser ($os)";
}

$sql = "SELECT id, username, role, last_login, last_activity, is_online, ip_address, user_agent,
        TIMESTAMPDIFF(SECOND, last_activity, NOW()) AS sec_since_active
        FROM akun 
        ORDER BY 
            (is_online = 1 AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) DESC,
            last_activity DESC, 
            username ASC";

$result = $koneksi->query($sql);
$data = [];
$totalAccounts = 0;
$totalOnline = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $totalAccounts++;
        $secDiff = $row['sec_since_active'] !== null ? (int)$row['sec_since_active'] : null;
        // Akun dianggap aktif jika is_online = 1 dan aktivitas terakhir <= 300 detik (5 menit)
        $isCurrentlyActive = ($row['is_online'] == 1 && $secDiff !== null && $secDiff <= 300);

        if ($isCurrentlyActive) {
            $totalOnline++;
        }

        $rawIp = trim($row['ip_address'] ?? '');
        if (empty($rawIp)) {
            $displayIp = 'Belum tercatat';
        } elseif ($rawIp === '::1' || $rawIp === '127.0.0.1') {
            $displayIp = '127.0.0.1 (Localhost)';
        } else {
            $displayIp = $rawIp;
        }

        $data[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'is_online' => $isCurrentlyActive,
            'status_label' => $isCurrentlyActive ? 'Sedang Aktif' : 'Offline',
            'last_activity_raw' => $row['last_activity'],
            'last_activity_formatted' => formatRelativeTime($row['last_activity'], $secDiff),
            'last_activity_full' => formatIndoDateTime($row['last_activity']),
            'last_login_formatted' => formatIndoDateTime($row['last_login']),
            'ip_address' => $displayIp,
            'device' => parseDevice($row['user_agent']),
            'is_current_user' => ((int)$row['id'] === (int)$_SESSION['id'])
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'summary' => [
        'total' => $totalAccounts,
        'online' => $totalOnline,
        'offline' => ($totalAccounts - $totalOnline)
    ],
    'data' => $data,
    'server_time' => date('Y-m-d H:i:s')
]);
