<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (csrf_validate()) {
        $id = filter_var($_POST['id_jurusan'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $usage = db_execute($conn, 'SELECT id_siswa FROM siswa WHERE id_jurusan = ? LIMIT 1', 'i', [$id]);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($usage))) {
                flash('Jurusan masih digunakan oleh data siswa.', 'danger');
            } else {
                db_execute($conn, 'DELETE FROM jurusan WHERE id_jurusan = ?', 'i', [$id]);
                flash('Data jurusan berhasil dihapus.');
            }
        } else {
            flash('ID jurusan tidak valid.', 'danger');
        }
    } else {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    }
    header('Location: editdatajurusan.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan']) || isset($_POST['edit']))) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    } else {
        $name = trim(strtoupper((string) ($_POST['nama_jurusan'] ?? '')));
        $id = isset($_POST['edit']) ? filter_var($_POST['id_jurusan'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($name === '' || (isset($_POST['edit']) && !$id)) {
            flash('Nama jurusan wajib diisi.', 'danger');
        } elseif (isset($_POST['edit'])) {
            db_execute($conn, 'UPDATE jurusan SET nama_jurusan = ? WHERE id_jurusan = ?', 'si', [$name, $id]);
            flash('Data jurusan berhasil diperbarui.');
        } else {
            db_execute($conn, 'INSERT INTO jurusan (nama_jurusan) VALUES (?)', 's', [$name]);
            flash('Data jurusan berhasil ditambahkan.');
        }
    }
    header('Location: editdatajurusan.php');
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
                            <h6 class="m-0 font-weight-bold text-primary">Data Jurusan</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Jurusan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                    $no=1;
                                    $query = "SELECT * FROM jurusan ";
                                    $exec = mysqli_query($conn,$query);
                                    while($res = mysqli_fetch_assoc($exec)) :

                                     ?>
                                      
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= e($res['nama_jurusan']) ?></td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus data jurusan ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_jurusan" value="<?= (int) $res['id_jurusan'] ?>"><button type="submit" name="delete" class="btn btn-sm btn-danger">Hapus</button></form>
                                                <a href="#" class="view_data btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#myModal" id="<?= (int) $res['id_jurusan'] ?>">Edit</a>
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
        <h5 class="modal-title" id="exampleModalLabel">Data Jurusan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body">
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label for="major-name">Nama jurusan</label><input id="major-name" type="text" name="nama_jurusan" placeholder="Nama jurusan" class="form-control" maxlength="50" required></div>
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
        <h5 class="modal-title" id="exampleModalLabel">Edit Data Jurusan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
      </div>
      <div class="modal-body" id="datajurusan">

      
      </div>
    
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
