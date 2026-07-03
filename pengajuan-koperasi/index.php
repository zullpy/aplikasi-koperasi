<?php 
require_once '../database/auth.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Anggaran Koperasi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
</head>

<body>
    <?php $activePage = 'pengajuan-koperasi';
    include '../components/navbar.php'; ?>
    <div class="wrap">
        <div class="top-bar">
            <h1>Pengajuan Anggaran</h1>
            <div class="add-btn-group">
                <button class="btn add-type-btn btn-stok" onclick="openAdd('stok')"><i class="ti ti-package"></i> Stok</button>
                <button class="btn add-type-btn btn-peralatan" onclick="openAdd('peralatan')"><i class="ti ti-tools"></i> Peralatan</button>
                <button class="btn add-type-btn btn-operasional" onclick="openAdd('operasional')"><i class="ti ti-settings"></i> Operasional</button>
            </div>
        </div>

        <div class="search-bar">
            <label>Filter tanggal:</label>
            <input type="date" id="filterFrom" onchange="render()">
            <span>s/d</span>
            <input type="date" id="filterTo" onchange="render()">
            <button class="btn sm" onclick="clearFilter()">Reset</button>
        </div>

        <div id="list"></div>
    </div>

    <!-- MODAL TAMBAH / EDIT -->
    <div class="overlay" id="modalAdd" style="display:none" onclick="closeOnBg(event, 'modalAdd')">
        <div class="modal modal-wide">
            <div class="modal-header">
                <h3><i id="modalAddIcon" class="ti ti-package"></i> <span id="modalAddTitle">Tambah Pengajuan Stok</span></h3>
                <button class="btn sm" onclick="closeModal('modalAdd')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <input type="hidden" id="fJenis">

                <div class="field-row">
                    <div class="field">
                        <label>Tanggal</label>
                        <input type="date" id="fTanggal" style="width:100%">
                    </div>
                </div>
                <div class="field">
                    <label id="labelTujuan">Tujuan Pembelian</label>
                    <input type="text" id="fTujuan" placeholder="Contoh: Pembelian sembako minggu ini..." style="width:100%">
                </div>

                <!-- Section Items (Stok & Peralatan) -->
                <div id="sectionItems">
                    <div class="items-table-wrap">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:40px"></th>
                                    <th id="thNamaBarang">Nama Barang</th>
                                    <th style="width:80px;text-align:center">Sisa Stok</th>
                                    <th style="width:70px;text-align:center">Satuan</th>
                                    <th style="width:70px">Qty</th>
                                    <th>Harga Satuan (Rp)</th>
                                    <th style="width:110px;text-align:right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="itemRows"></tbody>
                        </table>
                    </div>
                    <button class="btn sm add-row-btn" onclick="addItemRow()"><i class="ti ti-plus"></i> <span id="labelTambahItem">Tambah Barang</span></button>

                    <div class="total-bar">
                        <span class="total-label">Total Pengajuan</span>
                        <span class="total-value" id="grandTotal">Rp 0</span>
                    </div>
                </div>

                <!-- Section Operasional -->
                <div id="sectionOperasional" style="display:none">
                    <div class="field">
                        <label>Anggaran yang Diajukan (Rp)</label>
                        <input type="text" id="fAnggaran" placeholder="0" inputmode="numeric" oninput="onAnggaranInput(this)" style="width:100%">
                    </div>
                    <div class="total-bar">
                        <span class="total-label">Total Pengajuan</span>
                        <span class="total-value" id="grandTotalOps">Rp 0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalAdd')">Batal</button>
                <button class="btn primary" onclick="saveItem()">Simpan Pengajuan</button>
            </div>
        </div>
    </div>

    <!-- MODAL APPROVAL -->
    <div class="overlay" id="modalApproval" style="display:none" onclick="closeOnBg(event, 'modalApproval')">
        <div class="modal">
            <div class="modal-header">
                <h3>Approval Pengajuan</h3>
                <button class="btn sm" onclick="closeModal('modalApproval')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvalId">
                <div class="info-box">
                    <div class="info-label">Keterangan</div>
                    <div class="info-value" id="approvalDesc">-</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Total diajukan</div>
                    <div class="info-value accent" id="approvalJumlah">Rp 0</div>
                </div>

                <div class="field">
                    <label>Keputusan</label>
                    <div class="keputusan-wrap">
                        <button class="keputusan-btn" id="btnSetuju" onclick="pilihKeputusan('approved')">
                            <i class="ti ti-check"></i> Setuju
                        </button>
                        <button class="keputusan-btn" id="btnTolak" onclick="pilihKeputusan('rejected')">
                            <i class="ti ti-x"></i> Tidak Setuju
                        </button>
                    </div>
                </div>

                <div id="approvedFields" class="approval-fields">
                    <div class="field">
                        <label>Saldo masuk anggaran (Rp)</label>
                        <input type="text" id="aSaldo" placeholder="0" inputmode="numeric" oninput="onSaldoInput(this)" style="width:100%">
                    </div>
                    <div class="field">
                        <label>Bukti transfer</label>
                        <div id="aBuktiExist" style="display:none">
                            <p style="font-size:12px;color:var(--text-success);margin-bottom:6px"><i class="ti ti-file-check"></i> <span id="aBuktiNote"></span></p>
                            <img id="aBuktiImg" class="img-preview" style="display:none">
                            <label class="btn sm" style="margin-top:8px">
                                <i class="ti ti-upload"></i> Ganti bukti TF
                                <input type="file" id="aBuktiFile" accept="image/*" onchange="previewBukti()" style="display:none">
                            </label>
                        </div>
                        <div id="aBuktiEmpty" style="display:block">
                            <label class="upload-area" style="display:block">
                                <i class="ti ti-upload" style="font-size:24px;color:var(--text-muted)"></i>
                                <p id="buktiLabel">Klik untuk upload bukti transfer</p>
                                <input type="file" id="aBuktiFile" accept="image/*" onchange="previewBukti()" style="display:none">
                            </label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Catatan (opsional)</label>
                        <textarea id="aCatatan" style="width:100%"></textarea>
                    </div>
                </div>

                <div id="rejectedFields" class="approval-fields">
                    <div class="field">
                        <label>Alasan penolakan</label>
                        <textarea id="aAlasan" style="width:100%"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalApproval')">Batal</button>
                <button class="btn primary" id="btnSimpanApproval" style="display:none" onclick="saveApproval()">Simpan keputusan</button>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BUKTI TF -->
    <div class="overlay" id="modalBukti" style="display:none" onclick="closeOnBg(event, 'modalBukti')">
        <div class="modal">
            <div class="modal-header">
                <h3>Detail Approval</h3>
                <button class="btn sm" onclick="closeModal('modalBukti')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <div class="info-label">Saldo masuk</div>
                    <div class="info-value accent" id="viewSaldo">Rp 0</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Disetujui</div>
                    <div class="info-value" id="viewTgl">-</div>
                </div>
                <div class="field">
                    <label>Bukti transfer</label>
                    <img id="viewBuktiImg" class="img-preview" style="display:none">
                    <p id="viewBuktiNote" style="font-size:12px;color:var(--text-secondary);margin-top:6px"></p>
                </div>
                <div id="viewCatatan" class="info-box" style="display:none">
                    <div class="info-label">Catatan</div>
                    <div class="info-value" id="viewCatatanText"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalBukti')">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL PENGAJUAN -->
    <div class="overlay" id="modalDetail" style="display:none" onclick="closeOnBg(event, 'modalDetail')">
        <div class="modal">
            <div class="modal-header">
                <h3>Detail Pengajuan</h3>
                <button class="btn sm" onclick="closeModal('modalDetail')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body" id="detailContent"></div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalDetail')">Tutup</button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL TANDA TANGAN DIGITAL (TAMBAHAN BARU) ===== -->
    <div class="overlay" id="modalSignature" style="display:none" onclick="closeOnBg(event,'modalSignature')">
        <div class="modal modal-wide" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3><i class="ti ti-pencil"></i> Tanda Tangan Digital</h3>
                <button class="btn sm" onclick="closeModal('modalSignature')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <div class="info-label">Pengajuan</div>
                    <div class="info-value" id="sigPengajuanTitle">-</div>
                    <div class="info-label" style="margin-top:8px">Total Diajukan</div>
                    <div class="info-value accent" id="sigPengajuanTotal">Rp 0</div>
                </div>

                <div class="field">
                    <label>Penandatangan</label>
                    <select id="sigRole" onchange="prepareSignatureCanvas()" style="width:100%" disabled>
                        <!-- <option value="">-- Pilih Penandatangan --</option> -->
                        <option value="ketua">Yudi Hendrian (Ketua Koperasi)</option>
                        <option value="bendahara">Nancy Febi Yolla (Bendahara)</option>
                        <option value="admin">Evin Yentiana (Admin)</option>
                    </select>
                </div>

                <div id="sigCanvasArea" style="display:none;">
                    <div class="field">
                        <label>Area Tanda Tangan</label>
                        <div style="border:2px dashed var(--border-strong,#ccc);border-radius:8px;background:#fff;touch-action:none;">
                            <canvas id="signatureCanvas" width="800" height="250" style="width:100%;height:250px;cursor:crosshair;display:block;"></canvas>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:8px;">
                            <div id="sigBtnArea" style="display:flex;gap:8px;margin-top:8px;">
                                <button class="btn sm danger" onclick="clearSignatureCanvas()"><i class="ti ti-trash"></i> Hapus TTD</button>
                                <button class="btn sm primary" onclick="saveSignature()"><i class="ti ti-check"></i> Simpan Tanda Tangan</button>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Status Tanda Tangan</label>
                        <div id="sigStatusList" style="font-size:13px;color:var(--text-secondary);min-height:24px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Datalist untuk Autocomplete Stok -->
    <datalist id="stokList"></datalist>
    <script>
        const SESSION_ROLE = "<?php echo $_SESSION['role'] ?? ''; ?>";
    </script>
    <script src="script.js"></script>
    <?php include '../components/made-by.php'; ?>
</body>

</html>