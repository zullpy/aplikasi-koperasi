/* ===================================================
   Profit Koperasi — script.js
   =================================================== */

/* ── Modal helpers ── */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

/* Close on overlay click */
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) closeModal(this.id);
    });
});

/* Close on ESC */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
});

/* ── Button: Input Data (modal gabungan) ── */
const btnInputData = document.getElementById('btnInputData');
if (btnInputData) {
    btnInputData.addEventListener('click', () => {
        resetUploadArea('uploadAreaProfit', 'previewProfit', 'uploadTextProfit', 'fileProfit');
        resetUploadArea('uploadAreaPajakData', 'previewPajakData', 'uploadTextPajakData', 'filePajakData');
        openModal('modalInputData');
    });
}

/* ── Helper: Parse Bukti list JS ── */
function parseBuktiListJs(str) {
    if (!str) return [];
    try {
        let decoded = JSON.parse(str);
        if (Array.isArray(decoded)) return decoded;
    } catch (e) { }
    if (str.includes(',')) return str.split(',').map(s => s.trim());
    return [str.trim()];
}

/* ── Modal: Edit Data ── */
function openModalEdit(data) {
    document.getElementById('editRecordId').value = data.id;
    document.getElementById('edit_tanggal').value = data.tanggal;
    document.getElementById('edit_profit_nominal').value = data.profit > 0 ? formatRupiah(Math.round(data.profit)) : '';
    document.getElementById('edit_pajak_nominal').value = data.pajak > 0 ? formatRupiah(Math.round(data.pajak)) : '';
    document.getElementById('edit_keterangan').value = data.keterangan || '';

    openModal('modalEditData');
}

/* ── Modal: Input Pajak ── */
function openModalPajak(recordId, currentPajak) {
    document.getElementById('pajakRecordId').value = recordId;
    const inputPajak = document.getElementById('pajak_nominal');
    inputPajak.value = currentPajak > 0 ? formatRupiah(currentPajak) : '';
    resetUploadArea('uploadAreaPajak', 'previewPajak', 'uploadTextPajak', 'filePajak');
    openModal('modalPajak');
}

/* ── Modal: Upload / Ganti Bukti ── */
function openUploadBukti(recordId, jenis, isGanti = false) {
    document.getElementById('uploadBuktiRecordId').value = recordId;
    document.getElementById('uploadBuktiJenis').value = jenis;

    const title = document.getElementById('uploadBuktiTitle');
    const actionText = isGanti ? 'Ganti' : 'Upload';

    title.textContent = jenis === 'pajak' ? `${actionText} Bukti Transfer Pajak` : `${actionText} Bukti Transfer Profit`;

    // Toggle file inputs
    const fileProfit = document.getElementById('fileExtra');
    const filePajak = document.getElementById('fileExtraPajak');
    fileProfit.style.display = jenis === 'pajak' ? 'none' : '';
    filePajak.style.display = jenis === 'pajak' ? '' : 'none';

    // Make upload area click the right input
    const uploadArea = document.getElementById('uploadAreaExtra');
    uploadArea.onclick = () => {
        (jenis === 'pajak' ? filePajak : fileProfit).click();
    };

    resetUploadArea('uploadAreaExtra', 'previewExtra', 'uploadTextExtra', jenis === 'pajak' ? 'fileExtraPajak' : 'fileExtra');
    openModal('modalUploadBukti');
}

/* ── Modal: Pratinjau Bukti (Gallery Multi-foto) ── */
function openPreviewBukti(files, titleText) {
    if (!files) files = [];
    if (typeof files === 'string') {
        try { files = JSON.parse(files); } catch (e) { files = [files]; }
    }
    if (!Array.isArray(files)) files = [files];

    const titleEl = document.getElementById('modalPreviewTitle');
    if (titleEl) {
        titleEl.innerHTML = `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> ${titleText || 'Pratinjau Bukti Transfer'} (${files.length} File)`;
    }

    const gallery = document.getElementById('previewBuktiGallery');
    if (gallery) {
        gallery.innerHTML = '';
        files.forEach((file, idx) => {
            const ext = file.split('.').pop().toLowerCase();
            const item = document.createElement('div');
            item.className = 'preview-gallery-item';

            if (ext === 'pdf') {
                item.innerHTML = `
                    <div class="preview-pdf-box">
                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span>Dokumen PDF ${files.length > 1 ? '#' + (idx + 1) : ''}</span>
                        <a href="../uploads/bukti_profit/${file}" target="_blank" class="btn-lihat-bukti">Buka PDF ↗</a>
                    </div>
                `;
            } else {
                item.innerHTML = `
                    <div class="preview-img-wrap">
                        <img src="../uploads/bukti_profit/${file}" alt="Bukti ${idx + 1}" loading="lazy">
                        <div class="preview-img-bar">
                            <span>Bukti #${idx + 1}</span>
                            <a href="../uploads/bukti_profit/${file}" target="_blank" class="btn-link-sm">Buka Ukuran Penuh ↗</a>
                        </div>
                    </div>
                `;
            }
            gallery.appendChild(item);
        });
    }

    openModal('modalPreviewBukti');
}

