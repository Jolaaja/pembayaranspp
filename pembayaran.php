<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/function/function_rupiah.php';
include __DIR__ . '/koneksi.php';

$rawNisn = $_GET['nisn'] ?? '';
$nisn = is_string($rawNisn) ? trim($rawNisn) : '';
$student = null;
$payments = [];
$paidCount = 0;
$paidTotal = 0.0;
$pendingCount = 0;
$overdueCount = 0;

if ($nisn !== '') {
    $studentStmt = db_execute(
        $conn,
        "SELECT s.id_siswa, s.nisn, s.nama, s.alamat, a.nama_angkatan, a.biaya, j.nama_jurusan, k.nama_kelas
         FROM siswa s JOIN angkatan a ON a.id_angkatan = s.id_angkatan
         JOIN jurusan j ON j.id_jurusan = s.id_jurusan
         JOIN kelas k ON k.id_kelas = s.id_kelas
         WHERE s.nisn = ? AND s.status = 'aktif' LIMIT 1",
        's',
        [$nisn]
    );
    $student = mysqli_fetch_assoc(mysqli_stmt_get_result($studentStmt));

    if ($student) {
        $paymentStmt = db_execute($conn, 'SELECT * FROM pembayaran WHERE id_siswa = ? ORDER BY jatuhtempo ASC', 'i', [(int) $student['id_siswa']]);
        $paymentResult = mysqli_stmt_get_result($paymentStmt);
        $today = date('Y-m-d');
        while ($payment = mysqli_fetch_assoc($paymentResult)) {
            $payments[] = $payment;
            if ($payment['ket'] === 'LUNAS') {
                $paidCount++;
                $paidTotal += (float) $payment['jumlah'];
            } elseif ($payment['jatuhtempo'] < $today) {
                $overdueCount++;
            } else {
                $pendingCount++;
            }
        }
    }
}

include __DIR__ . '/header.php';
?>
<section class="card payment-search-card">
    <div class="card-body">
        <form method="get" class="payment-search-form">
            <label class="sr-only" for="student-search">Cari siswa dengan NISN</label>
            <span class="payment-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg></span>
            <input id="student-search" class="form-control" type="search" name="nisn" value="<?= e($nisn) ?>" placeholder="Masukkan NISN siswa untuk melihat jadwal SPP" maxlength="50" required>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                Cari siswa
            </button>
        </form>
        <p class="search-hint">Cari berdasarkan NISN aktif yang terdaftar di sekolah.</p>
    </div>
</section>

<?php if ($nisn !== '' && !$student): ?>
    <section class="card search-result-empty">
        <div class="card-body">
            <span class="empty-state-icon">?</span>
            <h2>Siswa tidak ditemukan</h2>
            <p>Periksa kembali NISN. Pastikan siswa masih aktif dan datanya sudah terdaftar.</p>
        </div>
    </section>
