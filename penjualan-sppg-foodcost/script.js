// ===== Toast =====
function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotif');
    if (!toast) return;
    toast.className = 'toast-notif toast-' + type + ' show';
    toast.innerHTML = message;
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// ===== Modal =====
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = 'auto';
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) {
            m.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
});

function toggleAccordion(header) {
    header.classList.toggle('open');
    header.nextElementSibling.classList.toggle('active');
}

async function updateNoFaktur() {
    const tglInput = document.querySelector('input[name="tanggal"]');
    const fakturInput = document.querySelector('input[name="no_faktur"]');
    if (!tglInput || !tglInput.value || !fakturInput) return;
    try {
        const res = await fetch(`database/get-no-faktur.php?tanggal=${tglInput.value}`);
        fakturInput.value = await res.text();
    } catch (e) { }
}
document.querySelector('input[name="tanggal"]')?.addEventListener('change', updateNoFaktur);

// =====================================================
// ✅ AUTO-FILL HARGA DARI TABEL BARANG (db_draft_barang)
// =====================================================
let debounceTimer = null;
const cacheBarang = {}; // cache hasil fetch

async function fetchHargaBarang(query) {
    if (!query || query.length < 2) return [];
    if (cacheBarang[query]) return cacheBarang[query];
    try {
        const res = await fetch(`database/get-harga-barang.php?q=${encodeURIComponent(query)}`);
        const data = await res.json();
        cacheBarang[query] = data;
        return data;
    } catch (e) {
        console.error('Error fetch harga:', e);
        return [];
    }
}

// Setup autocomplete untuk input nama barang di row form
function setupAutocompleteBarang(inputEl, hargaEl, satuanEl) {
    inputEl.addEventListener('input', async (e) => {
        const q = e.target.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) {
            // Kosongkan datalist
            const dl = inputEl.list;
            if (dl) dl.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(async () => {
            const items = await fetchHargaBarang(q);
            const dl = inputEl.list;
            if (!dl) return;
            dl.innerHTML = '';
            items.forEach(it => {
                const opt = document.createElement('option');
                opt.value = it.nama;
                opt.dataset.harga = it.harga;
                opt.dataset.satuan = it.satuan;
                dl.appendChild(opt);
            });
        }, 300);
    });

    inputEl.addEventListener('change', async (e) => {
        const nama = e.target.value.trim();
        if (!nama) return;
        // Cari di cache semua query
        let matched = null;
        for (const key in cacheBarang) {
            const found = cacheBarang[key].find(it => it.nama.toLowerCase() === nama.toLowerCase());
            if (found) { matched = found; break; }
        }
        // Kalau belum ada di cache, fetch
        if (!matched) {
            const items = await fetchHargaBarang(nama);
            matched = items.find(it => it.nama.toLowerCase() === nama.toLowerCase());
        }
        if (matched) {
            hargaEl.value = matched.harga;
            if (satuanEl && matched.satuan) satuanEl.value = matched.satuan;
            hargaEl.classList.add('harga-locked');
            hargaEl.title = 'Harga otomatis dari database barang';
            // Trigger hitung jumlah
            const row = inputEl.closest('tr');
            if (row) {
                const qtyInput = row.querySelector('.input-qty');
                if (qtyInput) calculateRow(qtyInput);
            }
        }
    });
}

