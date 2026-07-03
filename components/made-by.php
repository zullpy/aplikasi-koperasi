<?php

/**
 * components/developer_credit.php
 * --------------------------------
 * Footer / credit bar reusable untuk semua halaman
 * (Transaksi, Data Master, Daftar, Pengajuan, Laporan, Laporan Keuangan, dll).
 *
 * Tahun otomatis mengikuti tahun berjalan (date("Y")),
 * jadi tidak perlu diubah manual tiap tahun.
 *
 * Cara pakai (di halaman lain):
 *
 *   <?php
 *   $appName       = "Koperasi Bina Usaha Sauyunan";
 *   $developerName = "Nama Developer";
 *   $developerUrl  = "https://portofolio-kamu.com"; // opsional, kosongkan jika tidak ada
 *   $startYear     = 2024; // opsional, kosongkan jika tidak perlu rentang tahun
 *   include __DIR__ . "/components/developer_credit.php";
 *   ?>
 *
 * Semua variabel di atas OPSIONAL. Kalau tidak diisi, akan pakai nilai default di bawah.
 *
 * PENTING - Supaya footer nempel di bawah layar walau konten sedikit:
 * Bungkus konten halaman (di layout utama/header.php) dengan struktur berikut,
 * lalu taruh include footer ini sebagai elemen TERAKHIR sebelum </body>:
 *
 *   <body style="display:flex; flex-direction:column; min-height:100vh; margin:0;">
 *       <header>...</header>
 *       <main style="flex:1 0 auto;">...</main>
 *       <?php include __DIR__ . "/components/developer_credit.php"; ?>
 *   </body>
 *
 * Kuncinya: body diberi min-height:100vh + flex-direction:column, dan
 * konten utama (main) diberi flex:1 0 auto supaya "mendorong" footer ke bawah.
 */

// Nilai default (dipakai kalau variabel belum di-set dari halaman pemanggil)
$appName       = $appName ?? "Koperasi Bina Usaha Sauyunan";
$developerName = $developerName ?? "Muhammad Zulfahmi";
$developerUrl  = $developerUrl ?? "";
$startYear     = $startYear ?? 2026;

$currentYear = date("Y");
$yearLabel   = ($startYear && (int)$startYear < (int)$currentYear)
    ? htmlspecialchars($startYear) . " - " . $currentYear
    : $currentYear;
?>

<footer class="developer-credit-footer">
    <p class="developer-credit-line">
        &copy; <?= $yearLabel ?>
        <span class="developer-credit-appname"><?= htmlspecialchars($appName) ?></span>
        &nbsp;&middot;&nbsp;
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
            fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
        </svg>
        Dikembangkan oleh
        <?php if (!empty($developerUrl)): ?>
            <a href="<?= htmlspecialchars($developerUrl) ?>" target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars($developerName) ?>
            </a>
        <?php else: ?>
            <span class="developer-credit-name"><?= htmlspecialchars($developerName) ?></span>
        <?php endif; ?>
    </p>
</footer>

<style>
    @media print {
        .developer-credit-footer {
            display: none !important;
        }
    }

    .developer-credit-footer {
        width: 100%;
        border-top: 1px solid #dbeafe;
        background-color: #eef4fb;
        padding: 6px 16px;
        box-sizing: border-box;
        text-align: center;
        position: sticky;
        bottom: 0;
        z-index: 10;
    }

    .developer-credit-line {
        margin: 0;
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        font-size: 12px;
        color: #64748b;
    }

    .developer-credit-line svg {
        margin-left: 2px;
    }

    .developer-credit-appname {
        color: #475569;
    }

    .developer-credit-line a,
    .developer-credit-name {
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
    }

    .developer-credit-line a:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }
</style>