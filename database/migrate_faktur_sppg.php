<?php
// Script untuk mengupdate/migrasi nomor faktur SPPG foodcost dan addcost ke format baru:
// Foodcost: 0001FJ-FC02082026
// Addcost : 0001FJ-AC02082026

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/koneksi.php';

$connections = [];
if (isset($koneksi2) && $koneksi2 instanceof mysqli && !$koneksi2->connect_error) {
    $connections['koneksi2 (db_mbg)'] = $koneksi2;
}

$local = @new mysqli('localhost', 'root', '', 'db_mbg');
if ($local && !$local->connect_error) {
    $connections['local (db_mbg)'] = $local;
}

if (empty($connections)) {
    die("Tidak ada koneksi database yang tersedia.\n");
}

echo "=== MIGRASI NOMOR FAKTUR SPPG (FOODCOST & ADDCOST) ===\n\n";

foreach ($connections as $name => $dbConn) {
    echo "--- Memproses database pada koneksi: $name ---\n";

    // Ensure columns exist
    @$dbConn->query("ALTER TABLE pengambilan_barang ADD COLUMN IF NOT EXISTS no_faktur_foodcost VARCHAR(50) DEFAULT NULL AFTER no_pengambilan");
    @$dbConn->query("ALTER TABLE pengambilan_barang ADD COLUMN IF NOT EXISTS no_faktur_addcost VARCHAR(50) DEFAULT NULL AFTER no_faktur_foodcost");

    // Copy old no_faktur column to no_faktur_foodcost/addcost if empty
    @$dbConn->query("UPDATE pengambilan_barang SET no_faktur_foodcost = no_faktur WHERE (no_faktur_foodcost IS NULL OR no_faktur_foodcost = '') AND no_faktur IS NOT NULL AND no_faktur != '' AND (no_faktur LIKE '%FC%' OR no_faktur LIKE '%fc%')");
    @$dbConn->query("UPDATE pengambilan_barang SET no_faktur_addcost = no_faktur WHERE (no_faktur_addcost IS NULL OR no_faktur_addcost = '') AND no_faktur IS NOT NULL AND no_faktur != '' AND (no_faktur LIKE '%AC%' OR no_faktur LIKE '%ac%')");

    $res = $dbConn->query("
        SELECT id_pengambilan, tanggal_pengambilan, no_faktur_foodcost, no_faktur_addcost, no_faktur 
        FROM pengambilan_barang 
        WHERE (no_faktur_foodcost IS NOT NULL AND no_faktur_foodcost != '') 
           OR (no_faktur_addcost IS NOT NULL AND no_faktur_addcost != '')
           OR (no_faktur IS NOT NULL AND no_faktur != '')
    ");

    if (!$res) {
        echo "Gagal query data: " . $dbConn->error . "\n\n";
        continue;
    }

    $countFc = 0;
    $countAc = 0;

    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id_pengambilan'];
        $tgl = $row['tanggal_pengambilan'];
        $tglStr = date('dmY', strtotime($tgl));

        // Update Foodcost
        if (!empty($row['no_faktur_foodcost'])) {
            $oldVal = trim($row['no_faktur_foodcost']);
            if (!preg_match('/^\d{4}FJ-FC\d{8}$/', $oldVal)) {
                if (preg_match('/^(\d+)/', $oldVal, $m)) {
                    $ctr = (int)$m[1];
                    $newVal = sprintf('%04dFJ-FC%s', $ctr, $tglStr);
                    $stmt = $dbConn->prepare("UPDATE pengambilan_barang SET no_faktur_foodcost = ? WHERE id_pengambilan = ?");
                    $stmt->bind_param('si', $newVal, $id);
                    $stmt->execute();
                    $stmt->close();
                    echo "[FOODCOST] ID $id: '$oldVal' => '$newVal'\n";
                    $countFc++;
                }
            }
        }

        // Update Addcost
        if (!empty($row['no_faktur_addcost'])) {
            $oldVal = trim($row['no_faktur_addcost']);
            if (!preg_match('/^\d{4}FJ-AC\d{8}$/', $oldVal)) {
                if (preg_match('/^(\d+)/', $oldVal, $m)) {
                    $ctr = (int)$m[1];
                    $newVal = sprintf('%04dFJ-AC%s', $ctr, $tglStr);
                    $stmt = $dbConn->prepare("UPDATE pengambilan_barang SET no_faktur_addcost = ? WHERE id_pengambilan = ?");
                    $stmt->bind_param('si', $newVal, $id);
                    $stmt->execute();
                    $stmt->close();
                    echo "[ADDCOST] ID $id: '$oldVal' => '$newVal'\n";
                    $countAc++;
                }
            }
        }
    }

    echo "Selesai untuk $name: $countFc data Foodcost & $countAc data Addcost telah diperbarui.\n\n";
}
