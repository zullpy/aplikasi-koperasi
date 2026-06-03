function openModal(){
    const modal = document.querySelector('.modal');
    modal.style.display = 'block';
}

function closeModal(){
    const modal = document.querySelector('.modal');
    modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.modal form');
    if (form) {
        form.addEventListener('submit', (e) => {
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

    // Handle delete button click
    document.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            const id = deleteBtn.getAttribute('data-id');
            if (id) {
                if (confirm('Apakah Anda yakin ingin menghapus transaksi pembelian ini?')) {
                    window.location.href = `../database/delete-barang.php?id=${id}`;
                }
            } else {
                alert('ID Transaksi tidak ditemukan!');
            }
        }
    });
});