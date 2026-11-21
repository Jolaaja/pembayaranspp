<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 50;
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM admin_audit_log');
$total = (int) mysqli_fetch_assoc($countResult)['total'];
$lastPage = max(1, (int) ceil($total / $perPage));
$page = min($page, $lastPage);
$offset = ($page - 1) * $perPage;
$entriesStmt = db_execute(
    $conn,
    'SELECT l.event_type, l.entity_type, l.entity_id, l.message, l.created_at, a.nama_admin, a.user_admin
     FROM admin_audit_log l LEFT JOIN admin a ON a.id_admin = l.id_admin
     ORDER BY l.id_audit DESC LIMIT ? OFFSET ?',
    'ii',
    [$perPage, $offset]
);
$entries = mysqli_stmt_get_result($entriesStmt);

include __DIR__ . '/header.php';
?>
<section class="card">
    <div class="card-header card-header-actions">
        <div><h6>Aktivitas administrator</h6><small>Menampilkan <?= number_format(min($perPage, max(0, $total - $offset)), 0, ',', '.') ?> dari <?= number_format($total, 0, ',', '.') ?> aktivitas terbaru</small></div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Objek</th><th>Catatan</th></tr></thead>
                <tbody>
                <?php $hasEntries = false; while ($entry = mysqli_fetch_assoc($entries)): $hasEntries = true; ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime($entry['created_at']))) ?></td>
                        <td><?= e($entry['nama_admin'] ?: 'Akun dihapus') ?><?= $entry['user_admin'] ? ' · ' . e($entry['user_admin']) : '' ?></td>
                        <td><?= e(str_replace('_', ' ', $entry['event_type'])) ?></td>
                        <td><?= e($entry['entity_type'] ?: '—') ?><?= $entry['entity_id'] ? ' #' . (int) $entry['entity_id'] : '' ?></td>
                        <td><?= e($entry['message']) ?></td>
                    </tr>
                <?php endwhile; if (!$hasEntries): ?>
                    <tr><td colspan="5" class="empty-state">Belum ada aktivitas tercatat.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($lastPage > 1): ?>
            <nav class="audit-pagination" aria-label="Halaman aktivitas">
                <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?page=<?= $page - 1 ?>">Sebelumnya</a><?php endif; ?>
                <span>Halaman <?= $page ?> dari <?= $lastPage ?></span>
                <?php if ($page < $lastPage): ?><a class="btn btn-secondary btn-sm" href="?page=<?= $page + 1 ?>">Berikutnya</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
