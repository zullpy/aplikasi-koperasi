/* ============================================================
   TAMBAH MODAL — multi-item form
   ============================================================ */
let itemIndex = 0;

function openAddModal() {
    const form = document.getElementById('tambah-form');
    if (form) form.reset();
    const container = document.getElementById('item-rows-container');
    if (container) container.innerHTML = '';
    itemIndex = 0;

    updateItemCount();
    updateGrandTotal();

    resetDropzone('add_nota_kamera');
    resetDropzone('add_nota_file');

    const today = new Date().toISOString().split('T')[0];
    const tglInput = document.getElementById('add_tanggal');
    if (tglInput) tglInput.value = today;

    const cashRadio = document.getElementById('add_metode_cash');
    if (cashRadio) cashRadio.checked = true;
    toggleBiayaAdmin('add');

    const modal = document.getElementById('tambahModal');
    if (modal) modal.style.display = 'block';
}

function closeTambahModal() {
    const modal = document.getElementById('tambahModal');
    if (modal) modal.style.display = 'none';
}

function addItemRow() {
    const idx = itemIndex++;
    const container = document.getElementById('item-rows-container');
    const emptyState = document.getElementById('item-empty-state');
    const totalRow = document.getElementById('total-row');
    if (emptyState) emptyState.style.display = 'none';
    if (totalRow) totalRow.style.display = 'flex';

    const row = document.createElement('div');
    row.className = 'item-input-row';
    row.dataset.idx = idx;

    row.innerHTML = `
        <div class="item-input-field field-nama">
            <label class="item-field-label">Nama Barang</label>
            <div class="autocomplete-wrapper-inline">
                <input type="text" name="nama_barang[]" class="item-nama-input"
                    placeholder="Cari nama barang..." autocomplete="off" required data-idx="${idx}">
                <div class="suggestions-inline" id="sug-${idx}"></div>
            </div>
        </div>
        <div class="item-input-field field-harga">
            <label class="item-field-label">Harga Beli</label>
            <input type="text" name="harga[]" class="item-harga-input" placeholder="Rp 0" required data-idx="${idx}">
        </div>
        <div class="item-input-field field-volume">
            <label class="item-field-label">Volume</label>
            <input type="number" name="volume[]" class="item-volume-input" placeholder="0" min="1" required data-idx="${idx}">
        </div>
        <div class="item-input-field field-satuan">
            <label class="item-field-label">Satuan</label>
            <input type="text" name="satuan[]" class="item-satuan-input" placeholder="Dus / Pcs" required data-idx="${idx}">
        </div>
        <div class="item-input-field field-ket">
            <label class="item-field-label">Keterangan</label>
            <input type="text" name="keterangan[]" class="item-ket-input" placeholder="Opsional" data-idx="${idx}">
        </div>
        <div class="item-input-field field-subtotal">
            <label class="item-field-label">Sub Total</label>
            <div class="subtotal-display" id="subtotal-${idx}">Rp 0</div>
        </div>
        <div class="item-input-field field-hapus">
            <button type="button" class="hapus-item-btn" onclick="removeItemRow(this)" aria-label="Hapus baris">
                <i class="ph ph-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(row);
    updateItemCount();

    const hargaInput = row.querySelector('.item-harga-input');
    const volumeInput = row.querySelector('.item-volume-input');

    hargaInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '');
        this.value = raw === '' ? '' : 'Rp ' + Number(raw).toLocaleString('id-ID');
        recalcSubtotal(idx);
    });

    volumeInput.addEventListener('input', function () {
        recalcSubtotal(idx);
    });

    const namaInput = row.querySelector('.item-nama-input');
    let toastDebounce = null;

    namaInput.addEventListener('input', function () {
        const keyword = this.value.trim();
        const sugBox = document.getElementById(`sug-${idx}`);

        if (keyword.length < 2) {
            if (sugBox) sugBox.innerHTML = '';
            hideItemToast();
            return;
        }

        fetch(`../database/cari-barang-pembelian.php?q=${encodeURIComponent(keyword)}`)
            .then(res => res.text())
            .then(html => {
                if (sugBox) {
                    sugBox.innerHTML = html.replace(/onclick="pilihBarang\('([^']+)'\)"/g,
                        `onclick="pilihBarangInline('$1', ${idx})"`);
                }
            });

        clearTimeout(toastDebounce);
        toastDebounce = setTimeout(() => {
            fetch(`../database/cek-barang.php?nama_barang=${encodeURIComponent(keyword)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ada') {
                        showItemToast(buildToastAda(data), 'success');
                    } else {
                        showItemToast(
                            `⚠️ <strong>Barang belum terdaftar</strong><br>Silakan daftarkan di <a href="../daftar-harga-barang-food/index.php" style="color:#fff;text-decoration:underline">Daftar Harga</a> terlebih dahulu`,
                            'warning'
                        );
                    }
                });
        }, 500);
    });

    namaInput.focus();
}

