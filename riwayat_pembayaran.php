<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/function/function_rupiah.php';
include __DIR__ . '/koneksi.php';

$paymentId = filter_var($_GET['idspp'] ?? null, FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) {
    http_response_code(400);
    exit('Nomor pembayaran tidak valid.');
}

$paymentStmt = db_execute(
    $conn,
    'SELECT p.idspp, p.bulan, p.jumlah, p.ket, p.tglbayar, p.jatuhtempo,
            s.nisn, s.nama, s.status
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     WHERE p.idspp = ?',
    'i',
    [$paymentId]
);
$paymentResult = mysqli_stmt_get_result($paymentStmt);
$payment = mysqli_fetch_assoc($paymentResult);
mysqli_stmt_close($paymentStmt);
if (!$payment) {
    http_response_code(404);
    exit('Data pembayaran tidak ditemukan.');
}

$eventsStmt = db_execute(
    $conn,
    'SELECT e.event_type, e.amount, e.payment_date, e.details, e.created_at, a.nama_admin
     FROM pembayaran_events e LEFT JOIN admin a ON a.id_admin = e.id_admin
     WHERE e.idspp = ? ORDER BY e.created_at DESC, e.id_event DESC',
    'i',
    [$paymentId]
);
$events = mysqli_stmt_get_result($eventsStmt);
mysqli_stmt_close($eventsStmt);
$isPaid = $payment['ket'] === 'LUNAS';
$isOverdue = !$isPaid && $payment['status'] === 'aktif' && $payment['jatuhtempo'] < date('Y-m-d');
$statusLabel = $isPaid ? 'Lunas' : ($isOverdue ? 'Terlambat' : 'Belum lunas');
$statusClass = $isPaid ? 'status-paid' : ($isOverdue ? 'status-overdue' : 'status-pending');
$paymentUrl = 'pembayaran.php?' . http_build_query(['nisn' => $payment['nisn']]);
include __DIR__ . '/header.php';
?>
<section class="payment-history-page">
    <div class="payment-history-heading">
        <div>
            <span class="eyebrow">TRANSAKSI SPP</span>
            <h2>Riwayat pembayaran</h2>
            <p>Aktivitas terbaru ditampilkan lebih dahulu.</p>
        </div>
        <div class="payment-history-actions">
            <?php if ($isPaid): ?><a class="btn btn-secondary btn-sm" href="cetak_slip_pembayaran.php?idspp=<?= (int) $paymentId ?>" target="_blank" rel="noopener">Lihat slip</a><?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="<?= e($paymentUrl) ?>">Kembali ke siswa</a>
        </div>
    </div>
    <div class="payment-history-profile">
        <div class="payment-history-student">
            <span class="student-profile-mark" aria-hidden="true"><?= e(strtoupper(substr($payment['nama'], 0, 1))) ?></span>
            <div>
                <span class="summary-label">SISWA</span>
                <strong><?= e($payment['nama']) ?></strong>
                <small>NISN <?= e($payment['nisn']) ?><?= $payment['status'] === 'nonaktif' ? ' · Siswa nonaktif' : '' ?></small>
            </div>
        </div>
        <span class="status-chip <?= $statusClass ?>"><span></span><?= $statusLabel ?></span>
    </div>
    <div class="payment-history-summary">
        <article><span class="summary-label">PERIODE</span><strong><?= e($payment['bulan']) ?></strong></article>
        <article><span class="summary-label">NOMINAL</span><strong><?= format_rupiah((float) $payment['jumlah']) ?></strong></article>
        <article><span class="summary-label">JATUH TEMPO</span><strong><?= e(date('d/m/Y', strtotime($payment['jatuhtempo']))) ?></strong></article>
    </div>
    <section class="card payment-history-card">
        <div class="card-header card-header-actions">
            <div><h6>Aktivitas transaksi</h6><small>Catatan pembayaran dan pembatalan</small></div>
        </div>
        <div class="card-body">
            <?php $hasEvents = false; ?>
            <ol class="payment-timeline" aria-label="Urutan aktivitas pembayaran">
                <?php while ($event = mysqli_fetch_assoc($events)): $hasEvents = true; ?>
                    <?php
                    $isReversal = $event['event_type'] === 'reversed';
                    $isPayment = $event['event_type'] === 'paid';
                    $eventLabel = $isPayment
                        ? 'Pembayaran diterima'
                        : ($isReversal ? 'Pembayaran dibatalkan' : 'Aktivitas pembayaran');
                    $eventClass = $isReversal ? 'is-reversed' : ($isPayment ? 'is-paid' : 'is-neutral');
                    ?>
                    <li class="payment-timeline-item <?= $eventClass ?>">
                        <span class="payment-timeline-marker" aria-hidden="true"><?= $isReversal ? '↶' : ($isPayment ? '✓' : '·') ?></span>
                        <article class="payment-timeline-entry">
                            <div class="payment-timeline-top">
                                <div><h3><?= e($eventLabel) ?></h3><time datetime="<?= e(date('c', strtotime($event['created_at']))) ?>"><?= e(date('d/m/Y · H:i', strtotime($event['created_at']))) ?></time></div>
                                <strong class="payment-timeline-amount"><?= format_rupiah((float) $event['amount']) ?></strong>
                            </div>
                            <p><?= e($event['details'] ?: 'Tidak ada catatan tambahan.') ?></p>
                            <div class="payment-timeline-meta">
                                <span>Petugas: <?= e($event['nama_admin'] ?: 'Administrator dihapus') ?></span>
                                <?php if ($event['payment_date']): ?><span>Tanggal bayar: <?= e(date('d/m/Y', strtotime($event['payment_date']))) ?></span><?php endif; ?>
                            </div>
                        </article>
                    </li>
                <?php endwhile; ?>
            </ol>
            <?php if (!$hasEvents): ?>
                <div class="payment-history-empty">
                    <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <strong>Belum ada aktivitas</strong>
                    <p>Riwayat akan muncul di sini setelah pembayaran diproses.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</section>
<?php include __DIR__ . '/footer.php'; ?>
