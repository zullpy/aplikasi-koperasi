<?php
// =========================================================================
// AUTO DATABASE MIGRATION RUNNER
// Otomatis menjalankan file migrasi (.sql / .php) di folder migrations/
// saat aplikasi di-load di local maupun di server production.
// =========================================================================

function runAutoMigrations($db, $dbNameLabel = 'db1')
{
    if (!$db || $db->connect_error) return;

    // 1. Buat tabel schema_migrations jika belum ada
    $createTable = "CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL PRIMARY KEY,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    @$db->query($createTable);

    // 2. Ambil daftar migrasi yang sudah pernah dieksekusi
    $executed = [];
    $res = @$db->query("SELECT version FROM schema_migrations");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $executed[$row['version']] = true;
        }
        $res->free();
    }

    // 3. Scan folder database/migrations/
    $migrationDir = __DIR__ . '/migrations';
    if (!is_dir($migrationDir)) {
        return;
    }

    // Kumpulkan semua file .sql dan .php
    $migrationFiles = [];
    $files = glob($migrationDir . '/*.{sql,php}', GLOB_BRACE) ?: [];
    foreach ($files as $f) {
        $version = basename($f);
        $migrationFiles[$version] = $f;
    }

    // Urutkan file berdasarkan nama (presisi waktu/urutan)
    ksort($migrationFiles);

    // 4. Eksekusi file migrasi yang belum pernah dijalankan
    foreach ($migrationFiles as $version => $filePath) {
        if (isset($executed[$version])) {
            continue; // Sudah pernah dijalankan
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $success = false;

        if ($ext === 'sql') {
            $sql = file_get_contents($filePath);
            if (trim($sql) !== '') {
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                $success = true;
                foreach ($queries as $q) {
                    if ($q !== '') {
                        try {
                            if (!@$db->query($q)) {
                                // Abaikan error duplikat atau tabel tidak ditemukan di DB lain (1060, 1061, 1050, 1146)
                                if (!in_array($db->errno, [1060, 1061, 1050, 1146], true)) {
                                    $success = false;
                                }
                            }
                        } catch (Throwable $tq) {
                            // Suppress exception
                        }
                    }
                }
            } else {
                $success = true;
            }
        } elseif ($ext === 'php') {
            try {
                $koneksi = $db;
                include $filePath;
                $success = true;
            } catch (Throwable $e) {
                $success = false;
            }
        }

        if ($success) {
            $stmt = $db->prepare("INSERT IGNORE INTO schema_migrations (version) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param("s", $version);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Jalankan otomatis untuk $koneksi (db_draft_barang / db1)
if (isset($koneksi) && $koneksi instanceof mysqli) {
    runAutoMigrations($koneksi, 'db1');
}

// Jalankan otomatis untuk $koneksi2 (db_mbg / db2) jika ada
if (isset($koneksi2) && $koneksi2 instanceof mysqli) {
    runAutoMigrations($koneksi2, 'db2');
}
