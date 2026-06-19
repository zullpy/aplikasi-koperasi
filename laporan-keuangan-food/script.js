/* =========================================================
   FILTER TAHUN - auto submit saat dropdown diubah
========================================================= */
function initFilterTahun() {
    const select = document.getElementById('tahunFilter');
    if (!select) return;
    select.addEventListener('change', () => {
        document.getElementById('filterForm').submit();
    });
}

/* =========================================================
   EKSPOR LAPORAN KE PDF (html2pdf.js)
========================================================= */
function exportPDF() {
    const element = document.getElementById('laporanArea');
    const tahun = element.getAttribute('data-tahun') || new Date().getFullYear();

    const opt = {
        margin: 0.4,
        filename: `Laporan_Keuangan_${tahun}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };

    // Sembunyikan elemen yang tidak perlu dicetak
    document.querySelectorAll('.no-print').forEach(el => el.style.visibility = 'hidden');

    html2pdf().set(opt).from(element).save().then(() => {
        document.querySelectorAll('.no-print').forEach(el => el.style.visibility = 'visible');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initFilterTahun();

    const exportBtn = document.getElementById('btnExportPDF');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportPDF);
    }

    const hapusForm = document.querySelectorAll('.form-hapus-saldo');
    hapusForm.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!confirm('Hapus catatan ini?')) {
                e.preventDefault();
            }
        });
    });
});