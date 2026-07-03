<?php require_once '../database/auth.php';?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Pengajuan — Bendahara</title>
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="approve.css">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">

        <!-- ── HEADER ── -->
        <header class="header">
            <div class="header-left">
                <div class="header-icon">
                    <i class="ph ph-clipboard-text"></i>
                </div>
                <div>
                    <h1>Approval Pengajuan Belanja</h1>
                    <p>Dashboard Bendahara · Koperasi Bina Usaha Sauyunan</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a href="index.php" class="header-back" title="Kembali ke Beranda">
                    <i class="ph ph-arrow-left"></i> Kembali
                </a>
            </div>
        </header>

        <!-- ── STATS ── -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon"><i class="ph ph-clock"></i></div>
                <div class="stat-info">
                    <h3 id="countPending">–</h3>
                    <p>Menunggu Approval</p>
                </div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon"><i class="ph ph-check-circle"></i></div>
                <div class="stat-info">
                    <h3 id="countApproved">–</h3>
                    <p>Disetujui</p>
                </div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-icon"><i class="ph ph-x-circle"></i></div>
                <div class="stat-info">
                    <h3 id="countRejected">–</h3>
                    <p>Ditolak</p>
                </div>
            </div>
            <div class="stat-card total">
                <div class="stat-icon"><i class="ph ph-currency-circle-dollar"></i></div>
                <div class="stat-info">
                    <h3 id="totalAmount">–</h3>
                    <p>Total Pengajuan</p>
                </div>
            </div>
        </div>

        <!-- ── TOOLBAR ── -->
        <div class="toolbar">
            <div class="filter-tabs">
                <button class="tab-btn active" data-filter="all">
                    <i class="ph ph-list"></i> Semua
                    <span class="tab-count">0</span>
                </button>
                <button class="tab-btn" data-filter="pending">
                    <i class="ph ph-clock"></i> Pending
                    <span class="tab-count">0</span>
                </button>
                <button class="tab-btn" data-filter="approved">
                    <i class="ph ph-check"></i> Disetujui
                    <span class="tab-count">0</span>
                </button>
                <button class="tab-btn" data-filter="rejected">
                    <i class="ph ph-x"></i> Ditolak
                    <span class="tab-count">0</span>
                </button>
            </div>
        </div>

        <!-- ── CARDS GRID ── -->
        <div class="cards-grid" id="cardsGrid"></div>

        <!-- ── EMPTY STATE ── -->
        <div id="emptyState" class="empty-state">
            <div class="empty-icon"><i class="ph ph-tray"></i></div>
            <h3>Tidak Ada Pengajuan</h3>
            <p>Belum ada data pengajuan belanja untuk filter ini</p>
        </div>

    </div><!-- /container -->


    <!-- ══════════════════════════════════
         MODAL: ALASAN PENOLAKAN
    ══════════════════════════════════ -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>Alasan Penolakan</h2>
                    <p>Isi catatan agar operator dapat memperbaiki pengajuan</p>
                </div>
                <button class="modal-close" onclick="closeRejectModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <label class="form-label">
                    <i class="ph ph-note-pencil"></i>&nbsp; Catatan untuk Operator
                </label>
                <textarea id="rejectionReason" class="form-textarea"
                    placeholder="Contoh: Harga tidak sesuai, mohon konfirmasi ulang dengan supplier..."
                    rows="5"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeRejectModal()">
                    <i class="ph ph-arrow-left"></i> Batal
                </button>
                <button class="btn btn-danger" onclick="confirmReject()">
                    <i class="ph ph-x-circle"></i> Konfirmasi Tolak
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         MODAL: SETUJUI + SALDO MASUK + BUKTI TRANSFER
    ══════════════════════════════════ -->
    <!-- ═══════════════════════════════════════════════════════════
     MODAL APPROVE (Saldo Masuk + Bukti Transfer)
