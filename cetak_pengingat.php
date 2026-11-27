<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';
include __DIR__ . '/function/function_rupiah.php';

try {
    $filters = parse_payment_reminder_filters($_GET);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    exit($error->getMessage());
}
$result = fetch_payment_reminders($conn, $filters['status'], $filters['search'], $filters['days_ahead'], 1, null);
$schoolSettings = fetch_school_settings($conn);
$schoolContacts = array_filter([$schoolSettings['school_phone'], $schoolSettings['school_email'], $schoolSettings['school_website']]);
$statusLabels = [
    'overdue' => 'Tunggakan terlambat',
    'upcoming' => 'Jatuh tempo dalam ' . $filters['days_ahead'] . ' hari',
    'all' => 'Semua tagihan belum lunas',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengingat pembayaran · <?= e($schoolSettings['school_name']) ?></title>
    <style>
        :root { color-scheme: light; --ink: #1d3032; --muted: #647773; --line: #e2e9e4; --forest: #153a3a; --mint: #edf6ef; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 26px; background: #f3f6f3; color: var(--ink); font-family: "Segoe UI", Arial, sans-serif; }
        .reminder-report { width: min(100%, 1120px); margin: 0 auto; padding: 32px 36px; border: 1px solid var(--line); border-radius: 16px; background: #fff; box-shadow: 0 20px 60px rgba(18,48,42,.08); }
        .report-head { display: flex; align-items: center; gap: 14px; padding-bottom: 20px; border-bottom: 2px solid var(--forest); }
        .report-head img { width: 54px; height: 54px; object-fit: contain; }
        .school-name { margin: 0; color: var(--forest); font-size: 15px; font-weight: 800; }
        .school-contact { margin: 4px 0 0; color: var(--muted); font-size: 9px; overflow-wrap: anywhere; }
        .report-title { margin-left: auto; text-align: right; }
        .report-title h1 { margin: 0; font-family: Georgia,serif; font-size: 22px; font-weight: 500; }
        .report-title p { margin: 6px 0 0; color: var(--muted); font-size: 10px; }
        .report-summary { display: flex; justify-content: space-between; gap: 18px; margin: 20px 0; padding: 14px 16px; border: 1px solid #dfece4; border-radius: 11px; background: #f5faf6; }
        .report-summary span { display: block; color: var(--muted); font-size: 9px; }
        .report-summary strong { display: block; margin-top: 5px; color: var(--forest); font-size: 13px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { padding: 10px 9px; border-bottom: 1px solid var(--line); font-size: 10px; text-align: left; vertical-align: top; }
        th { background: var(--mint); color: var(--forest); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #fafcfb; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 30px 12px; color: var(--muted); text-align: center; }
        .report-foot { display: flex; justify-content: space-between; gap: 18px; margin-top: 20px; color: var(--muted); font-size: 9px; }
        .toolbar { display: flex; justify-content: center; gap: 10px; margin: 16px auto 0; }
        .toolbar button { min-height: 39px; padding: 0 16px; border: 1px solid var(--forest); border-radius: 9px; background: var(--forest); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer; }
        .toolbar .back { border-color: #dfe7e2; background: #fff; color: var(--forest); }
        @page { size: A4 landscape; margin: 12mm; }
        @media (max-width: 700px) {
            body { padding: 10px; }
            .reminder-report { padding: 22px 16px; }
            .report-head { gap: 9px; }
            .report-head img { width: 42px; height: 42px; }
            .school-name { font-size: 11px; }
            .school-contact { font-size: 8px; }
            .report-title h1 { font-size: 16px; }
            th, td { padding: 8px 6px; font-size: 9px; }
        }
        @media print {
            body { padding: 0; background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .reminder-report { width: 100%; padding: 0; border: 0; border-radius: 0; box-shadow: none; }
            .toolbar { display: none; }
            tr { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main class="reminder-report">
        <header class="report-head">
            <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
            <div>
                <p class="school-name"><?= e($schoolSettings['school_name']) ?></p>
                <?php if ($schoolSettings['school_address']): ?><p class="school-contact"><?= e($schoolSettings['school_address']) ?></p><?php endif; ?>
                <?php if ($schoolSettings['school_npsn']): ?><p class="school-contact">NPSN <?= e($schoolSettings['school_npsn']) ?></p><?php endif; ?>
                <?php if ($schoolContacts): ?><p class="school-contact"><?= e(implode(' · ', $schoolContacts)) ?></p><?php endif; ?>
            </div>
            <div class="report-title"><h1>Pengingat pembayaran SPP</h1><p><?= e($statusLabels[$filters['status']]) ?> · Dicetak <?= e(date('d/m/Y H:i')) ?></p></div>
        </header>
        <section class="report-summary" aria-label="Ringkasan pengingat">
            <div><span>Jumlah tagihan</span><strong><?= number_format($result['total'], 0, ',', '.') ?></strong></div>
            <div><span>Total nominal</span><strong><?= format_rupiah($result['amount']) ?></strong></div>
            <div><span>Filter pencarian</span><strong><?= $filters['search'] !== '' ? e($filters['search']) : 'Semua siswa' ?></strong></div>
        </section>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No</th><th>NISN</th><th>Nama siswa</th><th>Kelas</th><th>HP orang tua/wali</th><th>Periode SPP</th><th>Jatuh tempo</th><th class="right">Nominal</th></tr></thead>
                <tbody>
                    <?php if (!$result['rows']): ?><tr><td class="empty" colspan="8">Tidak ada tagihan pada filter ini.</td></tr><?php endif; ?>
                    <?php foreach ($result['rows'] as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($row['nisn']) ?></td>
                            <td><?= e($row['nama']) ?></td>
                            <td><?= e($row['nama_kelas']) ?></td>
                            <td><?= e($row['guardian_phone'] ?? '—') ?></td>
                            <td><?= e($row['bulan']) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($row['jatuhtempo']))) ?></td>
                            <td class="right"><?= format_rupiah((float) $row['jumlah']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <footer class="report-foot">
            <span>Daftar hanya memuat siswa aktif dan tagihan yang belum lunas.</span>
            <span>Portal administrasi sekolah</span>
        </footer>
    </main>
    <nav class="toolbar" aria-label="Aksi pengingat">
        <button type="button" class="back" onclick="if (window.opener) window.close(); else history.back()">Tutup</button>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </nav>
</body>
</html>
