<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$idspp = filter_input(INPUT_GET, 'idspp', FILTER_VALIDATE_INT);
if (!$idspp || $idspp < 1) {
    http_response_code(400);
    exit('ID pembayaran tidak valid.');
}

$stmt = db_execute(
    $conn,
    'SELECT p.*, s.nisn, s.nama, k.nama_kelas, j.nama_jurusan
     FROM pembayaran p
     JOIN siswa s ON s.id_siswa = p.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     JOIN jurusan j ON j.id_jurusan = s.id_jurusan
     WHERE p.idspp = ? AND p.ket = ?',
    'is',
    [$idspp, 'LUNAS']
);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if (!$data) {
    http_response_code(404);
    exit('Data pembayaran tidak ditemukan.');
}

$schoolSettings = fetch_school_settings($conn);
$paymentDetailsStmt = db_execute(
    $conn,
    "SELECT payment_method, payment_reference, details
     FROM pembayaran_events WHERE idspp = ? AND event_type = 'paid'
     ORDER BY id_event DESC LIMIT 1",
    'i',
    [$idspp]
);
$paymentDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentDetailsStmt)) ?: [];
mysqli_stmt_close($paymentDetailsStmt);
$paymentMethodLabels = [
    'cash' => 'Tunai',
    'bank_transfer' => 'Transfer bank',
    'qris' => 'QRIS',
    'other' => 'Lainnya',
];
$receiptNumber = $data['nobayar'] ?: 'SPP-' . str_pad((string) $data['idspp'], 8, '0', STR_PAD_LEFT);
$schoolContacts = array_filter([$schoolSettings['school_phone'], $schoolSettings['school_email'], $schoolSettings['school_website']]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Pembayaran SPP · <?= e($receiptNumber) ?></title>
    <style>
        :root { color-scheme: light; --ink: #1d3032; --muted: #647773; --line: #e5ece7; --forest: #153a3a; --mint: #eaf5ee; --gold: #d5a85c; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 34px 18px; background: #f3f6f3; color: var(--ink); font-family: "Segoe UI", Arial, sans-serif; }
        .receipt { position: relative; width: min(100%, 720px); margin: 0 auto; overflow: hidden; border: 1px solid var(--line); border-radius: 20px; background: #fff; box-shadow: 0 24px 70px rgba(18,48,42,.1); }
        .receipt::before { position: absolute; top: 0; right: 0; left: 0; height: 5px; background: linear-gradient(90deg, var(--forest), #4ca77b, var(--gold)); content: ""; }
        .receipt-head { display: flex; align-items: center; gap: 15px; padding: 32px 38px 24px; border-bottom: 1px solid var(--line); }
        .receipt-head img { width: 58px; height: 58px; }
        .brand-name { margin: 0; color: var(--forest); font-size: 15px; font-weight: 800; letter-spacing: .12em; }
        .brand-caption { margin: 5px 0 0; color: var(--muted); font-size: 11px; overflow-wrap: anywhere; }
        .document-title { margin-left: auto; text-align: right; }
        .document-title strong { display: block; font-size: 12px; letter-spacing: .08em; }
        .document-title small { display: block; margin-top: 6px; color: var(--muted); font-size: 10px; }
        .receipt-content { padding: 30px 38px 36px; }
        .amount-card { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 22px; border: 1px solid #dcece1; border-radius: 14px; background: linear-gradient(115deg, #f0f8f2, #fbfdfb); }
        .amount-label { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
        .amount-value { display: block; margin-top: 6px; color: var(--forest); font-family: Georgia, serif; font-size: 30px; letter-spacing: -.03em; }
        .paid-chip { display: inline-flex; align-items: center; gap: 7px; padding: 8px 11px; border: 1px solid #cfe7d5; border-radius: 999px; background: #eaf6ed; color: #27633f; font-size: 10px; font-weight: 800; letter-spacing: .07em; white-space: nowrap; }
        .paid-chip::before { width: 7px; height: 7px; border-radius: 50%; background: #368962; content: ""; }
        .section-title { margin: 26px 0 11px; color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th, .detail-table td { padding: 12px 0; border-bottom: 1px solid var(--line); font-size: 12px; text-align: left; vertical-align: top; }
        .detail-table th { width: 38%; color: var(--muted); font-weight: 500; }
        .detail-table td { color: var(--ink); font-weight: 650; }
        .receipt-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 25px; margin-top: 34px; }
        .receipt-note { max-width: 290px; color: var(--muted); font-size: 10px; line-height: 1.65; }
        .signature { width: 185px; color: var(--muted); font-size: 10px; text-align: center; }
        .signature-space { height: 58px; border-bottom: 1px solid #9aaba3; }
        .signature-label { margin-top: 8px; }
        .toolbar { display: flex; justify-content: center; gap: 10px; margin: 18px auto 0; }
        .toolbar button { min-height: 40px; padding: 0 16px; border: 1px solid var(--forest); border-radius: 9px; background: var(--forest); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
        .toolbar .back { border-color: #dfe7e2; background: #fff; color: var(--forest); }
        @page { size: A5 portrait; margin: 12mm; }
        @media (max-width: 560px) {
            body { padding: 14px 10px; }
            .receipt { border-radius: 14px; }
            .receipt-head { gap: 10px; padding: 25px 20px 18px; }
            .receipt-head img { width: 44px; height: 44px; }
            .brand-name { font-size: 11px; }
            .brand-caption, .document-title small { font-size: 9px; }
            .document-title strong { font-size: 9px; }
            .receipt-content { padding: 22px 20px 26px; }
            .amount-value { font-size: 24px; }
            .receipt-foot { align-items: stretch; flex-direction: column; }
            .signature { align-self: flex-end; }
        }
        @media print {
            body { padding: 0; background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .receipt { width: 100%; border: 1px solid #dbe3dd; border-radius: 10px; box-shadow: none; }
            .receipt-head { padding: 20px 24px 16px; }
            .receipt-content { padding: 20px 24px 24px; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <main class="receipt">
        <header class="receipt-head">
            <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
            <div>
                <p class="brand-name"><?= e($schoolSettings['school_name']) ?></p>
                <p class="brand-caption"><?= e($schoolSettings['school_address'] ?: 'Administrasi keuangan sekolah') ?></p>
                <?php if ($schoolSettings['school_npsn']): ?><p class="brand-caption">NPSN <?= e($schoolSettings['school_npsn']) ?></p><?php endif; ?>
                <?php if ($schoolContacts): ?><p class="brand-caption"><?= e(implode(' · ', $schoolContacts)) ?></p><?php endif; ?>
            </div>
            <div class="document-title"><strong>BUKTI PEMBAYARAN</strong><small>TAHUN AJARAN <?= e(substr((string) $data['bulan'], -4)) ?></small></div>
        </header>
        <section class="receipt-content">
            <div class="amount-card">
                <div><span class="amount-label">Total pembayaran</span><strong class="amount-value">Rp <?= number_format((float) $data['jumlah'], 0, ',', '.') ?></strong></div>
                <span class="paid-chip">LUNAS</span>
            </div>
            <h1 class="section-title">Rincian pembayaran</h1>
            <table class="detail-table">
                <tbody>
                    <tr><th>No. bukti</th><td><?= e($receiptNumber) ?></td></tr>
                    <tr><th>Nama siswa</th><td><?= e($data['nama']) ?></td></tr>
                    <tr><th>NISN</th><td><?= e($data['nisn']) ?></td></tr>
                    <tr><th>Kelas / jurusan</th><td><?= e($data['nama_kelas']) ?> / <?= e($data['nama_jurusan']) ?></td></tr>
                    <tr><th>Bulan SPP</th><td><?= e($data['bulan']) ?></td></tr>
                    <tr><th>Tanggal pembayaran</th><td><?= e(date('d/m/Y', strtotime((string) $data['tglbayar']))) ?></td></tr>
                    <?php if (isset($paymentMethodLabels[$paymentDetails['payment_method'] ?? ''])): ?><tr><th>Metode pembayaran</th><td><?= e($paymentMethodLabels[$paymentDetails['payment_method']]) ?></td></tr><?php endif; ?>
                    <?php if (!empty($paymentDetails['payment_reference'])): ?><tr><th>Referensi transfer / QRIS</th><td><?= e($paymentDetails['payment_reference']) ?></td></tr><?php endif; ?>
                    <?php if (!empty($paymentDetails['details']) && $paymentDetails['details'] !== 'Pembayaran SPP dikonfirmasi.'): ?><tr><th>Catatan</th><td><?= e($paymentDetails['details']) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
            <footer class="receipt-foot">
                <p class="receipt-note">Simpan bukti ini sebagai arsip pembayaran. Dicetak pada <?= e(date('d/m/Y H:i')) ?>.</p>
                <div class="signature"><div>Petugas penerima</div><div class="signature-space"></div><div class="signature-label">( __________________ )</div></div>
            </footer>
        </section>
    </main>
    <nav class="toolbar" aria-label="Aksi slip pembayaran">
        <button type="button" class="back" onclick="if (window.opener) window.close(); else history.back()">Tutup</button>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </nav>
</body>
</html>
