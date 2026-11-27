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
$result = fetch_payment_reminders(
    $conn,
    $filters['status'],
    $filters['search'],
    $filters['days_ahead'],
    $filters['page']
);
$statusLabels = [
    'overdue' => 'Tunggakan terlambat',
    'upcoming' => 'Segera jatuh tempo',
    'all' => 'Semua tagihan belum lunas',
];
$filterQuery = [
    'jenis' => $filters['status'],
    'hari' => $filters['days_ahead'],
    'q' => $filters['search'],
];
$printUrl = 'cetak_pengingat.php?' . http_build_query($filterQuery);
$exportUrl = 'export_pengingat.php?' . http_build_query($filterQuery);
include __DIR__ . '/header.php';
?>
<section class="reminder-summary">
    <article class="card reminder-summary-card">
        <span class="stat-tag">DAFTAR TERPILIH</span>
        <strong><?= number_format($result['total'], 0, ',', '.') ?><small> tagihan</small></strong>
        <span><?= e($statusLabels[$filters['status']]) ?></span>
    </article>
    <article class="card reminder-summary-card">
        <span class="stat-tag">TOTAL NOMINAL</span>
        <strong><?= format_rupiah($result['amount']) ?></strong>
        <span><?= $filters['status'] === 'upcoming' ? 'Jadwal aktif belum lunas' : 'Tagihan dari siswa aktif' ?></span>
    </article>
    <article class="card reminder-summary-card reminder-summary-accent">
        <span class="stat-tag">BATAS PENGINGAT</span>
        <strong><?= $filters['status'] === 'overdue' ? 'Lewat tempo' : ($filters['status'] === 'upcoming' ? $filters['days_ahead'] . ' hari' : 'Semua jadwal') ?></strong>
        <span>Rentang data yang ditampilkan</span>
    </article>
</section>