// =====================================================
// ✅ DYNAMIC FORM ROWS (dengan autocomplete)
// =====================================================
let rowIndex = 0;
function addRow() {
    rowIndex++;
    const tbody = document.querySelector('#tableItem tbody');
    const tr = document.createElement('tr');
    tr.dataset.rowIndex = rowIndex;

    let kategoriOptions = '';
    KATEGORI_LIST.forEach(k => {
        kategoriOptions += `<option value="${k}">${k}</option>`;
    });

    tr.innerHTML = `
        <td>
            <input type="text" name="item_barang[${rowIndex}]" class="input-nama-barang" placeholder="Ketik nama barang..." list="datalistBarang_${rowIndex}" required autocomplete="off">
            <datalist id="datalistBarang_${rowIndex}"></datalist>
        </td>
        <td><select name="kategori[${rowIndex}]" class="kategori-select" required>${kategoriOptions}</select></td>
        <td><input type="number" name="qty[${rowIndex}]" class="input-qty" step="0.01" min="0" placeholder="0" required onchange="calculateRow(this)"></td>
        <td><input type="text" name="satuan[${rowIndex}]" class="input-satuan" placeholder="pcs/kg" required></td>
        <td><input type="number" name="harga_satuan[${rowIndex}]" class="input-harga harga-locked" step="0.01" min="0" placeholder="Auto" required onchange="calculateRow(this)"></td>
        <td><input type="number" name="jumlah[${rowIndex}]" class="input-jumlah" readonly placeholder="0" style="background:#f1f5f9;font-weight:600;"></td>
        <td><input type="file" name="nota_files[${rowIndex}][]" class="file-input-multi" multiple accept="image/*,.pdf" style="font-size:10px;"></td>
        <td><input type="file" name="foto_files[${rowIndex}][]" class="file-input-multi" multiple accept="image/*" style="font-size:10px;"></td>
        <td>
            <input type="hidden" name="row_index[]" value="${rowIndex}">
            <button type="button" class="btn btn-sm" style="background:var(--danger);color:#fff;" onclick="removeRow(this)">✕</button>
        </td>
    `;
    tbody.appendChild(tr);

    // Setup autocomplete untuk row baru
    const namaInput = tr.querySelector('.input-nama-barang');
    const hargaInput = tr.querySelector('.input-harga');
    const satuanInput = tr.querySelector('.input-satuan');
    setupAutocompleteBarang(namaInput, hargaInput, satuanInput);
}

function removeRow(btn) {
    const tbody = document.querySelector('#tableItem tbody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
    else alert('Minimal 1 item!');
}

function calculateRow(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
    const harga = parseFloat(row.querySelector('.input-harga').value) || 0;
    row.querySelector('.input-jumlah').value = (qty * harga).toFixed(2);
}

// ===== Preview Multiple Foto Menu =====
function previewFotoMenuMulti(input) {
    const preview = document.getElementById('fotoMenuPreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" class="remove-preview" onclick="this.parentElement.remove()">×</button>`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// ===== Compress Image =====
function compressImage(file, options = {}) {
    const { maxWidth = 1600, maxHeight = 1600, quality = 0.7, maxSizeKB = 800, minQuality = 0.4 } = options;
    return new Promise((resolve, reject) => {
        if (!file.type.startsWith('image/') || file.type === 'image/gif') { resolve(file); return; }
        if (file.size < 500 * 1024) { resolve(file); return; }
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let width = img.width, height = img.height;
                if (width > maxWidth || height > maxHeight) {
                    const ratio = Math.min(maxWidth / width, maxHeight / height);
                    width = Math.round(width * ratio);
                    height = Math.round(height * ratio);
                }
                const canvas = document.createElement('canvas');
                canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, width, height);
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);
                let currentQuality = quality;
                const tryCompress = (q) => {
                    canvas.toBlob((blob) => {
                        if (!blob) { reject(new Error('Gagal compress')); return; }
                        if (blob.size > maxSizeKB * 1024 && q > minQuality) { tryCompress(q - 0.1); return; }
                        const compressedFile = new File([blob], file.name.replace(/.[^.]+$/, '.jpg'), { type: 'image/jpeg', lastModified: Date.now() });
                        resolve(compressedFile);
                    }, 'image/jpeg', q);
                };
                tryCompress(currentQuality);
            };
            img.onerror = () => reject(new Error('Gagal memuat gambar'));
            img.src = e.target.result;
        };
        reader.onerror = () => reject(new Error('Gagal membaca file'));
        reader.readAsDataURL(file);
    });
}