function buildToastAda(data) {
    const rp = n => 'Rp ' + Number(n).toLocaleString('id-ID');
    let html = `✅ <strong>Barang terdaftar</strong><br>`;
    html += `Harga beli: <strong>${rp(data.harga)}</strong>`;
    if (data.tanggal_terupdate_baru) {
        html += ` <span style="opacity:.8;font-size:.85em">(${data.tanggal_terupdate_baru})</span>`;
    }
    if (data.harga_min && data.harga_max && data.harga_min != data.harga_max) {
        html += `<br>Min: ${rp(data.harga_min)} &nbsp;|&nbsp; Max: ${rp(data.harga_max)}`;
    }
    html += `<br>Stok: <strong>${data.stok} ${data.satuan}</strong>`;
    return html;
}

function pilihBarangInline(nama, idx) {
    const row = document.querySelector(`.item-input-row[data-idx="${idx}"]`);
    if (!row) return;
    const namaInput = row.querySelector('.item-nama-input');
    if (namaInput) namaInput.value = nama;

    const sugBox = document.getElementById(`sug-${idx}`);
    if (sugBox) sugBox.innerHTML = '';

    fetch(`../database/cek-barang.php?nama_barang=${encodeURIComponent(nama)}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ada') {
                const hargaInput = row.querySelector('.item-harga-input');
                if (hargaInput && data.harga) {
                    hargaInput.value = 'Rp ' + Number(data.harga).toLocaleString('id-ID');
                    recalcSubtotal(idx);
                }
                const satuanInput = row.querySelector('.item-satuan-input');
                if (satuanInput && data.satuan) {
                    satuanInput.value = data.satuan;
                }
                showItemToast(buildToastAda(data), 'success');
            } else {
                showItemToast(
                    `⚠️ <strong>Barang belum terdaftar</strong><br>Silakan daftarkan di <a href="../daftar-harga-barang-food/index.php" style="color:#fff;text-decoration:underline">Daftar Harga</a> terlebih dahulu`,
                    'warning'
                );
            }
        });
}

function removeItemRow(btn) {
    const row = btn.closest('.item-input-row');
    if (row) row.remove();
    const container = document.getElementById('item-rows-container');
    const emptyState = document.getElementById('item-empty-state');
    const totalRow = document.getElementById('total-row');

    if (container && container.children.length === 0) {
        if (emptyState) emptyState.style.display = 'flex';
        if (totalRow) totalRow.style.display = 'none';
    }
    updateItemCount();
    updateGrandTotal();
}

function recalcSubtotal(idx) {
    const row = document.querySelector(`.item-input-row[data-idx="${idx}"]`);
    if (!row) return;
    const hargaRaw = (row.querySelector('.item-harga-input').value || '').replace(/\D/g, '');
    const volume = parseFloat(row.querySelector('.item-volume-input').value) || 0;
    const harga = parseFloat(hargaRaw) || 0;
    const subtotal = harga * volume;

    const el = document.getElementById(`subtotal-${idx}`);
    if (el) el.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    updateGrandTotal();
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.item-input-row').forEach(row => {
        const hargaRaw = (row.querySelector('.item-harga-input')?.value || '').replace(/\D/g, '');
        const volume = parseFloat(row.querySelector('.item-volume-input')?.value) || 0;
        total += (parseFloat(hargaRaw) || 0) * volume;
    });
    const biayaAdminEl = document.getElementById('add_biaya_admin');
    if (biayaAdminEl && biayaAdminEl.closest('#tambahModal')) {
        const biayaRaw = (biayaAdminEl.value || '').replace(/\D/g, '');
        total += parseFloat(biayaRaw) || 0;
    }
    const el = document.getElementById('grand-total-display');
    if (el) el.textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function updateItemCount() {
    const count = document.querySelectorAll('.item-input-row').length;
    const badge = document.getElementById('item-count-badge');
    if (badge) badge.textContent = count + ' item';
}

/* ============================================================
   METODE PEMBAYARAN — toggle biaya admin
   ============================================================ */
function toggleBiayaAdmin(prefix) {
    const selected = document.querySelector(`input[name="metode_pembayaran"]:checked`);
    const adminGroup = document.getElementById(`${prefix}_biaya_admin_group`);
    const adminInput = document.getElementById(`${prefix}_biaya_admin`);
    if (!adminGroup) return;
    const metode = selected ? selected.value : 'cash';
    if (metode === 'cash') {
        adminGroup.style.display = 'none';
        if (adminInput) adminInput.value = '';
    } else {
        adminGroup.style.display = 'flex';
        adminGroup.style.flexDirection = 'column';
    }
    if (prefix === 'add') updateGrandTotal();
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.autocomplete-wrapper-inline')) {
        document.querySelectorAll('.suggestions-inline').forEach(el => el.innerHTML = '');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#tambahModal input[name="metode_pembayaran"]').forEach(radio => {
        radio.addEventListener('change', () => toggleBiayaAdmin('add'));
    });

    const addBiayaInput = document.getElementById('add_biaya_admin');
    if (addBiayaInput) {
        addBiayaInput.addEventListener('input', function () {
            let raw = this.value.replace(/\D/g, '');
            this.value = raw === '' ? '' : 'Rp ' + Number(raw).toLocaleString('id-ID');
            updateGrandTotal();
        });
    }

    document.querySelectorAll('#transaksiModal input[name="metode_pembayaran"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const adminGroup = document.getElementById('edit_biaya_admin_group');
            const adminInput = document.getElementById('edit_biaya_admin');
            if (!adminGroup) return;
            if (radio.value === 'cash') {
                adminGroup.style.display = 'none';
                if (adminInput) adminInput.value = '';
            } else {
                adminGroup.style.display = 'flex';
                adminGroup.style.flexDirection = 'column';
            }
        });
    });

    const editBiayaInput = document.getElementById('edit_biaya_admin');
    if (editBiayaInput) {
        editBiayaInput.addEventListener('input', function () {
            let raw = this.value.replace(/\D/g, '');
            this.value = raw === '' ? '' : 'Rp ' + Number(raw).toLocaleString('id-ID');
        });
    }

    document.addEventListener('change', (e) => {
        if (e.target.type === 'radio' && e.target.name === 'metode_pembayaran') {
            const group = e.target.closest('.metode-radio-group');
            if (!group) return;
            group.querySelectorAll('.metode-radio-label').forEach(lbl => lbl.classList.remove('active'));
            e.target.closest('.metode-radio-label')?.classList.add('active');
        }
    });

    const tambahForm = document.getElementById('tambah-form');
    if (tambahForm) {
        tambahForm.addEventListener('submit', (e) => {
            const rows = document.querySelectorAll('.item-input-row');
            if (rows.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Belum ada barang',
                    text: 'Tambahkan minimal 1 barang sebelum menyimpan transaksi.',
                    width: window.innerWidth < 768 ? '280px' : '400px'
                });
            }
        });
    }

    /* ============================================================
       SEARCH BAR
       ============================================================ */
    const searchInput = document.getElementById('search-bar');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const keyword = searchInput.value.toLowerCase().trim();
            const dateGroups = document.querySelectorAll('.date-group');
            let totalVisible = 0;

            dateGroups.forEach(dateGroup => {
                let anyVisibleInDate = false;
                dateGroup.querySelectorAll('.supplier-accordion').forEach(supplierAcc => {
                    let anyVisibleInSupplier = false;
                    supplierAcc.querySelectorAll('.item-row').forEach(row => {
                        const nama = row.getAttribute('data-nama') || '';
                        const match = keyword === '' || nama.includes(keyword);
                        row.style.display = match ? '' : 'none';
                        if (match) anyVisibleInSupplier = true;
                    });
                    supplierAcc.style.display = anyVisibleInSupplier ? '' : 'none';
                    if (anyVisibleInSupplier && keyword !== '') supplierAcc.open = true;
                    if (anyVisibleInSupplier) anyVisibleInDate = true;
                });
                dateGroup.style.display = anyVisibleInDate ? '' : 'none';
                const dateAcc = dateGroup.querySelector('.date-accordion');
                if (dateAcc && anyVisibleInDate && keyword !== '') dateAcc.open = true;
                if (anyVisibleInDate) totalVisible++;
            });

            const emptyState = document.getElementById('empty-search-state');
            if (emptyState) emptyState.style.display = (keyword !== '' && totalVisible === 0) ? 'flex' : 'none';
        });
    }

    /* ============================================================
       NOTA FORM VALIDATION
       ============================================================ */
    const notaForm = document.getElementById('nota-form');
    if (notaForm) {
        notaForm.addEventListener('submit', (e) => {
            const cameraInput = document.getElementById('nota_kamera_only');
            const fileInput = document.getElementById('nota_file_only');
            if (!(cameraInput?.files.length > 0) && !(fileInput?.files.length > 0)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Oops...',
                    text: 'Silakan unggah nota belanja Anda!',
                    width: window.innerWidth < 768 ? '280px' : '400px'
                });
            }
        });
    }

    /* ============================================================
       DROPZONE BINDINGS
       ============================================================ */
    ['add_nota_kamera', 'add_nota_file', 'nota_kamera_only', 'nota_file_only'].forEach(bindDropzone);

    const hargaInput = document.getElementById('harga');
    if (hargaInput) {
        hargaInput.addEventListener('input', function () {
            let angka = this.value.replace(/\D/g, '');
            this.value = angka === '' ? '' : 'Rp ' + Number(angka).toLocaleString('id-ID');
        });
    }

    const input = document.getElementById('nama_barang');
    const suggestions = document.getElementById('suggestions');
    if (input) {
        input.addEventListener('input', () => {
            const keyword = input.value.trim();
            if (keyword === '') { if (suggestions) suggestions.innerHTML = ''; return; }
            if (keyword.length >= 2) {
                fetch(`../database/cari-barang-pembelian.php?q=${encodeURIComponent(keyword)}`)
                    .then(res => res.text())
                    .then(data => { if (suggestions) suggestions.innerHTML = data; });
            }
        });
    }

    document.querySelectorAll('.metode-radio-label').forEach(lbl => {
        const radio = lbl.querySelector('input[type="radio"]');
        if (radio && radio.checked) lbl.classList.add('active');
    });
});

function bindDropzone(inputId) {
    const inputEl = document.getElementById(inputId);
    if (!inputEl) return;
    const dropzone = document.querySelector(`label.upload-dropzone[for="${inputId}"]`);
    if (!dropzone) return;
    const filenameEl = dropzone.querySelector('.upload-filename');
    const textEl = dropzone.querySelector('.upload-text');
    const originalText = textEl ? textEl.textContent : '';

    function updateDisplay() {
        const file = inputEl.files && inputEl.files[0];
        if (file) {
            dropzone.classList.add('has-file');
            if (filenameEl) filenameEl.textContent = file.name;
            if (textEl) textEl.textContent = 'Berkas terpilih:';
        } else {
            dropzone.classList.remove('has-file');
            if (filenameEl) filenameEl.textContent = '';
            if (textEl) textEl.textContent = originalText;
        }
    }

    inputEl.addEventListener('change', updateDisplay);
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragging'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragging'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragging');
        if (e.dataTransfer.files?.length > 0) { inputEl.files = e.dataTransfer.files; updateDisplay(); }
    });
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inputEl.click(); }
    });
    inputEl._resetDropzone = () => {
        dropzone.classList.remove('has-file');
        if (filenameEl) filenameEl.textContent = '';
        if (textEl) textEl.textContent = originalText;
    };
}

function resetDropzone(inputId) {
    const inputEl = document.getElementById(inputId);
    if (inputEl && typeof inputEl._resetDropzone === 'function') inputEl._resetDropzone();
}

function pilihBarang(nama) {
    const input = document.getElementById('nama_barang');
    const suggestions = document.getElementById('suggestions');
    if (input) input.value = nama;
    if (suggestions) suggestions.innerHTML = '';
}

/* ============================================================
   GLOBAL CLICK DELEGATION
   ============================================================ */
document.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-btn');
    const deleteBtn = e.target.closest('.delete-btn');
    const addNotaBtn = e.target.closest('.add-nota-btn');
    const gantiNotaBtn = e.target.closest('.ganti-nota-btn');
    const detailBtn = e.target.closest('.detail-btn');
    const lihatNotaBtn = e.target.closest('.lihat-nota-btn');

    if (editBtn) {
        e.preventDefault();
        loadEditData(editBtn.getAttribute('data-id'));
    }

    if (deleteBtn) {
        e.preventDefault();
        const id = deleteBtn.getAttribute('data-id');
        if (id) {
            Swal.fire({
                title: 'Hapus Transaksi?',
                text: 'Data yang dihapus tidak dapat dikembalikan',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#d33',
                width: window.innerWidth < 768 ? '280px' : '400px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `../database/delete-barang.php?id=${id}`;
                }
            });
        }
    }

    // Tombol TAMBAH NOTA (belum ada nota)
    if (addNotaBtn) {
        e.preventDefault();
        const id = addNotaBtn.getAttribute('data-id');
        const supplier = addNotaBtn.getAttribute('data-supplier') || '';
        if (id) openNotaModal(id, supplier, false);
    }

    // Tombol GANTI NOTA (sudah ada nota)
    if (gantiNotaBtn) {
        e.preventDefault();
        const id = gantiNotaBtn.getAttribute('data-id');
        const supplier = gantiNotaBtn.getAttribute('data-supplier') || '';
        if (id) openNotaModal(id, supplier, true);
    }

    if (detailBtn) {
        e.preventDefault();
        openDetailModal(detailBtn);
    }

    if (lihatNotaBtn) {
        e.preventDefault();
        openNotaPreview(lihatNotaBtn.getAttribute('data-nota'));
    }
});

const detailEditBtn = document.getElementById('detail-edit-btn');
if (detailEditBtn) {
    detailEditBtn.addEventListener('click', () => {
        const detailModal = document.getElementById('detailModal');
        const id = detailModal ? detailModal.dataset.currentId : '';
        closeDetailModal();
        loadEditData(id);
    });
}

/* ============================================================
   EDIT MODAL
   ============================================================ */
function openModal() {
    const modal = document.getElementById('transaksiModal');
    if (modal) modal.style.display = 'block';
}

function closeModal() {
    const modal = document.getElementById('transaksiModal');
    if (modal) modal.style.display = 'none';
}

function loadEditData(id) {
    if (!id) {
        Swal.fire({ icon: 'error', title: 'Oops...', text: 'ID Transaksi tidak ditemukan!', width: window.innerWidth < 768 ? '280px' : '400px' });
        return;
    }
    fetch(`../database/get-barang.php?id=${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                const data = result.data;

                document.getElementById('id_pembelian').value = data.id_pembelian;
                document.getElementById('id_supplier').value = data.id_supplier || '';
                document.getElementById('nama_barang').value = data.nama_barang || '';
                document.getElementById('tanggal_pembelian').value = data.tanggal_pembelian || '';
                document.getElementById('harga').value = data.harga || '';
                document.getElementById('volume').value = data.volume || '';
                document.getElementById('satuan').value = data.satuan || '';
                document.getElementById('keterangan').value = data.keterangan || '';

                const metode = (data.metode_pembayaran || 'cash').toLowerCase();
                const radioEl = document.querySelector(`#transaksiModal input[name="metode_pembayaran"][value="${metode}"]`);
                if (radioEl) {
                    radioEl.checked = true;
                    document.querySelectorAll('#transaksiModal .metode-radio-label').forEach(lbl => lbl.classList.remove('active'));
                    radioEl.closest('.metode-radio-label')?.classList.add('active');
                }

                const adminGroup = document.getElementById('edit_biaya_admin_group');
                const adminInput = document.getElementById('edit_biaya_admin');
                if (metode === 'cash') {
                    if (adminGroup) adminGroup.style.display = 'none';
                    if (adminInput) adminInput.value = '';
                } else {
                    if (adminGroup) { adminGroup.style.display = 'flex'; adminGroup.style.flexDirection = 'column'; }
                    if (adminInput && data.biaya_admin) {
                        const biayaNum = parseFloat(String(data.biaya_admin).replace(/\D/g, '')) || 0;
                        adminInput.value = biayaNum > 0 ? 'Rp ' + biayaNum.toLocaleString('id-ID') : '';
                    }
                }

                document.getElementById('modal-title').textContent = 'Edit Transaksi Pembelian';
                document.getElementById('modal-form').setAttribute('action', '../database/update-barang.php');
                document.getElementById('submit-btn').textContent = 'Simpan Perubahan';

                openModal();
            } else {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Gagal mengambil data: ' + result.message, width: window.innerWidth < 768 ? '280px' : '400px' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Terjadi kesalahan koneksi!', width: window.innerWidth < 768 ? '280px' : '400px' });
        });
}

