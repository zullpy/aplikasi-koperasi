function formatRupiah(angka) {
    angka = Math.round(Number(angka) || 0);
    return 'Rp ' + angka.toLocaleString('id-ID');
}
function formatTanggal(tgl) {
    const d = new Date(tgl);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRibuDatabase(val) {
    if (val === undefined || val === null) return '';
    let num = parseFloat(val);
    if (isNaN(num)) return '';
    let bulat = Math.round(num);
    return bulat.toLocaleString('id-ID');
}

function formatRibuInput(val) {
    if (val === undefined || val === null) return '';
    let clean = String(val).replace(/\D/g, '');
    if (clean === '') return '';
    return parseInt(clean, 10).toLocaleString('id-ID');
}

function parseRibu(val) {
    if (typeof val === 'number') return val;
    if (!val) return 0;
    let clean = String(val).replace(/\D/g, '');
    return parseFloat(clean) || 0;
}

function inputMask(inputEl) {
    let selectionStart = inputEl.selectionStart;
    let originalLength = inputEl.value.length;
    
    let formatted = formatRibuInput(inputEl.value);
    inputEl.value = formatted;
    
    let newLength = inputEl.value.length;
    let selectionOffset = newLength - originalLength;
    inputEl.setSelectionRange(selectionStart + selectionOffset, selectionStart + selectionOffset);
}

// ---- modal input ----
function bukaModalInput() {
    document.getElementById('modalInput').classList.add('active');
}
function tutupModalInput() {
    document.getElementById('modalInput').classList.remove('active');
}
document.getElementById('modalInput')?.addEventListener('click', function (e) {
    if (e.target === this) tutupModalInput(); // klik area luar modal-box = tutup
});

// ---- live calc form input ----
function hitungPreview() {
    const kb = parseRibu(document.getElementById('kpmBesar').value);
    const kk = parseRibu(document.getElementById('kpmKecil').value);
    const ab = kb * HARGA_BESAR;
    const ak = kk * HARGA_KECIL;
    document.getElementById('anggaranBesar').value = formatRupiah(ab);
    document.getElementById('anggaranKecil').value = formatRupiah(ak);
    document.getElementById('jumlahKpm').innerText = (kb + kk).toLocaleString('id-ID');
    document.getElementById('totalAnggaranPreview').innerText = formatRupiah(ab + ak);
}

// ---- simpan input harian ----
function simpanHarian() {
    const kb = parseRibu(document.getElementById('kpmBesar').value);
    const kk = parseRibu(document.getElementById('kpmKecil').value);

    if (kb === 0 && kk === 0) {
        Swal.fire('Oops', 'Isi minimal salah satu KPM (besar/kecil)', 'warning');
        return;
    }

    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'simpan_harian', kpm_besar: kb, kpm_kecil: kk })
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        if (res.success) {
            tutupModalInput();
            Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message, timer: 1500, showConfirmButton: false });
            document.getElementById('kpmBesar').value = '';
            document.getElementById('kpmKecil').value = '';
            hitungPreview();
            bulanTerpilih = '';
            muatDataBulan();
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        Swal.fire('Error', 'Terjadi kesalahan saat menyimpan', 'error');
    });
}

// ---- update keuntungan per kategori (koperasi/yayasan/helmi) ----
function updateKeuntungan(tanggal, kategori, inputEl) {
    const nilai = parseFloat(inputEl.value) || 0;

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_keuntungan', tanggal, kategori, nilai })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('nominal-' + kategori + '-' + tanggal).innerText = formatRupiah(res.nominal);
            document.getElementById('nominal-management-' + tanggal).innerText = formatRupiah(res.nominal_management);
            hitungFooter();
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    });
}

// ---- update belanja foodcost secara langsung ----
function updateBelanjaFoodcost(tanggal, inputEl) {
    const belanja = parseRibu(inputEl.value);

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_belanja_foodcost', tanggal, belanja })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('nominal-management-' + tanggal).innerText = formatRupiah(res.nominal_management);
            hitungFooter();
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    });
}

let bulanTerpilih = '';

function gantiBulanFilter(val) {
    bulanTerpilih = val;
    muatDataBulan();
}

