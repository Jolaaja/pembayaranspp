<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/function/function_rupiah.php';
include __DIR__ . '/koneksi.php';

$period = $_GET['periode'] ?? 'six_months';
if (!is_string($period) || !in_array($period, ['month', 'semester', 'six_months'], true)) {
    http_response_code(400);
    exit('Periode dashboard tidak valid.');
}

$today = new DateTimeImmutable('today');
$currentMonth = new DateTimeImmutable('first day of this month');
$monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$semesterNumber = (int) $today->format('n') <= 6 ? 1 : 2;
$periodLabels = [
    'month' => 'Bulan ini',
    'semester' => 'Semester ' . $semesterNumber,
    'six_months' => '6 bulan terakhir',
];
$periodStart = match ($period) {
    'month' => $currentMonth,
    'semester' => new DateTimeImmutable($today->format('Y') . '-' . ($semesterNumber === 1 ? '01' : '07') . '-01'),
    default => $currentMonth->modify('-5 months'),
};
$periodEnd = $today;
$dateFrom = $periodStart->format('Y-m-d');
$dateTo = $periodEnd->format('Y-m-d');
$periodLabel = $periodLabels[$period];

$periodSummaryStmt = db_execute(
    $conn,
    "SELECT COUNT(*) AS transactions, COALESCE(SUM(jumlah), 0) AS income
     FROM pembayaran WHERE ket = 'LUNAS' AND tglbayar BETWEEN ? AND ?",
    'ss',
    [$dateFrom, $dateTo]
);
$periodSummaryResult = mysqli_stmt_get_result($periodSummaryStmt);
$periodSummary = mysqli_fetch_assoc($periodSummaryResult);
mysqli_stmt_close($periodSummaryStmt);

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
$groupExpression = $period === 'month' ? "DATE_FORMAT(tglbayar, '%Y-%m-%d')" : "DATE_FORMAT(tglbayar, '%Y-%m')";
$groupedIncomeStmt = db_execute(
    $conn,
    "SELECT {$groupExpression} AS period_key, COALESCE(SUM(jumlah), 0) AS income
     FROM pembayaran WHERE ket = 'LUNAS' AND tglbayar BETWEEN ? AND ?
     GROUP BY period_key ORDER BY period_key",
    'ss',
    [$dateFrom, $dateTo]
);
$groupedIncomeResult = mysqli_stmt_get_result($groupedIncomeStmt);
mysqli_stmt_close($groupedIncomeStmt);
$incomeByPeriod = [];
if ($groupedIncomeResult) {
    while ($row = mysqli_fetch_assoc($groupedIncomeResult)) {
        $incomeByPeriod[$row['period_key']] = (float) $row['income'];
    }
}
$periodPoints = [];
for ($cursor = $periodStart; $cursor <= $periodEnd; $cursor = $cursor->modify('+1 month')) {
    if ($period === 'month') {
        $daysToShow = (int) $periodEnd->format('j');
        for ($day = 1; $day <= $daysToShow; $day++) {
            $date = $cursor->setDate((int) $cursor->format('Y'), (int) $cursor->format('n'), $day);
            $key = $date->format('Y-m-d');
            $periodPoints[] = [
                'label' => (string) $day,
                'title' => $date->format('d/m/Y'),
                'income' => $incomeByPeriod[$key] ?? 0,
            ];
        }
        break;
    }

    $key = $cursor->format('Y-m');
    $periodPoints[] = [
        'label' => $monthNames[(int) $cursor->format('n')],
        'title' => $monthNames[(int) $cursor->format('n')] . ' ' . $cursor->format('Y'),
        'income' => $incomeByPeriod[$key] ?? 0,
    ];
}
$maxIncome = max(array_column($periodPoints, 'income'));
$periodRangeLabel = $periodStart->format('d/m/Y') . ' – ' . $periodEnd->format('d/m/Y');
include __DIR__ . '/header.php';
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
            <div class="stat-top"><span class="stat-icon stat-icon-mint"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17 10 11l4 4 6-8"/><path d="M15 7h5v5"/></svg></span><span class="stat-tag"><?= e(strtoupper($periodLabel)) ?></span></div>
            <div class="stat-label">Transaksi lunas</div>
            <div class="stat-value"><?= number_format((int) $periodSummary['transactions'], 0, ',', '.') ?><span class="stat-unit">transaksi</span></div>
            <div class="stat-foot">Sepanjang waktu: <?= number_format((int) $totalRow['total'], 0, ',', '.') ?> transaksi</div>
        </div>
    </article>
    <article class="card stat-card">
        <div class="card-body">
            <div class="stat-top"><span class="stat-icon stat-icon-gold">Rp</span><span class="stat-tag"><?= e(strtoupper($periodLabel)) ?></span></div>
            <div class="stat-label">Pemasukan periode</div>
            <div class="stat-value"><?= format_rupiah((float) $periodSummary['income']) ?></div>
            <div class="stat-foot">Sepanjang waktu: <?= format_rupiah((float) $totalRow['income']) ?></div>
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
            <div class="stat-foot"><span>Total <?= format_rupiah((float) $overdueRow['amount']) ?> belum dibayar</span><a class="stat-foot-link" href="pengingat.php?jenis=overdue">Lihat daftar</a></div>
        </div>
    </article>