/* ============================================================
   NOTA MODAL — per supplier
   ============================================================ */
function openNotaModal(id, supplierName = '', isGanti = false) {
    const form = document.getElementById('nota-form');
    if (form) form.reset();
    const idInput = document.getElementById('nota_id_barang');
    if (idInput) idInput.value = id;

    resetDropzone('nota_kamera_only');
    resetDropzone('nota_file_only');

    // Update judul & subtitle modal
    const titleEl = document.getElementById('nota-modal-title');
    const subtitleEl = document.getElementById('nota-supplier-name');
    if (titleEl) {
        titleEl.innerHTML = `<i class="ph ph-receipt"></i> ${isGanti ? 'Ganti' : 'Unggah'} Bukti Nota`;
    }
    if (subtitleEl) {
        subtitleEl.innerHTML = supplierName
            ? `<i class="ph ph-storefront"></i> Supplier: <strong>${supplierName}</strong>`
            : '';
    }

    const modal = document.getElementById('notaModal');
    if (modal) modal.style.display = 'block';
}

function closeNotaModal() {
    const modal = document.getElementById('notaModal');
    if (modal) modal.style.display = 'none';
}

/* ============================================================
   DETAIL MODAL
   ============================================================ */
function closeDetailModal() {
    const modal = document.getElementById('detailModal');
    if (modal) modal.style.display = 'none';
}