<?php elseif ($student): ?>
    <section class="student-payment-profile">
        <div class="student-profile-main">
            <span class="student-profile-mark"><?= e(strtoupper(substr($student['nama'], 0, 1))) ?></span>
            <div class="student-profile-copy">
                <span class="eyebrow">PROFIL SISWA</span>
                <h2><?= e($student['nama']) ?></h2>
                <p>NISN <?= e($student['nisn']) ?> <span>·</span> <?= e($student['nama_kelas']) ?> <span>·</span> <?= e($student['nama_jurusan']) ?></p>
            </div>
        </div>
        <div class="student-profile-meta">
            <span>Angkatan</span><strong><?= e($student['nama_angkatan']) ?></strong>
            <span>SPP per bulan</span><strong><?= format_rupiah((float) $student['biaya']) ?></strong>
        </div>
    </section>

    <div class="payment-summary-grid">
        <article class="payment-summary-card"><span class="summary-label">SUDAH LUNAS</span><strong><?= number_format($paidCount, 0, ',', '.') ?> <small>bulan</small></strong><span class="summary-foot"><?= format_rupiah($paidTotal) ?> terbayar</span></article>
        <article class="payment-summary-card"><span class="summary-label">BELUM JATUH TEMPO</span><strong><?= number_format($pendingCount, 0, ',', '.') ?> <small>tagihan</small></strong><span class="summary-foot">Menunggu pembayaran</span></article>
        <article class="payment-summary-card <?= $overdueCount > 0 ? 'payment-summary-alert' : '' ?>"><span class="summary-label">TERLAMBAT</span><strong><?= number_format($overdueCount, 0, ',', '.') ?> <small>tagihan</small></strong><span class="summary-foot">Jadwal melewati jatuh tempo</span></article>
    </div>

    <section class="card payment-schedule-card">
        <div class="card-header card-header-actions">
            <div><h6>Jadwal pembayaran</h6><small><?= number_format(count($payments), 0, ',', '.') ?> jadwal SPP tercatat</small></div>
            <span class="schedule-period"><?= e($student['nama_angkatan']) ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table payment-schedule-table" aria-label="Jadwal pembayaran SPP">
                    <thead><tr><th>Bulan</th><th>Jatuh tempo</th><th>No. bayar</th><th>Tanggal bayar</th><th>Nominal</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?>
                        <tr class="payment-empty-row"><td colspan="7" class="empty-state">Belum ada jadwal pembayaran untuk siswa ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $isPaid = $payment['ket'] === 'LUNAS';
                            $isOverdue = !$isPaid && $payment['jatuhtempo'] < date('Y-m-d');
                            $statusLabel = $isPaid ? 'Lunas' : ($isOverdue ? 'Terlambat' : 'Belum lunas');
                            $statusClass = $isPaid ? 'status-paid' : ($isOverdue ? 'status-overdue' : 'status-pending');
                            ?>
                            <tr>
                                <td data-label="Bulan"><strong class="payment-month"><?= e($payment['bulan']) ?></strong></td>
                                <td data-label="Jatuh tempo" data-sort-value="<?= e($payment['jatuhtempo']) ?>"><?= e(date('d/m/Y', strtotime($payment['jatuhtempo']))) ?></td>
                                <td data-label="No. bayar"><?= e($payment['nobayar'] ?: '—') ?></td>
                                <td data-label="Tanggal bayar" data-sort-value="<?= e($payment['tglbayar'] ?: '') ?>"><?= $payment['tglbayar'] ? e(date('d/m/Y', strtotime($payment['tglbayar']))) : '—' ?></td>
                                <td data-label="Nominal" data-sort-value="<?= e((string) $payment['jumlah']) ?>"><strong><?= format_rupiah((float) $payment['jumlah']) ?></strong></td>
                                <td data-label="Status"><span class="status-chip <?= $statusClass ?>"><span></span><?= $statusLabel ?></span></td>
                                <td data-label="Aksi">
                                    <div class="payment-row-actions">
                                        <?php if ($isPaid): ?>
                                            <form method="post" action="proses_transaksi.php" onsubmit="return confirm('Batalkan pembayaran <?= e($payment['bulan']) ?>? Riwayat transaksi tetap disimpan.')">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="act" value="batal">
                                                <input type="hidden" name="id" value="<?= (int) $payment['idspp'] ?>">
                                                <input type="hidden" name="nisn" value="<?= e($student['nisn']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Batalkan</button>
                                            </form>
                                            <a class="btn btn-success btn-sm" href="cetak_slip_pembayaran.php?idspp=<?= (int) $payment['idspp'] ?>" target="_blank" rel="noopener">Slip</a>
                                        <?php else: ?>
                                            <form method="post" action="proses_transaksi.php" onsubmit="return confirm('Konfirmasi pembayaran <?= e($payment['bulan']) ?>?')">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="act" value="bayar">
                                                <input type="hidden" name="id" value="<?= (int) $payment['idspp'] ?>">
                                                <input type="hidden" name="nisn" value="<?= e($student['nisn']) ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Proses</button>
                                            </form>
                                        <?php endif; ?>
                                        <a class="payment-history-link" href="riwayat_pembayaran.php?idspp=<?= (int) $payment['idspp'] ?>">Riwayat</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="payment-intro">
        <span class="payment-intro-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 6h13M8 10h8m-8 4h6"/></svg></span>
        <div><h2>Kelola pembayaran dengan mudah</h2><p>Masukkan NISN untuk melihat jadwal, status, dan riwayat pembayaran siswa.</p></div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
