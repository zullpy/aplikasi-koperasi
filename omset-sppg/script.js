function formatRupiah(angka) {
    angka = Math.round(Number(angka) || 0);
    return 'Rp ' + angka.toLocaleString('id-ID');
}
function formatTanggal(tglStr) {
    if (!tglStr) return '-';
    const p = String(tglStr).split('-');
    if (p.length === 3) {
        const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    return tglStr;
}

function formatNamaBulan(bulanStr) {
    if (!bulanStr) return '-';
    const p = String(bulanStr).split('-');
    if (p.length >= 2) {
        const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
        return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }
    return bulanStr;
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

// ---- helper tanggal hari ini (format YYYY-MM-DD, local time) ----
function tanggalHariIni() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const t = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${t}`;
}

// ---- modal input ----
function bukaModalInput() {
    document.getElementById('tanggalInput').value = tanggalHariIni();
    document.getElementById('kpmBesar').value = '';
    document.getElementById('kpmKecil').value = '';
    hitungPreview();
    cekTanggalInput();
    document.getElementById('modalInput').classList.add('active');
}
function tutupModalInput() {
    document.getElementById('modalInput').classList.remove('active');
}
document.getElementById('modalInput')?.addEventListener('click', function (e) {
    if (e.target === this) tutupModalInput(); // klik area luar modal-box = tutup
});

// ---- cek apakah tanggal yang dipilih di modal sudah ada datanya ----
function cekTanggalInput() {
    const tgl = document.getElementById('tanggalInput').value;
    const info = document.getElementById('infoSudahInput');
    if (!tgl) {
        info.style.display = 'none';
        return;
    }
    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'cek_tanggal', tanggal: tgl })
    })
        .then(r => r.json())
        .then(res => {
            info.style.display = (res.success && res.ada) ? 'flex' : 'none';
        })
        .catch(() => { info.style.display = 'none'; });
}

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

// ---- simpan input harian (untuk tanggal yang dipilih) ----
function simpanHarian() {
    const tanggal = document.getElementById('tanggalInput').value;
    const kb = parseRibu(document.getElementById('kpmBesar').value);
    const kk = parseRibu(document.getElementById('kpmKecil').value);

    if (!tanggal) {
        Swal.fire('Oops', 'Tanggal wajib diisi', 'warning');
        return;
    }
    if (kb === 0 && kk === 0) {
        Swal.fire('Oops', 'Isi minimal salah satu KPM (besar/kecil)', 'warning');
        return;
    }

    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'simpan_harian',
            tanggal,
            kpm_besar: kb,
            kpm_kecil: kk
        })
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
                bulanTerpilih = tanggal.substring(0, 7);
                muatDataBulan();
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch((err) => {
            btn.disabled = false;
            Swal.fire('Error', err?.message || 'Terjadi kesalahan saat menyimpan', 'error');
        });
}

// ---- update KPM (besar/kecil) per baris tanggal ----
function updateKpm(tanggal, jenis, inputEl) {
    const nilai = parseRibu(inputEl.value);

    if (nilai < 0) {
        Swal.fire('Oops', 'KPM tidak boleh negatif', 'warning');
        return;
    }

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_kpm', tanggal, jenis, nilai })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // sinkronkan input dengan nilai final dari server
                inputEl.value = formatRibuDatabase(jenis === 'besar' ? res.kpm_besar : res.kpm_kecil);

                // Total Anggaran = murni hasil KPM (tidak dikurangi Anggaran Diterima)
                document.getElementById('anggaran-' + tanggal).innerText = formatRupiah(res.total_anggaran);
                document.getElementById('kpm-total-' + tanggal).innerText = (res.total_kpm || 0).toLocaleString('id-ID');
                document.getElementById('nominal-koperasi-' + tanggal).innerText = formatRupiah(res.nominal_koperasi);
                document.getElementById('nominal-yayasan-' + tanggal).innerText = formatRupiah(res.nominal_yayasan);
                document.getElementById('nominal-helmi-' + tanggal).innerText = formatRupiah(res.nominal_helmi);
                document.getElementById('nominal-management-' + tanggal).innerText = formatRupiah(res.nominal_management);

                hitungFooter();
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat mengubah KPM', 'error');
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

// ---- update anggaran diterima secara langsung (sebelumnya "biaya admin") ----
function updateAnggaranDiterima(tanggal, inputEl) {
    const anggaranDiterima = parseRibu(inputEl.value);

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_anggaran_diterima', tanggal, anggaran_diterima: anggaranDiterima })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('nominal-management-' + tanggal).innerText = formatRupiah(res.nominal_management);
                hitungFooter();
                muatDataBulan();
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat mengubah anggaran diterima', 'error');
        });
}

// ---- update pajak secara langsung ----
function updatePajak(tanggal, inputEl) {
    const pajak = parseRibu(inputEl.value);

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_pajak', tanggal, pajak })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('nominal-management-' + tanggal).innerText = formatRupiah(res.nominal_management);
                hitungFooter();
                muatDataBulan();
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat mengubah pajak', 'error');
        });
}

// ---- update pajak mingguan secara langsung ----
function updatePajakMingguan(tglMulai, tglSelesai, inputEl) {
    const pajak = parseRibu(inputEl.value);

    fetch('../database/api-omset-sppg.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_pajak_mingguan',
            tgl_mulai: tglMulai,
            tgl_selesai: tglSelesai,
            pajak: pajak
        })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                muatDataBulan();
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Terjadi kesalahan saat mengubah pajak mingguan', 'error');
        });
}

let bulanTerpilih = '';

function gantiBulanFilter(val) {
    bulanTerpilih = val;
    muatDataBulan();
}

// ---- render tabel ----
function muatDataBulan() {
    const tbody = document.getElementById('tbodyOmset');
    fetch('../database/api-omset-sppg.php?action=get_bulan' + (bulanTerpilih ? '&bulan=' + bulanTerpilih : ''))
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="14" class="empty-state">${res.message || 'Gagal memuat data'}</td></tr>`;
                }
                return;
            }

            const bulanLabel = formatNamaBulan(res.bulan);
            const labelEl = document.getElementById('labelBulan');
            if (labelEl) labelEl.innerText = bulanLabel;

            // Update dropdown opsi bulan tanpa merusak DOM jika sudah ada
            const filterSelect = document.getElementById('filterBulan');
            if (filterSelect) {
                if (res.list_bulan && filterSelect.options.length === 0) {
                    filterSelect.innerHTML = res.list_bulan.map(b => {
                        const label = formatNamaBulan(b);
                        return `<option value="${b}">${label}</option>`;
                    }).join('');
                }
                filterSelect.value = res.bulan;
            }
            bulanTerpilih = res.bulan;

            if (res.rows.length === 0) {
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="14" class="empty-state"><i class="ph ph-calendar-blank" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>Belum ada data omset bulan ini</td></tr>`;
                }
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

                    // KPM Besar & Kecil: editable langsung, Total: readonly hasil penjumlahan
                    const kolomKpm = `
                        <td>
                            <input type="text" class="rate-input" id="kpm-besar-${row.tanggal}"
                                value="${formatRibuDatabase(row.kpm_besar) || ''}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                oninput="inputMask(this)"
                                onchange="updateKpm('${row.tanggal}', 'besar', this)">
                        </td>
                        <td>
                            <input type="text" class="rate-input" id="kpm-kecil-${row.tanggal}"
                                value="${formatRibuDatabase(row.kpm_kecil) || ''}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                oninput="inputMask(this)"
                                onchange="updateKpm('${row.tanggal}', 'kecil', this)">
                        </td>
                        <td class="nominal-cell" id="kpm-total-${row.tanggal}">${(parseInt(row.total_kpm, 10) || 0).toLocaleString('id-ID')}</td>
                    `;

                    // Anggaran Diterima: input nominal langsung
                    const kolomAnggaranDiterima = `
                        <td class="nominal-cell">
                            <input type="text" class="rate-input rate-input-lg" id="anggaran-diterima-${row.tanggal}"
                                value="${formatRibuDatabase(row.biaya_admin) || ''}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                oninput="inputMask(this)"
                                onchange="updateAnggaranDiterima('${row.tanggal}', this)">
                        </td>
                    `;

                    // Pajak: input nominal langsung
                    const kolomPajak = `
                        <td class="nominal-cell">
                            <input type="text" class="rate-input rate-input-lg" id="pajak-${row.tanggal}"
                                value="${formatRibuDatabase(row.pajak) || ''}"
                                ${!IS_BENDAHARA ? 'disabled' : ''}
                                oninput="inputMask(this)"
                                onchange="updatePajak('${row.tanggal}', this)">
                        </td>
                    `;

                    // Belanja Foodcost: input nominal langsung
                    const kolomBelanja = `
                        <td class="nominal-cell">
                            <input type="text" class="rate-input rate-input-lg" id="belanja-foodcost-${row.tanggal}"
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

                    // Total Anggaran yang tampil = murni hasil KPM
                    const totalAnggaranTampil = parseFloat(row.total_anggaran) || 0;

                    return `
                        <tr>
                            <td>${formatTanggal(row.tanggal)}</td>
                            <td class="nominal-cell" id="anggaran-${row.tanggal}">${formatRupiah(totalAnggaranTampil)}</td>
                            ${kolomAnggaranDiterima}
                            ${kolomKpm}
                            ${kolomBelanja}
                            ${kolomKategori}
                            ${kolomManagement}
                        </tr>
                    `;
                }).join('');
            }

            renderFooter(res.total);

            // Render rekap mingguan jika ada
            const tbodyRekap = document.getElementById('tbodyRekapMingguan');
            if (tbodyRekap && res.rekap_mingguan) {
                if (res.rekap_mingguan.length === 0) {
                    tbodyRekap.innerHTML = `<tr><td colspan="8" class="empty-state"><i class="ph ph-calendar-x" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>Belum ada data rekap KPM</td></tr>`;
                } else {
                    let no = 1;
                    const formatTanggalRingkas = (tglStr) => {
                        if (!tglStr) return '-';
                        const p = tglStr.split('-');
                        if (p.length === 3) return `${p[2]}/${p[1]}/${p[0]}`;
                        return tglStr;
                    };
                    tbodyRekap.innerHTML = res.rekap_mingguan.map(row => {
                        const tglMulai = formatTanggalRingkas(row.tgl_mulai);
                        const tglSelesai = formatTanggalRingkas(row.tgl_selesai);
                        const rentangTanggal = `${tglMulai} - ${tglSelesai}`;
                        return `
                            <tr>
                                <td>${no++}</td>
                                <td style="font-weight: 600; text-align: center;">${rentangTanggal}</td>
                                <td class="nominal-cell">${formatRupiah(row.koperasi)}</td>
                                <td class="nominal-cell">${formatRupiah(row.yayasan)}</td>
                                <td class="nominal-cell">${formatRupiah(row.helmi)}</td>
                                <td class="nominal-cell">${formatRupiah(row.management)}</td>
                                <td class="nominal-cell">
                                    <input type="text" class="rate-input rate-input-lg"
                                        value="${formatRibuDatabase(row.pajak) || ''}"
                                        ${!IS_BENDAHARA ? 'disabled' : ''}
                                        oninput="inputMask(this)"
                                        onchange="updatePajakMingguan('${row.tgl_mulai}', '${row.tgl_selesai}', this)">
                                </td>
                                <td>
                                    <a href="cetak-rincian.php?start=${row.tgl_mulai}&end=${row.tgl_selesai}" target="_blank" class="btn-print" title="Cetak PDF Rincian Mingguan">
                                        <i class="ph ph-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }
            }
        })
        .catch(() => {
            document.getElementById('tbodyOmset').innerHTML = `<tr><td colspan="14" class="empty-state">Gagal memuat data</td></tr>`;
        });
}

function renderFooter(total) {
    if (document.getElementById('fTotalAnggaran')) {
        document.getElementById('fTotalAnggaran').innerText = formatRupiah(total.total_anggaran);
    }
    document.getElementById('fAnggaranDiterima').innerText = formatRupiah(total.anggaran_diterima);
    if (document.getElementById('fPajak')) {
        document.getElementById('fPajak').innerText = formatRupiah(total.pajak);
    }
    if (document.getElementById('fTotalKpm')) {
        document.getElementById('fTotalKpm').innerText = (total.total_kpm || 0).toLocaleString('id-ID');
    }
    document.getElementById('fBelanjaFoodcost').innerText = formatRupiah(total.pagu_belanja);
    document.getElementById('fNomKoperasi').innerText = formatRupiah(total.nominal_koperasi);
    document.getElementById('fNomYayasan').innerText = formatRupiah(total.nominal_yayasan);
    document.getElementById('fNomHelmi').innerText = formatRupiah(total.nominal_helmi);
    document.getElementById('fNomManagement').innerText = formatRupiah(total.nominal_management);
}

// recompute footer langsung dari DOM setelah update keuntungan (tanpa reload penuh)
function hitungFooter() {
    let totalAnggaran = 0, totalAnggaranDiterima = 0, totalPajak = 0, totalKpm = 0, totalBelanja = 0, totalKoperasi = 0, totalYayasan = 0, totalHelmi = 0, totalManagement = 0;

    // Total Anggaran
    document.querySelectorAll('[id^="anggaran-"]').forEach(el => {
        if (!el.id.startsWith('anggaran-diterima-')) {
            totalAnggaran += parseFloat(el.innerText.replace(/[^\d-]/g, '')) || 0;
        }
    });

    // Anggaran Diterima: baca dari input value
    document.querySelectorAll('[id^="anggaran-diterima-"]').forEach(el => {
        totalAnggaranDiterima += parseRibu(el.value);
    });

    // Pajak: baca dari input value
    document.querySelectorAll('[id^="pajak-"]').forEach(el => {
        totalPajak += parseRibu(el.value);
    });

    // Total KPM
    document.querySelectorAll('[id^="kpm-total-"]').forEach(el => {
        totalKpm += parseInt(el.innerText.replace(/\D/g, ''), 10) || 0;
    });

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

    if (document.getElementById('fTotalAnggaran')) document.getElementById('fTotalAnggaran').innerText = formatRupiah(totalAnggaran);
    document.getElementById('fAnggaranDiterima').innerText = formatRupiah(totalAnggaranDiterima);
    if (document.getElementById('fPajak')) {
        document.getElementById('fPajak').innerText = formatRupiah(totalPajak);
    }
    if (document.getElementById('fTotalKpm')) {
        document.getElementById('fTotalKpm').innerText = totalKpm.toLocaleString('id-ID');
    }
    document.getElementById('fBelanjaFoodcost').innerText = formatRupiah(totalBelanja);
    document.getElementById('fNomKoperasi').innerText = formatRupiah(totalKoperasi);
    document.getElementById('fNomYayasan').innerText = formatRupiah(totalYayasan);
    document.getElementById('fNomHelmi').innerText = formatRupiah(totalHelmi);
    document.getElementById('fNomManagement').innerText = formatRupiah(totalManagement);
}

// init
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('kpmBesar').addEventListener('input', function () {
        inputMask(this);
        hitungPreview();
    });
    document.getElementById('kpmKecil').addEventListener('input', function () {
        inputMask(this);
        hitungPreview();
    });
    document.getElementById('tanggalInput')?.addEventListener('change', cekTanggalInput);
    hitungPreview();
    muatDataBulan();
});