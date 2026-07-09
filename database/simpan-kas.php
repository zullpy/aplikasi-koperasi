<?php
session_start();
header('Content-Type: application/json');
require_once 'koneksi.php';

function respond($success, $message, $data = null)
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Metode tidak diizinkan.');
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    respond(false, 'Akses ditolak. Hanya admin yang dapat melakukan transaksi kas.');
}

$id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$tanggal    = trim($_POST['tanggal'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$jenis      = trim($_POST['jenis'] ?? '');
$nominalRaw = trim($_POST['nominal'] ?? '');

// Bersihkan format ribuan (mis. "150.000" atau "150,000") jadi angka murni
$nominal = (float) preg_replace('/[^0-9]/', '', $nominalRaw);

// ==== Validasi ====
$errors = [];

if ($tanggal === '' || !DateTime::createFromFormat('Y-m-d', $tanggal)) {
    $errors[] = 'Tanggal tidak valid.';
}
if ($keterangan === '') {
    $errors[] = 'Keterangan wajib diisi.';
} elseif (mb_strlen($keterangan) > 255) {
    $errors[] = 'Keterangan maksimal 255 karakter.';
}
if (!in_array($jenis, ['masuk', 'keluar'], true)) {
    $errors[] = 'Jenis transaksi harus Pemasukan atau Pengeluaran.';
}
if ($nominal <= 0) {
    $errors[] = 'Nominal harus lebih besar dari 0.';
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors));
}

$dibuatOleh = $_SESSION['nama'] ?? ($_SESSION['username'] ?? null);

if ($id > 0) {
    // ==== UPDATE ====
    $stmt = $koneksi->prepare(
        'UPDATE kas_koperasi
         SET tanggal = ?, keterangan = ?, jenis = ?, nominal = ?
         WHERE id = ?'
    );
    if (!$stmt) {
        respond(false, 'Gagal menyiapkan query update: ' . $koneksi->error);
    }
    $stmt->bind_param('sssdi', $tanggal, $keterangan, $jenis, $nominal, $id);

    if ($stmt->execute()) {
        respond(true, 'Transaksi berhasil diperbarui.');
    } else {
        respond(false, 'Gagal memperbarui transaksi: ' . $stmt->error);
    }
} else {
    // ==== INSERT ====
    $stmt = $koneksi->prepare(
        'INSERT INTO kas_koperasi (tanggal, keterangan, jenis, nominal, dibuat_oleh)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        respond(false, 'Gagal menyiapkan query simpan: ' . $koneksi->error);
    }
    $stmt->bind_param('sssds', $tanggal, $keterangan, $jenis, $nominal, $dibuatOleh);

    if ($stmt->execute()) {
        respond(true, 'Transaksi berhasil disimpan.', ['id' => $stmt->insert_id]);
    } else {
        respond(false, 'Gagal menyimpan transaksi: ' . $stmt->error);
    }
}