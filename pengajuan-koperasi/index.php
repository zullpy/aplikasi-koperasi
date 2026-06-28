<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Anggaran Koperasi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php $activePage = 'pengajuan-koperasi';
    include '../components/navbar.php'; ?>

    <h2 class="sr-only">Menu pengajuan anggaran koperasi dengan tombol keputusan approval.</h2>
    <div class="wrap">
        <div class="top-bar">
            <h1><i class="ti ti-cash" aria-hidden="true"></i> Pengajuan Anggaran</h1>
            <button class="btn primary" onclick="openAdd()"><i class="ti ti-plus" aria-hidden="true"></i> Pengajuan Anggaran</button>
        </div>
        <div class="search-bar">
            <label>Filter tanggal:</label>
            <input type="date" id="filterFrom" onchange="render()">
            <span style="color:var(--text-muted);font-size:13px">s/d</span>
            <input type="date" id="filterTo" onchange="render()">
            <button class="btn sm" onclick="clearFilter()"><i class="ti ti-x" aria-hidden="true"></i> Reset</button>
        </div>
        <div id="list"></div>
    </div>

    <!-- Modal Tambah/Edit -->
    <div id="modalAdd" style="display:none" class="overlay" onclick="closeOnBg(event,'modalAdd')">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalAddTitle">Tambah Pengajuan</h3>
                <button class="btn sm" onclick="closeModal('modalAdd')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="field">
                    <label>Jenis</label>
                    <select id="fJenis" onchange="toggleJenis()">
                        <option value="stok">Stok</option>
                        <option value="lainlain">Lain-lain</option>
                    </select>
                </div>
                <div class="field">
                    <label>Tanggal</label>
                    <input type="date" id="fTanggal">
                </div>
                <div class="field">
                    <label>Keterangan</label>
                    <input type="text" id="fKeterangan" placeholder="Contoh: Beli beras 10 kg / Beli printer">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label>Jumlah yang Diajukan (Rp)</label>
                        <input type="number" id="fJumlah" placeholder="0">
                    </div>
                    <div class="field">
                        <label>Qty</label>
                        <input type="number" id="fQty" placeholder="0">
                    </div>
                </div>
                <div class="field" id="notaWrap">
                    <label>Upload Nota (Stok)</label>
                    <div class="upload-area" onclick="document.getElementById('fNota').click()">
                        <i class="ti ti-file-upload" style="font-size:24px;color:var(--text-muted)" aria-hidden="true"></i>
                        <p id="notaLabel">Klik untuk upload nota</p>
                    </div>
                    <input type="file" id="fNota" accept="image/*,.pdf" style="display:none" onchange="previewNota()">
                    <img id="notaPreview" style="display:none" class="img-preview">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalAdd')">Batal</button>
                <button class="btn" id="btnSimpanLagi" onclick="saveItem(true)" style="display:none"><i class="ti ti-plus" aria-hidden="true"></i> Simpan & Tambah Lagi</button>
                <button class="btn primary" onclick="saveItem(false)"><i class="ti ti-check" aria-hidden="true"></i> Simpan</button>
            </div>
        </div>
    </div>

    <!-- Modal Approval -->
    <div id="modalApproval" style="display:none" class="overlay" onclick="closeOnBg(event,'modalApproval')">
        <div class="modal">
            <div class="modal-header">
                <h3>Approval Pengajuan</h3>
                <button class="btn sm" onclick="closeModal('modalApproval')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvalId">

                <!-- Info pengajuan -->
                <div class="info-box">
                    <p class="info-label">Keterangan</p>
                    <p class="info-value" id="approvalDesc"></p>
                    <p class="info-label" style="margin-top:6px">Jumlah diajukan</p>
                    <p class="info-value accent" id="approvalJumlah"></p>
                </div>

                <!-- Tombol keputusan -->
                <div class="field">
                    <label>Keputusan</label>
                    <div class="keputusan-wrap">
                        <button class="keputusan-btn" id="btnSetuju" onclick="pilihKeputusan('approved')">
                            <i class="ti ti-circle-check" aria-hidden="true"></i>
                            Setuju
                        </button>
                        <button class="keputusan-btn" id="btnTolak" onclick="pilihKeputusan('rejected')">
                            <i class="ti ti-circle-x" aria-hidden="true"></i>
                            Tidak Setuju
                        </button>
                    </div>
                </div>

                <!-- Fields jika setuju -->
                <div class="approval-fields" id="approvedFields">
                    <hr class="divider">
                    <div class="field">
                        <label>Saldo masuk anggaran (Rp)</label>
                        <input type="number" id="aSaldo" placeholder="Nominal yang dicairkan" style="width:100%">
                    </div>
                    <div class="field">
                        <label>Bukti transfer</label>
                        <div id="aBuktiExist" style="display:none">
                            <img id="aBuktiImg" class="img-preview" style="display:none">
                            <p id="aBuktiNote" style="font-size:12px;color:var(--text-secondary);margin-top:6px"></p>
                            <button class="btn sm" style="margin-top:8px" onclick="document.getElementById('aBuktiFile').click()"><i class="ti ti-upload" aria-hidden="true"></i> Ganti bukti TF</button>
                        </div>
                        <div id="aBuktiEmpty">
                            <div class="upload-area" onclick="document.getElementById('aBuktiFile').click()">
                                <i class="ti ti-upload" style="font-size:24px;color:var(--text-muted)" aria-hidden="true"></i>
                                <p id="buktiLabel">Klik untuk upload bukti transfer</p>
                            </div>
                        </div>
                        <input type="file" id="aBuktiFile" accept="image/*" style="display:none" onchange="previewBukti()">
                    </div>
                    <div class="field">
                        <label>Catatan (opsional)</label>
                        <textarea id="aCatatan" placeholder="Tambahkan catatan jika diperlukan..." style="width:100%"></textarea>
                    </div>
                </div>

                <!-- Fields jika ditolak -->
                <div class="approval-fields" id="rejectedFields">
                    <hr class="divider">
                    <div class="field">
                        <label>Alasan penolakan</label>
                        <textarea id="aAlasan" placeholder="Tuliskan alasan penolakan..." style="width:100%"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalApproval')">Batal</button>
                <button class="btn primary" id="btnSimpanApproval" onclick="saveApproval()" style="display:none">Simpan keputusan</button>
            </div>
        </div>
    </div>

    <!-- Modal Lihat Bukti TF -->
    <div id="modalBukti" style="display:none" class="overlay" onclick="closeOnBg(event,'modalBukti')">
        <div class="modal" style="max-width:420px">
            <div class="modal-header">
                <h3>Detail Approval</h3>
                <button class="btn sm" onclick="closeModal('modalBukti')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="field-row" style="margin-bottom:12px">
                    <div>
                        <p style="font-size:12px;color:var(--text-secondary)">Saldo masuk</p>
                        <p style="font-size:16px;font-weight:500;color:var(--text-success)" id="viewSaldo"></p>
                    </div>
                    <div>
                        <p style="font-size:12px;color:var(--text-secondary)">Disetujui</p>
                        <p style="font-size:13px;font-weight:500" id="viewTgl"></p>
                    </div>
                </div>
                <p style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Bukti transfer</p>
                <img id="viewBuktiImg" class="img-preview" style="display:none">
                <p id="viewBuktiNote" style="font-size:13px;color:var(--text-muted)"></p>
                <div id="viewCatatan" style="margin-top:10px;display:none">
                    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">Catatan</p>
                    <p id="viewCatatanText" style="font-size:13px"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('modalBukti')">Tutup</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>