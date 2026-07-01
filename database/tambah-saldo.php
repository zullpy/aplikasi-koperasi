<?php
// ===================== PROSES TAMBAH SALDO MASUK =====================
// File ini di-include di paling atas laporan-sppg.php (sebelum ada output apapun)
// karena pakai header() untuk redirect.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_saldo') {
    $id_pengajuan = (int) $_POST['id_pengajuan'];
    $tambah_saldo = (float) str_replace(['.', ','], ['', '.'], $_POST['tambah_saldo']);

    if ($tambah_saldo > 0 && $id_pengajuan > 0) {

        // ── Proses upload bukti transfer (jika ada file yang dikirim) ──
        $nama_file_baru = null;

        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['bukti_transfer'];
            $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
            $maxSize  = 5 * 1024 * 1024; // 5 MB

            if (in_array($ekstensi, $allowed) && $file['size'] <= $maxSize) {
                $folderUpload = '../uploads/bukti_transfer/';
                if (!is_dir($folderUpload)) {
                    mkdir($folderUpload, 0755, true);
                }

                $nama_file_baru = 'bukti_' . $id_pengajuan . '_' . time() . '_' . uniqid() . '.' . $ekstensi;

                if (!move_uploaded_file($file['tmp_name'], $folderUpload . $nama_file_baru)) {
                    $nama_file_baru = null; // gagal pindah file, jangan simpan nama filenya
                }
            }
        }

        // Update uang_masuk, hitung ulang sisa_uang = uang_masuk - total_belanja,
        // dan simpan nama file bukti transfer jika ada upload baru
        if ($nama_file_baru !== null) {
            $sql = "UPDATE pengajuan_belanja 
                    SET uang_masuk = uang_masuk + ?, 
                        sisa_uang = (uang_masuk + ?) - total_belanja,
                        bukti_transfer = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, 'ddsi', $tambah_saldo, $tambah_saldo, $nama_file_baru, $id_pengajuan);
        } else {
            $sql = "UPDATE pengajuan_belanja 
                    SET uang_masuk = uang_masuk + ?, 
                        sisa_uang = (uang_masuk + ?) - total_belanja 
                    WHERE id = ?";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, 'ddi', $tambah_saldo, $tambah_saldo, $id_pengajuan);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // NOTE: sebelumnya di sini ada bug "../laporan-sppg/index.php.php" (dobel .php),
    // sudah dibetulkan karena file ini di-include langsung di dalam index.php.
    header("Location: index.php?status=success");
    exit;
}
