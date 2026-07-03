<?php
// =====================================================================
// FUNGSI-FUNGSI — MODUL LAPORAN KOPERASI
// Simpan file ini di: ../database/laporan-koperasi-func.php
// (menyesuaikan pola include di index.php Laporan SPPG)
// =====================================================================

if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}

function labelJenisKoperasi($jenis)
{
    $map = [
        'stok'        => 'Stok',
        'peralatan'   => 'Peralatan',
        'operasional' => 'Operasional',
    ];
    return $map[$jenis] ?? ucfirst($jenis);
}

// ===== AMBIL DATA LAPORAN (hanya status approved) =====
function getDataLaporanKoperasi($koneksi)
{
    $sql = "SELECT * FROM pengajuan_anggaran WHERE status = 'approved' ORDER BY tanggal DESC, id DESC";
    $result = $koneksi->query($sql);

    $rows = [];
    while ($r = $result->fetch_assoc()) {
        // Nominal dasar untuk laporan HARUS pakai nominal yang sudah di-ACC admin (kolom `saldo`),
        // bukan nominal permintaan awal (kolom `jumlah`). `saldo` baru terisi setelah status = approved.
        // Fallback ke `jumlah` cuma jaga-jaga kalau ada data lama yang `saldo`-nya masih NULL.
        $nominalDisetujui = isset($r['saldo']) && $r['saldo'] !== null ? (float) $r['saldo'] : (float) $r['jumlah'];

        $r['saldo_masuk']   = hitungSaldoMasukKoperasi($koneksi, $r['id'], $nominalDisetujui);
        $r['total_belanja'] = hitungTotalBelanjaKoperasi($koneksi, $r['id'], $r['jenis'], $nominalDisetujui);
        $r['sisa_saldo']    = $r['saldo_masuk'] - $r['total_belanja'];

        // Kwitansi/nota level-pengajuan (dipakai untuk jenis selain 'stok', yang tidak
        // punya tombol Cetak + Tanda Tangan — diganti upload kwitansi/nota manual).
        $r['kwitansi'] = decodeNotaPathKoperasi($r['kwitansi_path'] ?? null);

        $rows[] = $r;
    }
    return $rows;
}

// ===== HITUNG SALDO MASUK (jumlah awal + akumulasi tambah saldo) =====
function hitungSaldoMasukKoperasi($koneksi, $pengajuanId, $jumlahAwal)
{
    $stmt = $koneksi->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM saldo_koperasi WHERE pengajuan_id = ?");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $tambahan = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return (float) $jumlahAwal + $tambahan;
}

// ===== HITUNG TOTAL BELANJA =====
// stok / peralatan  -> jumlah subtotal semua item
// operasional       -> langsung pakai nominal pengajuan (tidak ada item)
function hitungTotalBelanjaKoperasi($koneksi, $pengajuanId, $jenis, $jumlahAwal)
{
    if ($jenis === 'operasional') {
        return (float) $jumlahAwal;
    }

    $stmt = $koneksi->prepare("SELECT COALESCE(SUM(subtotal), 0) AS total FROM detail_pengajuan WHERE pengajuan_id = ?");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $total = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total;
}

// ===== RINGKASAN TOTAL (untuk summary card) =====
function getTotalRingkasanKoperasi($rows)
{
    $total = ['saldo_masuk' => 0, 'belanja' => 0, 'sisa_saldo' => 0];

    foreach ($rows as $r) {
        $total['saldo_masuk'] += $r['saldo_masuk'];
        $total['belanja']     += $r['total_belanja'];
    }
    $total['sisa_saldo'] = $total['saldo_masuk'] - $total['belanja'];

    return $total;
}

