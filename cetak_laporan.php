<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$rawStart = $_GET['awal'] ?? '';
$rawEnd = $_GET['akhir'] ?? '';
$awal = is_string($rawStart) ? trim($rawStart) : '';
$akhir = is_string($rawEnd) ? trim($rawEnd) : '';
if (!is_valid_iso_date($awal) || !is_valid_iso_date($akhir) || $awal > $akhir) {
    http_response_code(400);
    exit('Rentang tanggal laporan tidak valid.');
}

$stmt = db_execute(
    $conn,
    'SELECT p.idspp, p.nobayar, p.tglbayar, p.bulan, p.jumlah, s.nisn, s.nama, k.nama_kelas
     FROM pembayaran p
     JOIN siswa s ON s.id_siswa = p.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE p.ket = ? AND p.tglbayar BETWEEN ? AND ?
     ORDER BY p.tglbayar DESC, p.idspp DESC',
    'sss',
    ['LUNAS', $awal, $akhir]
);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
$total = 0.0;
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
    $total += (float) $row['jumlah'];
}
mysqli_stmt_close($stmt);
$count = count($rows);
$schoolSettings = fetch_school_settings($conn);
$schoolContacts = array_filter([$schoolSettings['school_phone'], $schoolSettings['school_email']]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pembayaran SPP · <?= e($awal) ?> — <?= e($akhir) ?></title>
    <style>
        :root { color-scheme: light; --ink: #1d3032; --muted: #647773; --line: #e2e9e4; --forest: #153a3a; --mint: #edf6ef; --gold: #d5a85c; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f3f6f3; color: var(--ink); font-family: "Segoe UI", Arial, sans-serif; }
        .report { width: min(100%, 1180px); margin: 0 auto; padding: 36px 40px; border: 1px solid var(--line); border-radius: 17px; background: #fff; box-shadow: 0 20px 60px rgba(18,48,42,.08); }
        .report-head { display: flex; align-items: center; gap: 16px; padding-bottom: 24px; border-bottom: 2px solid var(--forest); }
        .report-head img { width: 56px; height: 56px; }
        .brand-name { margin: 0; color: var(--forest); font-size: 15px; font-weight: 800; letter-spacing: .12em; }
        .brand-caption { margin: 5px 0 0; color: var(--muted); font-size: 10px; }
        .report-title { margin-left: auto; text-align: right; }
        .report-title h1 { margin: 0; color: var(--ink); font-family: Georgia, serif; font-size: 22px; font-weight: 500; }
        .report-title p { margin: 6px 0 0; color: var(--muted); font-size: 11px; }
        .report-summary { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; margin: 22px 0; }
        .summary-card { padding: 14px 16px; border: 1px solid var(--line); border-radius: 11px; background: #fbfcfb; }
        .summary-card span { display: block; color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .summary-card strong { display: block; margin-top: 7px; color: var(--forest); font-family: Georgia, serif; font-size: 19px; font-weight: 500; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { padding: 10px 9px; border-bottom: 1px solid var(--line); font-size: 10px; text-align: left; vertical-align: top; }
        th { background: var(--mint); color: var(--forest); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #fafcfb; }
        tfoot td { border-top: 2px solid var(--forest); border-bottom: 0; background: #f5f8f5; font-weight: 800; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 30px 12px; color: var(--muted); text-align: center; }
        .report-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 25px; margin-top: 30px; color: var(--muted); font-size: 9px; }
        .signature { width: 190px; text-align: center; }
        .signature-space { height: 50px; border-bottom: 1px solid #9aaba3; }
        .signature-label { margin-top: 7px; }
        .toolbar { display: flex; justify-content: center; gap: 10px; margin: 16px auto 0; }
        .toolbar button { min-height: 39px; padding: 0 16px; border: 1px solid var(--forest); border-radius: 9px; background: var(--forest); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer; }
        .toolbar .back { border-color: #dfe7e2; background: #fff; color: var(--forest); }
        @page { size: A4 landscape; margin: 12mm; }
        @media (max-width: 700px) {
            body { padding: 10px; }
            .report { padding: 22px 16px; }
            .report-head { gap: 10px; }
            .report-head img { width: 42px; height: 42px; }
            .brand-name { font-size: 11px; }
            .report-title h1 { font-size: 16px; }
            .brand-caption, .report-title p { font-size: 9px; }
            .summary-card { padding: 11px; }
            .summary-card strong { font-size: 14px; }
            .report-foot { align-items: stretch; flex-direction: column; }
            .signature { align-self: flex-end; }
        }
        @media print {
            body { padding: 0; background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .report { width: 100%; padding: 0; border: 0; border-radius: 0; box-shadow: none; }
            .report-head { padding-bottom: 14px; }
            .report-summary { margin: 14px 0; }
            th, td { padding: 7px 6px; font-size: 8px; }
            .toolbar { display: none; }
            tr, .summary-card, .report-foot { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main class="report">
        <header class="report-head">
            <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
            <div>
                <p class="brand-name"><?= e($schoolSettings['school_name']) ?></p>
                <p class="brand-caption"><?= e($schoolSettings['school_address'] ?: 'Administrasi keuangan sekolah') ?></p>
                <?php if ($schoolContacts): ?><p class="brand-caption"><?= e(implode(' · ', $schoolContacts)) ?></p><?php endif; ?>
            </div>
            <div class="report-title"><h1>Laporan pembayaran</h1><p><?= e(date('d/m/Y', strtotime($awal))) ?> – <?= e(date('d/m/Y', strtotime($akhir))) ?></p></div>
        </header>
        <section class="report-summary" aria-label="Ringkasan laporan">
            <div class="summary-card"><span>Transaksi lunas</span><strong><?= number_format($count, 0, ',', '.') ?> transaksi</strong></div>
            <div class="summary-card"><span>Total pemasukan</span><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></div>
            <div class="summary-card"><span>Periode laporan</span><strong><?= e(date('d/m/Y', strtotime($awal))) ?> – <?= e(date('d/m/Y', strtotime($akhir))) ?></strong></div>
        </section>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No</th><th>Tanggal</th><th>No. bukti</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Bulan SPP</th><th class="right">Jumlah</th></tr></thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td class="empty" colspan="8">Tidak ada transaksi lunas pada periode ini.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php $receiptNumber = $row['nobayar'] ?: 'SPP-' . str_pad((string) $row['idspp'], 8, '0', STR_PAD_LEFT); ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e(date('d/m/Y', strtotime((string) $row['tglbayar']))) ?></td>
                            <td><?= e($receiptNumber) ?></td>
                            <td><?= e($row['nisn']) ?></td>
                            <td><?= e($row['nama']) ?></td>
                            <td><?= e($row['nama_kelas']) ?></td>
                            <td><?= e($row['bulan']) ?></td>
                            <td class="right">Rp <?= number_format((float) $row['jumlah'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?><tfoot><tr><td colspan="7" class="right">Total pemasukan</td><td class="right">Rp <?= number_format($total, 0, ',', '.') ?></td></tr></tfoot><?php endif; ?>
            </table>
        </div>
        <footer class="report-foot">
            <span>Dicetak pada <?= e(date('d/m/Y H:i')) ?> · <?= e($schoolSettings['school_name']) ?></span>
            <div class="signature"><div>Mengetahui,</div><div class="signature-space"></div><div class="signature-label">( __________________ )</div></div>
        </footer>
    </main>
    <nav class="toolbar" aria-label="Aksi laporan">
        <button type="button" class="back" onclick="if (window.opener) window.close(); else history.back()">Tutup</button>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </nav>
</body>
</html>