<section class="card reminder-card">
    <div class="card-header reminder-card-header">
        <div><h6>Jadwal pembayaran</h6><small>Daftar aktif untuk tindak lanjut petugas</small></div>
        <div class="reminder-actions">
            <a class="btn btn-secondary btn-sm" href="<?= e($printUrl) ?>" target="_blank" rel="noopener">Cetak daftar</a>
            <a class="btn btn-primary btn-sm" href="<?= e($exportUrl) ?>">Ekspor CSV</a>
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="reminder-filter-form">
            <div class="form-group">
                <label for="reminder-type">Jenis pengingat</label>
                <select id="reminder-type" class="form-control" name="jenis">
                    <option value="overdue" <?= $filters['status'] === 'overdue' ? 'selected' : '' ?>>Tunggakan terlambat</option>
                    <option value="upcoming" <?= $filters['status'] === 'upcoming' ? 'selected' : '' ?>>Segera jatuh tempo</option>
                    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>Semua belum lunas</option>
                </select>
            </div>
            <div class="form-group" data-reminder-days <?= $filters['status'] !== 'upcoming' ? 'hidden' : '' ?>>
                <label for="reminder-days">Dalam berapa hari?</label>
                <select id="reminder-days" class="form-control" name="hari">
                    <?php foreach ([3, 7, 14, 30] as $days): ?>
                        <option value="<?= $days ?>" <?= $filters['days_ahead'] === $days ? 'selected' : '' ?>><?= $days ?> hari</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group reminder-search">
                <label for="reminder-search">Cari siswa</label>
                <input id="reminder-search" class="form-control" type="search" name="q" maxlength="100" value="<?= e($filters['search']) ?>" placeholder="Nama, NISN, atau kelas">
            </div>
            <button type="submit" class="btn btn-primary">Tampilkan</button>
        </form>

        <?php if ($result['rows']): ?>
            <div class="table-responsive">
                <table class="table reminder-table">
                    <thead><tr><th>Siswa</th><th>Periode SPP</th><th>Jatuh tempo</th><th>Status</th><th>Nominal</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <?php
                        $daysUntil = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($row['jatuhtempo']))->format('%r%a');
                        $dueLabel = $daysUntil < 0
                            ? 'Terlambat ' . abs($daysUntil) . ' hari'
                            : ($daysUntil === 0 ? 'Jatuh tempo hari ini' : 'Dalam ' . $daysUntil . ' hari');
                        $rowStatusClass = $daysUntil < 0 ? 'status-overdue' : 'status-pending';
                        $studentUrl = 'pembayaran.php?' . http_build_query(['nisn' => $row['nisn']]);
                        $contactEditUrl = 'editdatasiswa.php?' . http_build_query(['q' => $row['nisn']]);
                        $whatsappNumber = normalize_indonesian_mobile((string) ($row['guardian_phone'] ?? ''));
                        $guardianName = trim((string) ($row['guardian_name'] ?? ''));
                        $duePhrase = $daysUntil < 0 ? 'sudah melewati jatuh tempo pada ' : 'jatuh tempo pada ';
                        $whatsappMessage = 'Yth. ' . ($guardianName !== '' ? $guardianName : 'Bapak/Ibu orang tua/wali')
                            . ', kami mengingatkan bahwa tagihan SPP ' . $row['bulan'] . ' untuk ' . $row['nama']
                            . ' sebesar ' . format_rupiah((float) $row['jumlah']) . ' ' . $duePhrase
                            . date('d/m/Y', strtotime($row['jatuhtempo']))
                            . '. Jika sudah dibayar, mohon abaikan pesan ini. Terima kasih.';
                        $whatsappUrl = $whatsappNumber === null
                            ? null
                            : 'https://wa.me/' . rawurlencode(ltrim($whatsappNumber, '+')) . '?text=' . rawurlencode($whatsappMessage);
                        ?>
                        <tr>
                            <td>
                                <div class="reminder-student">
                                    <span class="student-directory-mark"><?= e(strtoupper(substr($row['nama'], 0, 1))) ?></span>
                                    <span>
                                        <strong><?= e($row['nama']) ?></strong>
                                        <small>NISN <?= e($row['nisn']) ?> · <?= e($row['nama_kelas']) ?></small>
                                        <?php if ($row['guardian_phone']): ?>
                                            <small>Wali: <?= e(trim((string) $row['guardian_name']) !== '' ? $row['guardian_name'] : 'Orang tua/wali') ?> · <?= e($row['guardian_phone']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= e($row['bulan']) ?></td>
                            <td><strong><?= e(date('d/m/Y', strtotime($row['jatuhtempo']))) ?></strong><small class="reminder-due-note"><?= e($dueLabel) ?></small></td>
                            <td><span class="status-chip <?= $rowStatusClass ?>"><span></span><?= $daysUntil < 0 ? 'Terlambat' : 'Belum lunas' ?></span></td>
                            <td class="reminder-amount"><?= format_rupiah((float) $row['jumlah']) ?></td>
                            <td><div class="reminder-row-actions">
                                <a class="btn btn-secondary btn-sm" href="<?= e($studentUrl) ?>">Proses</a>
                                <?php if ($whatsappUrl !== null): ?>
                                    <a class="btn btn-success btn-sm" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                                <?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <a class="reminder-contact-missing" href="<?= e($contactEditUrl) ?>">Isi kontak wali</a>
                                <?php else: ?>
                                    <span class="reminder-contact-missing">Minta admin isi kontak wali</span>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="reminder-empty-state">
                <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <strong>Tidak ada tagihan pada filter ini</strong>
                <p>Ubah jenis pengingat atau kata pencarian untuk melihat jadwal lain.</p>
            </div>
        <?php endif; ?>

        <?php if ($result['last_page'] > 1): ?>
            <nav class="server-pagination" aria-label="Halaman pengingat pembayaran">
                <?php if ($result['page'] > 1): ?>
                    <?php $previousQuery = http_build_query($filterQuery + ['page' => $result['page'] - 1]); ?>
                    <a class="btn btn-secondary btn-sm" href="?<?= e($previousQuery) ?>">Sebelumnya</a>
                <?php endif; ?>
                <span aria-current="page">Halaman <?= number_format($result['page'], 0, ',', '.') ?> dari <?= number_format($result['last_page'], 0, ',', '.') ?></span>
                <?php if ($result['page'] < $result['last_page']): ?>
                    <?php $nextQuery = http_build_query($filterQuery + ['page' => $result['page'] + 1]); ?>
                    <a class="btn btn-secondary btn-sm" href="?<?= e($nextQuery) ?>">Berikutnya</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
