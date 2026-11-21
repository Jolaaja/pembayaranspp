<?php
require_once __DIR__ . '/app.php';
require_login();
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
$payment = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentStmt));
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
include __DIR__ . '/header.php';
?>
<section class="card">
    <div class="card-header card-header-actions">
        <div><h6>Riwayat pembayaran</h6><small><?= e($payment['nama']) ?> · NISN <?= e($payment['nisn']) ?></small></div>
        <a class="btn btn-secondary btn-sm" href="pembayaran.php">Kembali ke pembayaran</a>
    </div>
    <div class="card-body">
        <div class="payment-history-summary">
            <div><span>Periode</span><strong><?= e($payment['bulan']) ?></strong></div>
            <div><span>Nominal</span><strong>Rp <?= number_format((float) $payment['jumlah'], 0, ',', '.') ?></strong></div>
            <div><span>Status saat ini</span><strong><?= e($payment['ket'] ?: 'BELUM LUNAS') ?></strong></div>
            <div><span>Jatuh tempo</span><strong><?= e($payment['jatuhtempo']) ?></strong></div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Nominal</th><th>Petugas</th><th>Catatan</th></tr></thead>
                <tbody>
                <?php $hasEvents = false; while ($event = mysqli_fetch_assoc($events)): $hasEvents = true; ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime($event['created_at']))) ?></td>
                        <td><?= e($event['event_type'] === 'paid' ? 'Pembayaran diterima' : 'Pembayaran dibatalkan') ?></td>
                        <td>Rp <?= number_format((float) $event['amount'], 0, ',', '.') ?></td>
                        <td><?= e($event['nama_admin'] ?: 'Administrator dihapus') ?></td>
                        <td><?= e($event['details']) ?><?= $event['payment_date'] ? ' · tanggal bayar ' . e($event['payment_date']) : '' ?></td>
                    </tr>
                <?php endwhile; if (!$hasEvents): ?>
                    <tr><td colspan="5" class="empty-state">Belum ada aktivitas pembayaran tercatat.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
