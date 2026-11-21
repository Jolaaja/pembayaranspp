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
    'adminedit.php' => ['Pengaturan akun', 'Perbarui informasi akun administrator.'],
];
[$pageTitle, $pageDescription] = $pageInfo[$currentPage] ?? ['Aplikasi SPP', 'Pengelolaan pembayaran sekolah.'];
$adminName = (string) ($_SESSION['nama_admin'] ?? 'Administrator');
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153a3a">
    <meta name="description" content="Aplikasi pengelolaan pembayaran SPP sekolah">
    <title><?= e($pageTitle) ?> | SPP</title>
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a class="brand-lockup" href="index.php" aria-label="SPP, ke ringkasan">
            <span class="brand-mark">S</span>
            <span class="brand-copy"><strong>SEKOLAH</strong><small>PORTAL KEUANGAN</small></span>
        </a>
        <div class="sidebar-caption">MENU UTAMA</div>
        <nav class="side-nav" aria-label="Navigasi utama">
            <a class="side-link <?= $currentPage === 'index.php' ? 'is-active' : '' ?>" href="index.php"><span class="side-icon">⌂</span><span>Ringkasan</span></a>
            <a class="side-link <?= $currentPage === 'pembayaran.php' ? 'is-active' : '' ?>" href="pembayaran.php"><span class="side-icon">↗</span><span>Pembayaran</span></a>
            <a class="side-link <?= $currentPage === 'datasiswa.php' ? 'is-active' : '' ?>" href="datasiswa.php"><span class="side-icon">♙</span><span>Data siswa</span></a>
            <div class="sidebar-caption sidebar-caption-spaced">DATA SEKOLAH</div>
            <a class="side-link <?= $currentPage === 'editdatasiswa.php' ? 'is-active' : '' ?>" href="editdatasiswa.php"><span class="side-icon">＋</span><span>Kelola siswa</span></a>
            <a class="side-link <?= $currentPage === 'editdatakelas.php' ? 'is-active' : '' ?>" href="editdatakelas.php"><span class="side-icon">▦</span><span>Kelas</span></a>
            <a class="side-link <?= $currentPage === 'editdatajurusan.php' ? 'is-active' : '' ?>" href="editdatajurusan.php"><span class="side-icon">◇</span><span>Jurusan</span></a>
            <a class="side-link <?= $currentPage === 'editdataangkatan.php' ? 'is-active' : '' ?>" href="editdataangkatan.php"><span class="side-icon">◷</span><span>Angkatan &amp; biaya</span></a>
            <div class="sidebar-caption sidebar-caption-spaced">LAINNYA</div>
            <a class="side-link <?= $currentPage === 'laporan.php' ? 'is-active' : '' ?>" href="laporan.php"><span class="side-icon">▤</span><span>Laporan</span></a>
        </nav>
        <div class="sidebar-foot">
            <span class="status-dot"></span>
            <span><strong>Sistem aktif</strong><small>Data tersimpan di sekolah</small></span>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="icon-button mobile-menu" data-sidebar-toggle aria-label="Buka navigasi"><span>☰</span></button>
            <div class="topbar-context"><span class="eyebrow">PORTAL ADMINISTRATOR</span><span class="topbar-date"><?= date('d/m/Y') ?></span></div>
            <div class="topbar-account">
                <a class="account-copy" href="adminedit.php"><strong><?= e($adminName) ?></strong><small>Pengaturan akun</small></a>
                <a class="account-avatar" href="adminedit.php" aria-label="Pengaturan akun"><?= e($adminInitial) ?></a>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="account-logout" type="submit" title="Keluar" aria-label="Keluar">↗</button>
                </form>
            </div>
        </header>
        <main class="app-content">
            <div class="page-heading">
                <div><span class="eyebrow">RUANG KERJA</span><h1><?= e($pageTitle) ?></h1><p><?= e($pageDescription) ?></p></div>
            </div>
            <?php show_flash(); ?>
            <div class="container-fluid page-container">
