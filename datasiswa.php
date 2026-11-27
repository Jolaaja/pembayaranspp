<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$rawSearch = $_GET['q'] ?? '';
$rawSort = $_GET['sort'] ?? 'nama';
$rawDirection = $_GET['direction'] ?? 'asc';
$rawPage = $_GET['page'] ?? '1';
$requestedPage = is_string($rawPage) ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if (!is_string($rawSearch) || strlen($rawSearch) > 200
    || !is_string($rawSort) || !in_array($rawSort, ['nama', 'nisn', 'angkatan', 'kelas', 'jurusan'], true)
    || !is_string($rawDirection) || !in_array($rawDirection, ['asc', 'desc'], true)
    || $requestedPage === false) {
    http_response_code(400);
    exit('Filter direktori siswa tidak valid.');
}
$search = trim($rawSearch);
$sort = $rawSort;
$direction = $rawDirection;
$page = $requestedPage;
$studentPage = fetch_student_page($conn, $search, $sort, $direction, $page, 25);
$students = $studentPage['rows'];
$total = $studentPage['total'];
$page = $studentPage['page'];
$lastPage = $studentPage['last_page'];
$startRow = $total > 0 ? (($page - 1) * $studentPage['per_page']) + 1 : 0;
$endRow = min($page * $studentPage['per_page'], $total);
include __DIR__ . '/header.php';
?>
<section class="card student-directory-card">
    <div class="card-header card-header-actions">
        <div><h6>Direktori siswa aktif</h6><small>Menampilkan <?= number_format($startRow, 0, ',', '.') ?>–<?= number_format($endRow, 0, ',', '.') ?> dari <?= number_format($total, 0, ',', '.') ?> siswa</small></div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a class="btn btn-primary btn-sm" href="editdatasiswa.php"><span aria-hidden="true">＋</span> Kelola siswa</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" class="directory-filter-form">
            <div class="form-group directory-search-field">
                <label for="directory-search">Cari siswa</label>
                <input id="directory-search" class="form-control" type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Nama, NISN, kelas, jurusan, atau angkatan">
            </div>
            <div class="form-group">
                <label for="directory-sort">Urutkan berdasarkan</label>
                <select id="directory-sort" class="form-control" name="sort">
                    <option value="nama" <?= $sort === 'nama' ? 'selected' : '' ?>>Nama siswa</option>
                    <option value="nisn" <?= $sort === 'nisn' ? 'selected' : '' ?>>NISN</option>
                    <option value="kelas" <?= $sort === 'kelas' ? 'selected' : '' ?>>Kelas</option>
                    <option value="jurusan" <?= $sort === 'jurusan' ? 'selected' : '' ?>>Jurusan</option>
                    <option value="angkatan" <?= $sort === 'angkatan' ? 'selected' : '' ?>>Angkatan</option>
                </select>
            </div>
            <div class="form-group">
                <label for="directory-direction">Arah</label>
                <select id="directory-direction" class="form-control" name="direction">
                    <option value="asc" <?= $direction === 'asc' ? 'selected' : '' ?>>A–Z</option>
                    <option value="desc" <?= $direction === 'desc' ? 'selected' : '' ?>>Z–A</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Terapkan</button>
        </form>
        <div class="table-responsive">
            <table class="table student-directory-table" data-server-controls="true">
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Jurusan</th><th>Angkatan</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php $hasStudents = false; while ($student = mysqli_fetch_assoc($students)): $hasStudents = true; ?>
                    <tr>
                        <td><div class="student-directory-identity"><span class="student-directory-mark"><?= e(strtoupper(substr($student['nama'], 0, 1))) ?></span><span><strong><?= e($student['nama']) ?></strong><small>NISN <?= e($student['nisn']) ?></small></span></div></td>
                        <td><span class="table-tag"><?= e($student['nama_kelas']) ?></span></td>
                        <td><?= e($student['nama_jurusan']) ?></td>
                        <td><?= e($student['nama_angkatan']) ?></td>
                        <td><a class="btn btn-primary btn-sm" href="pembayaran.php?nisn=<?= e(rawurlencode($student['nisn'])) ?>">Proses pembayaran</a></td>
                    </tr>
                <?php endwhile; if (!$hasStudents): ?>
                    <tr><td colspan="5"><div class="directory-empty"><span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m2-11a3 3 0 0 1 0 6m1 2a5 5 0 0 1 3 3"/></svg></span><strong><?= $search === '' ? 'Belum ada siswa aktif' : 'Siswa tidak ditemukan' ?></strong><p><?= $search === '' ? 'Siswa yang terdaftar akan tampil di direktori ini.' : 'Ubah kata pencarian atau periksa kembali filter.' ?></p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($lastPage > 1): ?>
            <nav class="server-pagination" aria-label="Halaman direktori siswa">
                <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'page' => $page - 1])) ?>">Sebelumnya</a><?php endif; ?>
                <span aria-current="page">Halaman <?= number_format($page, 0, ',', '.') ?> dari <?= number_format($lastPage, 0, ',', '.') ?></span>
                <?php if ($page < $lastPage): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'page' => $page + 1])) ?>">Berikutnya</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
