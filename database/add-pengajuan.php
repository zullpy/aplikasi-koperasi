<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// ===== DELETE =====
if (($body['action'] ?? '') === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM pengajuan_anggaran WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $koneksi->error]);
    }
    $stmt->close();
    exit;
}

// ===== INSERT / UPDATE =====
$id      = !empty($body['id']) ? (int)$body['id'] : null;
$jenis   = in_array($body['jenis'] ?? '', ['stok', 'peralatan', 'operasional', 'lainlain'])
    ? $body['jenis'] : 'stok';
$tanggal = $body['tanggal'] ?? '';
$tujuan  = trim($body['tujuan'] ?? '');
$jumlah  = (float)($body['jumlah'] ?? 0);
$items   = $body['items'] ?? [];

if (!$tanggal || !$tujuan) {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan tujuan wajib diisi']);
    exit;
}

$koneksi->begin_transaction();

try {
    if ($id) {
        $stmt = $koneksi->prepare("
            UPDATE pengajuan_anggaran
            SET jenis = ?, tujuan = ?, tanggal = ?, jumlah = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssdi", $jenis, $tujuan, $tanggal, $jumlah, $id);
        $stmt->execute();
        $stmt->close();

        $del = $koneksi->prepare("DELETE FROM detail_pengajuan WHERE pengajuan_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
    } else {
        $stmt = $koneksi->prepare("
            INSERT INTO pengajuan_anggaran (jenis, tujuan, tanggal, jumlah, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->bind_param("sssd", $jenis, $tujuan, $tanggal, $jumlah);
        $stmt->execute();
        $id = (int)$koneksi->insert_id;
        $stmt->close();
    }

    if (!empty($items)) {
        $ins = $koneksi->prepare("
            INSERT INTO detail_pengajuan (pengajuan_id, keterangan, qty, harga_satuan, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($items as $it) {
            $ket      = trim($it['keterangan'] ?? '');
            $qty      = (float)($it['qty'] ?? 0);
            $harga    = (float)($it['harga'] ?? 0);
            $subtotal = (float)($it['subtotal'] ?? 0);
            $ins->bind_param("isddd", $id, $ket, $qty, $harga, $subtotal);
            $ins->execute();
        }
        $ins->close();
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
