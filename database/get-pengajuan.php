<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

$result = $koneksi->query("
    SELECT id, jenis, tujuan, tanggal, jumlah, status,
           saldo, catatan, approved_at, alasan
    FROM pengajuan_anggaran
    ORDER BY tanggal DESC, id DESC
");

if (!$result) {
    echo json_encode(['success' => false, 'message' => $koneksi->error]);
    exit;
}

$pengajuanList = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

if (!$pengajuanList) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$ids          = array_column($pengajuanList, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

$stmtDp = $koneksi->prepare("
    SELECT pengajuan_id, keterangan, qty, harga_satuan AS harga, subtotal
    FROM detail_pengajuan
    WHERE pengajuan_id IN ($placeholders)
    ORDER BY id ASC
");
$stmtDp->bind_param($types, ...$ids);
$stmtDp->execute();
$resDp      = $stmtDp->get_result();
$allDetails = $resDp->fetch_all(MYSQLI_ASSOC);
$resDp->free();
$stmtDp->close();

$detailMap = [];
foreach ($allDetails as $d) {
    $detailMap[$d['pengajuan_id']][] = [
        'keterangan' => $d['keterangan'],
        'qty'        => (float)$d['qty'],
        'harga'      => (float)$d['harga'],
        'subtotal'   => (float)$d['subtotal'],
    ];
}

$data = [];
foreach ($pengajuanList as $pa) {
    $data[] = [
        'id'         => (int)$pa['id'],
        'jenis'      => $pa['jenis'],
        'tujuan'     => $pa['tujuan'] ?? '',
        'tanggal'    => $pa['tanggal'],
        'jumlah'     => (float)$pa['jumlah'],
        'status'     => $pa['status'],
        'saldo'      => (float)($pa['saldo'] ?? 0),
        'approvedAt' => $pa['approved_at'] ?? '',
        'alasan'     => $pa['alasan'] ?? '',
        'bukti'      => '',
        'buktiName'  => '',
        'catatan'    => $pa['catatan'] ?? '',
        'items'      => $detailMap[$pa['id']] ?? [],
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
