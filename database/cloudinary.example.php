<?php
// =========================================================================
// CONTOH TEMPLATE KONFIGURASI CLOUDINARY (APLIKASI KOPDES)
// Salin file ini menjadi: database/cloudinary.php lalu isi kredensial akun Anda.
// =========================================================================

define('CLOUDINARY_CLOUD_NAME', 'YOUR_CLOUD_NAME');
define('CLOUDINARY_API_KEY',    'YOUR_API_KEY');
define('CLOUDINARY_API_SECRET', 'YOUR_API_SECRET');

// Folder utama di Cloudinary Media Library khusus Aplikasi Kopdes
define('CLOUDINARY_BASE_FOLDER', 'aplikasi-kopdes');

// Fallback otomatis ke penyimpanan lokal jika Cloudinary down / belum diisi
define('CLOUDINARY_FALLBACK_LOCAL', true);
