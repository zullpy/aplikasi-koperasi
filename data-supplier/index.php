<?php 
require_once '../database/auth.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Supplier | Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php $activePage = 'data-supplier'; include '../components/navbar.php'; ?>

    <header class="page-header">
        <div class="page-header-left">
            <div class="header-icon"><i class="ti ti-building-store"></i></div>
            <div>
                <div class="header-title">Data Supplier</div>
                <div class="header-subtitle">Kelola daftar supplier</div>
            </div>
        </div>
        <a href="../data-pelanggan/index.php" class="header-back">
            <i class="ti ti-users"></i> Ke Pelanggan
        </a>
    </header>

    <main class="main-content">
        <div class="toolbar">
            <div class="toolbar-left">
                <span class="section-title">Supplier</span>
                <span class="count-badge" id="count-badge">0</span>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <div class="search-box">
                    <i class="ti ti-search"></i>
                    <input type="text" id="search-input" placeholder="Cari supplier..." />
                </div>
                <button class="btn-add" onclick="openModal()">
                    <i class="ti ti-plus"></i> Tambah Supplier
                </button>
            </div>
        </div>
        <div class="card-grid" id="card-grid"></div>
    </main>

    <!-- MODAL -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-icon" id="modal-icon"><i class="ti ti-building-store"></i></div>
                    <span class="modal-title" id="modal-title">Tambah Supplier</span>
                </div>
                <button class="modal-close" onclick="closeModal()"><i class="ti ti-x"></i></button>
            </div>
            <div class="form-group">
                <label class="form-label" for="f-nama">Nama Supplier</label>
                <input type="text" id="f-nama" placeholder="Contoh: CV Maju Bersama" />
            </div>
            <div class="form-group">
                <label class="form-label" for="f-kontak">No. Kontak</label>
                <input type="number" id="f-kontak" placeholder="Contoh: 081234567890" />
            </div>
            <div class="form-group">
                <label class="form-label" for="f-alamat">Alamat</label>
                <textarea id="f-alamat" rows="3" placeholder="Masukkan alamat lengkap..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Batal</button>
                <button class="btn-save" id="btn-save" onclick="saveData()">Simpan</button>
            </div>
        </div>
    </div>

    <!-- CONFIRM DELETE -->
    <div class="confirm-overlay" id="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon"><i class="ti ti-trash"></i></div>
            <div class="confirm-title">Hapus Supplier?</div>
            <div class="confirm-msg">
                Data <strong id="confirm-name"></strong> akan dihapus secara permanen.
            </div>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="closeConfirm()">Batal</button>
                <button class="btn-confirm-delete" id="btn-delete" onclick="doDelete()">Hapus</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script src="script.js"></script>
    <script>
        initPage({
            apiUrl: '../database/add-supplier.php',  /* path dari data-supplier/ ke database/ */
            type: 'supplier'
        });
    </script>
</body>
</html>