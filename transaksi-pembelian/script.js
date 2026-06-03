function openModal(){
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
    
    openModal();
}

function closeModal(){
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
            const idInput = document.getElementById('id_barang');
            const isEditMode = idInput && idInput.value !== '';
            
            // If in edit mode, nota file is not mandatory
            if (isEditMode) {
                return;
            }
            
            const cameraInput = document.getElementById('nota_kamera');
            const fileInput = document.getElementById('nota_file');
            
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                const hasCamera = cameraInput && cameraInput.files.length > 0;
                const hasFile = fileInput && fileInput.files.length > 0;
                
                if (!hasCamera && !hasFile) {
                    e.preventDefault();
                    alert('Silakan unggah nota belanja Anda! Pilih salah satu dari Kamera atau File.');
                }
            } else {
                const hasFile = fileInput && fileInput.files.length > 0;
                if (!hasFile) {
                    e.preventDefault();
                    alert('Silakan pilih berkas foto nota terlebih dahulu!');
                }
            }
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
                    alert('Silakan unggah nota belanja Anda! Pilih salah satu dari Kamera atau File.');
                }
            } else {
                const hasFile = fileInput && fileInput.files.length > 0;
                if (!hasFile) {
                    e.preventDefault();
                    alert('Silakan pilih berkas foto nota terlebih dahulu!');
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
                            document.getElementById('tanggal').value = data.tgl_terupdate || '';
                            document.getElementById('harga_beli').value = data.harga_beli || '';
                            document.getElementById('stok_akhir').value = data.stok_akhir || '';
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
                            
                            // Open modal
                            openModal();
                        } else {
                            alert('Gagal mengambil data barang: ' + result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        alert('Terjadi kesalahan koneksi saat mengambil data barang!');
                    });
            } else {
                alert('ID Transaksi tidak ditemukan!');
            }
        }
        
        if (deleteBtn) {
            e.preventDefault();
            const id = deleteBtn.getAttribute('data-id');
            if (id) {
                if (confirm('Apakah Anda yakin ingin menghapus data transaksi pembelian ini?')) {
                    window.location.href = `../database/delete-barang.php?id=${id}`;
                }
            } else {
                alert('ID Transaksi tidak ditemukan!');
            }
        }

        if (addNotaBtn) {
            e.preventDefault();
            const id = addNotaBtn.getAttribute('data-id');
            if (id) {
                openNotaModal(id);
            } else {
                alert('ID Transaksi tidak ditemukan!');
            }
        }
    });
});