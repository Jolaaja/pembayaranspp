<?php
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/function/function_rupiah.php';
include_once __DIR__ . '/koneksi.php';
$account = portal_require_login($conn);
$schoolSettings = fetch_school_settings($conn);
$students = portal_fetch_students($conn, (int) $account['id_portal_account']);

$requestedStudent = filter_var($_GET['siswa'] ?? null, FILTER_VALIDATE_INT);
$selectedStudent = null;
foreach ($students as $student) {
    if ($requestedStudent === false || $requestedStudent === null || (int) $student['id_siswa'] === $requestedStudent) {
        $selectedStudent = $student;
        break;
    }
}

$payments = $selectedStudent
    ? portal_fetch_student_payments($conn, (int) $account['id_portal_account'], (int) $selectedStudent['id_siswa'])
    : [];
$unpaid = array_values(array_filter($payments, static function (array $payment): bool {
    return $payment['ket'] !== 'LUNAS';
}));
$paid = array_values(array_filter($payments, static function (array $payment): bool {
    return $payment['ket'] === 'LUNAS';
}));
usort($unpaid, static function (array $first, array $second): int {
    return strcmp($first['jatuhtempo'], $second['jatuhtempo'])
        ?: ((int) $first['idspp'] <=> (int) $second['idspp']);
});
usort($paid, static function (array $first, array $second): int {
    return strcmp((string) $second['tglbayar'], (string) $first['tglbayar'])
        ?: strcmp($second['jatuhtempo'], $first['jatuhtempo']);
});
$unpaidTotal = array_sum(array_map(static function (array $payment): float {
    return (float) $payment['jumlah'];
}, $unpaid));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153a3a">
    <title>Ringkasan pembayaran | <?= e($schoolSettings['school_name']) ?></title>
    <link rel="icon" href="img/logo-spp.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css?v=<?= (int) filemtime(__DIR__ . '/css/modern.css') ?>">
</head>
<body class="portal-page">
<div class="portal-layout">
    <header class="portal-topbar">
        <a class="portal-school-brand" href="portal_dashboard.php">
            <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
            <span><strong><?= e($schoolSettings['school_name']) ?></strong><small>PORTAL KELUARGA</small></span>
        </a>
        <div class="portal-account-actions">
            <span><?= e($account['full_name']) ?> · <?= $account['account_type'] === 'guardian' ? 'Orang tua/wali' : 'Siswa' ?></span>
            <a class="btn btn-secondary btn-sm" href="portal_change_password.php">Ganti password</a>
            <form method="post" action="portal_logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-secondary btn-sm" type="submit">Keluar</button>
            </form>
        </div>
    </header>
    <main class="portal-content">
        <div class="portal-heading">
            <div><span class="eyebrow">INFORMASI SISWA</span><h1>Tagihan &amp; riwayat pembayaran</h1><p>Data hanya menampilkan siswa yang ditautkan sekolah ke akun Anda.</p></div>
        </div>

        <?php if (count($students) > 1): ?>
            <nav class="portal-student-switcher" aria-label="Pilih siswa">
                <?php foreach ($students as $student): ?>
                    <a class="<?= $selectedStudent && (int) $selectedStudent['id_siswa'] === (int) $student['id_siswa'] ? 'is-selected' : '' ?>"
                       href="portal_dashboard.php?siswa=<?= (int) $student['id_siswa'] ?>">
                        <?= e($student['nama']) ?><small><?= e($student['nama_kelas']) ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if (!$selectedStudent): ?>
            <section class="portal-empty-state">
                <h2>Belum ada siswa yang ditautkan</h2>
                <p>Hubungi petugas sekolah agar akun Anda ditautkan ke data siswa yang benar.</p>
            </section>
        <?php else: ?>
            <section class="portal-student-card">
                <div><span class="eyebrow">SISWA</span><h2><?= e($selectedStudent['nama']) ?></h2></div>
                <dl><div><dt>NISN</dt><dd><?= e($selectedStudent['nisn']) ?></dd></div><div><dt>Kelas</dt><dd><?= e($selectedStudent['nama_kelas']) ?></dd></div></dl>
            </section>
            <section class="portal-summary-grid" aria-label="Ringkasan tagihan">
                <article><span>Tagihan belum lunas</span><strong><?= number_format(count($unpaid), 0, ',', '.') ?></strong></article>
                <article><span>Total belum lunas</span><strong><?= e(format_rupiah($unpaidTotal)) ?></strong></article>
                <article><span>Pembayaran tercatat</span><strong><?= number_format(count($paid), 0, ',', '.') ?></strong></article>
            </section>
            <section class="portal-data-card">
                <div class="portal-card-heading"><div><h2>Jadwal tagihan</h2><p>Tagihan disusun berdasarkan jatuh tempo.</p></div></div>
                <div class="table-responsive">
                    <table class="table portal-table">
                        <thead><tr><th>Periode</th><th>Jatuh tempo</th><th>Nominal</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($unpaid as $payment): ?>
                            <tr><td><?= e($payment['bulan']) ?></td><td><?= e(date('d/m/Y', strtotime($payment['jatuhtempo']))) ?></td><td><?= e(format_rupiah($payment['jumlah'])) ?></td><td><span class="portal-status is-unpaid"><?= $payment['jatuhtempo'] < date('Y-m-d') ? 'Terlambat' : 'Belum lunas' ?></span></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$unpaid): ?><tr><td colspan="4" class="portal-no-data">Tidak ada tagihan yang belum lunas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="portal-data-card">
                <div class="portal-card-heading"><div><h2>Riwayat pembayaran</h2><p>Pembayaran yang sudah dikonfirmasi petugas sekolah.</p></div></div>
                <div class="table-responsive">
                    <table class="table portal-table">
                        <thead><tr><th>Periode</th><th>Tanggal bayar</th><th>Nomor transaksi</th><th>Nominal</th></tr></thead>
                        <tbody>
                        <?php foreach ($paid as $payment): ?>
                            <tr><td><?= e($payment['bulan']) ?></td><td><?= e($payment['tglbayar'] ? date('d/m/Y', strtotime($payment['tglbayar'])) : '—') ?></td><td><?= e($payment['nobayar'] ?: '—') ?></td><td><?= e(format_rupiah($payment['jumlah'])) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$paid): ?><tr><td colspan="4" class="portal-no-data">Belum ada pembayaran tercatat.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
        <p class="portal-readonly-note">Portal ini hanya untuk melihat informasi. Pembayaran tetap dilakukan melalui sekolah dan dicatat setelah dikonfirmasi petugas.</p>
    </main>
</div>
</body>
</html>