// ===== Upload Inline Photo =====
function uploadInlinePhoto(input, action, id) {
    const files = input.files;
    if (!files || files.length === 0) return;
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.innerHTML = `<div class="spinner"></div><p id="loadingText">Mempersiapkan gambar...</p>`;
        loading.classList.add('active');
    }
    const needCompress = (action === 'add_foto_receiving' || action === 'add_menu_photo');
    const processFiles = async () => {
        const processedFiles = [];
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const loadingText = document.getElementById('loadingText');
            if (loadingText) loadingText.textContent = `Memproses file ${i + 1}/${files.length}...`;
            try {
                if (needCompress && file.type.startsWith('image/') && file.type !== 'image/gif') {
                    const compressed = await compressImage(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.75, maxSizeKB: 800 });
                    processedFiles.push(compressed);
                } else {
                    processedFiles.push(file);
                }
            } catch (err) { processedFiles.push(file); }
        }
        return processedFiles;
    };
    processFiles().then(processedFiles => {
        const loadingText = document.getElementById('loadingText');
        if (loadingText) loadingText.textContent = 'Mengupload...';
        const promises = processedFiles.map(file => {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('foto', file);
            if (action === 'add_menu_photo') fd.append('id_belanja', id);
            else fd.append('id_detail', id);
            return fetch('database/upload_photo.php', { method: 'POST', body: fd })
                .then(async r => {
                    const text = await r.text();
                    try { return JSON.parse(text); }
                    catch (e) { throw new Error('Server error: ' + text.substring(0, 100)); }
                });
        });
        return Promise.all(promises);
    }).then(results => {
        if (loading) loading.classList.remove('active');
        const failed = results.find(r => !r.success);
        if (failed) alert('❌ Gagal upload: ' + failed.message);
        else location.reload();
    }).catch(err => {
        if (loading) loading.classList.remove('active');
        alert('❌ Error: ' + err.message);
    });
    input.value = '';
}

// ===== View Photos =====
function viewPhotos(idDetail, type, count) {
    const modal = document.getElementById('photoViewerModal');
    const title = document.getElementById('photoViewerTitle');
    const grid = document.getElementById('photoGrid');
    title.textContent = type === 'nota' ? `Lampiran Nota (${count})` : `Foto Receiving (${count})`;
    grid.innerHTML = '<div style="text-align:center;padding:40px;"><div class="spinner"></div><p>Memuat...</p></div>';
    openModal('photoViewerModal');
    fetch(`database/get_photos.php?id_detail=${idDetail}&type=${type}`)
        .then(async r => {
            const text = await r.text();
            try { return JSON.parse(text); } catch (e) { throw new Error('Gagal memuat foto'); }
        })
        .then(data => {
            grid.innerHTML = '';
            if (data.photos && data.photos.length > 0) {
                const photoPath = type === 'nota' ? 'uploads/nota/' : 'uploads/foto/';
                data.photos.forEach((photo, index) => {
                    const item = document.createElement('div');
                    item.className = 'photo-item';
                    item.innerHTML = `<img src="${photoPath}${photo}" onclick="viewFullImage('${photoPath}${photo}')" alt="${type} ${index + 1}"><div class="photo-label">${type === 'nota' ? 'Nota' : 'Foto'} ${index + 1}</div>`;
                    grid.appendChild(item);
                });
            } else {
                grid.innerHTML = '<p style="text-align:center;padding:40px;">Tidak ada foto</p>';
            }
        })
        .catch(error => {
            grid.innerHTML = `<p style="text-align:center;color:var(--danger);padding:40px;">Gagal memuat: ${error.message}</p>`;
        });
}

function viewFullImage(src) {
    const ext = src.split('.').pop().toLowerCase();
    if (ext === 'pdf') { window.open(src, '_blank'); return; }
    document.getElementById('fullImage').src = src;
    document.getElementById('fullImageModal').classList.add('active');
}
function closeFullImage() {
    document.getElementById('fullImageModal').classList.remove('active');
}

function exportPDF(tanggal) {
    const loading = document.getElementById('loadingOverlay');
    loading.classList.add('active');
    setTimeout(() => {
        window.location.href = `database/export_pdf.php?tanggal=${tanggal}`;
        loading.classList.remove('active');
    }, 500);
}

function openEditItem(btn) {
    document.getElementById('edit_id_detail').value = btn.dataset.id;
    document.getElementById('edit_item_barang').value = btn.dataset.item;
    document.getElementById('edit_qty').value = btn.dataset.qty;
    document.getElementById('edit_satuan').value = btn.dataset.satuan;
    document.getElementById('edit_harga').value = btn.dataset.harga;
    document.getElementById('edit_kategori').value = btn.dataset.kategori;
    openModal('modalEdit');
}

