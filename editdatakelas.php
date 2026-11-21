<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (csrf_validate()) {
        $id = filter_var($_POST['id_kelas'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $usage = db_execute($conn, 'SELECT id_siswa FROM siswa WHERE id_kelas = ? LIMIT 1', 'i', [$id]);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($usage))) {
                flash('Kelas masih digunakan oleh data siswa.', 'danger');
            } else {
                db_execute($conn, 'DELETE FROM kelas WHERE id_kelas = ?', 'i', [$id]);
                audit_event($conn, 'class_deleted', 'kelas', $id, 'Data kelas dihapus.');
                flash('Data kelas berhasil dihapus.');
            }
        } else {
            flash('ID kelas tidak valid.', 'danger');
        }
    } else {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    }
    header('Location: editdatakelas.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan']) || isset($_POST['edit']))) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    } else {
        $name = trim(strtoupper((string) ($_POST['nama_kelas'] ?? '')));
        $id = isset($_POST['edit']) ? filter_var($_POST['id_kelas'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($name === '' || (isset($_POST['edit']) && !$id)) {
            flash('Nama kelas wajib diisi.', 'danger');
        } elseif (isset($_POST['edit'])) {
            db_execute($conn, 'UPDATE kelas SET nama_kelas = ? WHERE id_kelas = ?', 'si', [$name, $id]);
            audit_event($conn, 'class_updated', 'kelas', $id, 'Data kelas diperbarui.');
            flash('Data kelas berhasil diperbarui.');
        } else {
            db_execute($conn, 'INSERT INTO kelas (nama_kelas) VALUES (?)', 's', [$name]);
            audit_event($conn, 'class_created', 'kelas', mysqli_insert_id($conn), 'Data kelas dibuat.');
            flash('Data kelas berhasil ditambahkan.');
        }
    }
    header('Location: editdatakelas.php');
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
                            <h6 class="m-0 font-weight-bold text-primary">Data Kelas</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Kelas</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                    $no=1;
                                    $query = "SELECT * FROM kelas ";
                                    $exec = mysqli_query($conn,$query);
                                    while($res = mysqli_fetch_assoc($exec)) :

                                     ?>
                                      
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= e($res['nama_kelas']) ?></td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus data kelas ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_kelas" value="<?= (int) $res['id_kelas'] ?>"><button type="submit" name="delete" class="btn btn-sm btn-danger">Hapus</button></form>
                                                <a href="#" class="view_data btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#myModal" id="<?= (int) $res['id_kelas'] ?>">Edit</a>
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
        <h5 class="modal-title" id="exampleModalLabel">Data Kelas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body">
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label for="class-name">Nama kelas</label><input id="class-name" type="text" name="nama_kelas" placeholder="Contoh: XI IPA 1" class="form-control" maxlength="50" required></div>
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
        <h5 class="modal-title" id="exampleModalLabel">Edit Data Kelas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body" id="datakelas">

      
      </div>
    
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
