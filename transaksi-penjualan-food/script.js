// ===== MODAL DASAR =====
function openModal() { document.getElementById("modalTransaksi").classList.add("active"); }
function closeModal() { document.getElementById("modalTransaksi").classList.remove("active"); }

// ===== TAMBAH BARANG =====
function tambahBarang() {
    const container = document.getElementById("barangContainer");
    const row = document.createElement("div");
    row.className = "barang-row";
    row.innerHTML = `
        <div class="autocomplete">
            <input type="text" class="barang-input" placeholder="Cari barang..." autocomplete="off" required>
            <input type="hidden" name="id_barang[]" class="id-barang">
            <div class="suggestions"></div>
        </div>
        <input type="number" name="qty[]" class="qty" min="1" value="1" oninput="hitungSubtotal(this)">
        <input type="text" name="satuan[]" class="satuan" placeholder="Satuan" readonly>
        <input type="text" name="harga[]" class="harga" placeholder="Harga" readonly>
        <input type="text" name="subtotal[]" class="subtotal" placeholder="Sub Total" readonly>
        <button type="button" class="hapus-barang" onclick="hapusBarang(this)"><i class="ph ph-trash"></i></button>
    `;
    container.appendChild(row);
}
function hapusBarang(button) { button.parentElement.remove(); hitungGrandTotal(); }

// ===== AUTOCOMPLETE BARANG =====
document.addEventListener("input", async function (e) {
    if (!e.target.classList.contains("barang-input")) return;
    const keyword = e.target.value;
    const suggestionBox = e.target.parentElement.querySelector(".suggestions");
    if (keyword.length < 1) { suggestionBox.innerHTML = ""; return; }
    const response = await fetch("../database/search-barang.php?keyword=" + encodeURIComponent(keyword));
    const data = await response.json();
    let html = "";
    data.forEach(barang => {
        html += `<div class="suggestion-item" data-id="${barang.id_barang}" data-harga="${barang.harga_jual}" data-nama="${barang.nama_barang}" data-satuan="${barang.satuan}">${barang.nama_barang}</div>`;
    });
    suggestionBox.innerHTML = html;
});

document.addEventListener("click", function (e) {
    if (!e.target.classList.contains("suggestion-item")) return;
    const item = e.target;
    const row = item.closest(".barang-row");
    row.querySelector(".barang-input").value = item.dataset.nama;
    row.querySelector(".id-barang").value = item.dataset.id;
    row.querySelector(".satuan").value = item.dataset.satuan;
    row.querySelector(".harga").value = item.dataset.harga;
    hitungSubtotal(row.querySelector(".qty"));
    row.querySelector(".suggestions").innerHTML = "";
});

// ===== HITUNG =====
function hitungSubtotal(qtyInput) {
    const row = qtyInput.closest(".barang-row");
    const qty = parseInt(qtyInput.value) || 0;
    const harga = parseInt(row.querySelector(".harga").value.replace(/[^0-9]/g, "")) || 0;
    row.querySelector(".subtotal").value = qty * harga;
    hitungGrandTotal();
}
function hitungGrandTotal() {
    let total = 0;
    document.querySelectorAll(".subtotal").forEach(item => total += parseInt(item.value) || 0);
    const el = document.getElementById("grandTotal");
    if (el) el.innerText = "Rp " + total.toLocaleString("id-ID");
}

function isiDataPelanggan() {
    const select = document.getElementById('id_pelanggan');
    const option = select.options[select.selectedIndex];
    document.getElementById('no_kontak').value = option.getAttribute('data-telepon') || '';
    document.getElementById('alamat').value = option.getAttribute('data-alamat') || '';
}

