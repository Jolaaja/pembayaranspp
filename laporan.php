<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-d');
$rawStart = $_GET['awal'] ?? $defaultStart;
$rawEnd = $_GET['akhir'] ?? $defaultEnd;
$awal = is_string($rawStart) ? trim($rawStart) : '';
$akhir = is_string($rawEnd) ? trim($rawEnd) : '';
if (!is_valid_iso_date($awal) || !is_valid_iso_date($akhir) || $awal > $akhir) {
    http_response_code(400);
    exit('Rentang tanggal laporan tidak valid.');
}

$summaryStmt = db_execute(
    $conn,
    "SELECT COUNT(*) AS transactions, COALESCE(SUM(jumlah), 0) AS income
     FROM pembayaran WHERE ket = 'LUNAS' AND tglbayar BETWEEN ? AND ?",
    'ss',
    [$awal, $akhir]
);
$summaryResult = mysqli_stmt_get_result($summaryStmt);
$summary = mysqli_fetch_assoc($summaryResult);
mysqli_stmt_close($summaryStmt);
$classStmt = db_execute(
    $conn,
    "SELECT k.nama_kelas, COUNT(p.idspp) AS transactions, COALESCE(SUM(p.jumlah), 0) AS income
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE p.ket = 'LUNAS' AND p.tglbayar BETWEEN ? AND ?
     GROUP BY k.id_kelas, k.nama_kelas ORDER BY income DESC",
    'ss',
    [$awal, $akhir]
);
$classResult = mysqli_stmt_get_result($classStmt);
mysqli_stmt_close($classStmt);
$monthlyStmt = db_execute(
    $conn,
    "SELECT DATE_FORMAT(tglbayar, '%Y-%m') AS payment_month, COUNT(*) AS transactions, COALESCE(SUM(jumlah), 0) AS income
     FROM pembayaran WHERE ket = 'LUNAS' AND tglbayar BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(tglbayar, '%Y-%m') ORDER BY payment_month DESC",
    'ss',
    [$awal, $akhir]
);
$monthlyResult = mysqli_stmt_get_result($monthlyStmt);
mysqli_stmt_close($monthlyStmt);
$monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$overdueStmt = db_execute(
    $conn,
    "SELECT COUNT(*) AS transactions, COALESCE(SUM(p.jumlah), 0) AS amount
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     WHERE s.status = 'aktif' AND p.ket IS NULL AND p.jatuhtempo < CURDATE()"
);
$overdueResult = mysqli_stmt_get_result($overdueStmt);
$overdue = mysqli_fetch_assoc($overdueResult);
mysqli_stmt_close($overdueStmt);

include __DIR__ . '/header.php';
?>
<section class="card report-filter">
    <div class="card-header"><h6>Periode laporan</h6></div>
    <div class="card-body">
        <form method="get" class="report-filter-form">
            <div class="form-group"><label for="report-start">Dari tanggal</label><input id="report-start" class="form-control" type="date" name="awal" value="<?= e($awal) ?>" required></div>
            <div class="form-group"><label for="report-end">Sampai tanggal</label><input id="report-end" class="form-control" type="date" name="akhir" value="<?= e($akhir) ?>" required></div>
            <button type="submit" class="btn btn-primary">Tampilkan laporan</button>
            <a class="btn btn-secondary" href="cetak_laporan.php?awal=<?= e(rawurlencode($awal)) ?>&amp;akhir=<?= e(rawurlencode($akhir)) ?>" target="_blank">Pratinjau cetak</a>
            <a class="btn btn-success" href="export_laporan.php?awal=<?= e(rawurlencode($awal)) ?>&amp;akhir=<?= e(rawurlencode($akhir)) ?>">Ekspor CSV</a>
        </form>
    </div>
</section>

<div class="row">
    <div class="col-xl-4 col-md-6 mb-4"><article class="card report-stat"><div class="card-body"><span class="stat-tag">TRANSAKSI LUNAS</span><strong><?= number_format((int) $summary['transactions'], 0, ',', '.') ?></strong><small>pembayaran dikonfirmasi pada periode ini</small></div></article></div>
    <div class="col-xl-4 col-md-6 mb-4"><article class="card report-stat"><div class="card-body"><span class="stat-tag">PEMASUKAN PERIODE</span><strong>Rp <?= number_format((float) $summary['income'], 0, ',', '.') ?></strong><small>akumulasi pembayaran lunas</small></div></article></div>
    <div class="col-xl-4 col-md-6 mb-4"><article class="card report-stat report-stat-alert"><div class="card-body"><span class="stat-tag">TUNGGAKAN AKTIF</span><strong><?= number_format((int) $overdue['transactions'], 0, ',', '.') ?></strong><small>jatuh tempo terlewat · Rp <?= number_format((float) $overdue['amount'], 0, ',', '.') ?></small></div></article></div>
</div>

<div class="row">
    <div class="col-xl-6">
        <section class="card">
            <div class="card-header"><h6>Pemasukan per kelas</h6></div>
            <div class="card-body"><div class="table-responsive">
                <table class="table"><thead><tr><th>Kelas</th><th>Transaksi</th><th>Pemasukan</th></tr></thead><tbody>
                <?php $hasClass = false; while ($row = mysqli_fetch_assoc($classResult)): $hasClass = true; ?>
                    <tr><td><?= e($row['nama_kelas']) ?></td><td><?= number_format((int) $row['transactions'], 0, ',', '.') ?></td><td>Rp <?= number_format((float) $row['income'], 0, ',', '.') ?></td></tr>
                <?php endwhile; if (!$hasClass): ?><tr><td colspan="3" class="empty-state">Belum ada pembayaran pada periode ini.</td></tr><?php endif; ?>
                </tbody></table>
            </div></div>
        </section>
    </div>
    <div class="col-xl-6">
        <section class="card">
            <div class="card-header"><h6>Pemasukan per bulan</h6></div>
            <div class="card-body"><div class="table-responsive">
                <table class="table"><thead><tr><th>Bulan</th><th>Transaksi</th><th>Pemasukan</th></tr></thead><tbody>
                <?php $hasMonth = false; while ($row = mysqli_fetch_assoc($monthlyResult)): $hasMonth = true; ?>
                    <?php $monthDate = strtotime($row['payment_month'] . '-01'); ?>
                    <tr><td><?= e($monthNames[(int) date('n', $monthDate)] . ' ' . date('Y', $monthDate)) ?></td><td><?= number_format((int) $row['transactions'], 0, ',', '.') ?></td><td>Rp <?= number_format((float) $row['income'], 0, ',', '.') ?></td></tr>
                <?php endwhile; if (!$hasMonth): ?><tr><td colspan="3" class="empty-state">Belum ada pembayaran pada periode ini.</td></tr><?php endif; ?>
                </tbody></table>
            </div></div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
