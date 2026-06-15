function openModal() {
    document
        .getElementById("modalTransaksi")
        .classList.add("active");
}

function closeModal() {
    document
        .getElementById("modalTransaksi")
        .classList.remove("active");
}

function tambahBarang() {

    const container =
        document.getElementById("barangContainer");

    const row =
        document.createElement("div");

    row.className = "barang-row";

    row.innerHTML = `
        <div class="autocomplete">

            <input
                type="text"
                class="barang-input"
                placeholder="Cari barang..."
                autocomplete="off"
                required
            >

            <input
                type="hidden"
                name="id_barang[]"
                class="id-barang"
            >

            <div class="suggestions"></div>

        </div>

        <input
            type="number"
            name="qty[]"
            class="qty"
            min="1"
            value="1"
            oninput="hitungSubtotal(this)"
        >

        <input
            type="text"
            name="satuan[]"
            class="satuan"
            placeholder="Satuan"
            readonly
        >

        <input
            type="text"
            name="harga[]"
            class="harga"
            placeholder="Harga"
            readonly
        >

        <input
            type="text"
            name="subtotal[]"
            class="subtotal"
            placeholder="Sub Total"
            readonly
        >

        <button
            type="button"
            class="hapus-barang"
            onclick="hapusBarang(this)"
        >
            <i class="ph ph-trash"></i>
        </button>
    `;

    container.appendChild(row);
}

function hapusBarang(button) {
    button.parentElement.remove();
}

function ambilHarga(select) {

    let option =
        select.options[select.selectedIndex];

    let harga =
        option.getAttribute("data-harga");

    let row =
        select.closest(".barang-row");

    row.querySelector(".harga").value =
        harga;

    hitungSubtotal(
        row.querySelector(".qty")
    );
}

document.addEventListener("input", async function (e) {

    if (!e.target.classList.contains("barang-input"))
        return;

    const keyword = e.target.value;

    const suggestionBox =
        e.target.parentElement.querySelector(".suggestions");

    if (keyword.length < 1) {
        suggestionBox.innerHTML = "";
        return;
    }

    const response = await fetch(
        "../database/search-barang.php?keyword=" +
        encodeURIComponent(keyword)
    );

    const data = await response.json();

    let html = "";

    data.forEach(barang => {

        html += `
            <div
                class="suggestion-item"
                data-id="${barang.id_barang}"
                data-harga="${barang.harga_jual}"
                data-nama="${barang.nama_barang}"
                data-satuan="${barang.satuan}"
            >
                ${barang.nama_barang}
            </div>
        `;
    });

    suggestionBox.innerHTML = html;
});

document.addEventListener("click", function (e) {

    if (!e.target.classList.contains("suggestion-item"))
        return;

    const item = e.target;

    const row =
        item.closest(".barang-row");

    row.querySelector(".barang-input").value =
        item.dataset.nama;

    row.querySelector(".id-barang").value =
        item.dataset.id;

    row.querySelector(".satuan").value =
        item.dataset.satuan;

    row.querySelector(".harga").value =
        item.dataset.harga;
    hitungSubtotal(
        row.querySelector(".qty")
    );

    row.querySelector(".suggestions").innerHTML = "";
});

function hitungSubtotal(qtyInput) {

    const row =
        qtyInput.closest(".barang-row");

    const qty =
        parseInt(qtyInput.value) || 0;

    const hargaText =
        row.querySelector(".harga").value;

    const harga =
        parseInt(
            hargaText.replace(/[^0-9]/g, "")
        ) || 0;

    const subtotal = qty * harga;

    row.querySelector(".subtotal").value =
        subtotal;

    hitungGrandTotal();
    console.log({
        qty,
        harga,
        subtotal
    });
}

function hitungGrandTotal() {

    let total = 0;

    document
        .querySelectorAll(".subtotal")
        .forEach(item => {

            total +=
                parseInt(item.value) || 0;

        });

    document.getElementById(
        "grandTotal"
    ).innerText =
        "Rp " +
        total.toLocaleString("id-ID");
}

function isiDataPelanggan() {
    const select = document.getElementById('id_pelanggan');
    const option = select.options[select.selectedIndex];

    document.getElementById('no_kontak').value =
        option.getAttribute('data-telepon') || '';

    document.getElementById('alamat').value =
        option.getAttribute('data-alamat') || '';
}

async function openDetail(id) {
    const res = await fetch('../database/get-detail-transaksi.php?id_transaksi=' + id);
    const data = await res.json();

    // isi konten modal detail
    document.getElementById('detailNama').textContent = data.nama_pelanggan;
    document.getElementById('detailFaktur').textContent = data.no_faktur;
    document.getElementById('detailTanggal').textContent = data.tanggal;
    document.getElementById('detailTotal').textContent = 'Rp ' + Number(data.total).toLocaleString('id-ID');

    let itemsHtml = '';
    data.items.forEach(item => {
        itemsHtml += `
            <tr>
                <td>${item.nama_barang}</td>
                <td>${item.qty} ${item.satuan}</td>
                <td>Rp ${Number(item.harga_jual).toLocaleString('id-ID')}</td>
                <td>Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td>
            </tr>`;
    });
    document.getElementById('detailItems').innerHTML = itemsHtml;

    document.getElementById('modalDetail').classList.add('active');
}

function closeDetail() {
    document.getElementById('modalDetail').classList.remove('active');
}
