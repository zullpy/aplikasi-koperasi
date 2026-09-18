<?php
// =========================================================================
// CLOUDINARY HELPER (APLIKASI KOPDES)
// Mendukung upload langsung ke Cloudinary REST API via native PHP cURL,
// fallback otomatis ke penyimpanan lokal jika koneksi gagal / belum diset,
// dan kompatibilitas penuh untuk foto/nota/bukti lama.
// =========================================================================

// Muat konfigurasi Cloudinary
$configFile = __DIR__ . '/cloudinary.php';
if (file_exists($configFile)) {
    require_once $configFile;
} elseif (file_exists(__DIR__ . '/cloudinary.example.php')) {
    require_once __DIR__ . '/cloudinary.example.php';
}

/**
 * Cek apakah kredensial Cloudinary sudah diisi valid
 */
function cloudinary_is_configured(): bool
{
    return defined('CLOUDINARY_CLOUD_NAME')
        && defined('CLOUDINARY_API_KEY')
        && defined('CLOUDINARY_API_SECRET')
        && !empty(trim(CLOUDINARY_CLOUD_NAME))
        && !empty(trim(CLOUDINARY_API_KEY))
        && !empty(trim(CLOUDINARY_API_SECRET))
        && trim(CLOUDINARY_CLOUD_NAME) !== 'YOUR_CLOUD_NAME';
}

/**
 * Kompresi lokal cepat menggunakan GD sebelum upload cURL ke Cloudinary.
 * Mereduksi ukuran file kamera HP (5MB-10MB) menjadi ~300KB dalam hitungan milidetik
 * sehingga proses cURL upload Cloudinary berjalan secepat kilat (0.2 - 0.5 detik).
 */
function fast_compress_image(string $filePath, int $quality = 75, int $maxWidth = 1600): bool
{
    if (!extension_loaded('gd') || !file_exists($filePath)) {
        return false;
    }
    clearstatcache(true, $filePath);
    $size = @filesize($filePath);
    if ($size === false || $size < 500 * 1024) {
        return true; // File sudah ringan (< 500KB), tidak perlu kompresi ulang
    }

    $info = @getimagesize($filePath);
    if (!$info) return false;
    list($w, $h, $type) = $info;

    $img = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $img = @imagecreatefromjpeg($filePath);
            break;
        case IMAGETYPE_PNG:
            $img = @imagecreatefrompng($filePath);
            break;
        case 18: // IMAGETYPE_WEBP
            if (function_exists('imagecreatefromwebp')) {
                $img = @imagecreatefromwebp($filePath);
            }
            break;
    }
    if (!$img) return false;

    // Resize proporsional jika resolusi terlalu raksasa (misal 4000x3000)
    if ($maxWidth > 0 && $w > $maxWidth) {
        $newW = $maxWidth;
        $newH = (int)floor($h * ($maxWidth / $w));
        $newImg = imagecreatetruecolor($newW, $newH);

        if ($type == IMAGETYPE_PNG || $type == 18) {
            imagealphablending($newImg, false);
            imagesavealpha($newImg, true);
        }

        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $newImg;
    }

    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($img, $filePath, $quality);
            break;
        case IMAGETYPE_PNG:
            $pngQ = max(0, min(9, 9 - round(($quality / 100) * 9)));
            imagepng($img, $filePath, $pngQ);
            break;
        case 18:
            if (function_exists('imagewebp')) {
                imagewebp($img, $filePath, $quality);
            }
            break;
    }

    imagedestroy($img);
    clearstatcache(true, $filePath);
    return true;
}

/**
 * Upload file langsung ke Cloudinary API menggunakan cURL
 *
 * @param string $filePath Path file fisik di server ATAU data-uri base64
 * @param string $subfolder Subfolder di dalam aplikasi-kopdes (misal 'nota', 'tf_penjualan_foodcost', 'bukti_transfer')
 * @param string|null $publicId Nama custom public_id (opsional)
 * @param string $resourceType 'auto' (mendukung gambar & PDF), 'image', atau 'raw'
 * @return array ['success' => bool, 'url' => string, 'public_id' => string, 'format' => string]
 * @throws Exception Jika upload gagal
 */