function openAddItemModal(idBelanja, judulMenu) {
    const form = document.getElementById('formAddItem');
    form.reset();
    document.getElementById('additem_id_belanja').value = idBelanja;
    document.getElementById('additem_judul_menu').textContent = `Menambahkan item untuk: ${judulMenu}`;
    openModal('modalAddItem');
}

// ✅ Auto-fill harga di modal tambah barang susulan
document.addEventListener('DOMContentLoaded', () => {
    const additemInput = document.getElementById('additem_nama_barang');
    const additemHarga = document.getElementById('additem_harga');
    const additemSatuan = document.getElementById('additem_satuan');
    if (additemInput && additemHarga) {
        setupAutocompleteBarang(additemInput, additemHarga, additemSatuan);
    }
});

function hitungJumlahSusulan() {
    // tidak perlu, dihitung server-side
}

function uploadFakturTTD(input, tanggal) {
    const file = input.files[0];
    if (!file) return;
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.innerHTML = `<div class="spinner"></div><p>Mengupload faktur...</p>`;
        loading.classList.add('active');
    }
    const doUpload = (uploadFile) => {
        const fd = new FormData();
        fd.append('action', 'add_faktur_ttd');
        fd.append('tanggal', tanggal);
        fd.append('foto', uploadFile);
        fetch('database/upload-faktur.php', { method: 'POST', body: fd })
            .then(async r => {
                const text = await r.text();
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Server error'); }
            })
            .then(result => {
                if (loading) loading.classList.remove('active');
                if (!result.success) alert('❌ Gagal: ' + result.message);
                else window.location.href = 'menu.php?faktur_uploaded=1';
            })
            .catch(err => {
                if (loading) loading.classList.remove('active');
                alert('❌ Error: ' + err.message);
            });
    };
    if (file.type.startsWith('image/') && file.type !== 'image/gif') {
        compressImage(file, { maxWidth: 1800, maxHeight: 1800, quality: 0.8, maxSizeKB: 1000 })
            .then(doUpload).catch(() => doUpload(file));
    } else {
        doUpload(file);
    }
    input.value = '';
}

