<?php
require_once __DIR__ . '/app.php';
require_login();

$currentPage = basename($_SERVER['PHP_SELF']);
$pageInfo = [
    'index.php' => ['Ringkasan', 'Pantau pemasukan dan aktivitas pembayaran sekolah.'],
    'pembayaran.php' => ['Pembayaran SPP', 'Cari siswa dan kelola pembayaran bulanan.'],
    'datasiswa.php' => ['Data siswa', 'Lihat daftar siswa dan informasi akademik.'],
    'editdatasiswa.php' => ['Kelola siswa', 'Tambah, perbarui, dan kelola data siswa.'],
    'editdatakelas.php' => ['Kelola kelas', 'Atur daftar kelas sekolah.'],
    'editdatajurusan.php' => ['Kelola jurusan', 'Atur program dan jurusan sekolah.'],
    'editdataangkatan.php' => ['Kelola angkatan', 'Atur tahun angkatan dan nominal SPP.'],
    'laporan.php' => ['Laporan', 'Pilih periode dan cetak rekap pembayaran.'],
    'pengingat.php' => ['Pengingat pembayaran', 'Tinjau tunggakan dan jadwal jatuh tempo.'],
    'adminedit.php' => ['Pengaturan akun', 'Perbarui informasi dan password akun Anda.'],
    'identitassekolah.php' => ['Identitas sekolah', 'Atur profil, identitas visual, dan riwayat sekolah.'],
    'adminusers.php' => ['Pengguna', 'Kelola akun administrator dan kasir.'],
    'portal_accounts.php' => ['Akun portal keluarga', 'Kelola akses orang tua, wali, dan siswa.'],
    'portal_account_students.php' => ['Tautan siswa portal', 'Atur siswa yang dapat dilihat oleh akun portal.'],
    'auditlog.php' => ['Aktivitas', 'Tinjau perubahan dan transaksi yang tercatat.'],
];
[$pageTitle, $pageDescription] = $pageInfo[$currentPage] ?? ['Aplikasi SPP', 'Pengelolaan pembayaran sekolah.'];
$adminName = (string) ($_SESSION['nama_admin'] ?? 'Administrator');
$adminId = (int) $_SESSION['admin'];
$isAdmin = (string) ($_SESSION['role'] ?? '') === 'admin';
$schoolSettings = fetch_school_settings($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153a3a">
    <meta name="description" content="Aplikasi pengelolaan pembayaran SPP sekolah">
    <title><?= e($pageTitle) ?> | SPP</title>
    <link rel="icon" href="img/logo-spp.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css?v=<?= (int) filemtime(__DIR__ . '/css/modern.css') ?>">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a class="brand-lockup" href="index.php" aria-label="Portal SPP, ke ringkasan">
        <img class="brand-mark" src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
        <span class="brand-copy"><strong><?= e($schoolSettings['school_name']) ?></strong><small><?= $schoolSettings['school_npsn'] ? 'NPSN ' . e($schoolSettings['school_npsn']) : 'PORTAL · KEUANGAN' ?></small></span>
        </a>
        <div class="sidebar-caption">MENU UTAMA</div>
        <nav class="side-nav" aria-label="Navigasi utama">
            <a class="side-link <?= $currentPage === 'index.php' ? 'is-active' : '' ?>" href="index.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg><span>Ringkasan</span></a>
            <a class="side-link <?= $currentPage === 'pembayaran.php' ? 'is-active' : '' ?>" href="pembayaran.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18m-13 5h4"/></svg><span>Pembayaran</span></a>
            <a class="side-link <?= $currentPage === 'datasiswa.php' ? 'is-active' : '' ?>" href="datasiswa.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m2-11a3 3 0 0 1 0 6m1 2a5 5 0 0 1 3 3"/></svg><span>Data siswa</span></a>
            <?php if ($isAdmin): ?>
                <div class="sidebar-caption sidebar-caption-spaced">DATA SEKOLAH</div>
                <a class="side-link <?= $currentPage === 'editdatasiswa.php' ? 'is-active' : '' ?>" href="editdatasiswa.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M19 8v7m-3.5-3.5h7"/></svg><span>Kelola siswa</span></a>
                <a class="side-link <?= $currentPage === 'editdatakelas.php' ? 'is-active' : '' ?>" href="editdatakelas.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10m6-10v10"/></svg><span>Kelas</span></a>
                <a class="side-link <?= $currentPage === 'editdatajurusan.php' ? 'is-active' : '' ?>" href="editdatajurusan.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 9-9 9-9-9 9-9Z"/><circle cx="12" cy="12" r="2"/></svg><span>Jurusan</span></a>
                <a class="side-link <?= $currentPage === 'editdataangkatan.php' ? 'is-active' : '' ?>" href="editdataangkatan.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Angkatan &amp; biaya</span></a>
                <a class="side-link <?= $currentPage === 'adminusers.php' ? 'is-active' : '' ?>" href="adminusers.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m2-11a3 3 0 0 1 0 6m1 2a5 5 0 0 1 3 3"/></svg><span>Pengguna</span></a>
                <a class="side-link <?= in_array($currentPage, ['portal_accounts.php', 'portal_account_students.php'], true) ? 'is-active' : '' ?>" href="portal_accounts.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8" r="3.5"/><path d="M1.5 20a6.5 6.5 0 0 1 13 0m4-12v8m-4-4h8"/></svg><span>Akun portal keluarga</span></a>
                <a class="side-link <?= $currentPage === 'identitassekolah.php' ? 'is-active' : '' ?>" href="identitassekolah.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-4h4v4"/></svg><span>Identitas sekolah</span></a>
                <a class="side-link <?= $currentPage === 'auditlog.php' ? 'is-active' : '' ?>" href="auditlog.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01"/></svg><span>Aktivitas</span></a>
            <?php endif; ?>
            <div class="sidebar-caption sidebar-caption-spaced">LAINNYA</div>
            <a class="side-link <?= $currentPage === 'pengingat.php' ? 'is-active' : '' ?>" href="pengingat.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Pengingat</span></a>
            <a class="side-link <?= $currentPage === 'laporan.php' ? 'is-active' : '' ?>" href="laporan.php"><svg class="side-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V4m0 16h17M8 16v-5m5 5V7m5 9V9"/></svg><span>Laporan</span></a>
        </nav>
        <div class="sidebar-foot">
            <span class="status-dot"></span>
            <span><strong>Sistem aktif</strong><small>Data tersimpan di sekolah</small></span>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="icon-button mobile-menu" data-sidebar-toggle aria-label="Buka navigasi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <div class="topbar-context"><span class="eyebrow">PORTAL KEUANGAN SEKOLAH</span><span class="topbar-date"><?= date('d/m/Y') ?></span></div>
            <div class="topbar-account">
                <a class="account-copy" href="adminedit.php"><strong><?= e($adminName) ?></strong><small><?= $isAdmin ? 'Administrator · Pengaturan akun' : 'Kasir · Pengaturan akun' ?></small></a>
                <a class="profile-avatar-link" href="adminedit.php" aria-label="Buka profil akun"><img class="profile-avatar profile-avatar-topbar" src="avatar.php?id=<?= $adminId ?>" alt=""></a>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="account-logout" type="submit" title="Keluar" aria-label="Keluar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg></button>
                </form>
            </div>
        </header>
        <main class="app-content">
            <div class="page-heading">
                <div><span class="eyebrow">RUANG KERJA</span><h1><?= e($pageTitle) ?></h1><p><?= e($pageDescription) ?></p></div>
            </div>
            <?php show_flash(); ?>
            <div class="container-fluid page-container">