function openDetailModal(btn) {
    const d = btn.dataset;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    setText('detail-nama', d.nama || '-');
    setText('detail-keterangan', d.keterangan || '-');
    setText('detail-tanggal', d.tanggal || '-');
    setText('detail-harga', 'Rp ' + Number(d.harga || 0).toLocaleString('id-ID'));
    setText('detail-volume', `${d.volume || '-'} ${d.satuan || ''}`.trim());
    setText('detail-jumlah', 'Rp ' + Number(d.jumlah || 0).toLocaleString('id-ID'));
    setText('detail-supplier', d.supplier || '-');

    const metode = d.metode || '';
    const metodeEl = document.getElementById('detail-metode');
    if (metodeEl) {
        const iconMap = { qris: 'ph-qr-code', transfer: 'ph-bank', cash: 'ph-money' };
        const icon = iconMap[metode.toLowerCase()] || 'ph-money';
        metodeEl.innerHTML = metode
            ? `<span class="badge-metode badge-${metode.toLowerCase()}"><i class="ph ${icon}"></i> ${metode.toUpperCase()}</span>`
            : '-';
    }

    const biayaAdmin = parseFloat(d.biayaAdmin || 0);
    const adminItem = document.getElementById('detail-admin-item');
    const adminEl = document.getElementById('detail-biaya-admin');
    if (adminItem) {
        if (biayaAdmin > 0) {
            adminItem.style.display = '';
            if (adminEl) adminEl.textContent = 'Rp ' + biayaAdmin.toLocaleString('id-ID');
        } else {
            adminItem.style.display = 'none';
        }
    }

    const telEl = document.getElementById('detail-telepon');
    if (telEl) telEl.innerHTML = `<i class="ph ph-phone"></i> ${d.telepon || '-'}`;

    const alamatEl = document.getElementById('detail-alamat');
    if (alamatEl) alamatEl.innerHTML = `<i class="ph ph-map-pin"></i> ${d.alamat || '-'}`;

    const detailModal = document.getElementById('detailModal');
    if (detailModal) {
        detailModal.dataset.currentId = d.id || '';
        detailModal.style.display = 'block';
    }
}

