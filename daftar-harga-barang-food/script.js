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
        modalTitle.textContent = 'Tambah Barang';
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

    openModal();
}

function closeModal() {
    const modal = document.querySelector('.modal');
    modal.style.display = 'none';
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

    // Handle click events (for edit, delete, and add-nota buttons)
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-btn');

        if (editBtn) {
            e.preventDefault();
            const id = editBtn.getAttribute('data-id');
            if (id) {
                // Fetch barang details via AJAX
                fetch(`../database/get-barang.php?id=${id}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            const data = result.data;

                            // Fill fields in the modal
                            document.getElementById('id_barang').value = data.id_barang;
                            document.getElementById('nama_barang').value = data.nama_barang || '';
                            document.getElementById('kategori').value = data.kategori || '';
                            if (data.harga_beli) {
                                document.getElementById('harga_beli').value =
                                    'Rp ' + Number(data.harga_beli).toLocaleString('id-ID');
                            } else {
                                document.getElementById('harga_beli').value = '';
                            }
                            document.getElementById('suplier').value = data.suplier || '';
                            document.getElementById('satuan').value = data.satuan || '';
                            // document.getElementById('alamat').value = data.keterangan || '';

                            // Update modal headers/actions
                            const modalTitle = document.getElementById('modal-title');
                            if (modalTitle) {
                                modalTitle.textContent = 'Edit Barang';
                            }

                            const modalForm = document.getElementById('modal-form');
                            if (modalForm) {
                                modalForm.setAttribute('action', '../database/update-barang-baru.php');
                            }

                            const submitBtn = document.getElementById('submit-btn');
                            if (submitBtn) {
                                submitBtn.textContent = 'Simpan Perubahan';
                            }

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
    });
});

const hargaInput = document.getElementById('harga_beli');

hargaInput.addEventListener('input', function () {
    let angka = this.value.replace(/\D/g, '');

    if (angka === '') {
        this.value = '';
        return;
    }

    this.value = 'Rp ' + Number(angka).toLocaleString('id-ID');
});