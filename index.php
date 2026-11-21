<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/function/function_rupiah.php';
include __DIR__ . '/koneksi.php';
include __DIR__ . '/header.php';

$totalRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total, COALESCE(SUM(jumlah), 0) AS income FROM pembayaran WHERE ket = 'LUNAS'"));
$todayRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS income FROM pembayaran WHERE tglbayar = CURDATE() AND ket = 'LUNAS'"));
$overdueRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total, COALESCE(SUM(p.jumlah), 0) AS amount
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     WHERE s.status = 'aktif' AND p.ket IS NULL AND p.jatuhtempo < CURDATE()"
));
$recent = mysqli_query(
    $conn,
    "SELECT p.idspp, p.bulan, p.jumlah, p.tglbayar, s.nama, s.nisn, k.nama_kelas
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE p.ket = 'LUNAS' ORDER BY p.tglbayar DESC, p.idspp DESC LIMIT 5"
);
$monthly = mysqli_query(
    $conn,
    "SELECT DATE_FORMAT(tglbayar, '%Y-%m') AS month_key, COALESCE(SUM(jumlah), 0) AS income
     FROM pembayaran WHERE ket = 'LUNAS' AND tglbayar >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(tglbayar, '%Y-%m')"
);
$incomeByMonth = [];
while ($row = mysqli_fetch_assoc($monthly)) {
    $incomeByMonth[$row['month_key']] = (float) $row['income'];
}
$monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$monthPoints = [];
$monthCursor = new DateTimeImmutable('first day of this month');
for ($offset = 5; $offset >= 0; $offset--) {
    $monthDate = $monthCursor->modify('-' . $offset . ' months');
    $key = $monthDate->format('Y-m');
    $monthPoints[] = [
        'key' => $key,
        'label' => $monthNames[(int) $monthDate->format('n')],
        'year' => $monthDate->format('Y'),
        'income' => $incomeByMonth[$key] ?? 0,
    ];
}
$maxIncome = max(array_column($monthPoints, 'income'));
?>
<section class="dashboard-welcome">
    <div>
        <span class="eyebrow">DASHBOARD KEUANGAN</span>
        <h2>Selamat datang, <?= e($_SESSION['nama_admin'] ?? 'Administrator') ?></h2>
        <p>Ringkasan pembayaran sekolah, diperbarui setiap kali halaman dibuka.</p>
    </div>
    <a class="btn btn-primary" href="pembayaran.php">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Buat transaksi
    </a>
</section>

<div class="dashboard-stats">
    <article class="card stat-card">
        <div class="card-body">
            <div class="stat-top"><span class="stat-icon stat-icon-mint"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17 10 11l4 4 6-8"/><path d="M15 7h5v5"/></svg></span><span class="stat-tag">SEPANJANG WAKTU</span></div>
            <div class="stat-label">Transaksi lunas</div>
            <div class="stat-value"><?= number_format((int) $totalRow['total'], 0, ',', '.') ?><span class="stat-unit">transaksi</span></div>
            <div class="stat-foot">Pembayaran berhasil dikonfirmasi</div>
        </div>
    </article>
    <article class="card stat-card">
        <div class="card-body">
            <div class="stat-top"><span class="stat-icon stat-icon-gold">Rp</span><span class="stat-tag">AKUMULASI</span></div>
            <div class="stat-label">Total pemasukan</div>
            <div class="stat-value"><?= format_rupiah((float) $totalRow['income']) ?></div>
            <div class="stat-foot">Akumulasi dari pembayaran lunas</div>
        </div>
    </article>
    <article class="card stat-card">
        <div class="card-body">
            <div class="stat-top"><span class="stat-icon stat-icon-mint"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg></span><span class="stat-tag">HARI INI · <?= date('d/m/Y') ?></span></div>
            <div class="stat-label">Pemasukan hari ini</div>
            <div class="stat-value"><?= format_rupiah((float) $todayRow['income']) ?></div>
            <div class="stat-foot">Pembayaran diterima hari ini</div>
        </div>
    </article>
    <article class="card stat-card stat-card-alert">
        <div class="card-body">
            <div class="stat-top"><span class="stat-icon stat-icon-rose"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></span><span class="stat-tag">PERLU TINDAKAN</span></div>
            <div class="stat-label">Jadwal terlambat</div>
            <div class="stat-value"><?= number_format((int) $overdueRow['total'], 0, ',', '.') ?><span class="stat-unit">tagihan</span></div>
            <div class="stat-foot">Total <?= format_rupiah((float) $overdueRow['amount']) ?> belum dibayar</div>
        </div>
    </article>
