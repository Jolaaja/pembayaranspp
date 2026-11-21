<?php
include __DIR__ . '/function/function_rupiah.php';
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (csrf_validate()) {
        $id = filter_var($_POST['id_angkatan'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $usage = db_execute($conn, 'SELECT id_siswa FROM siswa WHERE id_angkatan = ? LIMIT 1', 'i', [$id]);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($usage))) {
                flash('Angkatan masih digunakan oleh data siswa.', 'danger');
            } else {
                db_execute($conn, 'DELETE FROM angkatan WHERE id_angkatan = ?', 'i', [$id]);
                audit_event($conn, 'cohort_deleted', 'angkatan', $id, 'Data angkatan dihapus.');
                flash('Data angkatan berhasil dihapus.');
            }
        } else {
            flash('ID angkatan tidak valid.', 'danger');
        }
    } else {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    }
    header('Location: editdataangkatan.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan']) || isset($_POST['edit']))) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    } else {
        $name = trim(strtoupper((string) ($_POST['nama_angkatan'] ?? '')));
        $fee = filter_var($_POST['biaya'] ?? null, FILTER_VALIDATE_INT);
        $id = isset($_POST['edit']) ? filter_var($_POST['id_angkatan'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($name === '' || $fee === false || $fee === null || $fee < 0 || (isset($_POST['edit']) && !$id)) {
            flash('Lengkapi nama angkatan dan biaya dengan benar.', 'danger');
        } elseif (isset($_POST['edit'])) {
            db_execute($conn, 'UPDATE angkatan SET nama_angkatan = ?, biaya = ? WHERE id_angkatan = ?', 'ssi', [$name, (string) $fee, $id]);
            audit_event($conn, 'cohort_updated', 'angkatan', $id, 'Data angkatan diperbarui.');
            flash('Angkatan berhasil diperbarui.');
        } else {
            db_execute($conn, 'INSERT INTO angkatan (nama_angkatan, biaya) VALUES (?, ?)', 'ss', [$name, (string) $fee]);
            audit_event($conn, 'cohort_created', 'angkatan', mysqli_insert_id($conn), 'Data angkatan dibuat.');
            flash('Angkatan berhasil ditambahkan.');
        }
    }
    header('Location: editdataangkatan.php');
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
                            <h6 class="m-0 font-weight-bold text-primary">Data Angkatan</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Angkatan</th>
                                            <th>Biaya</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                    $no=1;
                                    $query = "SELECT * FROM angkatan ";
                                    $exec = mysqli_query($conn,$query);
                                    while($res = mysqli_fetch_assoc($exec)) :

                                     ?>
                                      
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= e($res['nama_angkatan']) ?></td>
                                            <td><?= format_rupiah($res['biaya']) ?></td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus data angkatan ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_angkatan" value="<?= (int) $res['id_angkatan'] ?>"><button type="submit" name="delete" class="btn btn-sm btn-danger">Hapus</button></form>
                                                <a href="#" class="view_data btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#myModal" id="<?= (int) $res['id_angkatan'] ?>">Edit</a>
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
        <h5 class="modal-title" id="exampleModalLabel">Data Angkatan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body">
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label for="cohort-name">Nama angkatan</label><input id="cohort-name" type="text" name="nama_angkatan" placeholder="Contoh: 2026" class="form-control" maxlength="50" required></div>
            <div class="form-group"><label for="cohort-fee">Biaya SPP per bulan</label><input id="cohort-fee" type="number" name="biaya" placeholder="Masukkan nominal rupiah" class="form-control" min="0" step="1" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" name="simpan" class="btn btn-primary">Simpan</button></div>
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
        <h5 class="modal-title" id="exampleModalLabel">Edit Data Angkatan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body" id="dataangkatan">

      
      </div>
    
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
