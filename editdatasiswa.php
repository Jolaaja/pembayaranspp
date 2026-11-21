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
include __DIR__ . '/header.php';
?>

<!-- button triger -->
 <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#exampleModal">Tambah Data</button>
<!-- button triger -->
                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Data Siswa</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>NISN</th>
                                            <th>Nama Siswa</th>
                                            <th>Angkatan</th>
                                            <th>Kelas</th>
                                            <th>Jurusan</th>
                                            <th>Alamat</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                     <tbody>
                                    <?php 
                                    $query = "SELECT siswa.*,angkatan.*,jurusan.*,kelas.* FROM siswa,angkatan,jurusan,kelas WHERE siswa.id_angkatan = angkatan.id_angkatan AND siswa.id_jurusan = jurusan.id_jurusan AND siswa.id_kelas = kelas.id_kelas AND siswa.status = 'aktif' ORDER BY id_siswa";
                                    $exec = mysqli_query($conn,$query);
                                    while($res = mysqli_fetch_assoc($exec)) :

                                     ?>
                                     
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
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Data Siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body">
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="text" name="nisn" placeholder="NISN" class="form-control mb-2" maxlength="50" required>
            <input type="text" name="nama_siswa" placeholder="Nama Siswa" class="form-control mb-2" maxlength="50" required>
            <label for="new-cohort">Angkatan</label>
            <select id="new-cohort" class="form-control mb-2" name="id_angkatan" required>
                <option value="" selected disabled>Pilih angkatan</option>
                <?php 
                    $exec = mysqli_query($conn,"SELECT * FROM angkatan order by id_angkatan");
                    while ($angkatan = mysqli_fetch_assoc($exec)) :
                    echo '<option value="' . (int) $angkatan['id_angkatan'] . '">' . e($angkatan['nama_angkatan']) . '</option>';
                endwhile;
                 ?>
            </select>
            <label for="new-class">Kelas</label>
            <select id="new-class" class="form-control mb-2" name="id_kelas" required>
                <option value="" selected disabled>Pilih kelas</option>
                <?php 
                    $exec = mysqli_query($conn,"SELECT * FROM kelas order by id_kelas");
                    while ($angkatan = mysqli_fetch_assoc($exec)) :
                    echo '<option value="' . (int) $angkatan['id_kelas'] . '">' . e($angkatan['nama_kelas']) . '</option>';
                endwhile;
                 ?>
            </select>
            <label for="new-major">Jurusan</label>
             <select id="new-major" class="form-control" name="id_jurusan" required>
                <option value="" selected disabled>Pilih jurusan</option>
                <?php 
                    $exec = mysqli_query($conn,"SELECT * FROM jurusan order by id_jurusan");
                    while ($angkatan = mysqli_fetch_assoc($exec)) :
                    echo '<option value="' . (int) $angkatan['id_jurusan'] . '">' . e($angkatan['nama_jurusan']) . '</option>';
                endwhile;
                 ?>
            </select>
            <div class="form-group mt-3"><label for="new-address">Alamat</label><textarea id="new-address" class="form-control" name="alamat" placeholder="Alamat siswa" required></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" name="simpan" class="btn btn-primary">Simpan siswa</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal edit data siswa -->
<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Data Siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body" id="datasiswa">

      
      </div>
    
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>