</div>

<section class="dashboard-panels">
    <article class="card dashboard-chart-card">
        <div class="card-header card-header-actions">
            <div><h6>Tren pemasukan</h6><small>Enam bulan terakhir</small></div>
            <a class="btn btn-secondary btn-sm" href="laporan.php">Lihat laporan</a>
        </div>
        <div class="card-body">
            <div class="income-chart" role="img" aria-label="Grafik pemasukan enam bulan terakhir">
                <?php foreach ($monthPoints as $point): ?>
                    <?php $barHeight = $maxIncome > 0 ? max(5, (int) round($point['income'] / $maxIncome * 100)) : 4; ?>
                    <div class="income-chart-column" title="<?= e($point['label'] . ' ' . $point['year'] . ': ' . format_rupiah($point['income'])) ?>">
                        <div class="income-chart-value"><?= $point['income'] > 0 ? e(format_rupiah($point['income'])) : '' ?></div>
                        <div class="income-chart-track"><span class="income-chart-bar <?= $point['income'] > 0 ? 'has-value' : '' ?>" style="height: <?= $barHeight ?>%"></span></div>
                        <div class="income-chart-label"><?= e($point['label']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($maxIncome <= 0): ?><p class="chart-empty-note">Grafik akan terisi setelah pembayaran pertama dicatat.</p><?php endif; ?>
            </div>
        </div>
    </article>

    <article class="card dashboard-recent-card">
        <div class="card-header card-header-actions">
            <div><h6>Pembayaran terbaru</h6><small>Lima transaksi terakhir</small></div>
            <a class="btn btn-secondary btn-sm" href="pembayaran.php">Buka pembayaran</a>
        </div>
        <div class="card-body dashboard-recent-body">
            <div class="recent-payment-list">
                <?php $hasRecent = false; while ($payment = mysqli_fetch_assoc($recent)): $hasRecent = true; ?>
                    <div class="recent-payment">
                        <span class="recent-payment-mark"><?= e(strtoupper(substr($payment['nama'], 0, 1))) ?></span>
                        <span class="recent-payment-copy"><strong><?= e($payment['nama']) ?></strong><small><?= e($payment['nama_kelas']) ?> · <?= e($payment['bulan']) ?></small></span>
                        <span class="recent-payment-amount"><strong><?= format_rupiah((float) $payment['jumlah']) ?></strong><small><?= e(date('d/m/Y', strtotime($payment['tglbayar']))) ?></small></span>
                    </div>
                <?php endwhile; if (!$hasRecent): ?>
                    <div class="dashboard-empty-state">
                        <span class="empty-state-icon">₊</span>
                        <strong>Belum ada transaksi</strong>
                        <p>Pembayaran yang sudah dikonfirmasi akan muncul di sini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
</section>

<section class="dashboard-bottom">
    <div class="dashboard-note">
        <span class="note-mark">S</span>
        <div><strong>Administrasi rapi, layanan lebih baik.</strong><p>Kelola pembayaran dan pantau tunggakan dari satu tempat.</p></div>
    </div>
    <div class="quick-actions">
        <a href="datasiswa.php"><span>♙</span>Lihat siswa</a>
        <a href="laporan.php"><span>▤</span>Buat laporan</a>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