// ===== DETAIL =====
async function openDetail(id) {
    const res = await fetch('../database/get-detail-transaksi.php?id_transaksi=' + id);
    const data = await res.json();
    if (data.error) { Swal.fire('Error', data.error, 'error'); return; }

    document.getElementById('detailNama').textContent = data.nama_pelanggan;
    document.getElementById('detailFaktur').textContent = data.no_faktur;
    document.getElementById('detailTanggal').textContent = data.tanggal;
    document.getElementById('detailTotal').textContent = 'Rp ' + Number(data.total).toLocaleString('id-ID');

    // Status
    const status = data.status_pembayaran || 'lunas';
    let statusHtml = '';
    if (status === 'lunas') {
        statusHtml = `<span class="lunas-big-badge"><i class="ph-fill ph-check-circle"></i> LUNAS</span>`;
    } else if (status === 'sebagian') {
        statusHtml = `<span class="status-badge badge-sebagian" style="font-size:0.9rem;padding:6px 14px;">SEBAGIAN - Sisa Rp ${Number(data.sisa_bayar).toLocaleString('id-ID')}</span>`;
    } else {
        statusHtml = `<span class="status-badge badge-belum" style="font-size:0.9rem;padding:6px 14px;">BELUM LUNAS</span>`;
    }
    document.getElementById('detailStatusBox').innerHTML = statusHtml;

    let itemsHtml = '';
    data.items.forEach(item => {
        itemsHtml += `<tr><td>${item.nama_barang}</td><td>${item.qty} ${item.satuan}</td><td>Rp ${Number(item.harga_jual).toLocaleString('id-ID')}</td><td>Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td></tr>`;
    });
    document.getElementById('detailItems').innerHTML = itemsHtml;

    // Riwayat pembayaran
    let payHtml = '';
    if (data.pembayaran && data.pembayaran.length > 0) {
        payHtml = `<div class="riwayat-section"><h4><i class="ph ph-receipt"></i> Riwayat Pembayaran</h4>`;
        data.pembayaran.forEach(p => {
            payHtml += `
                <div class="riwayat-item">
                    <div class="riwayat-info">
                        <strong>${new Date(p.tanggal_bayar).toLocaleString('id-ID')}</strong>
                        <small>${p.keterangan || '-'}</small>
                        ${p.bukti_bayar ? `<a href="../uploads/bukti-bayar/${p.bukti_bayar}" target="_blank" class="bukti-link"><i class="ph ph-image"></i> Lihat Bukti</a>` : ''}
                    </div>
                    <div class="riwayat-amount">Rp ${Number(p.jumlah_bayar).toLocaleString('id-ID')}</div>
                </div>`;
        });
        payHtml += `</div>`;
    }
    document.getElementById('detailPembayaranBox').innerHTML = payHtml;

    document.getElementById('modalDetail').classList.add('active');
}
function closeDetail() { document.getElementById('modalDetail').classList.remove('active'); }

// ===== MODAL BAYAR =====
let currentSisaBayar = 0;
let currentTotal = 0;

function openBayar(id, total, sudahBayar) {
    currentTotal = total;
    currentSisaBayar = total - sudahBayar;
    document.getElementById('bayar_id_transaksi').value = id;
    document.getElementById('bayar_total_display').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
    document.getElementById('bayar_sudah_display').textContent = 'Rp ' + Number(sudahBayar).toLocaleString('id-ID');
    document.getElementById('bayar_sisa_display').textContent = 'Rp ' + Number(currentSisaBayar).toLocaleString('id-ID');
    document.getElementById('input_jumlah_bayar').value = '';
    document.getElementById('input_sisa_setelah').value = 'Rp ' + Number(currentSisaBayar).toLocaleString('id-ID');
    document.getElementById('bukti_preview').innerHTML = '';
    document.getElementById('upload_label').textContent = 'Klik untuk upload bukti (JPG/PNG/PDF)';
    document.getElementById('modalBayar').classList.add('active');
}
function closeBayar() { document.getElementById('modalBayar').classList.remove('active'); }

