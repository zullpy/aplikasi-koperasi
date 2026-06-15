function openModal() {
    const modal = document.querySelector('.modal');
    modal.style.display = 'block';
}

function openAddModal() {
    // Reset form fields
    const form = document.getElementById('modal-form');
    if (form) {
        form.reset();
    }

    // Reset modal header, action, and submit button
    const modalTitle = document.getElementById('modal-title');
    if (modalTitle) {
        modalTitle.textContent = 'Tambah Transaksi Pembelian';
    }

    if (form) {
        form.setAttribute('action', '../database/add-transaksi.php');
    }

    const idInput = document.getElementById('id_barang');
    if (idInput) {
        idInput.value = '';
    }

    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.textContent = 'Tambah';
    }

    const label = document.getElementById('nota_file_label');
    if (label) {
        label.innerHTML = 'Foto Nota (File)';
    }

    const cameraGroup = document.querySelector('.camera-only');
    const fileGroup = document.querySelector('.file-input-group');

    cameraGroup.style.display = 'block';
    fileGroup.style.display = 'block';


    openModal();
}

function closeModal() {
    const modal = document.querySelector('.modal');
    modal.style.display = 'none';
}

function openNotaModal(id) {
    const form = document.getElementById('nota-form');
    if (form) {
        form.reset();
    }
    const idInput = document.getElementById('nota_id_barang');
    if (idInput) {
        idInput.value = id;
    }
    const modal = document.getElementById('notaModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeNotaModal() {
    const modal = document.getElementById('notaModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Search bar logic
    const searchInput = document.getElementById('search-bar');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const keyword = searchInput.value.toLowerCase().trim();

            // Filter desktop table rows
            const tableRows = document.querySelectorAll('.modern-table tbody tr');
            tableRows.forEach(row => {
                const namaBarang = row.querySelector('td:first-child');
                if (namaBarang) {
                    const text = namaBarang.textContent.toLowerCase();
                    row.style.display = text.includes(keyword) ? '' : 'none';
                }
            });

            // Filter mobile cards
            const mobileCards = document.querySelectorAll('.mobile-card .barang-card');
            mobileCards.forEach(card => {
                const summary = card.querySelector('summary');
                if (summary) {
                    const text = summary.textContent.toLowerCase();
                    card.style.display = text.includes(keyword) ? '' : 'none';
                }
            });
        });
    }

    const form = document.getElementById('modal-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const idInput = document.getElementById('id_pembelian');
            const isEditMode = idInput && idInput.value !== '';

            // If in edit mode, nota file is not mandatory
            if (isEditMode) {
                return;
            }

            const cameraInput = document.getElementById('nota_kamera');
            const fileInput = document.getElementById('nota_file');

            const isMobile = window.innerWidth <= 768;

            // if (isMobile) {
            //     const hasCamera = cameraInput && cameraInput.files.length > 0;
            //     const hasFile = fileInput && fileInput.files.length > 0;

            //     if (!hasCamera && !hasFile) {
            //         e.preventDefault();
            //         Swal.fire({
            //             icon: 'warning',
            //             title: 'Oops...',
            //             text: 'Silakan unggah nota belanja Anda! Pilih salah satu dari Kamera atau File.',
            //             width: window.innerWidth < 768 ? '280px' : '400px'
            //         });
            //     }
            // } else {
            //     const hasFile = fileInput && fileInput.files.length > 0;
            //     if (!hasFile) {
            //         e.preventDefault();
            //         Swal.fire({
            //             icon: 'warning',
            //             title: 'Oops...',
            //             text: 'Silakan pilih berkas foto nota terlebih dahulu!',
            //             width: window.innerWidth < 768 ? '280px' : '400px'
            //         });
            //     }
            // }
        });
    }

    const notaForm = document.getElementById('nota-form');
    if (notaForm) {
        notaForm.addEventListener('submit', (e) => {
            const cameraInput = document.getElementById('nota_kamera_only');
            const fileInput = document.getElementById('nota_file_only');

            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                const hasCamera = cameraInput && cameraInput.files.length > 0;
                const hasFile = fileInput && fileInput.files.length > 0;

                if (!hasCamera && !hasFile) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Oops...',
                        text: 'Silakan unggah nota belanja Anda! Pilih salah satu dari Kamera atau File.',
                        width: window.innerWidth < 768 ? '280px' : '400px'
                    });
                }
            } else {
                const hasFile = fileInput && fileInput.files.length > 0;
                if (!hasFile) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Oops...',
                        text: 'Silakan pilih berkas foto nota terlebih dahulu!',
                        width: window.innerWidth < 768 ? '280px' : '400px'
                    });
                }
            }
        });
    }

    // Handle click events (for edit, delete, and add-nota buttons)
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-btn');
        const deleteBtn = e.target.closest('.delete-btn');
        const addNotaBtn = e.target.closest('.add-nota-btn');

        if (editBtn) {
            e.preventDefault();
            const id = editBtn.getAttribute('data-id');
            const cameraGroup = document.querySelector('.camera-only');
            const fileGroup = document.querySelector('.file-input-group');

            cameraGroup.style.display = 'none';
            fileGroup.style.display = 'none';
            if (id) {
                // Fetch barang details via AJAX
                fetch(`../database/get-barang.php?id=${id}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            const data = result.data;

                            // Fill fields in the modal
                            document.getElementById('id_pembelian').value = data.id_pembelian;
                            document.getElementById('id_supplier').value = data.id_supplier || '';
                            document.getElementById('nama_barang').value = data.nama_barang || '';
                            document.getElementById('tanggal_pembelian').value = data.tanggal_pembelian || '';
                            document.getElementById('harga').value = data.harga || '';
                            document.getElementById('volume').value = data.volume || '';
                            document.getElementById('satuan').value = data.satuan || '';
                            document.getElementById('keterangan').value = data.keterangan || '';

                            // Update modal headers/actions
                            const modalTitle = document.getElementById('modal-title');
                            if (modalTitle) {
                                modalTitle.textContent = 'Edit Transaksi Pembelian';
                            }

                            const modalForm = document.getElementById('modal-form');
                            if (modalForm) {
                                modalForm.setAttribute('action', '../database/update-barang.php');
                            }

                            const submitBtn = document.getElementById('submit-btn');
                            if (submitBtn) {
                                submitBtn.textContent = 'Simpan Perubahan';
                            }

                            const label = document.getElementById('nota_file_label');
                            if (label) {
                                label.innerHTML = 'Foto Nota (File) <small style="color: var(--text-muted); font-weight: normal;">(Biarkan kosong jika tidak ingin mengubah nota)</small>';
                            }

                            const cameraGroup = document.querySelector('.camera-only');
                            const fileGroup = document.querySelector('.file-input-group');

                            cameraGroup.style.display = 'none';
                            fileGroup.style.display = 'none';

                            // Open modal
                            openModal();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'Gagal mengambil data barang: ' + result.message,
                                width: window.innerWidth < 768 ? '280px' : '400px'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Terjadi kesalahan koneksi saat mengambil data barang!',
                            width: window.innerWidth < 768 ? '280px' : '400px'
                        });
                    });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'ID Transaksi tidak ditemukan!',
                    width: window.innerWidth < 768 ? '280px' : '400px'
                });
            }
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
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'ID Transaksi tidak ditemukan!',
                    width: window.innerWidth < 768 ? '280px' : '400px'
                });
            }
        }

        if (addNotaBtn) {
            e.preventDefault();
            const id = addNotaBtn.getAttribute('data-id');
            if (id) {
                openNotaModal(id);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'ID Transaksi tidak ditemukan!',
                    width: window.innerWidth < 768 ? '280px' : '400px'
                });
            }
        }
    });
});

const hargaInput = document.getElementById('harga');

hargaInput.addEventListener('input', function () {
    let angka = this.value.replace(/\D/g, '');

    if (angka === '') {
        this.value = '';
        return;
    }

    this.value = 'Rp ' + Number(angka).toLocaleString('id-ID');
});

const input = document.getElementById('nama_barang');
const suggestions = document.getElementById('suggestions');
const infoBarang = document.getElementById('info-barang');

input.addEventListener('input', () => {

    const keyword = input.value.trim();

    if (keyword === '') {
        suggestions.innerHTML = '';
        infoBarang.innerHTML = '';
        return;
    }

    // autocomplete
    if (keyword.length >= 2) {
        fetch(`../database/cari-barang-pembelian.php?q=${encodeURIComponent(keyword)}`)
            .then(res => res.text())
            .then(data => {
                suggestions.innerHTML = data;
            });
    }

    // cek barang
    fetch(`../database/cek-barang.php?nama_barang=${encodeURIComponent(keyword)}`)
        .then(res => res.json())
        .then(data => {

            if (data.status === 'ada') {
                const tgl = data.tanggal_terupdate_baru
                    ? ` <small style="opacity:0.7">(${data.tanggal_terupdate_baru})</small>`
                    : '';
                showToast(
                    `✅ Barang sudah terdaftar<br>
                Harga: Rp ${Number(data.harga).toLocaleString('id-ID')}${tgl}<br>
                Min: Rp ${Number(data.harga_min).toLocaleString('id-ID')}<br>
                Max: Rp ${Number(data.harga_max).toLocaleString('id-ID')}<br>
                Stok: ${data.stok} ${data.satuan}`,
                    'success'
                );
            } else {
                showToast(
                    '⚠️ Barang belum terdaftar <br> silahkan daftarkan terlebih dahulu! <br> di <a href="../daftar-harga-barang-food/index.php" style="color: var(--text-primary)">Daftar Harga</a>',
                    'warning'
                );
            }
        });
});



function pilihBarang(nama) {

    input.value = nama;

    suggestions.innerHTML = '';

    fetch(`../database/cek-barang.php?nama_barang=${encodeURIComponent(nama)}`)
        .then(res => res.json())
        .then(data => {

            if (data.status === 'ada') {
                const tgl = data.tanggal_terupdate_baru
                    ? ` <small style="opacity:0.7">(${data.tanggal_terupdate_baru})</small>`
                    : '';
                let infoHarga = `
                    Harga: Rp ${Number(data.harga).toLocaleString('id-ID')}${tgl}<br>
                `;
                if (data.harga_min != data.harga_max) {
                    infoHarga += `
                    Min: Rp ${Number(data.harga_min).toLocaleString('id-ID')}<br>
                    Max: Rp ${Number(data.harga_max).toLocaleString('id-ID')}<br>
                `;
                }

                showToast(
                    `✅ Barang sudah terdaftar<br>
                    ${infoHarga}
                    Stok: ${data.stok} ${data.satuan}`,
                    'success'
                );

                infoBarang.style.color = 'green';
            }

        });

}

const toast = document.getElementById('toast');

function showToast(message, type = 'success') {

    toast.className = '';
    toast.classList.add(type);

    toast.innerHTML = message;

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    clearTimeout(window.toastTimer);

    window.toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, Infinity);
}