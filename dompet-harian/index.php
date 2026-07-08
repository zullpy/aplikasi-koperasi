<?php
require_once '../database/auth.php';
$userRole = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dompet Belanja Harian — SPPG</title>
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <?php $activePage = 'dompet-belanja-harian';
    include '../components/navbar.php'; ?>
    
    <!-- HERO -->
    <section class="page-hero">
        <div class="page-hero-inner">
            <div>
                <div class="page-hero-eyebrow">SPPG</div>
                <h1 class="page-hero-title">Dompet Belanja Harian</h1>
                <p class="page-hero-desc">Catat dan kelola pengeluaran belanja dapur harian dengan mudah.</p>
            </div>
            <div class="page-hero-icon">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="30" stroke="rgba(255,255,255,0.25)" stroke-width="2" />
                    <path d="M20 24h24l-3 18H23L20 24z" stroke="#fff" stroke-width="2" stroke-linejoin="round" />
                    <circle cx="26" cy="48" r="2" fill="#fff" />
                    <circle cx="38" cy="48" r="2" fill="#fff" />
                    <path d="M16 20h4" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                </svg>
            </div>
        </div>
    </section>
    <!-- MAIN -->
    <main class="app-main">
        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="search-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.6" />
                    <path d="M12.5 12.5L16 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Cari nama menu..." />
                <button id="searchClear" class="search-clear" aria-label="Bersihkan">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M4 4L12 12M12 4L4 12" stroke="#64748b" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <?php if (in_array($userRole, ['bendahara', 'ketua'])): ?>
            <a href="approve.php" class="btn-approval">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M2 8l4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Approval
            </a>
            <?php endif; ?>
            <?php if ($userRole === 'admin'): ?>
                <button id="btnOpenModal" class="btn-add">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 3V13M3 8H13" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    Tambah Estimasi Belanja
                </button>
            <?php endif; ?>
        </div>
        <div id="searchInfo" class="search-info"></div>
        <!-- TABLE CONTAINER (grouped by date > menu) -->
        <div id="tableContainer" class="table-container"></div>
        <!-- EMPTY STATE -->
        <div id="emptyState" class="empty-state" style="display:none;">
            <div class="empty-icon-wrap">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <path d="M8 10h20l-2 16H10L8 10z" stroke="#2563a8" stroke-width="2" stroke-linejoin="round" />
                    <path d="M13 10V8a5 5 0 0 1 10 0v2" stroke="#2563a8" stroke-width="2" stroke-linecap="round" />
                </svg>
            </div>
            <div class="empty-title">Belum ada data belanja</div>
            <div class="empty-desc">Klik "Tambah Belanja" untuk mulai mencatat pengeluaran harian.</div>
        </div>
    </main>
    <?php if ($userRole !== 'purchase'): ?>
    <!-- ============ MODAL BELANJA ============ -->
    <div id="modalOverlay" class="modal-overlay">
        <div class="modal modal-wide">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <path d="M3 5h12l-1.5 9h-9L3 5z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M6 5V4a3 3 0 0 1 6 0v1" stroke="#fff" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div class="modal-title" id="modalTitle">Tambah Belanja Harian</div>
                </div>
                <button id="btnCloseModal" class="modal-close" aria-label="Tutup">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 2L12 12M12 2L2 12" stroke="#fff" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <!-- Header Transaksi -->
                <div class="form-section-title">Informasi Transaksi</div>
                <div class="form-group">
                    <label class="form-label" for="inputTanggal">Tanggal</label>
                    <div class="input-icon-wrapper">
                        <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <rect x="2" y="3" width="12" height="11" rx="1.5" stroke="#94a3b8" stroke-width="1.4" />
                            <path d="M2 7h12M5 1.5v3M11 1.5v3" stroke="#94a3b8" stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                        <input type="date" id="inputTanggal" class="form-input has-icon" />
                    </div>
                    <div id="errorTanggal" class="form-error"></div>
                </div>
                <div class="form-row form-row-menu">
                    <div class="form-group form-group-menu">
                        <label class="form-label" for="inputNamaMenu">Nama Menu</label>
                        <input type="text" id="inputNamaMenu" class="form-input" placeholder="cth: Rolade Telur" />
                        <div id="errorNamaMenu" class="form-error"></div>
                    </div>
                    <div class="form-group form-group-porsi">
                        <label class="form-label" for="inputPorsi">Porsi</label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M2 8c0-3 2.5-5 6-5s6 2 6 5-2.5 5-6 5-6-2-6-5z" stroke="#94a3b8" stroke-width="1.4" />
                                <path d="M5 8h6" stroke="#94a3b8" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                            <input type="text" id="inputPorsi" class="form-input has-icon" placeholder="cth: 3 orang" />
                        </div>
                    </div>
                </div>
                <!-- Daftar Barang -->
                <div class="form-section-title">
                    <span>Daftar Barang</span>
                    <button type="button" class="btn-mini btn-mini-primary" id="btnAddBarangRow">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        Tambah Baris
                    </button>
                </div>
                <div id="barangList" class="barang-list"></div>
                <div id="errorBarang" class="form-error"></div>
                <!-- Subtotal -->
                <div class="subtotal-preview">
                    <div class="subtotal-label">Total Estimasi</div>
                    <div id="subtotalValue" class="subtotal-value">Rp 0</div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnCancel" class="btn-cancel">Batal</button>
                <button id="btnSave" class="btn-save">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($userRole !== 'purchase'): ?>
    <!-- ============ MODAL TOLAK ============ -->
    <div id="rejectModal" class="modal-overlay">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon" style="background:rgba(239,68,68,0.25);">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <path d="M9 3L9 10M9 13v.5" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                            <circle cx="9" cy="9" r="7" stroke="#fff" stroke-width="1.5" />
                        </svg>
                    </div>
                    <div class="modal-title">Alasan Penolakan</div>
                </div>
                <button id="btnCancelReject" class="modal-close" aria-label="Tutup">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 2L12 12M12 2L2 12" stroke="#fff" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectTargetId" />
                <div class="form-group">
                    <label class="form-label">Tuliskan alasan penolakan pengajuan ini</label>
                    <textarea
                        id="rejectionReason"
                        class="form-input"
                        rows="4"
                        placeholder="Contoh: Harga melebihi anggaran yang ditetapkan..."
                        style="resize:vertical; font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnCancelReject" class="btn-cancel">Batal</button>
                <button id="btnConfirmReject" class="btn-save" style="background:linear-gradient(135deg,#ef4444,#b91c1c); box-shadow:0 4px 14px rgba(239,68,68,0.35);">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 2L12 12M12 2L2 12" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    Konfirmasi Tolak
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- ============ MODAL PREVIEW NOTA ============ -->
    <div id="notaModalOverlay" class="modal-overlay">
        <div class="modal modal-nota">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <rect x="1" y="2.5" width="16" height="13" rx="1.5" stroke="#fff" stroke-width="1.5" />
                            <circle cx="5.5" cy="8" r="1.5" fill="#fff" />
                            <path d="M1 15l5-5 3 3 2.5-2.5L17 15" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="modal-title" id="notaModalTitle">Preview Nota</div>
                </div>
                <button id="btnCloseNotaModal" class="modal-close" aria-label="Tutup">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 2L12 12M12 2L2 12" stroke="#fff" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="modal-body nota-modal-body" id="notaModalBody">
                <!-- injected by JS -->
            </div>
            <div class="modal-footer">
                <button onclick="closeNotaModal()" class="btn-cancel">Tutup</button>
            </div>
        </div>
    </div>
    <!-- ============ MODAL UPLOAD NOTA PER ITEM ============ -->
    <div id="uploadNotaModalOverlay" class="modal-overlay">
        <div class="modal modal-nota">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <path d="M9 12V4M6 7l3-3 3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M3 14h12" stroke="#fff" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div class="modal-title" id="uploadNotaModalTitle">Upload Nota</div>
                </div>
                <button id="btnCloseUploadNotaModal" class="modal-close" aria-label="Tutup">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M2 2L12 12M12 2L2 12" stroke="#fff" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="modal-body nota-modal-body" id="uploadNotaModalBody">
                <!-- injected by JS -->
            </div>
            <div class="modal-footer" id="uploadNotaModalFooter">
                <button onclick="closeUploadNotaModal()" class="btn-cancel">Batal</button>
                <button id="btnSubmitUploadNota" class="btn-upload-submit" disabled>
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                        <path d="M7.5 11V3M4.5 6l3-3 3 3" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M2 13h11" stroke="#fff" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                    Upload Nota
                </button>
            </div>
        </div>
    </div>
    <!-- TOAST -->
    <div id="toast" class="toast"></div>
    <script>
        window.CURRENT_USER_ROLE = '<?php echo htmlspecialchars($userRole); ?>';
    </script>
    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>