// Submit pembayaran via AJAX (mencegah form resubmit saat refresh)
document.addEventListener('DOMContentLoaded', function () {
    const formBayar = document.querySelector('#modalBayar form');
    if (!formBayar) return;
    formBayar.addEventListener('submit', async function (e) {
        e.preventDefault(); // Hentikan submit biasa

        const submitBtn = formBayar.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner"></i> Menyimpan...';

        const formData = new FormData(formBayar);
        try {
            const res = await fetch('../database/add-pembayaran.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await res.json();
            closeBayar();
            await Swal.fire({
                icon: data.icon,
                title: data.title,
                text: data.text,
                confirmButtonColor: '#2563a8'
            });
            location.reload(); // Reload setelah SweetAlert ditutup
        } catch (err) {
            Swal.fire('Error', 'Terjadi kesalahan jaringan. Silakan coba lagi.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});

function formatRupiahInput(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value === '') { input.value = ''; return; }
    input.value = Number(value).toLocaleString('id-ID');
}

function hitungSisaSetelahBayar() {
    const input = document.getElementById('input_jumlah_bayar').value;
    const bayar = parseInt(input.replace(/[^0-9]/g, '')) || 0;
    const sisa = currentSisaBayar - bayar;
    document.getElementById('input_sisa_setelah').value = 'Rp ' + Number(sisa < 0 ? 0 : sisa).toLocaleString('id-ID');
}

function setQuickAmount(percent) {
    const amount = Math.ceil(currentSisaBayar * percent / 100);
    document.getElementById('input_jumlah_bayar').value = Number(amount).toLocaleString('id-ID');
    hitungSisaSetelahBayar();
}

function previewBukti(input) {
    const preview = document.getElementById('bukti_preview');
    const label = document.getElementById('upload_label');
    preview.innerHTML = '';
    if (input.files && input.files[0]) {
        const file = input.files[0];
        label.textContent = file.name;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML = `<img src="${e.target.result}" alt="bukti">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `<div class="file-info"><i class="ph ph-file-pdf"></i><div><strong>${file.name}</strong><br><small>${(file.size / 1024).toFixed(1)} KB</small></div></div>`;
        }
    }
}

// ===== MODAL EDIT =====
async function openEdit(id) {
    const res = await fetch('../database/get-detail-transaksi.php?id_transaksi=' + id);
    const data = await res.json();
    if (data.error) { Swal.fire('Error', data.error, 'error'); return; }

    document.getElementById('edit_id_transaksi').value = data.id_transaksi;
    document.getElementById('edit_no_faktur').value = data.no_faktur;
    document.getElementById('edit_tanggal').value = data.tanggal.split(' ')[0];
    document.getElementById('edit_id_pelanggan').value = data.id_pelanggan;

    const container = document.getElementById('editBarangContainer');
    container.innerHTML = '';
    data.items.forEach(item => {
        tambahBarangEditWith(item);
    });
    hitungEditGrandTotal();
    document.getElementById('modalEdit').classList.add('active');
}
function closeEdit() { document.getElementById('modalEdit').classList.remove('active'); }

function tambahBarangEdit() {
    tambahBarangEditWith({ id_barang: '', nama_barang: '', qty: 1, satuan: '', harga_jual: 0, subtotal: 0 });
}
function tambahBarangEditWith(item) {
    const container = document.getElementById('editBarangContainer');
    const row = document.createElement('div');
    row.className = 'barang-row';
    row.innerHTML = `
        <div class="autocomplete">
            <input type="text" class="barang-input" value="${item.nama_barang}" placeholder="Cari barang..." autocomplete="off" required>
            <input type="hidden" name="id_barang[]" class="id-barang" value="${item.id_barang}">
            <div class="suggestions"></div>
        </div>
        <input type="number" name="qty[]" class="qty" min="1" value="${item.qty}" oninput="hitungEditSubtotal(this)">
        <input type="text" name="satuan[]" class="satuan" value="${item.satuan}" readonly>
        <input type="text" name="harga[]" class="harga" value="${item.harga_jual}" readonly>
        <input type="text" name="subtotal[]" class="subtotal" value="${item.subtotal}" readonly>
        <button type="button" class="hapus-barang" onclick="hapusBarangEdit(this)"><i class="ph ph-trash"></i></button>
    `;
    container.appendChild(row);
}
function hapusBarangEdit(btn) { btn.parentElement.remove(); hitungEditGrandTotal(); }
function hitungEditSubtotal(qtyInput) {
    const row = qtyInput.closest('.barang-row');
    const qty = parseInt(qtyInput.value) || 0;
    const harga = parseInt(row.querySelector('.harga').value) || 0;
    row.querySelector('.subtotal').value = qty * harga;
    hitungEditGrandTotal();
}
function hitungEditGrandTotal() {
    let total = 0;
    document.querySelectorAll('#editBarangContainer .subtotal').forEach(i => total += parseInt(i.value) || 0);
    document.getElementById('editGrandTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
}