// ===== AMBIL DETAIL ITEM (rincian barang stok/peralatan) =====
function getDetailItemKoperasi($koneksi, $pengajuanId)
{
    $stmt = $koneksi->prepare("SELECT * FROM detail_pengajuan WHERE pengajuan_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Setiap item dilengkapi array 'notas' (bisa lebih dari 1 nota per barang),
    // supaya bisa langsung dipakai di modal galeri nota (bukaGaleriNota).
    foreach ($res as &$item) {
        $item['notas'] = decodeNotaPathKoperasi($item['nota_path'] ?? null);
    }
    unset($item);

    return $res;
}

// ===== DECODE nota_path -> array path nota =====
// Mendukung 2 bentuk data di kolom nota_path:
// - Baru   : JSON array string, misal '["nota_koperasi/a.jpg","nota_koperasi/b.jpg"]'
// - Lama   : path tunggal biasa, misal 'nota_koperasi/a.jpg' (data sebelum fitur multi-nota)
function decodeNotaPathKoperasi($notaPath)
{
    if (empty($notaPath)) return [];

    $decoded = json_decode($notaPath, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, fn($p) => !empty($p)));
    }

    return [$notaPath];
}

// ===== AMBIL RIWAYAT TAMBAH SALDO =====
function getRiwayatSaldoKoperasi($koneksi, $pengajuanId)
{
    $stmt = $koneksi->prepare("SELECT * FROM saldo_koperasi WHERE pengajuan_id = ? ORDER BY tanggal DESC, id DESC");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

// ===== EDIT HARGA — ITEM (jenis stok/peralatan) =====
function updateHargaItemKoperasi($koneksi, $itemId, $hargaBaru)
{
    $itemId    = (int) $itemId;
    $hargaBaru = (float) $hargaBaru;

    $stmt = $koneksi->prepare("SELECT qty FROM detail_pengajuan WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return false;

    $subtotal = (float) $row['qty'] * $hargaBaru;

    $stmt = $koneksi->prepare("UPDATE detail_pengajuan SET harga_satuan = ?, subtotal = ? WHERE id = ?");
    $stmt->bind_param('ddi', $hargaBaru, $subtotal, $itemId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// ===== EDIT HARGA — PENGAJUAN OPERASIONAL (tidak ada item) =====
function updateJumlahPengajuanKoperasi($koneksi, $pengajuanId, $jumlahBaru)
{
    $pengajuanId = (int) $pengajuanId;
    $jumlahBaru  = (float) $jumlahBaru;

    // `saldo` ikut di-update juga karena laporan (getDataLaporanKoperasi) sekarang membaca
    // nominal disetujui dari kolom `saldo`, bukan `jumlah`. Kalau cuma `jumlah` yang diupdate,
    // perubahan nominal dari tombol "Edit Nominal" tidak akan kelihatan di laporan.
    $stmt = $koneksi->prepare("UPDATE pengajuan_anggaran SET jumlah = ?, saldo = ? WHERE id = ? AND jenis = 'operasional'");
    $stmt->bind_param('ddi', $jumlahBaru, $jumlahBaru, $pengajuanId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// ===== TAMBAH BARANG (item baru + nota, bisa lebih dari 1 file) =====
// $filesNota diisi langsung dari $_FILES['nota'] (struktur array: name[], type[], tmp_name[], error[], size[])
// karena input file di form pakai name="nota[]" multiple.
function tambahBarangItemKoperasi($koneksi, $pengajuanId, $nama, $qty, $satuan, $harga, $filesNota = null)
{
    $pengajuanId = (int) $pengajuanId;
    $nama        = trim($nama);
    $qty         = (float) $qty;
    $satuan      = trim($satuan);
    $harga       = (float) $harga;
    $subtotal    = $qty * $harga;

    $notaPaths = uploadMultiNotaKoperasi($filesNota, 'nota_koperasi', 'nota');

    // Kalau ada file yang memang dikirim user tapi SEMUA gagal diupload (format/ukuran salah),
    // baru dianggap error. Kalau memang tidak ada file yang dipilih, lanjut simpan tanpa nota.
    $adaFileDikirim = !empty($filesNota) && !empty(array_filter(
        (array) ($filesNota['name'] ?? []),
        fn($n) => $n !== null && $n !== ''
    ));
    if ($adaFileDikirim && empty($notaPaths)) {
        return ['success' => false, 'message' => 'Upload nota gagal. Pastikan format JPG/PNG/PDF dan ukuran maksimal 5MB.'];
    }

    // Disimpan sebagai JSON array di kolom nota_path yang sama (mendukung banyak nota per barang).
    $notaPathJson = !empty($notaPaths) ? json_encode(array_values($notaPaths)) : null;

    $stmt = $koneksi->prepare("
        INSERT INTO detail_pengajuan (pengajuan_id, keterangan, qty, satuan, harga_satuan, subtotal, nota_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isdsdds', $pengajuanId, $nama, $qty, $satuan, $harga, $subtotal, $notaPathJson);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok];
}

// ===== TAMBAH NOTA UNTUK BARANG YANG SUDAH ADA (append, bukan replace) =====
// Dipakai kalau barang sudah tersimpan tapi belum ada notanya / mau nambah nota susulan.
// File baru digabung dengan nota_path yang sudah ada (tidak menghapus nota lama).
function tambahNotaItemKoperasi($koneksi, $itemId, $filesNota)
{
    $itemId = (int) $itemId;

    $adaFileDikirim = !empty($filesNota) && !empty(array_filter(
        (array) ($filesNota['name'] ?? []),
        fn($n) => $n !== null && $n !== ''
    ));
    if (!$adaFileDikirim) {
        return ['success' => false, 'message' => 'Belum ada file yang dipilih.'];
    }

    $stmt = $koneksi->prepare("SELECT nota_path FROM detail_pengajuan WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Barang tidak ditemukan.'];
    }

    $notaLama = decodeNotaPathKoperasi($row['nota_path']);
    $notaBaru = uploadMultiNotaKoperasi($filesNota, 'nota_koperasi', 'nota');

    if (empty($notaBaru)) {
        return ['success' => false, 'message' => 'Upload nota gagal. Pastikan format JPG/PNG/PDF dan ukuran maksimal 5MB.'];
    }

    $gabungan     = array_values(array_merge($notaLama, $notaBaru));
    $notaPathJson = json_encode($gabungan);

    $stmt = $koneksi->prepare("UPDATE detail_pengajuan SET nota_path = ? WHERE id = ?");
    $stmt->bind_param('si', $notaPathJson, $itemId);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok];
}

// ===== TAMBAH KWITANSI/NOTA — LEVEL PENGAJUAN (jenis selain 'stok') =====
// Dipakai untuk pengajuan 'peralatan' & 'operasional' yang tidak punya tombol
// Cetak + Tanda Tangan. Sebagai gantinya, purchase/admin cukup upload foto
// kwitansi/nota belanja langsung ke pengajuan-nya (bukan per-barang).
// File baru DITAMBAHKAN ke kwitansi_path yang sudah ada (append, bukan replace),
// mengikuti pola yang sama seperti tambahNotaItemKoperasi().
function tambahKwitansiKoperasi($koneksi, $pengajuanId, $filesKwitansi)
{
    $pengajuanId = (int) $pengajuanId;

    $adaFileDikirim = !empty($filesKwitansi) && !empty(array_filter(
        (array) ($filesKwitansi['name'] ?? []),
        fn($n) => $n !== null && $n !== ''
    ));
    if (!$adaFileDikirim) {
        return ['success' => false, 'message' => 'Belum ada file kwitansi/nota yang dipilih.'];
    }

    $stmt = $koneksi->prepare("SELECT kwitansi_path FROM pengajuan_anggaran WHERE id = ?");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
    }

    $kwitansiLama = decodeNotaPathKoperasi($row['kwitansi_path']);
    $kwitansiBaru = uploadMultiNotaKoperasi($filesKwitansi, 'kwitansi_koperasi', 'kwitansi');

    if (empty($kwitansiBaru)) {
        return ['success' => false, 'message' => 'Upload kwitansi/nota gagal. Pastikan format JPG/PNG/PDF dan ukuran maksimal 5MB.'];
    }

    $gabungan         = array_values(array_merge($kwitansiLama, $kwitansiBaru));
    $kwitansiPathJson = json_encode($gabungan);

    $stmt = $koneksi->prepare("UPDATE pengajuan_anggaran SET kwitansi_path = ? WHERE id = ?");
    $stmt->bind_param('si', $kwitansiPathJson, $pengajuanId);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok];
}

function hapusBarangItemKoperasi($koneksi, $itemId)
{
    $itemId = (int) $itemId;

    // Hapus juga semua file nota fisik kalau ada (bisa lebih dari 1 nota per barang)
    $stmt = $koneksi->prepare("SELECT nota_path FROM detail_pengajuan WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['nota_path'])) {
        foreach (decodeNotaPathKoperasi($row['nota_path']) as $notaFile) {
            $filePath = __DIR__ . '/../uploads/' . $notaFile;
            if (is_file($filePath)) @unlink($filePath);
        }
    }

    $stmt = $koneksi->prepare("DELETE FROM detail_pengajuan WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// ===== TAMBAH SALDO (dengan bukti transfer wajib) =====
function tambahSaldoKoperasi($koneksi, $pengajuanId, $jumlah, $fileBukti, $tanggal = null, $keterangan = null)
{
    $pengajuanId = (int) $pengajuanId;
    $jumlah      = (float) $jumlah;
    $tanggal     = $tanggal ?: date('Y-m-d');
    $buktiPath   = null;

    if (!empty($fileBukti['name']) && ($fileBukti['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $buktiPath = uploadFileKoperasi($fileBukti, 'bukti_saldo_koperasi', 'saldo');
        if ($buktiPath === false) {
            return ['success' => false, 'message' => 'Upload bukti transfer gagal. Pastikan format JPG/PNG/PDF dan ukuran maksimal 5MB.'];
        }
    } else {
        return ['success' => false, 'message' => 'Bukti transfer wajib diunggah.'];
    }

    $stmt = $koneksi->prepare("
        INSERT INTO saldo_koperasi (pengajuan_id, jumlah, bukti_transfer, tanggal, keterangan)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('idsss', $pengajuanId, $jumlah, $buktiPath, $tanggal, $keterangan);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok];
}

// ===== HELPER UPLOAD FILE (nota & bukti transfer) =====
function uploadFileKoperasi($file, $subfolder, $prefix)
{
    $allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxSize     = 5 * 1024 * 1024; // 5MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return false;
    if ($file['size'] > $maxSize) return false;

    // Validasi mime type asli file, bukan cuma dari nama file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) return false;

    $targetDir = __DIR__ . '/../uploads/' . $subfolder . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $namaFile = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target   = $targetDir . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $target)) return false;

    // Path relatif ini yang disimpan di DB & dipakai untuk menampilkan gambar
    return $subfolder . '/' . $namaFile;
}

// ===== HELPER UPLOAD MULTI-FILE NOTA (bisa lebih dari 1 file sekaligus) =====
// Menerima struktur mentah $_FILES['nota'] (name[], type[], tmp_name[], error[], size[])
// hasil dari <input type="file" name="nota[]" multiple>.
// Mengembalikan array path relatif untuk file yang BERHASIL diupload saja
// (file yang gagal validasi format/ukuran otomatis dilewati).
function uploadMultiNotaKoperasi($filesNota, $subfolder = 'nota_koperasi', $prefix = 'nota')
{
    $paths = [];
    if (empty($filesNota) || empty($filesNota['name'])) return $paths;

    $names = (array) $filesNota['name'];

    foreach ($names as $i => $namaFile) {
        if (empty($namaFile)) continue;

        $error = $filesNota['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) continue; // file ini gagal upload di sisi PHP, lewati saja

        $singleFile = [
            'name'     => $namaFile,
            'type'     => $filesNota['type'][$i] ?? '',
            'tmp_name' => $filesNota['tmp_name'][$i] ?? '',
            'error'    => $error,
            'size'     => $filesNota['size'][$i] ?? 0,
        ];

        $path = uploadFileKoperasi($singleFile, $subfolder, $prefix);
        if ($path !== false) {
            $paths[] = $path;
        }
    }

    return $paths;
}

// =====================================================================
// TANDA TANGAN DIGITAL (TTD) — Admin, Purchase, Ketua, Bendahara
// Tabel: ttd_laporan_koperasi (1 baris = 1 pengajuan + 1 role)
// =====================================================================

/**
 * Daftar role yang boleh tanda tangan beserta label tampilannya.
 * Key HARUS sama persis dengan nilai kolom `role` di tabel akun
 * ($_SESSION['role']) supaya pengecekan hak akses otomatis nyambung.
 */
function daftarRoleTtdKoperasi()
{
    return [
        'admin'     => 'Admin',
        'purchase'  => 'Purchase',
        'ketua'     => 'Ketua Koperasi',
        'bendahara' => 'Bendahara',
    ];
}

/**
 * Ambil semua tanda tangan milik 1 pengajuan, di-index per role.
 * Role yang belum tanda tangan tidak akan punya key di array hasil,
 * jadi cukup dicek pakai isset($ttd['admin']) dst.
 */
function getTtdPengajuanKoperasi($koneksi, $pengajuanId)
{
    $pengajuanId = (int) $pengajuanId;

    $stmt = $koneksi->prepare("SELECT * FROM ttd_laporan_koperasi WHERE pengajuan_id = ?");
    $stmt->bind_param('i', $pengajuanId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $byRole = [];
    foreach ($res as $r) {
        $byRole[$r['role']] = $r;
    }
    return $byRole;
}

/**
 * Ambil tanda tangan seluruh pengajuan sekaligus (dipakai supaya query di
 * halaman laporan tidak N+1 — dipanggil 1x, hasilnya dikelompokkan per
 * pengajuan_id lalu tinggal diakses per baris).
 * Return: [ pengajuan_id => [ role => row, ... ], ... ]
 */
function getTtdSemuaPengajuanKoperasi($koneksi)
{
    $sql = "SELECT * FROM ttd_laporan_koperasi ORDER BY pengajuan_id ASC";
    $result = $koneksi->query($sql);

    $grouped = [];
    while ($r = $result->fetch_assoc()) {
        $grouped[$r['pengajuan_id']][$r['role']] = $r;
    }
    return $grouped;
}

/**
 * Simpan / timpa tanda tangan untuk 1 pengajuan + 1 role (upsert).
 * Kalau role ini sudah pernah tanda tangan sebelumnya untuk pengajuan yang
 * sama, file lama otomatis dihapus dan digantikan file baru (ini yang
 * dipakai tombol "Ganti Tanda Tangan" di frontend — user tidak perlu
 * hapus manual dulu, cukup gambar ulang lalu simpan).
 *
 * $base64Image: data URL hasil canvas.toDataURL('image/png'),
 * contoh: "data:image/png;base64,iVBORw0KGgo..."
 */
function simpanTtdKoperasi($koneksi, $pengajuanId, $role, $signedBy, $base64Image)
{
    $pengajuanId = (int) $pengajuanId;
    $rolesValid  = array_keys(daftarRoleTtdKoperasi());

    if (!in_array($role, $rolesValid, true)) {
        return ['success' => false, 'message' => 'Role tidak dikenali / tidak berhak tanda tangan.'];
    }

    $path = simpanFileTtdKoperasi($base64Image);
    if ($path === false) {
        return ['success' => false, 'message' => 'Tanda tangan kosong / data tidak valid. Silakan gambar ulang.'];
    }

    // Hapus file tanda tangan lama (role + pengajuan yang sama) kalau ada,
    // supaya tidak ada file nota "sampah" menumpuk tiap kali user ganti TTD.
    $stmt = $koneksi->prepare("SELECT signature_path FROM ttd_laporan_koperasi WHERE pengajuan_id = ? AND role = ?");
    $stmt->bind_param('is', $pengajuanId, $role);
    $stmt->execute();
    $lama = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($lama && !empty($lama['signature_path'])) {
        $fileLama = __DIR__ . '/../uploads/' . $lama['signature_path'];
        if (is_file($fileLama)) @unlink($fileLama);
    }

    $stmt = $koneksi->prepare("
        INSERT INTO ttd_laporan_koperasi (pengajuan_id, role, signature_path, signed_by, signed_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            signature_path = VALUES(signature_path),
            signed_by      = VALUES(signed_by),
            signed_at      = NOW()
    ");
    $stmt->bind_param('isss', $pengajuanId, $role, $path, $signedBy);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok];
}

/**
 * Hapus tanda tangan untuk 1 pengajuan + 1 role (file fisik + baris DB).
 * Dipakai tombol "Hapus Tanda Tangan" di modal (opsi selain "Ganti").
 */
function hapusTtdKoperasi($koneksi, $pengajuanId, $role)
{
    $pengajuanId = (int) $pengajuanId;

    $stmt = $koneksi->prepare("SELECT signature_path FROM ttd_laporan_koperasi WHERE pengajuan_id = ? AND role = ?");
    $stmt->bind_param('is', $pengajuanId, $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return true; // memang belum ada, anggap sukses (idempotent)

    if (!empty($row['signature_path'])) {
        $file = __DIR__ . '/../uploads/' . $row['signature_path'];
        if (is_file($file)) @unlink($file);
    }

    $stmt = $koneksi->prepare("DELETE FROM ttd_laporan_koperasi WHERE pengajuan_id = ? AND role = ?");
    $stmt->bind_param('is', $pengajuanId, $role);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Decode data URL base64 dari canvas lalu simpan sebagai file PNG fisik
 * di ../uploads/ttd_koperasi/. Return path relatif (disimpan di DB),
 * atau false kalau datanya kosong / tidak valid.
 */
function simpanFileTtdKoperasi($base64Image)
{
    if (empty($base64Image) || strpos($base64Image, 'base64,') === false) {
        return false;
    }

    [, $data] = explode('base64,', $base64Image, 2);
    $data = base64_decode(str_replace(' ', '+', $data));

    // Tanda tangan kosong biasanya menghasilkan PNG blank yang tetap punya
    // ukuran, jadi validasi minimal ukuran byte saja untuk menolak data rusak.
    if ($data === false || strlen($data) < 100) {
        return false;
    }

    $targetDir = __DIR__ . '/../uploads/ttd_koperasi/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $namaFile = 'ttd_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.png';
    $target   = $targetDir . $namaFile;

    if (file_put_contents($target, $data) === false) {
        return false;
    }

    return 'ttd_koperasi/' . $namaFile;
}

/**
 * Decode data URL base64 bukti transfer (hasil FileReader di browser, bisa JPG/PNG)
 * lalu simpan sebagai file fisik di ../uploads/bukti_approval_koperasi/.
 * Return path relatif (disimpan di DB), atau false kalau data kosong/tidak valid.
 */
function simpanBuktiTransferApprovalKoperasi($base64Image)
{
    if (empty($base64Image) || strpos($base64Image, 'base64,') === false) {
        return false;
    }

    // Ambil mime type dari header data URL, misal "data:image/jpeg;base64,...."
    $ext = 'png';
    if (preg_match('/^data:image\/(png|jpe?g);base64,/i', $base64Image, $m)) {
        $ext = strtolower($m[1]) === 'jpg' ? 'jpg' : strtolower($m[1]);
    } else {
        return false; // bukan gambar yang didukung
    }

    [, $data] = explode('base64,', $base64Image, 2);
    $data = base64_decode(str_replace(' ', '+', $data));

    if ($data === false || strlen($data) < 100) {
        return false;
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if (strlen($data) > $maxSize) {
        return false;
    }

    $targetDir = __DIR__ . '/../uploads/bukti_approval_koperasi/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $namaFile = 'bukti_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target   = $targetDir . $namaFile;

    if (file_put_contents($target, $data) === false) {
        return false;
    }

    return 'bukti_approval_koperasi/' . $namaFile;
}
