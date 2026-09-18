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
    if (el) { 
        el.classList.remove('open'); 
        if (el.classList.contains('modal-overlay-custom')) el.style.display = 'none';
        document.body.style.overflow = ''; 
    }
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

/* ── Modal: Pratinjau Bukti (Persis Dompet Harian) ── */
function openPreviewBukti(files, titleText, recordId, jenis = 'profit') {
    if (!files) files = [];
    if (typeof files === 'string') {
        try { files = JSON.parse(files); } catch (e) { files = [files]; }
    }
    if (!Array.isArray(files)) files = [files];

    const titleEl = document.getElementById('modalPreviewTitle');
    if (titleEl) {
        titleEl.textContent = `${titleText || 'Bukti Transfer'}${files.length > 1 ? ' (' + files.length + ')' : ''}`;
    }

    const gallery = document.getElementById('previewBuktiGallery');
    if (gallery) {
        gallery.innerHTML = '';
        if (files.length === 0) {
            gallery.innerHTML = '<p style="text-align:center;color:#64748b;padding:30px;">Tidak ada berkas bukti.</p>';
        } else {
            files.forEach((file, idx) => {
                const fileUrl = (file.startsWith('http://') || file.startsWith('https://')) ? file : `../uploads/bukti_profit/${file}`;
                const ext = file.split('?')[0].split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const item = document.createElement('div');
                item.className = 'nota-preview-item';

                const itemLabel = `Bukti Transfer ${files.length > 1 ? (idx + 1) : ''}`;
                const labelHtml = `
                    <div class="nota-preview-label">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <rect x="1" y="2" width="12" height="10" rx="1.2" stroke="currentColor" stroke-width="1.4"/>
                            <circle cx="4.5" cy="6" r="1.2" fill="currentColor"/>
                            <path d="M1 12l4-4 2.5 2.5 2-2L13 12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        ${itemLabel}
                    </div>
                `;

                const delBtnHtml = recordId ? `
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px;">
                        <button type="button" class="btn-delete-nota" onclick="hapusSingleBuktiProfit(${recordId}, '${jenis}', '${file}', this)">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <path d="M2 3.5h9M5 3.5V2.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v1M5.5 6v3.5M7.5 6v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                <path d="M3 3.5l.7 7a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5l.7-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Hapus Bukti
                        </button>
                    </div>
                ` : '';

                if (isPdf) {
                    item.innerHTML = `
                        ${labelHtml}
                        <div class="nota-preview-pdf-wrap">
                            <embed src="${fileUrl}" type="application/pdf" class="nota-preview-pdf">
                        </div>
                        ${delBtnHtml}
                    `;
                } else {
                    item.innerHTML = `
                        ${labelHtml}
                        <img src="${fileUrl}" alt="Bukti ${idx + 1}" class="nota-preview-img" onclick="window.open('${fileUrl}', '_blank')">
                        ${delBtnHtml}
                    `;
                }
                gallery.appendChild(item);
            });
        }
    }

    const modal = document.getElementById('modalPreviewBukti');
    if (modal) modal.style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalPreviewBukti');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal('modalPreviewBukti');
        });
    }
});

async function hapusSingleBuktiProfit(recordId, jenis, file, btnEl) {
    let confirmed = false;
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'Hapus Bukti Transfer?',
            text: 'File bukti transfer fisik akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Permanen',
            cancelButtonText: 'Batal',
            customClass: { popup: 'swal-kopdes' },
            didOpen: () => {
                const container = document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '99999999';
            }
        });
        confirmed = result.isConfirmed;
    } else {
        confirmed = confirm('Yakin ingin menghapus bukti transfer ini? File fisik di Cloudinary/server akan ikut terhapus permanen.');
    }

    if (!confirmed) return;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Menghapus...',
            text: 'Sedang menghapus file fisik...',
            allowOutsideClick: false,
            customClass: { popup: 'swal-kopdes' },
            didOpen: () => {
                const container = document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '99999999';
                Swal.showLoading();
            }
        });
    }

    const itemBox = btnEl ? (btnEl.closest('.nota-preview-item') || btnEl.closest('.preview-box-custom')) : null;
    if (btnEl) btnEl.disabled = true;

    const fd = new FormData();
    fd.append('aksi', 'hapus_single_bukti');
    fd.append('record_id', recordId);
    fd.append('jenis_bukti', jenis);
    fd.append('file', file);

    try {
        const res = await fetch('index.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Dihapus',
                    text: 'File fisik bukti transfer telah dimusnahkan.',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
            if (itemBox) itemBox.remove();
            const gallery = document.getElementById('previewBuktiGallery');
            if (gallery && gallery.querySelectorAll('.preview-box-custom').length === 0) {
                gallery.innerHTML = '<p style="text-align:center;color:#64748b;padding:30px;">Tidak ada berkas bukti tersisa.</p>';
                setTimeout(() => {
                    closeModal('modalPreviewBukti');
                    window.location.reload();
                }, 800);
            }
        } else {
            alert('Gagal menghapus bukti');
            if (btnEl) btnEl.disabled = false;
        }
    } catch (e) {
        alert('Error: ' + e.message);
        if (btnEl) btnEl.disabled = false;
    }
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