function cloudinary_upload(string $filePath, string $subfolder = '', ?string $publicId = null, string $resourceType = 'auto'): array
{
    $isBase64 = str_starts_with($filePath, 'data:image/');

    if (!$isBase64 && !file_exists($filePath)) {
        throw new Exception("File sumber tidak ditemukan: " . $filePath);
    }

    if (!$isBase64) {
        // Kompresi otomatis file gambar besar sebelum cURL ke Cloudinary
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            @fast_compress_image($filePath, 75, 1600);
        }
    }

    if (!cloudinary_is_configured()) {
        throw new Exception("Cloudinary belum dikonfigurasi. Silakan lengkapi kredensial di database/cloudinary.php");
    }

    $cloudName = trim(CLOUDINARY_CLOUD_NAME);
    $apiKey    = trim(CLOUDINARY_API_KEY);
    $apiSecret = trim(CLOUDINARY_API_SECRET);

    $baseFolder = defined('CLOUDINARY_BASE_FOLDER') ? trim(CLOUDINARY_BASE_FOLDER, '/') : 'aplikasi-kopdes';
    if (!empty($subfolder)) {
        $cleanSub = trim($subfolder, '/');
        if ($cleanSub === $baseFolder || str_starts_with($cleanSub, $baseFolder . '/')) {
            $targetFolder = $cleanSub;
        } else {
            $targetFolder = $baseFolder . '/' . $cleanSub;
        }
    } else {
        $targetFolder = $baseFolder;
    }

    $timestamp = time();

    // Parameter untuk kalkulasi SHA1 signature
    $paramsToSign = [
        'folder'    => $targetFolder,
        'timestamp' => $timestamp,
    ];

    if (!empty($publicId)) {
        $paramsToSign['public_id'] = $publicId;
    }

    ksort($paramsToSign);

    $signParts = [];
    foreach ($paramsToSign as $k => $v) {
        $signParts[] = "{$k}={$v}";
    }
    $toSignStr = implode('&', $signParts) . $apiSecret;
    $signature = sha1($toSignStr);

    // Payload POST multipart
    $postFields = [
        'file'      => $isBase64 ? $filePath : new CURLFile($filePath),
        'api_key'   => $apiKey,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder'    => $targetFolder,
    ];

    if (!empty($publicId)) {
        $postFields['public_id'] = $publicId;
    }

    $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);
    unset($ch);

    if ($curlError) {
        throw new Exception("Koneksi ke Cloudinary gagal: " . $curlError);
    }

    $json = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($json['secure_url'])) {
        $secureUrl = $json['secure_url'];
        // Tambahkan transformasi f_auto,q_auto untuk auto-kompresi & auto WebP
        if (str_contains($secureUrl, '/image/upload/') && !str_contains($secureUrl, 'f_auto')) {
            $secureUrl = str_replace('/image/upload/', '/image/upload/f_auto,q_auto/', $secureUrl);
        }
        return [
            'success'   => true,
            'url'       => $secureUrl,
            'public_id' => $json['public_id'] ?? '',
            'format'    => $json['format'] ?? '',
            'bytes'     => $json['bytes'] ?? 0,
        ];
    }

    $errorMsg = $json['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . $response);
    throw new Exception("Cloudinary Error: " . $errorMsg);
}

/**
 * Upload cerdas untuk $_FILES: Otomatis upload ke Cloudinary jika dikonfigurasi,
 * atau simpan lokal jika Cloudinary belum diset / fallback diaktifkan.
 *
 * @param array $fileItem Elemen dari $_FILES (misal $_FILES['bukti_transfer'])
 * @param string $subfolder Subfolder di Cloudinary (misal 'tf_penjualan_foodcost', 'nota')
 * @param string $localDir Folder lokal cadangan
 * @param string $prefix Prefix nama file jika disimpan lokal
 * @return string Mengembalikan URL Cloudinary (https://...) ATAU nama file lokal
 * @throws Exception
 */
