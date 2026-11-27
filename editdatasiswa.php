<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (csrf_validate()) {
        $id = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $archive = db_execute($conn, "UPDATE siswa SET status = 'nonaktif' WHERE id_siswa = ? AND status = 'aktif'", 'i', [$id]);
            $archived = mysqli_stmt_affected_rows($archive) === 1;
            if ($archived) {
                audit_event($conn, 'student_archived', 'siswa', $id, 'Siswa diarsipkan; riwayat pembayaran dipertahankan.');
            }
            flash($archived ? 'Siswa diarsipkan. Riwayat dan jadwal pembayaran tetap tersimpan.' : 'Siswa tidak ditemukan atau sudah diarsipkan.', $archived ? 'success' : 'warning');
        } else {
            flash('ID siswa tidak valid.', 'danger');
        }
    } else {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    }
    header('Location: editdatasiswa.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan']) || isset($_POST['edit']))) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
        header('Location: editdatasiswa.php');
        exit;
    }

    $name = trim((string) ($_POST['nama_siswa'] ?? $_POST['nama'] ?? ''));
    $nisn = trim((string) ($_POST['nisn'] ?? ''));
    $classId = filter_var($_POST['id_kelas'] ?? null, FILTER_VALIDATE_INT);
    $majorId = filter_var($_POST['id_jurusan'] ?? null, FILTER_VALIDATE_INT);
    $cohortId = filter_var($_POST['id_angkatan'] ?? null, FILTER_VALIDATE_INT);
    $address = trim((string) ($_POST['alamat'] ?? ''));

    if ($name === '' || $nisn === '' || !$classId || !$majorId || !$cohortId || $address === '') {
        flash('Lengkapi semua data siswa dengan benar.', 'danger');
        header('Location: editdatasiswa.php');
        exit;
    }

    if (isset($_POST['simpan'])) {
        $duplicate = db_execute($conn, 'SELECT id_siswa FROM siswa WHERE nisn = ? LIMIT 1', 's', [$nisn]);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) {
            flash('NISN tersebut sudah terdaftar.', 'danger');
            header('Location: editdatasiswa.php');
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            db_execute($conn, 'INSERT INTO siswa (nisn, nama, id_angkatan, id_jurusan, id_kelas, alamat) VALUES (?, ?, ?, ?, ?, ?)', 'ssiiis', [$nisn, $name, $cohortId, $majorId, $classId, $address]);
            $studentId = mysqli_insert_id($conn);
            $feeQuery = db_execute($conn, 'SELECT biaya FROM angkatan WHERE id_angkatan = ?', 'i', [$cohortId]);
            $cohort = mysqli_fetch_assoc(mysqli_stmt_get_result($feeQuery));
            if (!$cohort) {
                throw new RuntimeException('Angkatan yang dipilih tidak ditemukan.');
            }

            $months = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];
            $startMonth = new DateTimeImmutable('first day of this month');
            for ($offset = 0; $offset < 36; $offset++) {
                $monthDate = $startMonth->modify('+' . $offset . ' months');
                $dueDate = $monthDate->format('Y-m-') . '10';
                $monthLabel = $months[(int) $monthDate->format('n') - 1] . ' ' . $monthDate->format('Y');
                db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [$studentId, $dueDate, $monthLabel, (string) $cohort['biaya']]);
            }
            audit_event($conn, 'student_created', 'siswa', $studentId, 'Siswa dan jadwal SPP 36 bulan dibuat.');
            mysqli_commit($conn);
            flash('Data siswa dan 36 jadwal SPP berhasil dibuat.');
        } catch (mysqli_sql_exception | RuntimeException $error) {
            mysqli_rollback($conn);
            error_log('Student creation failed: ' . $error->getMessage());
            flash('Data siswa tidak dapat disimpan. Periksa kembali data dan database.', 'danger');
        }
    } else {
        $studentId = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        if (!$studentId) {
            flash('ID siswa tidak valid.', 'danger');
        } else {
            $update = db_execute($conn, 'UPDATE siswa SET nama = ?, id_jurusan = ?, id_angkatan = ?, id_kelas = ?, alamat = ? WHERE id_siswa = ?', 'siiisi', [$name, $majorId, $cohortId, $classId, $address, $studentId]);
            audit_event($conn, 'student_updated', 'siswa', $studentId, 'Data siswa diperbarui.');
            flash('Data siswa berhasil diperbarui.');
        }
    }
    header('Location: editdatasiswa.php');
    exit;
}
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
    exit('Filter daftar siswa tidak valid.');
}
$search = trim($rawSearch);
$sort = $rawSort;
$direction = $rawDirection;
$page = $requestedPage;
$studentPage = fetch_student_page($conn, $search, $sort, $direction, $page, 25, true);
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
        <div><h6>Kelola siswa aktif</h6><small>Menampilkan <?= number_format($startRow, 0, ',', '.') ?>–<?= number_format($endRow, 0, ',', '.') ?> dari <?= number_format($total, 0, ',', '.') ?> siswa</small></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#exampleModal">＋ Tambah siswa</button>
    </div>
    <div class="card-body">
        <form method="get" class="directory-filter-form">
            <div class="form-group directory-search-field">
                <label for="manage-directory-search">Cari siswa</label>
                <input id="manage-directory-search" class="form-control" type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Nama, NISN, kelas, jurusan, atau angkatan">
            </div>
            <div class="form-group">
                <label for="manage-directory-sort">Urutkan berdasarkan</label>
                <select id="manage-directory-sort" class="form-control" name="sort">
                    <option value="nama" <?= $sort === 'nama' ? 'selected' : '' ?>>Nama siswa</option>
                    <option value="nisn" <?= $sort === 'nisn' ? 'selected' : '' ?>>NISN</option>
                    <option value="kelas" <?= $sort === 'kelas' ? 'selected' : '' ?>>Kelas</option>
                    <option value="jurusan" <?= $sort === 'jurusan' ? 'selected' : '' ?>>Jurusan</option>
                    <option value="angkatan" <?= $sort === 'angkatan' ? 'selected' : '' ?>>Angkatan</option>
                </select>
            </div>
            <div class="form-group">
                <label for="manage-directory-direction">Arah</label>
                <select id="manage-directory-direction" class="form-control" name="direction">
                    <option value="asc" <?= $direction === 'asc' ? 'selected' : '' ?>>A–Z</option>
                    <option value="desc" <?= $direction === 'desc' ? 'selected' : '' ?>>Z–A</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Terapkan</button>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered" data-server-controls="true">
                <thead><tr><th>NISN</th><th>Nama siswa</th><th>Angkatan</th><th>Kelas</th><th>Jurusan</th><th>Alamat</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php $hasStudents = false; while ($res = mysqli_fetch_assoc($students)): $hasStudents = true; ?>
                    <tr>
                        <td><?= e($res['nisn']) ?></td>
                        <td><?= e($res['nama']) ?></td>
                        <td><?= e($res['nama_angkatan']) ?></td>
                        <td><?= e($res['nama_kelas']) ?></td>
                        <td><?= e($res['nama_jurusan']) ?></td>
                        <td><?= e($res['alamat']) ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Arsipkan siswa ini? Riwayat pembayarannya tetap disimpan.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_siswa" value="<?= (int) $res['id_siswa'] ?>"><button type="submit" name="delete" class="btn btn-sm btn-danger">Arsipkan</button></form>
                            <a href="#" class="view_data btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#myModal" id="<?= (int) $res['id_siswa'] ?>">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; if (!$hasStudents): ?>
                    <tr><td colspan="7"><div class="directory-empty"><span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m2-11a3 3 0 0 1 0 6m1 2a5 5 0 0 1 3 3"/></svg></span><strong><?= $search === '' ? 'Belum ada siswa aktif' : 'Siswa tidak ditemukan' ?></strong><p><?= $search === '' ? 'Tambahkan siswa untuk mulai mengelola jadwal pembayaran.' : 'Ubah kata pencarian atau periksa kembali filter.' ?></p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($lastPage > 1): ?>
            <nav class="server-pagination" aria-label="Halaman daftar siswa">
                <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'page' => $page - 1])) ?>">Sebelumnya</a><?php endif; ?>
                <span aria-current="page">Halaman <?= number_format($page, 0, ',', '.') ?> dari <?= number_format($lastPage, 0, ',', '.') ?></span>
                <?php if ($page < $lastPage): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'page' => $page + 1])) ?>">Berikutnya</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="add-student-title" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="add-student-title">Tambah siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body">
        <form action="" method="POST" class="entity-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="entity-form-grid">
                <div class="form-group"><label for="new-nisn">NISN</label><input id="new-nisn" type="text" name="nisn" class="form-control" maxlength="50" required></div>
                <div class="form-group"><label for="new-student-name">Nama siswa</label><input id="new-student-name" type="text" name="nama_siswa" class="form-control" maxlength="50" required></div>
                <div class="form-group">
                    <label for="new-cohort">Angkatan</label>
                    <select id="new-cohort" class="form-control" name="id_angkatan" required>
                        <option value="" selected disabled>Pilih angkatan</option>
                        <?php $cohorts = mysqli_query($conn, 'SELECT * FROM angkatan ORDER BY id_angkatan'); while ($cohort = mysqli_fetch_assoc($cohorts)): ?>
                            <option value="<?= (int) $cohort['id_angkatan'] ?>"><?= e($cohort['nama_angkatan']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-class">Kelas</label>
                    <select id="new-class" class="form-control" name="id_kelas" required>
                        <option value="" selected disabled>Pilih kelas</option>
                        <?php $classes = mysqli_query($conn, 'SELECT * FROM kelas ORDER BY id_kelas'); while ($class = mysqli_fetch_assoc($classes)): ?>
                            <option value="<?= (int) $class['id_kelas'] ?>"><?= e($class['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-major">Jurusan</label>
                    <select id="new-major" class="form-control" name="id_jurusan" required>
                        <option value="" selected disabled>Pilih jurusan</option>
                        <?php $majors = mysqli_query($conn, 'SELECT * FROM jurusan ORDER BY id_jurusan'); while ($major = mysqli_fetch_assoc($majors)): ?>
                            <option value="<?= (int) $major['id_jurusan'] ?>"><?= e($major['nama_jurusan']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group entity-form-full"><label for="new-address">Alamat</label><textarea id="new-address" class="form-control" name="alamat" placeholder="Alamat siswa" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" name="simpan" class="btn btn-primary">Simpan siswa</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal edit data siswa -->
<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="edit-student-title" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="edit-student-title">Edit data siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body" id="datasiswa">

      
      </div>
    
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>