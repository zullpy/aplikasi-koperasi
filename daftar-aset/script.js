const modal = document.getElementById('modalAset');
const form = document.getElementById('formAset');
const modalTitle = document.getElementById('modalTitle');

const modalPengecekan = document.getElementById('modalPengecekan');
const formPengecekan = document.getElementById('formPengecekan');

function bukaModalTambah() {
    form.reset();
    document.getElementById('aset_id').value = '';
    document.getElementById('aksi_form').value = 'tambah';
    modalTitle.innerHTML = '<i class="ph ph-plus-circle"></i> Tambah Aset';
    modal.classList.add('active');
}

function bukaModalEdit(id) {
    fetch(`?aksi=detail&id=${id}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('aset_id').value = data.id;
            document.getElementById('nama_aset').value = data.nama_aset;
            document.getElementById('jumlah').value = data.jumlah;
            document.getElementById('tanggal_beli').value = data.tanggal_beli;
            document.getElementById('kondisi').value = data.kondisi;
            document.getElementById('aksi_form').value = 'edit';
            modalTitle.innerHTML = '<i class="ph ph-pencil-simple"></i> Edit Aset';
            modal.classList.add('active');
        })
        .catch(() => {
            Swal.fire('Gagal', 'Tidak bisa mengambil data aset', 'error');
        });
}

function tutupModal() {
    modal.classList.remove('active');
}

function bukaModalPengecekan(id, nama, tglCek, kondisi) {
    document.getElementById('pengecekan_id').value = id;
    document.getElementById('pengecekan_nama').value = nama;
    document.getElementById('tanggal_pengecekan').value = tglCek || '';
    document.getElementById('kondisi_cek').value = kondisi || 'Baik';
    modalPengecekan.classList.add('active');
}

function tutupModalPengecekan() {
    modalPengecekan.classList.remove('active');
}

formPengecekan.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formPengecekan);

    fetch('', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                tutupModalPengecekan();
                Swal.fire({
                    icon: 'success',
                    title: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat menyimpan data', 'error');
        });
});

form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);

    fetch('', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                tutupModal(); // tutup modal dulu sebelum munculin alert
                Swal.fire({
                    icon: 'success',
                    title: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat menyimpan data', 'error');
        });
});

function hapusAset(id) {
    Swal.fire({
        title: 'Hapus Aset?',
        text: 'Data yang dihapus tidak dapat dikembalikan',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('aksi', 'hapus');
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        tutupModal();
                        Swal.fire({
                            icon: 'success',
                            title: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                });
        }
    });
}