function smart_upload_foto(array $fileItem, string $subfolder, string $localDir, string $prefix = 'file'): string
{
    $tmpName = $fileItem['tmp_name'] ?? '';
    $origName = $fileItem['name'] ?? '';

    if (empty($tmpName) || !file_exists($tmpName)) {
        throw new Exception("File upload tidak ditemukan");
    }

    // Coba upload ke Cloudinary terlebih dahulu jika sudah dikonfigurasi
    if (cloudinary_is_configured()) {
        try {
            $res = cloudinary_upload($tmpName, $subfolder);
            if (!empty($res['url'])) {
                return $res['url'];
            }
        } catch (Exception $e) {
            $fallback = defined('CLOUDINARY_FALLBACK_LOCAL') ? CLOUDINARY_FALLBACK_LOCAL : true;
            if (!$fallback) {
                throw $e;
            }
            error_log("Cloudinary upload failed, falling back to local: " . $e->getMessage());
        }
    }

    // Penyimpanan Lokal Cadangan
    if (!file_exists($localDir)) {
        mkdir($localDir, 0755, true);
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
    $newName = $cleanPrefix . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $targetPath = rtrim($localDir, '/') . '/' . $newName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new Exception("Gagal menyimpan file ke penyimpanan lokal: " . $targetPath);
    }

    return $newName;
}

/**
 * Upload paralel banyak file sekaligus ke Cloudinary menggunakan curl_multi
 * Memangkas waktu upload 3-5 file dari 6-10 detik menjadi hanya ~1.5 detik total.
 *
 * @param array $filePaths Array of file paths fisik atau tmp_name
 * @param string $subfolder Subfolder Cloudinary
 * @param string $resourceType 'auto' atau 'image'
 * @return array Array of hasil ['success' => bool, 'url' => string, 'error' => ?string] dengan key index yang sama
 */
function cloudinary_upload_batch(array $filePaths, string $subfolder = '', string $resourceType = 'auto'): array
{
    if (!cloudinary_is_configured()) {
        throw new Exception("Cloudinary belum dikonfigurasi.");
    }
    if (empty($filePaths)) {
        return [];
    }

    $cloudName = trim(CLOUDINARY_CLOUD_NAME);
    $apiKey    = trim(CLOUDINARY_API_KEY);
    $apiSecret = trim(CLOUDINARY_API_SECRET);

    $baseFolder = defined('CLOUDINARY_BASE_FOLDER') ? trim(CLOUDINARY_BASE_FOLDER, '/') : 'aplikasi-kopdes';
    if (!empty($subfolder)) {
        $cleanSub = trim($subfolder, '/');
        if ($cleanSub === $baseFolder || str_starts_with($cleanSub, $baseFolder . '/')) {
            $targetFolder = $cleanSub;
        } else {
            $targetFolder = $baseFolder . '/' . $cleanSub;
        }
    } else {
        $targetFolder = $baseFolder;
    }

    $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload";
    $mh = curl_multi_init();
    $curlHandles = [];
    $timestamp = time();

    foreach ($filePaths as $idx => $filePath) {
        if (!file_exists($filePath)) {
            continue;
        }

        // Kompresi cepat jika gambar mentah > 500KB
        @fast_compress_image($filePath, 75, 1600);

        $paramsToSign = [
            'folder'    => $targetFolder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);
        $signParts = [];
        foreach ($paramsToSign as $k => $v) {
            $signParts[] = "{$k}={$v}";
        }
        $signature = sha1(implode('&', $signParts) . $apiSecret);

        $postFields = [
            'file'      => new CURLFile($filePath),
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder'    => $targetFolder,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        curl_multi_add_handle($mh, $ch);
        $curlHandles[$idx] = $ch;
    }

    // Jalankan semua upload cURL secara simultan/paralel
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.05);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($curlHandles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $ch);
        @curl_close($ch);
        unset($ch);

        if ($curlError) {
            $results[$idx] = ['success' => false, 'url' => '', 'error' => $curlError];
            continue;
        }

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($json['secure_url'])) {
            $secureUrl = $json['secure_url'];
            if (str_contains($secureUrl, '/image/upload/') && !str_contains($secureUrl, 'f_auto')) {
                $secureUrl = str_replace('/image/upload/', '/image/upload/f_auto,q_auto/', $secureUrl);
            }
            $results[$idx] = ['success' => true, 'url' => $secureUrl, 'error' => null];
        } else {
            $err = $json['error']['message'] ?? ('HTTP ' . $httpCode);
            $results[$idx] = ['success' => false, 'url' => '', 'error' => $err];
        }
    }
    curl_multi_close($mh);

    return $results;
}