/* ── Hapus ── */
function konfirmasiHapus(recordId) {
    document.getElementById('hapusRecordId').value = recordId;
    openModal('modalHapus');
}

/* ── File Preview ── */
function previewFile(input, previewId, textId) {
    const preview = document.getElementById(previewId);
    const textEl = document.getElementById(textId);
    if (!input.files || input.files.length === 0) return;

    if (input.files.length === 1) {
        const file = input.files[0];

        if (file.type === 'application/pdf') {
            if (textEl) textEl.innerHTML = `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:4px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <strong>${file.name}</strong><br><small>${(file.size / 1024).toFixed(1)} KB</small>`;
            if (preview) preview.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            if (preview) { preview.src = e.target.result; preview.style.display = 'block'; }
            if (textEl) textEl.innerHTML = `<small>${file.name}</small>`;
        };
        reader.readAsDataURL(file);
    } else {
        const names = Array.from(input.files).map(f => f.name).join(', ');
        if (textEl) {
            textEl.innerHTML = `<strong>${input.files.length} file dipilih:</strong><br><small style="word-break:break-all">${names}</small>`;
        }
        if (preview) preview.style.display = 'none';
    }
}

function resetUploadArea(areaId, previewId, textId, fileInputId) {
    const preview = document.getElementById(previewId);
    const textEl = document.getElementById(textId);
    const fileIn = document.getElementById(fileInputId);
    if (preview) { preview.src = ''; preview.style.display = 'none'; }
    if (textEl) textEl.innerHTML = 'Klik atau seret file ke sini<br><small>JPG, PNG, WEBP, PDF — maks 5MB/file</small>';
    if (fileIn) fileIn.value = '';
}

/* ── Format Rupiah input ── */
function formatRupiah(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function attachRupiahFormat(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function () {
        let raw = this.value.replace(/\./g, '').replace(/[^0-9]/g, '');
        this.value = raw ? formatRupiah(raw) : '';
    });
    // Store raw numeric for form submit
    const form = input.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            input.value = input.value.replace(/\./g, '');
        }, { once: false });
    }
}

attachRupiahFormat('profit_nominal');
attachRupiahFormat('pajak_nominal');
attachRupiahFormat('pajak_data_nominal');
attachRupiahFormat('edit_profit_nominal');
attachRupiahFormat('edit_pajak_nominal');

/* ── Drag & Drop for Upload Areas ── */
function setupDragDrop(areaId, fileInputId, previewId, textId) {
    const area = document.getElementById(areaId);
    const input = document.getElementById(fileInputId);
    if (!area || !input) return;

    area.addEventListener('dragover', e => { e.preventDefault(); area.style.borderColor = '#2563eb'; });
    area.addEventListener('dragleave', () => { area.style.borderColor = ''; });
    area.addEventListener('drop', e => {
        e.preventDefault();
        area.style.borderColor = '';
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            previewFile(input, previewId, textId);
        }
    });
}

setupDragDrop('uploadAreaProfit', 'fileProfit', 'previewProfit', 'uploadTextProfit');
setupDragDrop('uploadAreaPajakData', 'filePajakData', 'previewPajakData', 'uploadTextPajakData');
setupDragDrop('uploadAreaPajak', 'filePajak', 'previewPajak', 'uploadTextPajak');
setupDragDrop('uploadAreaExtra', 'fileExtra', 'previewExtra', 'uploadTextExtra');
setupDragDrop('uploadAreaEditProfit', 'fileEditProfit', 'previewEditProfit', 'uploadTextEditProfit');
setupDragDrop('uploadAreaEditPajakData', 'fileEditPajakData', 'previewEditPajakData', 'uploadTextEditPajakData');

/* ── Auto-dismiss toast ── */
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => { toast.style.display = 'none'; }, 3700);
}