// ---- render tabel ----
function muatDataBulan() {
    fetch('../database/api-omset-sppg.php?action=get_bulan' + (bulanTerpilih ? '&bulan=' + bulanTerpilih : ''))
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;

            const bulanLabel = new Date(res.bulan + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            document.getElementById('labelBulan').innerText = bulanLabel;
            document.getElementById('infoSudahInput').style.display = res.sudah_input_hari_ini ? 'flex' : 'none';

            // Update dropdown opsi bulan secara dinamis
            const filterSelect = document.getElementById('filterBulan');
            if (filterSelect && res.list_bulan) {
                filterSelect.innerHTML = res.list_bulan.map(b => {
                    const label = new Date(b + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
                    return `<option value="${b}" ${b === res.bulan ? 'selected' : ''}>${label}</option>`;
                }).join('');
                bulanTerpilih = res.bulan;
            }

            const tbody = document.getElementById('tbodyOmset');
            if (res.rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" class="empty-state"><ph class="ph ph-calendar-blank"></ph><br>Belum ada data omset bulan ini</td></tr>`;
            } else {
                tbody.innerHTML = res.rows.map(row => {
                    const kategoris = ['koperasi', 'yayasan', 'helmi'];
                    const kolomKategori = kategoris.map(kat => `
                        <td>
                            <input type="number" class="rate-input" value="${parseFloat(row['keuntungan_' + kat]) || 0}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                onchange="updateKeuntungan('${row.tanggal}', '${kat}', this)">
                        </td>
                        <td class="nominal-cell" id="nominal-${kat}-${row.tanggal}">${formatRupiah(row['nominal_' + kat])}</td>
                    `).join('');

                    // Belanja Foodcost: input nominal langsung
                    const kolomBelanja = `
                        <td class="nominal-cell">
                            <input type="text" class="rate-input" id="belanja-foodcost-${row.tanggal}"
                                value="${formatRibuDatabase(row.pagu_belanja) || ''}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                oninput="inputMask(this)"
                                onchange="updateBelanjaFoodcost('${row.tanggal}', this)">
                        </td>
                    `;

                    // Management: nominal otomatis (readonly)
                    const kolomManagement = `
                        <td class="nominal-cell" id="nominal-management-${row.tanggal}">
                            ${formatRupiah(row['nominal_management'])}
                        </td>
                    `;

                    return `
                        <tr>
                            <td>${formatTanggal(row.tanggal)}</td>
                            <td>${formatRupiah(row.total_anggaran)}</td>
                            <td>${row.total_kpm}</td>
                            ${kolomBelanja}
                            ${kolomKategori}
                            ${kolomManagement}
                        </tr>
                    `;
                }).join('');
            }

            renderFooter(res.total);
        })
        .catch(() => {
            document.getElementById('tbodyOmset').innerHTML = `<tr><td colspan="11" class="empty-state">Gagal memuat data</td></tr>`;
        });
}

function renderFooter(total) {
    document.getElementById('fBelanjaFoodcost').innerText = formatRupiah(total.pagu_belanja);
    document.getElementById('fNomKoperasi').innerText = formatRupiah(total.nominal_koperasi);
    document.getElementById('fNomYayasan').innerText = formatRupiah(total.nominal_yayasan);
    document.getElementById('fNomHelmi').innerText = formatRupiah(total.nominal_helmi);
    document.getElementById('fNomManagement').innerText = formatRupiah(total.nominal_management);
}

// recompute footer langsung dari DOM setelah update keuntungan (tanpa reload penuh)
function hitungFooter() {
    let totalBelanja = 0, totalKoperasi = 0, totalYayasan = 0, totalHelmi = 0, totalManagement = 0;

    // Belanja Foodcost: baca dari input value
    document.querySelectorAll('[id^="belanja-foodcost-"]').forEach(el => {
        totalBelanja += parseRibu(el.value);
    });

    // Nominal koperasi/yayasan/helmi: baca dari td text
    document.querySelectorAll('[id^="nominal-koperasi-"]').forEach(el => totalKoperasi += parseFloat(el.innerText.replace(/[^\d-]/g, '')) || 0);
    document.querySelectorAll('[id^="nominal-yayasan-"]').forEach(el => totalYayasan += parseFloat(el.innerText.replace(/[^\d-]/g, '')) || 0);
    document.querySelectorAll('[id^="nominal-helmi-"]').forEach(el => totalHelmi += parseFloat(el.innerText.replace(/[^\d-]/g, '')) || 0);

    // Nominal management: baca dari td text
    document.querySelectorAll('[id^="nominal-management-"]').forEach(el => {
        totalManagement += parseFloat(el.innerText.replace(/[^\d-]/g, '')) || 0;
    });

    document.getElementById('fBelanjaFoodcost').innerText = formatRupiah(totalBelanja);
    document.getElementById('fNomKoperasi').innerText = formatRupiah(totalKoperasi);
    document.getElementById('fNomYayasan').innerText = formatRupiah(totalYayasan);
    document.getElementById('fNomHelmi').innerText = formatRupiah(totalHelmi);
    document.getElementById('fNomManagement').innerText = formatRupiah(totalManagement);
}

// init
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('kpmBesar').addEventListener('input', function() {
        inputMask(this);
        hitungPreview();
    });
    document.getElementById('kpmKecil').addEventListener('input', function() {
        inputMask(this);
        hitungPreview();
    });
    hitungPreview();
    muatDataBulan();
});