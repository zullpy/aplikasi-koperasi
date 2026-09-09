<?php
// ip_helper.php - Helper untuk mendapatkan IP address client secara akurat
if (!function_exists('getClientIP')) {
    function getClientIP() {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return ($ip === '::1') ? '127.0.0.1' : $ip;
                    }
                }
            }
        }
        $raw = $_SERVER['REMOTE_ADDR'] ?? '';
        return ($raw === '::1') ? '127.0.0.1' : ($raw ?: '127.0.0.1');
    }
}