// ===== Status Item =====
async function setStatus(idDetail, status, btnEl) {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.innerHTML = `<div class="spinner"></div><p>Menyimpan status...</p>`;
        loading.classList.add('active');
    }
    try {
        const fd = new FormData();
        fd.append('update_status_item', '1');
        fd.append('id_detail', idDetail);
        fd.append('status_item', status);
        const res = await fetch('menu.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (loading) loading.classList.remove('active');
        if (data.success) {
            const group = btnEl.closest('.status-toggle-group');
            group.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
            const ketBox = btnEl.closest('.item-row').querySelector('.keterangan-kurang-box');
            if (ketBox) ketBox.remove();
            showToast(`Status: <strong>${status === 'lengkap' ? 'Lengkap ✓' : 'Tidak Ada ✗'}</strong>`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('❌ ' + (data.message || 'Gagal'));
        }
    } catch (err) {
        if (loading) loading.classList.remove('active');
        alert('❌ Error: ' + err.message);
    }
}

function handleKurangClick(idDetail, btnEl) {
    const modal = document.getElementById('modalKeterangan');
    modal.dataset.idDetail = idDetail;
    document.getElementById('keteranganInput').value = '';
    openModal('modalKeterangan');
    setTimeout(() => document.getElementById('keteranganInput').focus(), 200);
}

async function submitKeteranganKurang() {
    const modal = document.getElementById('modalKeterangan');
    const idDetail = modal.dataset.idDetail;
    const keterangan = document.getElementById('keteranganInput').value.trim();
    if (keterangan.length < 5) {
        alert('⚠️ Keterangan minimal 5 karakter!');
        return;
    }
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.innerHTML = `<div class="spinner"></div><p>Menyimpan...</p>`;
        loading.classList.add('active');
    }
    try {
        const fd = new FormData();
        fd.append('update_status_item', '1');
        fd.append('id_detail', idDetail);
        fd.append('status_item', 'kurang');
        fd.append('keterangan_kurang', keterangan);
        const res = await fetch('menu.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (loading) loading.classList.remove('active');
        if (data.success) {
            closeModal('modalKeterangan');
            showToast('Status <strong>Kurang</strong> tersimpan', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('❌ ' + (data.message || 'Gagal'));
        }
    } catch (err) {
        if (loading) loading.classList.remove('active');
        alert('❌ Error: ' + err.message);
    }
}

// ===== Search Menu =====
function filterMenu(query) {
    const clearBtn = document.getElementById('searchClear');
    const resultInfo = document.getElementById('searchResult');
    const q = query.trim().toLowerCase();
    clearBtn.classList.toggle('visible', q.length > 0);
    const dateGroups = document.querySelectorAll('.date-group');
    let totalVisible = 0;
    dateGroups.forEach(group => {
        const cards = group.querySelectorAll('.menu-card');
        let visibleInGroup = 0;
        cards.forEach(card => {
            const title = card.querySelector('.menu-title');
            const namaMenu = title ? title.textContent.toLowerCase() : '';
            const match = q === '' || namaMenu.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visibleInGroup++;
        });
        const isGroupVisible = q === '' || visibleInGroup > 0;
        group.style.display = isGroupVisible ? '' : 'none';
        if (q !== '' && isGroupVisible) {
            const header = group.querySelector('.accordion-toggle');
            const content = group.querySelector('.date-content');
            if (header && !header.classList.contains('open')) {
                header.classList.add('open');
                content.classList.add('active');
            }
        }
        totalVisible += visibleInGroup;
    });
    if (q === '') resultInfo.innerHTML = '';
    else if (totalVisible === 0) resultInfo.innerHTML = `Tidak ada menu cocok untuk "<span class="highlight">${escapeHtml(query)}</span>"`;
    else resultInfo.innerHTML = `Ditemukan <span class="highlight">${totalVisible}</span> menu untuk "<span class="highlight">${escapeHtml(query)}</span>"`;
}
function clearSearch() {
    document.getElementById('searchMenu').value = '';
    filterMenu('');
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== Popup Upload Foto Menu =====
function showUploadMenuOptions(idBelanja) {
    const oldPopup = document.getElementById('uploadMenuPopup');
    if (oldPopup) oldPopup.remove();
    const popup = document.createElement('div');
    popup.id = 'uploadMenuPopup';
    popup.className = 'upload-menu-popup';
    popup.innerHTML = `
        <div class="upload-menu-popup-content">
            <div class="upload-menu-popup-title">Pilih cara upload foto</div>
            <button class="upload-menu-popup-btn btn-kamera-opt" onclick="triggerMenuPhoto('kamera', ${idBelanja})">
                <span>📷 Ambil Foto</span>
            </button>
            <button class="upload-menu-popup-btn btn-galeri-opt" onclick="triggerMenuPhoto('galeri', ${idBelanja})">
                <span>🖼️ Pilih dari Galeri</span>
            </button>
            <button class="upload-menu-popup-btn btn-cancel-opt" onclick="closeUploadMenuPopup()">Batal</button>
        </div>
    `;
    document.body.appendChild(popup);
    setTimeout(() => popup.classList.add('active'), 10);
}
function triggerMenuPhoto(type, idBelanja) {
    const inputId = type === 'kamera' ? `menuPhotoKamera_${idBelanja}` : `menuPhotoGaleri_${idBelanja}`;
    const input = document.getElementById(inputId);
    if (input) input.click();
    closeUploadMenuPopup();
}
function closeUploadMenuPopup() {
    const popup = document.getElementById('uploadMenuPopup');
    if (popup) {
        popup.classList.remove('active');
        setTimeout(() => popup.remove(), 200);
    }
}
document.addEventListener('click', function (e) {
    const popup = document.getElementById('uploadMenuPopup');
    if (popup && !popup.contains(e.target) && !e.target.closest('.btn-upload-menu')) {
        closeUploadMenuPopup();
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        document.getElementById('fullImageModal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
});