/**
 * Upload cerdas batch untuk banyak $_FILES sekaligus secara paralel
 *
 * @param array $fileItems Array of file items (masing-masing punya 'tmp_name', 'name')
 * @param string $subfolder Subfolder Cloudinary
 * @param string $localDir Folder lokal cadangan
 * @param string $prefix Prefix nama file jika disimpan lokal
 * @return array Array of URL Cloudinary / nama file lokal dengan index yang sama
 */
function smart_upload_foto_batch(array $fileItems, string $subfolder, string $localDir, string $prefix = 'file'): array
{
    if (empty($fileItems)) {
        return [];
    }

    $out = [];
    $toCloudinary = [];

    foreach ($fileItems as $idx => $item) {
        $tmp = $item['tmp_name'] ?? '';
        if (!empty($tmp) && file_exists($tmp)) {
            $toCloudinary[$idx] = $tmp;
        } else {
            $out[$idx] = null;
        }
    }

    // Coba upload paralel via Cloudinary jika aktif
    if (cloudinary_is_configured() && !empty($toCloudinary)) {
        try {
            $cloudResults = cloudinary_upload_batch($toCloudinary, $subfolder);
            foreach ($cloudResults as $idx => $res) {
                if (!empty($res['success']) && !empty($res['url'])) {
                    $out[$idx] = $res['url'];
                    unset($toCloudinary[$idx]); // Berhasil, tidak perlu simpan lokal
                }
            }
        } catch (Exception $e) {
            error_log("Cloudinary batch upload exception: " . $e->getMessage());
        }
    }

    // Sisa file yang belum berhasil ke Cloudinary disimpan ke folder lokal cadangan
    if (!empty($toCloudinary)) {
        if (!file_exists($localDir)) {
            mkdir($localDir, 0755, true);
        }
        foreach ($toCloudinary as $idx => $tmpName) {
            $origName = $fileItems[$idx]['name'] ?? 'file.jpg';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
            $newName = $cleanPrefix . '_' . date('Ymd_His') . '_' . $idx . '_' . uniqid() . '.' . $ext;
            $targetPath = rtrim($localDir, '/') . '/' . $newName;
            if (@move_uploaded_file($tmpName, $targetPath) || @copy($tmpName, $targetPath)) {
                $out[$idx] = $newName;
            }
        }
    }

    return $out;
}

/**
 * Upload cerdas untuk data Base64 Canvas Signature (Tanda Tangan)
 */
function smart_upload_base64(string $base64Data, string $subfolder, string $localDir, string $prefix = 'ttd'): string
{
    if (empty($base64Data)) {
        throw new Exception("Data tanda tangan kosong");
    }

    // Coba upload langsung base64 string ke Cloudinary
    if (cloudinary_is_configured()) {
        try {
            $res = cloudinary_upload($base64Data, $subfolder);
            if (!empty($res['url'])) {
                return $res['url'];
            }
        } catch (Exception $e) {
            $fallback = defined('CLOUDINARY_FALLBACK_LOCAL') ? CLOUDINARY_FALLBACK_LOCAL : true;
            if (!$fallback) {
                throw $e;
            }
            error_log("Cloudinary signature upload failed, falling back to local: " . $e->getMessage());
        }
    }

    // Penyimpanan Lokal Cadangan
    if (!file_exists($localDir)) {
        mkdir($localDir, 0755, true);
    }

    $data = $base64Data;
    if (preg_match('/^data:image\/(png|jpeg);base64,/', $data)) {
        $data = substr($data, strpos($data, ',') + 1);
    }
    $decoded = base64_decode($data);
    if ($decoded === false) {
        throw new Exception("Gagal mendekode data tanda tangan base64");
    }

    $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
    $newName = $cleanPrefix . '_' . date('Ymd_His') . '_' . uniqid() . '.png';
    $targetPath = rtrim($localDir, '/') . '/' . $newName;

    if (file_put_contents($targetPath, $decoded) === false) {
        throw new Exception("Gagal menyimpan tanda tangan ke folder lokal: " . $targetPath);
    }

    return $newName;
}

