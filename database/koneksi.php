<?php
// ==========================================
// DATABASE 1: db_draft_barang (TIDAK DIUBAH)
// ==========================================
$host = "localhost";
$username = "root";
$password = "";
$dbname = "db_draft_barang";

$koneksi = new mysqli($host, $username, $password, $dbname);

if ($koneksi->connect_error) {
    die("Koneksi db_draft_barang gagal: " . $koneksi->connect_error);
}


// ==========================================
// DATABASE 2: db_mbg (TAMBAHAN BARU)
// ==========================================
$host2 = "localhost"; // Sama, karena di server lokal yang sama
$username2 = "root";
$password2 = "";
$dbname2 = "db_mbg";

$koneksi2 = new mysqli($host2, $username2, $password2, $dbname2);

if ($koneksi2->connect_error) {
    die("Koneksi db_mbg gagal: " . $koneksi2->connect_error);
}

/**
 * Kompresi dan ubah ukuran gambar otomatis untuk menghemat ruang penyimpanan.
 * Mendukung format JPG, JPEG, PNG, dan WebP.
 *
 * @param string $sourcePath Path lengkap ke file gambar asal
 * @param int $quality Kualitas kompresi (0-100)
 * @param int $maxWidth Batas lebar maksimal gambar (0 jika tidak ingin mengubah ukuran)
 * @return bool True jika berhasil dikompresi/dilewati, false jika gagal karena error
 */
function compressImage($sourcePath, $quality = 70, $maxWidth = 1200) {
    if (!extension_loaded('gd')) {
        return false;
    }

    if (!file_exists($sourcePath)) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        // Jika bukan gambar yang valid (misal PDF), lewati saja dengan status sukses
        return true;
    }

    list($width, $height, $type) = $info;

    $image = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($sourcePath);
            break;
        case (defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18):
            if (function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($sourcePath);
            }
            break;
    }

    if (!$image) {
        // Jika gagal meload gambar (format tidak didukung GD), lewati saja agar tidak error
        return true;
    }

    // Ubah ukuran jika lebar gambar melebihi maxWidth
    if ($maxWidth > 0 && $width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Pertahankan transparansi untuk PNG dan WebP
        if ($type == IMAGETYPE_PNG || $type == (defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18)) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    // Simpan kembali gambar dengan kompresi
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($image, $sourcePath, $quality);
            break;
        case IMAGETYPE_PNG:
            // PNG quality: 0 (tanpa kompresi) sampai 9 (kompresi maksimum)
            $pngQuality = 9 - round(($quality / 100) * 9);
            $pngQuality = max(0, min(9, $pngQuality));
            $result = imagepng($image, $sourcePath, $pngQuality);
            break;
        case (defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18):
            if (function_exists('imagewebp')) {
                $result = imagewebp($image, $sourcePath, $quality);
            }
            break;
    }

    imagedestroy($image);
    return $result;
}