</div>

<section class="dashboard-panels">
    <article class="card dashboard-chart-card">
        <div class="card-header card-header-actions">
            <div><h6>Tren pemasukan</h6><small><?= e($periodLabel) ?> · <?= e($periodRangeLabel) ?></small></div>
            <div class="dashboard-chart-actions">
                <form method="get" class="dashboard-period-form">
                    <label class="sr-only" for="dashboard-period">Periode dashboard</label>
                    <select class="form-control" id="dashboard-period" name="periode">
                        <option value="six_months" <?= $period === 'six_months' ? 'selected' : '' ?>>6 bulan terakhir</option>
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Bulan ini</option>
                        <option value="semester" <?= $period === 'semester' ? 'selected' : '' ?>>Semester berjalan</option>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Terapkan</button>
                </form>
                <a class="btn btn-secondary btn-sm" href="laporan.php">Lihat laporan</a>
            </div>
        </div>
        <div class="card-body">
            <div class="income-chart <?= $period === 'month' ? 'income-chart-daily' : '' ?>" role="img" aria-label="Grafik pemasukan: <?= e($periodLabel) ?>, <?= e($periodRangeLabel) ?>">
                <?php foreach ($periodPoints as $point): ?>
                    <?php $barHeight = $maxIncome > 0 ? max(5, (int) round($point['income'] / $maxIncome * 100)) : 4; ?>
                    <div class="income-chart-column" title="<?= e($point['title'] . ': ' . format_rupiah($point['income'])) ?>">
                        <div class="income-chart-value"><?= $point['income'] > 0 ? e(format_rupiah($point['income'])) : '' ?></div>
                        <div class="income-chart-track"><span class="income-chart-bar <?= $point['income'] > 0 ? 'has-value' : '' ?>" style="height: <?= $barHeight ?>%"></span></div>
                        <div class="income-chart-label"><?= e($point['label']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($maxIncome <= 0): ?><p class="chart-empty-note">Grafik akan terisi setelah pembayaran pertama pada periode ini dicatat.</p><?php endif; ?>
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
                        <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18m-13 5h4"/></svg></span>
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
        <a href="datasiswa.php"><span class="quick-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m2-11a3 3 0 0 1 0 6m1 2a5 5 0 0 1 3 3"/></svg></span>Lihat siswa</a>
        <a href="laporan.php"><span class="quick-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V4m0 16h17M8 16v-5m5 5V7m5 9V9"/></svg></span>Buat laporan</a>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