/**
 * Menyelesaikan path foto: Mengembalikan URL Cloudinary jika sudah berupa link web,
 * atau path lokal lengkap jika masih berupa nama file lokal lama.
 *
 * @param string|null $photo Filename atau Full URL
 * @param string $localPrefix Path relatif folder lokal (misal '../uploads/nota/')
 * @return string
 */
function resolve_photo_url(?string $photo, string $localPrefix = ''): string
{
    if (empty($photo)) {
        return '';
    }

    $photo = trim($photo);

    // Jika sudah berupa URL Cloudinary atau web link
    if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
        // Otomatis optimalkan format (WebP) dan kompresi (q_auto) jika berasal dari Cloudinary
        if (str_contains($photo, 'res.cloudinary.com') && str_contains($photo, '/image/upload/') && !str_contains($photo, 'f_auto')) {
            return str_replace('/image/upload/', '/image/upload/f_auto,q_auto/', $photo);
        }
        return $photo;
    }

    $cleanPrefix = trim($localPrefix, '/');
    $cleanPhoto  = ltrim($photo, '/');

    // Cegah prefix berulang jika photo sudah diawali dengan folder prefix yang sama
    if (!empty($cleanPrefix) && str_starts_with($cleanPhoto, $cleanPrefix . '/')) {
        return $cleanPhoto;
    }

    return (!empty($cleanPrefix) ? $cleanPrefix . '/' : '') . $cleanPhoto;
}

/**
 * Ekstrak public_id dari URL Cloudinary
 */
function cloudinary_extract_public_id(string $url): ?string
{
    if (!str_contains($url, 'res.cloudinary.com')) {
        return null;
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) return null;

    $uploadPos = strpos($path, '/image/upload/');
    if ($uploadPos === false) return null;

    $afterUpload = substr($path, $uploadPos + strlen('/image/upload/'));
    $parts = explode('/', $afterUpload);

    // Hilangkan transformasi (misal f_auto,q_auto) dan versi (v1234567)
    while (!empty($parts)) {
        $first = $parts[0];
        if (str_contains($first, ',') || preg_match('/^v\d+$/', $first)) {
            array_shift($parts);
        } else {
            break;
        }
    }

    if (empty($parts)) return null;

    $publicIdWithExt = implode('/', $parts);
    return pathinfo($publicIdWithExt, PATHINFO_DIRNAME) !== '.' 
        ? pathinfo($publicIdWithExt, PATHINFO_DIRNAME) . '/' . pathinfo($publicIdWithExt, PATHINFO_FILENAME)
        : pathinfo($publicIdWithExt, PATHINFO_FILENAME);
}

/**
 * Hapus aset foto: Hapus dari Cloudinary jika berupa URL, atau unlink dari folder lokal jika file lokal
 */
function delete_photo_asset(?string $photo, string $localDir = ''): bool
{
    if (empty($photo)) {
        return false;
    }

    $photo = trim($photo);

    if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
        if (!cloudinary_is_configured()) return false;
        $publicId = cloudinary_extract_public_id($photo);
        if (!$publicId) return false;

        $cloudName = trim(CLOUDINARY_CLOUD_NAME);
        $apiKey    = trim(CLOUDINARY_API_KEY);
        $apiSecret = trim(CLOUDINARY_API_SECRET);
        $timestamp = time();

        $paramsToSign = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);

        $signParts = [];
        foreach ($paramsToSign as $k => $v) {
            $signParts[] = "{$k}={$v}";
        }
        $signature = sha1(implode('&', $signParts) . $apiSecret);

        $postFields = [
            'public_id' => $publicId,
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        $json = json_decode($res, true);
        @curl_close($ch);
        unset($ch);

        if (isset($json['result']) && $json['result'] === 'not found') {
            $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/raw/destroy");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);
            curl_exec($ch);
            @curl_close($ch);
            unset($ch);
        }
        return true;
    }

    $localPath = rtrim($localDir, '/') . '/' . basename($photo);
    if (file_exists($localPath) && is_file($localPath)) {
        return @unlink($localPath);
    }

    return false;
}
