<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$students = mysqli_query(
    $conn,
    "SELECT s.nisn, s.nama, a.nama_angkatan, j.nama_jurusan, k.nama_kelas
     FROM siswa s JOIN angkatan a ON a.id_angkatan = s.id_angkatan
     JOIN jurusan j ON j.id_jurusan = s.id_jurusan
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE s.status = 'aktif' ORDER BY s.nama, s.nisn"
);
$countRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa WHERE status = 'aktif'"));
include __DIR__ . '/header.php';
?>
<section class="card student-directory-card">
    <div class="card-header card-header-actions">
        <div><h6>Direktori siswa aktif</h6><small><?= number_format((int) $countRow['total'], 0, ',', '.') ?> siswa terdaftar</small></div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a class="btn btn-primary btn-sm" href="editdatasiswa.php"><span aria-hidden="true">＋</span> Kelola siswa</a><?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ((int) $countRow['total'] > 0): ?>
            <p class="directory-hint">Gunakan pencarian tabel untuk menemukan siswa berdasarkan nama, NISN, kelas, atau angkatan.</p>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table student-directory-table">
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Jurusan</th><th>Angkatan</th></tr></thead>
                <tbody>
                <?php $hasStudents = false; while ($student = mysqli_fetch_assoc($students)): $hasStudents = true; ?>
                    <tr>
                        <td><div class="student-directory-identity"><span class="student-directory-mark"><?= e(strtoupper(substr($student['nama'], 0, 1))) ?></span><span><strong><?= e($student['nama']) ?></strong><small>NISN <?= e($student['nisn']) ?></small></span></div></td>
                        <td><span class="table-tag"><?= e($student['nama_kelas']) ?></span></td>
                        <td><?= e($student['nama_jurusan']) ?></td>
                        <td><?= e($student['nama_angkatan']) ?></td>
                    </tr>
                <?php endwhile; if (!$hasStudents): ?>
                    <tr><td colspan="4"><div class="directory-empty"><span class="empty-state-icon">♙</span><strong>Belum ada siswa aktif</strong><p>Siswa yang terdaftar akan tampil di direktori ini.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