═══════════════════════════════════════════════════════════ -->
    <div id="approveModal" class="modal">
        <div class="modal-content modal-content-lg">
            <div class="modal-header">
                <div>
                    <h2>
                        <i class="ph ph-check-circle" style="color:var(--success)"></i>
                        Konfirmasi Persetujuan
                    </h2>
                    <p id="approveModalSubtitle">Isi saldo masuk dan bukti transfer sebelum menyetujui</p>
                </div>
                <button class="modal-close" onclick="closeApproveModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Info Total Belanja -->
                <div class="approve-info-box">
                    <div class="approve-info-row">
                        <span class="approve-info-label">
                            <i class="ph ph-shopping-cart"></i> Total Belanja
                        </span>
                        <span class="approve-info-value" id="approveInfoTotal">Rp 0</span>
                    </div>
                </div>

                <!-- 💰 Info Sisa dari Menu Sebelumnya -->
                <div class="approve-sisa-sebelumnya" id="approveSisaSebelumnyaRow" style="display:none;">
                    <div class="approve-sisa-sebelumnya-icon">
                        <i class="ph ph-coins" style="font-size:18px;"></i>
                    </div>
                    <div class="approve-sisa-sebelumnya-info">
                        <div class="approve-sisa-sebelumnya-label" id="approveSisaSebelumnya">
                            Sisa dari menu sebelumnya
                        </div>
                        <div class="approve-sisa-sebelumnya-value" id="approveSisaSebelumnyaValue">
                            Rp 0
                        </div>
                    </div>
                </div>

                <!-- Input Uang Masuk -->
                <div class="form-group">
                    <label class="form-label" for="inputUangMasuk">
                        Saldo / Uang Masuk <span class="required">*</span>
                    </label>
                    <div class="input-rp-wrap">
                        <span class="input-rp-prefix">Rp</span>
                        <input
                            type="text"
                            id="inputUangMasuk"
                            class="form-input input-rp"
                            placeholder="0"
                            inputmode="numeric"
                            oninput="onUangMasukInput(this)" />
                    </div>
                </div>

                <!-- Breakdown Total Masuk (Input + Sisa) -->
                <div class="approve-total-masuk-row" id="approveTotalMasukRow" style="display:none;">
                    <span class="approve-total-masuk-label">Total Masuk:</span>
                    <span class="approve-total-masuk-value" id="approveTotalMasuk">Rp 0</span>
                </div>

                <!-- Info Sisa Uang -->
                <div class="approve-info-row" id="approveInfoSisaRow" style="display:none;">
                    <span class="approve-info-label">
                        <i class="ph ph-wallet"></i> Sisa Uang
                    </span>
                    <span class="approve-info-value sisa-value" id="approveInfoSisa">Rp 0</span>
                </div>

                <!-- Upload Bukti Transfer -->
                <div class="form-group">
                    <label class="form-label">
                        Bukti Transfer <span style="color:var(--text-muted);font-weight:400;font-size:12px;">(opsional)</span>
                    </label>
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('inputBuktiTransfer').click()">
                        <input
                            type="file"
                            id="inputBuktiTransfer"
                            accept="image/*,.pdf"
                            style="display:none"
                            onchange="previewFile(this)" />
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <i class="ph ph-upload-simple"></i>
                            <span>Klik atau seret foto/file ke sini</span>
                            <small>JPG, PNG, PDF — maks. 5 MB</small>
                        </div>
                        <div class="upload-preview" id="uploadPreview" style="display:none;">
                            <img id="previewImg" src="" alt="Preview" style="display:none;" />
                            <div class="upload-preview-info">
                                <span id="previewName"></span>
                                <button class="btn-remove-file" onclick="removeFile(event)">
                                    <i class="ph ph-x-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeApproveModal()">Batal</button>
                <button class="btn btn-success" id="btnKonfirmasiSetujui" onclick="submitApprove()">
                    <i class="ph ph-check-circle"></i> Konfirmasi Setujui
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         MODAL: TANDA TANGAN
    ══════════════════════════════════ -->
    <div id="ttdModal" class="modal">
        <div class="modal-content modal-content-lg">
            <div class="modal-header">
                <div>
                    <h2>Tanda Tangan Digital</h2>
                    <p id="ttdModalSubtitle">Tanda tangan sebagai <strong></strong></p>
                </div>
                <button class="modal-close" onclick="closeTtdModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="ttd-info-box">
                    <i class="ph ph-info"></i>
                    <span>Gambar tanda tangan Anda di area bawah ini menggunakan mouse atau sentuhan layar</span>
                </div>
                <canvas id="ttdCanvas" class="ttd-canvas" width="520" height="200"></canvas>
                <div class="ttd-canvas-actions">
                    <button class="btn btn-ghost btn-sm" onclick="clearCanvas()">
                        <i class="ph ph-eraser"></i> Hapus
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeTtdModal()">
                    <i class="ph ph-arrow-left"></i> Batal
                </button>
                <button class="btn btn-primary" id="btnSimpanTtd" onclick="saveTtd()">
                    <i class="ph ph-pen-nib"></i> Simpan Tanda Tangan
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         PDF PREVIEW (hidden, for export)
    ══════════════════════════════════ -->
    <div id="pdfPreview" style="display:none;"></div>

    <script src="approve.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>