/* ============================================================
   NOTA PREVIEW MODAL
   ============================================================ */
function closeNotaPreview() {
    const modal = document.getElementById('notaPreviewModal');
    if (modal) modal.style.display = 'none';
    const body = document.getElementById('nota-preview-body');
    if (body) body.innerHTML = '';
}

function openNotaPreview(url) {
    if (!url) return;
    const body = document.getElementById('nota-preview-body');
    const isPdf = url.toLowerCase().split('?')[0].endsWith('.pdf');
    if (body) {
        body.innerHTML = isPdf
            ? `<embed src="${url}" type="application/pdf" class="nota-preview-pdf">`
            : `<img src="${url}" alt="Bukti Nota" class="nota-preview-image">`;
    }
    const openLink = document.getElementById('nota-open-new-tab');
    if (openLink) openLink.setAttribute('href', url);
    const modal = document.getElementById('notaPreviewModal');
    if (modal) modal.style.display = 'block';
}

/* ============================================================
   ITEM TOAST
   ============================================================ */
let itemToastEl = null;
let itemToastTimer = null;

function getItemToast() {
    if (!itemToastEl) {
        itemToastEl = document.createElement('div');
        itemToastEl.id = 'item-toast';
        document.body.appendChild(itemToastEl);
    }
    return itemToastEl;
}

function showItemToast(html, type = 'success') {
    const el = getItemToast();
    el.className = 'item-toast ' + type;
    el.innerHTML = html;
    clearTimeout(itemToastTimer);
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    itemToastTimer = setTimeout(() => el.classList.remove('show'), 6000);
}

function hideItemToast() {
    if (itemToastEl) itemToastEl.classList.remove('show');
    clearTimeout(itemToastTimer);
}

/* ============================================================
   TOAST (edit modal)
   ============================================================ */
const toast = document.getElementById('toast');
function showToast(message, type = 'success') {
    if (!toast) return;
    toast.className = '';
    toast.classList.add(type);
    toast.innerHTML = message;
    setTimeout(() => toast.classList.add('show'), 10);
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => toast.classList.remove('show'), 5000);
}