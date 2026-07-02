<?php
// ===================== FUNGSI & QUERY LAPORAN SPPG =====================

/**
 * Format angka ke Rupiah, nilai minus tetap ditampilkan dengan tanda "-"
 */
function rupiah($angka)
{
    $minus = $angka < 0 ? '-' : '';
    return $minus . 'Rp ' . number_format(abs($angka), 0, ',', '.');
}

/**
 * Ambil semua pengajuan belanja dengan status approved
 */
function getDataLaporan($koneksi)
{
    $sql = "SELECT * FROM pengajuan_belanja WHERE status = 'approved' ORDER BY tanggal DESC, id DESC";
    $result = mysqli_query($koneksi, $sql);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Ambil detail item belanja + nota per item, berdasarkan id pengajuan
 */
function getDetailItem($koneksi, $id_pengajuan)
{
    $sql = "SELECT d.*, u.file_path 
            FROM detail_item_belanja d
            LEFT JOIN upload_nota u ON u.item_id = d.id AND u.pengajuan_id = d.pengajuan_id
            WHERE d.pengajuan_id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id_pengajuan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

/**
 * Hitung total saldo masuk & total sisa saldo dari kumpulan data laporan
 */
function getTotalRingkasan($rows)
{
    $total_saldo_masuk = 0;
    $total_sisa_saldo  = 0;
    $total_belanja     = 0;

    foreach ($rows as $row) {
        $total_saldo_masuk += (float) $row['uang_masuk'];
        $total_sisa_saldo  += (float) $row['sisa_uang'];
        $total_belanja     += (float) $row['total_belanja'];
    }

    return [
        'saldo_masuk' => $total_saldo_masuk,
        'sisa_saldo'  => $total_sisa_saldo,
        'belanja'     => $total_belanja,
    ];
}

/**
 * Update harga satuan sebuah item belanja.
 * Subtotal item dihitung ulang otomatis (qty * harga).
 */
function updateHargaItem($koneksi, $item_id, $harga_baru)
{
    $item_id    = (int) $item_id;
    $harga_baru = (float) $harga_baru;

    $sql  = "UPDATE detail_item_belanja SET harga = ?, subtotal = qty * ? WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ddi', $harga_baru, $harga_baru, $item_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        // Ambil pengajuan_id dari item ini lalu sinkronkan total_belanja & sisa_uang
        $sql2  = "SELECT pengajuan_id FROM detail_item_belanja WHERE id = ?";
        $stmt2 = mysqli_prepare($koneksi, $sql2);
        mysqli_stmt_bind_param($stmt2, 'i', $item_id);
        mysqli_stmt_execute($stmt2);
        $res = mysqli_stmt_get_result($stmt2);
        if ($row = mysqli_fetch_assoc($res)) {
            syncTotalBelanja($koneksi, $row['pengajuan_id']);
        }
        mysqli_stmt_close($stmt2);
    }
    return $ok;
}

/**
 * Tambah barang baru ke sebuah pengajuan. Subtotal dihitung otomatis (qty * harga).
 */
function tambahBarangItem($koneksi, $pengajuan_id, $nama_barang, $qty, $satuan, $harga)
{
    $pengajuan_id = (int) $pengajuan_id;
    $nama_barang  = trim($nama_barang);
    $qty          = (float) $qty;
    $satuan       = trim($satuan);
    $harga        = (float) $harga;
    $subtotal     = $qty * $harga;

    $id_barang_default = 0; // TODO: ganti ke id_barang asli dari tabel `barang` jika perlu relasi yang valid

    $sql  = "INSERT INTO detail_item_belanja (id_barang, pengajuan_id, nama_barang, qty, satuan, harga, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'iisdsdd', $id_barang_default, $pengajuan_id, $nama_barang, $qty, $satuan, $harga, $subtotal);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        syncTotalBelanja($koneksi, $pengajuan_id);
    }
    return $ok;
}

/**
 * Hapus satu item belanja beserta nota terkait (jika ada),
 * lalu sinkronkan ulang total_belanja & sisa_uang pada pengajuan_belanja.
 */
function hapusBarangItem($koneksi, $item_id)
{
    $item_id = (int) $item_id;

    // Ambil pengajuan_id dulu sebelum item dihapus, dipakai untuk sinkronisasi setelahnya
    $sql  = "SELECT pengajuan_id FROM detail_item_belanja WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $pengajuan_id = null;
    if ($row = mysqli_fetch_assoc($res)) {
        $pengajuan_id = $row['pengajuan_id'];
    }
    mysqli_stmt_close($stmt);

    if ($pengajuan_id === null) {
        return false; // item tidak ditemukan, batal
    }

    // Hapus baris nota terkait item ini (jika ada)
    $sqlNota  = "DELETE FROM upload_nota WHERE item_id = ?";
    $stmtNota = mysqli_prepare($koneksi, $sqlNota);
    mysqli_stmt_bind_param($stmtNota, 'i', $item_id);
    mysqli_stmt_execute($stmtNota);
    mysqli_stmt_close($stmtNota);

    // Hapus item belanja
    $sqlDel  = "DELETE FROM detail_item_belanja WHERE id = ?";
    $stmtDel = mysqli_prepare($koneksi, $sqlDel);
    mysqli_stmt_bind_param($stmtDel, 'i', $item_id);
    $ok = mysqli_stmt_execute($stmtDel);
    mysqli_stmt_close($stmtDel);

    if ($ok) {
        syncTotalBelanja($koneksi, $pengajuan_id);
    }
    return $ok;
}

