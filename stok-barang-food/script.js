const PAGE_SIZE = 10;
let currentPage = 1;
let data = [];

function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

function fmtQty(n) {
    n = n || 0;
    return Number.isInteger(n) ? n.toLocaleString('id-ID') : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

async function loadData() {
    try {
        const res = await fetch('../database/get-stok-barang.php');
        const json = await res.json();
        data = (json.status === 'success') ? json.data : [];
    } catch (e) { 
        console.error(e); 
        data = []; 
    }
    draw();
}

// Ambil total stok dalam satuan eceran untuk satu gudang
function stokEceranOf(g) {
    if (!g) return 0;
    return g.stok_eceran || 0;
}

// Ambil stok grosir untuk satu gudang
function stokGrosirOf(g) {
    if (!g) return 0;
    return g.stok_grosir || 0;
}

// Title Case buat label satuan
function ucSatuan(s) {
    if (!s) return '';
    return s.toString().trim().toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}

// Format stok jadi gabungan satuan besar + sisa satuan eceran.
// qty_grosir = jumlah dus yang sudah ada di database
// qty_eceran = total pcs (bukan tambahan)
// Contoh: qty_grosir=2, qty_eceran=48, isi=24 -> "2 Dus" (karena 48/24=2, tidak ada sisa)
// Contoh: qty_grosir=1, qty_eceran=47, isi=24 -> "1 Dus 23 Pcs" (karena 47-24=23 sisa)
function fmtStok(d, g) {
    const totalEceran = stokEceranOf(g);
    const stokGrosir = stokGrosirOf(g);
    
    if (totalEceran <= 0 && stokGrosir <= 0) return null;
    
    const isi = d.isi_per_satuan;
    
    // Jika tidak ada konversi satuan, tampilkan polos
    if (!isi || isi <= 0) {
        if (totalEceran > 0) {
            return `${fmtQty(totalEceran)} ${ucSatuan(d.satuan_eceran || d.satuan)}`;
        }
        return `${fmtQty(stokGrosir)} ${ucSatuan(d.satuan)}`;
    }
    
    // Hitung dari total eceran langsung
    const besar = Math.floor(totalEceran / isi);
    let sisa = totalEceran - (besar * isi);
    
    if (sisa < 0.01) sisa = 0;
    sisa = Math.round(sisa * 100) / 100;
    
    const parts = [];
    if (besar > 0) {
        parts.push(`${fmtQty(besar)} ${ucSatuan(d.satuan)}`);
    }
    if (sisa > 0) {
        parts.push(`${fmtQty(sisa)} ${ucSatuan(d.satuan_eceran)}`);
    }
    
    if (parts.length === 0) return null;
    return parts.join(' ');
}

function totalQtyOf(d) {
    return d.total_qty_eceran || 0;
}

function totalNilaiOf(d) {
    return d.total_nilai_eceran || 0;
}

function getStatus(totalQty) {
    if (totalQty <= 0) return { cls: 'st-habis', label: 'Habis' };
    if (totalQty <= 10) return { cls: 'st-rendah', label: 'Rendah' };
    if (totalQty <= 30) return { cls: 'st-menipis', label: 'Menipis' };
    return { cls: 'st-aman', label: 'Aman' };
}

function filtered() {
    const q = document.getElementById('search-input').value.toLowerCase();
    const gf = document.getElementById('filter-gudang').value;
    const sf = document.getElementById('filter-status').value;
    
    return data.filter(d => {
        const matchQ = !q || d.nama.toLowerCase().includes(q);
        
        let matchG = true;
        if (gf === 'pusat') matchG = stokEceranOf(d.pusat) > 0;
        if (gf === 'sodong') matchG = stokEceranOf(d.sodong) > 0;
        if (gf === 'sariwangi') matchG = stokEceranOf(d.sariwangi) > 0;
        if (gf === 'manonjaya') matchG = stokEceranOf(d.manonjaya) > 0;
        if (gf === 'habis') matchG = totalQtyOf(d) <= 0;
        
        let matchS = true;
        if (sf) matchS = getStatus(totalQtyOf(d)).cls === sf;
        
        return matchQ && matchG && matchS;
    });
}

function updateCards(rows) {
    let pusat = 0, cabang = 0, totalQty = 0;
    
    rows.forEach(d => {
        const harga = d.harga_eceran;
        pusat += stokEceranOf(d.pusat) * harga;
        cabang += (stokEceranOf(d.sodong) + stokEceranOf(d.sariwangi) + stokEceranOf(d.manonjaya)) * harga;
        totalQty += totalQtyOf(d);
    });
    
    document.getElementById('sum-pusat').textContent = rupiah(pusat);
    document.getElementById('sub-pusat').textContent = rows.filter(d => stokEceranOf(d.pusat) > 0).length + ' item aktif';
    document.getElementById('sum-cabang').textContent = rupiah(cabang);
    document.getElementById('sum-total').textContent = rupiah(pusat + cabang);
    document.getElementById('sub-total').textContent = rows.length + ' barang · ' + fmtQty(totalQty) + ' unit';
}

function cellGudang(d, g, cls) {
    const label = fmtStok(d, g);
    if (!label) return `<td class="center"><span class="num-zero">0</span></td>`;
    return `<td class="center"><span class="num-stok ${cls}">${label}</span></td>`;
}

function renderTable() { 
    currentPage = 1; 
    draw(); 
}

function draw() {
    const rows = filtered();
    updateCards(rows);
    
    const total = rows.length;
    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (currentPage > pages) currentPage = pages;
    
    const slice = rows.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
    
    document.getElementById('item-count').textContent = total + ' barang';
    const from = total === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1;
    const to = Math.min(currentPage * PAGE_SIZE, total);
    document.getElementById('page-info').textContent = `Menampilkan ${from}–${to} dari ${total}`;
    
    const tbody = document.getElementById('tb-body');
    if (total === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:#64748b;">Tidak ada data barang</td></tr>`;
    } else {
        tbody.innerHTML = slice.map((d, i) => {
            const tQty = totalQtyOf(d);
            const st = getStatus(tQty);
            const no = (currentPage - 1) * PAGE_SIZE + i + 1;
            
            return `<tr>
                <td><span class="sb-no">${no}</span></td>
                <td>
                    <div class="sb-nama">${d.nama}</div>
                    <div class="sb-satuan">${ucSatuan(d.satuan_eceran)} · <span class="sb-status ${st.cls}">${st.label}</span></div>
                </td>
                <td class="center"><span class="num-harga">${rupiah(d.harga_eceran)}</span></td>
                ${cellGudang(d, d.pusat, 'c-pusat')}
                ${cellGudang(d, d.sodong, 'c-sodong')}
                ${cellGudang(d, d.sariwangi, 'c-sariwangi')}
                ${cellGudang(d, d.manonjaya, 'c-manonjaya')}
                <td class="center"><span class="num-total-qty">${fmtTotalQty(d, tQty)}</span></td>
                <td class="center"><span class="num-total-nilai">${rupiah(totalNilaiOf(d))}</span></td>
            </tr>`;
        }).join('');
    }
    
    // Pagination
    const pg = document.getElementById('pagination');
    const MAX_BTN = 5;
    let html = '';
    
    if (currentPage > 1) html += `<button onclick="goPage(${currentPage - 1})"><i class="ti ti-chevron-left" style="font-size:12px"></i></button>`;
    
    let s = Math.max(1, currentPage - Math.floor(MAX_BTN / 2));
    let e = s + MAX_BTN - 1;
    if (e > pages) { e = pages; s = Math.max(1, e - MAX_BTN + 1); }
    
    for (let p = s; p <= e; p++)
        html += `<button class="${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
    
    if (currentPage < pages) html += `<button onclick="goPage(${currentPage + 1})"><i class="ti ti-chevron-right" style="font-size:12px"></i></button>`;
    
    pg.innerHTML = html;

    // Render kartu mobile
    drawMobileCards(slice, total);
}


// ─── Mobile card renderer ─────────────────────────────────────────────
function gudangCell(d, g, cls, labelText) {
    const label = fmtStok(d, g);
    const qty = label
        ? `<span class="mc-gudang-qty">${label}</span>`
        : `<span class="mc-gudang-qty zero">— Kosong</span>`;
    return `<div class="mc-gudang-cell ${cls}">
                <div class="mc-gudang-label">${labelText}</div>
                ${qty}
            </div>`;
}

function drawMobileCards(slice, total) {
    const el = document.getElementById('mobile-list');
    if (!el) return;

    if (total === 0) {
        el.innerHTML = `<div style="text-align:center;padding:32px 16px;color:#64748b;font-size:13px;">Tidak ada data barang</div>`;
        return;
    }

    el.innerHTML = slice.map((d, i) => {
        const tQty = totalQtyOf(d);
        const st = getStatus(tQty);
        const no = (currentPage - 1) * PAGE_SIZE + i + 1;

        return `<div class="mc-item">
            <div class="mc-top">
                <span class="mc-no">${no}</span>
                <div class="mc-info">
                    <div class="mc-nama">${d.nama}</div>
                    <div class="mc-satuan">
                        ${ucSatuan(d.satuan_eceran)}
                        <span class="sb-status ${st.cls}">${st.label}</span>
                    </div>
                </div>
                <div class="mc-harga-wrap">
                    <span class="mc-harga-label">Harga Eceran</span>
                    <span class="mc-harga">${rupiah(d.harga_eceran)}</span>
                </div>
            </div>
            <div class="mc-gudang-grid">
                ${gudangCell(d, d.pusat,     'gd-pusat',     'Pusat')}
                ${gudangCell(d, d.sodong,    'gd-sodong',    'Sodong')}
                ${gudangCell(d, d.sariwangi, 'gd-sariwangi', 'Sariwangi')}
                ${gudangCell(d, d.manonjaya, 'gd-manonjaya', 'Manonjaya')}
            </div>
            <div class="mc-bottom">
                <div class="mc-total-wrap">
                    <span class="mc-total-label">Total Stok</span>
                    <span class="mc-total-qty">${fmtTotalQty(d, tQty)}</span>
                </div>
                <div class="mc-total-wrap mc-right">
                    <span class="mc-total-label">Nilai Barang</span>
                    <span class="mc-total-nilai">${rupiah(totalNilaiOf(d))}</span>
                </div>
            </div>
        </div>`;
    }).join('');
}


function fmtTotalQty(d, totalQty) {
    if (!totalQty || totalQty <= 0) return `0 ${ucSatuan(d.satuan_eceran)}`;
    
    const isi = d.isi_per_satuan;
    if (!isi || isi <= 0) {
        return `${fmtQty(totalQty)} ${ucSatuan(d.satuan_eceran || d.satuan)}`;
    }
    
    const besar = Math.floor(totalQty / isi);
    let sisa = totalQty - (besar * isi);
    sisa = Math.round(sisa * 100) / 100;
    
    const parts = [];
    if (besar > 0) parts.push(`${fmtQty(besar)} ${ucSatuan(d.satuan)}`);
    if (sisa > 0) parts.push(`${fmtQty(sisa)} ${ucSatuan(d.satuan_eceran)}`);
    
    if (parts.length === 0) return `0 ${ucSatuan(d.satuan_eceran)}`;
    return parts.join(' ');
}

function goPage(p) { 
    currentPage = p; 
    draw(); 
}

function resetFilter() {
    document.getElementById('search-input').value = '';
    document.getElementById('filter-gudang').value = '';
    document.getElementById('filter-status').value = '';
    renderTable();
}

loadData(); 