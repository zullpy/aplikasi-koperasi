let _apiUrl = '';
let _type   = '';
let _userRole = '';
let _editingId    = null;
let _deletingId   = null;
let _deletingName = '';

// key mapping sesuai struktur tabel di DB
const FIELDS = {
    supplier: { id: 'id_supplier', nama: 'nama_supplier', kontak: 'no_telepon', alamat: 'alamat' },
    customer: { id: 'id_pelanggan', nama: 'nama_pelanggan', kontak: 'no_telepon', alamat: 'alamat' }
};

function initPage({ apiUrl, type, role }) {
    _apiUrl = apiUrl;
    _type   = type;
    _userRole = role || '';
    fetchData();
    bindEvents();
}

// ── FETCH DATA ──
function fetchData(search = '') {
    const url = search ? `${_apiUrl}?search=${encodeURIComponent(search)}` : _apiUrl;
    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.success) renderCards(res.data);
            else showToast('Gagal memuat data', 'error');
        })
        .catch(err => {
            console.error(err);
            showToast('Tidak bisa terhubung ke server', 'error');
        });
}

// ── RENDER ──
function getInitials(name) {
    return String(name).trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function renderCards(items) {
    const grid    = document.getElementById('card-grid');
    const countEl = document.getElementById('count-badge');
    const f       = FIELDS[_type];
    countEl.textContent = items.length;

    if (items.length === 0) {
        const label = _type === 'supplier' ? 'supplier' : 'pelanggan';
        const icon  = _type === 'supplier' ? 'ti-building-store' : 'ti-users';
        const instruction = _userRole === 'admin' ? `<span>Klik "Tambah" untuk menambahkan ${label} baru.</span>` : '';
        grid.innerHTML = `
            <div class="empty-state">
                <i class="ti ${icon}"></i>
                <p>Belum ada data</p>
                ${instruction}
            </div>`;
        return;
    }

    grid.innerHTML = items.map(item => `
        <div class="data-card">
            <div class="card-top">
                <div class="avatar ${_type}">${getInitials(item[f.nama])}</div>
                ${_userRole === 'admin' ? `
                <div class="card-actions">
                    <button class="icon-btn" onclick="openModal(${item[f.id]})" title="Edit">
                        <i class="ti ti-edit"></i>
                    </button>
                    <button class="icon-btn del" onclick="confirmDelete(${item[f.id]}, '${escapeAttr(item[f.nama])}')" title="Hapus">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
                ` : ''}
            </div>
            <div class="card-name">${escapeHtml(item[f.nama])}</div>
            <div class="card-info">
                <div class="info-row">
                    <i class="ti ti-phone"></i>
                    <span>${escapeHtml(item[f.kontak] || '-')}</span>
                </div>
                <div class="info-row">
                    <i class="ti ti-map-pin"></i>
                    <span>${escapeHtml(item[f.alamat] || '-')}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// ── MODAL ──
function openModal(id = null) {
    _editingId   = id;
    const isEdit = id !== null;
    const label  = _type === 'supplier' ? 'Supplier' : 'Pelanggan';
    const icon   = _type === 'supplier' ? 'ti-building-store' : 'ti-users';
    const f      = FIELDS[_type];

    document.getElementById('modal-title').textContent = isEdit ? `Edit ${label}` : `Tambah ${label}`;
    document.getElementById('modal-icon').innerHTML    = `<i class="ti ${icon}"></i>`;

    if (isEdit) {
        fetch(_apiUrl)
            .then(r => r.json())
            .then(res => {
                const item = res.data.find(d => d[f.id] == id);
                if (item) {
                    document.getElementById('f-nama').value   = item[f.nama];
                    document.getElementById('f-kontak').value = item[f.kontak] || '';
                    document.getElementById('f-alamat').value = item[f.alamat] || '';
                }
            });
    } else {
        document.getElementById('f-nama').value   = '';
        document.getElementById('f-kontak').value = '';
        document.getElementById('f-alamat').value = '';
    }

    document.getElementById('modal-overlay').classList.add('open');
    setTimeout(() => document.getElementById('f-nama').focus(), 80);
}

function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
    _editingId = null;
}

function saveData() {
    const nama   = document.getElementById('f-nama').value.trim();
    const kontak = document.getElementById('f-kontak').value.trim();
    const alamat = document.getElementById('f-alamat').value.trim();
    const f      = FIELDS[_type];

    if (!nama) {
        const input = document.getElementById('f-nama');
        input.focus();
        input.style.borderColor = '#f59e0b';
        setTimeout(() => input.style.borderColor = '', 1200);
        return;
    }

    const isEdit  = _editingId !== null;
    const method  = isEdit ? 'PUT' : 'POST';

    // payload pakai key sesuai kolom DB
    const payload = {
        [f.nama]  : nama,
        [f.kontak]: kontak,
        [f.alamat]: alamat
    };
    if (isEdit) payload[f.id] = _editingId;

    const btn = document.getElementById('btn-save');
    btn.disabled    = true;
    btn.textContent = 'Menyimpan...';

    fetch(_apiUrl, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message);
                closeModal();
                fetchData(document.getElementById('search-input').value);
            } else {
                showToast(res.message || 'Gagal menyimpan data', 'error');
            }
        })
        .catch(() => showToast('Gagal terhubung ke server', 'error'))
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = 'Simpan';
        });
}

// ── DELETE ──
function confirmDelete(id, name) {
    _deletingId   = id;
    _deletingName = name;
    document.getElementById('confirm-name').textContent = name;
    document.getElementById('confirm-overlay').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
    _deletingId   = null;
    _deletingName = '';
}

function doDelete() {
    if (!_deletingId) return;
    const f   = FIELDS[_type];
    const btn = document.getElementById('btn-delete');
    btn.disabled    = true;
    btn.textContent = 'Menghapus...';

    fetch(_apiUrl, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ [f.id]: _deletingId })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message);
                closeConfirm();
                fetchData(document.getElementById('search-input').value);
            } else {
                showToast(res.message || 'Gagal menghapus data', 'error');
            }
        })
        .catch(() => showToast('Gagal terhubung ke server', 'error'))
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = 'Hapus';
        });
}

// ── TOAST ──
function showToast(msg, type = 'success') {
    const t   = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast' + (type === 'error' ? ' toast-error' : '');
    void t.offsetWidth;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2600);
}

// ── SEARCH debounce 300ms ──
let _searchTimer = null;
function handleSearch(e) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => fetchData(e.target.value.trim()), 300);
}

// ── BIND EVENTS ──
function bindEvents() {
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); closeConfirm(); }
    });
    document.getElementById('search-input').addEventListener('input', handleSearch);
}

// ── UTILS ──
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
function escapeAttr(str) {
    return String(str).replace(/'/g, "\\'");
}