/**
 * Hitung ulang total_belanja & sisa_uang pada pengajuan_belanja
 * berdasarkan penjumlahan subtotal seluruh detail_item_belanja miliknya.
 */
function syncTotalBelanja($koneksi, $pengajuan_id)
{
    $pengajuan_id = (int) $pengajuan_id;

    $sql  = "SELECT COALESCE(SUM(subtotal), 0) AS total FROM detail_item_belanja WHERE pengajuan_id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $pengajuan_id);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $total = mysqli_fetch_assoc($res)['total'];
    mysqli_stmt_close($stmt);

    $sql2  = "UPDATE pengajuan_belanja SET total_belanja = ?, sisa_uang = uang_masuk - ? WHERE id = ?";
    $stmt2 = mysqli_prepare($koneksi, $sql2);
    mysqli_stmt_bind_param($stmt2, 'ddi', $total, $total, $pengajuan_id);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
}

/**
 * Simpan tanda tangan digital untuk sebuah laporan (pengajuan_belanja) ke
 * tabel ttd_laporan_sppg, sesuai role akun yang sedang login
 * (bendahara / purchase / ketua / admin).
 *
 * Satu role hanya boleh punya SATU tanda tangan aktif per laporan
 * (dijaga oleh UNIQUE KEY pengajuan_id + role_penanda di database).
 * Kalau role yang sama tanda tangan ulang untuk laporan yang sama,
 * baris lama otomatis di-update (ON DUPLICATE KEY), bukan bikin baris baru.
 */
function simpanTandaTanganLaporan($koneksi, $pengajuan_id, $role_penanda, $user_id, $signature_data)
{
    $roleValid = ['bendahara', 'purchase', 'ketua', 'admin'];
    if (!in_array($role_penanda, $roleValid, true)) {
        return false;
    }

    $pengajuan_id = (int) $pengajuan_id;
    if ($pengajuan_id <= 0) {
        return false; // wajib terkait ke laporan yang valid
    }

    $user_id = $user_id !== null ? (int) $user_id : null;

    $sql  = "INSERT INTO ttd_laporan_sppg (pengajuan_id, role_penanda, user_id, signature_data)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                signature_data = VALUES(signature_data),
                created_at = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'isis', $pengajuan_id, $role_penanda, $user_id, $signature_data);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * Ambil seluruh tanda tangan yang tersimpan untuk satu laporan,
 * dikelompokkan berdasarkan role_penanda.
 * Contoh hasil: ['bendahara' => [...row...], 'ketua' => [...row...]]
 */
function getTandaTanganLaporan($koneksi, $pengajuan_id)
{
    $pengajuan_id = (int) $pengajuan_id;

    $sql  = "SELECT * FROM ttd_laporan_sppg WHERE pengajuan_id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $pengajuan_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[$row['role_penanda']] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Ambil label tampilan yang enak dibaca untuk sebuah role.
 */
function labelRole($role)
{
    $map = [
        'admin'     => 'Admin',
        'bendahara' => 'Bendahara',
        'purchase'  => 'Purchasing',
        'ketua'     => 'Ketua',
    ];
    return $map[$role] ?? ucfirst($role);
}

/**
 * Daftar role yang boleh tanda tangan beserta labelnya, urutan tampil
 * di grid status TTD. Key HARUS sama persis dengan $_SESSION['role'].
 */
function daftarRoleTtdSppg()
{
    return [
        'admin'     => labelRole('admin'),
        'purchase'  => labelRole('purchase'),
        'ketua'     => labelRole('ketua'),
        'bendahara' => labelRole('bendahara'),
    ];
}

/**
 * Hapus tanda tangan sebuah role untuk sebuah laporan.
 * Dipakai tombol "Hapus Tanda Tangan" di modal (opsi selain "Ganti").
 */
function hapusTandaTanganLaporan($koneksi, $pengajuan_id, $role_penanda)
{
    $pengajuan_id = (int) $pengajuan_id;

    $sql  = "DELETE FROM ttd_laporan_sppg WHERE pengajuan_id = ? AND role_penanda = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $pengajuan_id, $role_penanda);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}
