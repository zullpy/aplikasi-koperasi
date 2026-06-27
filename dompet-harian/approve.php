<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Pengajuan — Bendahara</title>
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="approve.css">
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
            <button class="header-refresh" onclick="fetchData()" title="Refresh Data">
                <i class="ph ph-arrows-clockwise"></i>
            </button>
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

        <!-- ── CARDS GRID (detail langsung tampil) ── -->
        <div class="cards-grid" id="cardsGrid">
            <!-- Diisi oleh JS -->
        </div>

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

    <script src="approve.js"></script>
</body>

</html>