function openModal() {
    const modal = document.querySelector('.modal');
    modal.style.display = 'block';
}

function openAddModal() {
    const form = document.getElementById('modal-form');
    if (form) form.reset();

    const modalTitle = document.getElementById('modal-title');
    if (modalTitle) modalTitle.textContent = 'Tambah Barang';

    if (form) form.setAttribute('action', '../database/add-barang-baru.php');

    const idInput = document.getElementById('id_barang');
    if (idInput) idInput.value = '';

    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="ph ph-check-circle"></i><span>Simpan</span>';
    }

    // Reset harga jual
    const hargaJual = document.getElementById('harga_jual');
    if (hargaJual) hargaJual.value = '';

    openModal();
}

function closeModal() {
    const modal = document.querySelector('.modal');
    modal.style.display = 'none';
}

// Helper: format angka ke "1.000" (tanpa "Rp")
function formatNumber(num) {
    if (num === '' || num === null || isNaN(num)) return '';
    return Number(num).toLocaleString('id-ID');
}

// Helper: bersihkan input jadi angka murni
function cleanNumber(str) {
    if (!str) return 0;
    return parseInt(str.replace(/\D/g, ''), 10) || 0;
}

// Hitung harga jual realtime
function hitungHargaJual() {
    const hargaBeli = cleanNumber(document.getElementById('harga_beli').value);
    const keuntungan = cleanNumber(document.getElementById('keuntungan').value);
    const hargaJualInput = document.getElementById('harga_jual');
    const priceInfo = document.getElementById('price-info');

    const hargaJual = hargaBeli + keuntungan;
    hargaJualInput.value = hargaJual > 0 ? formatNumber(hargaJual) : '';

    // Update info box
    if (hargaBeli > 0 && keuntungan > 0) {
        const persen = ((keuntungan / hargaBeli) * 100).toFixed(1);
        priceInfo.innerHTML = `
            <i class="ph ph-check-circle" style="color:#10b981;"></i>
            <span>Margin <strong>${persen}%</strong> dari harga beli • Untung <strong>Rp ${formatNumber(keuntungan)}</strong> per item</span>
        `;
        priceInfo.classList.add('active');
    } else if (hargaBeli > 0 && keuntungan === 0) {
        priceInfo.innerHTML = `
            <i class="ph ph-warning" style="color:#f59e0b;"></i>
            <span>Keuntungan belum diisi — harga jual sama dengan harga beli</span>
        `;
        priceInfo.classList.remove('active');
    } else {
        priceInfo.innerHTML = `
            <i class="ph ph-info"></i>
            <span>Isi harga beli & keuntungan, harga jual akan terhitung otomatis</span>
        `;
        priceInfo.classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // ===== Search bar =====
    const searchInput = document.getElementById('search-bar');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const keyword = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('.modern-table tbody tr').forEach(row => {
                const namaBarang = row.querySelector('td:first-child');
                if (namaBarang) {
                    row.style.display = namaBarang.textContent.toLowerCase().includes(keyword) ? '' : 'none';
                }
            });
            document.querySelectorAll('.mobile-card .barang-card').forEach(card => {
                const summary = card.querySelector('summary');
                if (summary) {
                    card.style.display = summary.textContent.toLowerCase().includes(keyword) ? '' : 'none';
                }
            });
        });
    }

    // ===== Edit button =====
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.preventDefault();
            const id = editBtn.getAttribute('data-id');
            if (id) {
                fetch(`../database/get-barang-baru.php?id=${id}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            const data = result.data;
                            document.getElementById('id_barang').value = data.id_barang;
                            document.getElementById('nama_barang').value = data.nama_barang || '';
                            document.getElementById('kategori').value = data.kategori || '';
                            document.getElementById('satuan').value = data.satuan || '';
                            document.getElementById('suplier').value = data.suplier || '';
                            document.getElementById('tanggal_terupdate_baru').value = data.tanggal_terupdate_baru || '';

                            if (data.harga_beli) {
                                document.getElementById('harga_beli').value = formatNumber(data.harga_beli);
                            } else {
                                document.getElementById('harga_beli').value = '';
                            }

                            // Keuntungan = harga_jual - harga_beli
                            const keuntungan = (parseInt(data.harga_jual) || 0) - (parseInt(data.harga_beli) || 0);
                            if (keuntungan > 0) {
                                document.getElementById('keuntungan').value = formatNumber(keuntungan);
                            } else {
                                document.getElementById('keuntungan').value = '';
                            }

                            if (data.harga_jual) {
                                document.getElementById('harga_jual').value = formatNumber(data.harga_jual);
                            } else {
                                document.getElementById('harga_jual').value = '';
                            }

                            document.getElementById('modal-title').textContent = 'Edit Barang';
                            document.getElementById('modal-form').setAttribute('action', '../database/update-barang-baru.php');
                            document.getElementById('submit-btn').innerHTML = '<i class="ph ph-check-circle"></i><span>Simpan Perubahan</span>';

                            openModal();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Gagal mengambil data: ' + result.message });
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        Swal.fire({ icon: 'error', title: 'Oops...', text: 'Terjadi kesalahan koneksi!' });
                    });
            }
        }
    });

    // ===== Format Harga Beli =====
    const hargaBeliInput = document.getElementById('harga_beli');
    if (hargaBeliInput) {
        hargaBeliInput.addEventListener('input', function () {
            const angka = cleanNumber(this.value);
            this.value = angka > 0 ? formatNumber(angka) : '';
            hitungHargaJual();
        });
    }

    // ===== Format Keuntungan =====
    const keuntunganInput = document.getElementById('keuntungan');
    if (keuntunganInput) {
        keuntunganInput.addEventListener('input', function () {
            const angka = cleanNumber(this.value);
            this.value = angka > 0 ? formatNumber(angka) : '';
            hitungHargaJual();
        });
    }
});