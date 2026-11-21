<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$adminId = (int) $_SESSION['admin'];
$stmt = mysqli_prepare($conn, 'SELECT nama_admin, user_admin FROM admin WHERE id_admin = ?');
mysqli_stmt_bind_param($stmt, 'i', $adminId);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$admin) {
    http_response_code(404);
    exit('Akun administrator tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
    } else {
        $name = trim((string) ($_POST['nama_admin'] ?? ''));
        $username = trim((string) ($_POST['user_admin'] ?? ''));
        $newPassword = (string) ($_POST['pass_admin'] ?? '');

        if ($name === '' || $username === '') {
            flash('Nama dan username wajib diisi.', 'danger');
        } elseif ($newPassword !== '' && strlen($newPassword) < 10) {
            flash('Password baru minimal 10 karakter.', 'danger');
        } else {
            if ($newPassword === '') {
                $update = mysqli_prepare($conn, 'UPDATE admin SET nama_admin = ?, user_admin = ? WHERE id_admin = ?');
                mysqli_stmt_bind_param($update, 'ssi', $name, $username, $adminId);
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = mysqli_prepare($conn, 'UPDATE admin SET nama_admin = ?, user_admin = ?, pass_admin = ? WHERE id_admin = ?');
                mysqli_stmt_bind_param($update, 'sssi', $name, $username, $passwordHash, $adminId);
            }

            if (mysqli_stmt_execute($update)) {
                $_SESSION['nama_admin'] = $name;
                flash('Pengaturan akun berhasil diperbarui.');
                header('Location: adminedit.php');
                exit;
            }
            flash('Pengaturan akun gagal diperbarui.', 'danger');
        }
    }
    header('Location: adminedit.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<section class="card profile-card">
    <div class="card-header"><h6>Informasi administrator</h6></div>
    <div class="card-body">
        <p class="form-intro">Perbarui nama dan username. Kosongkan password jika tidak ingin menggantinya.</p>
        <form method="post" action="adminedit.php" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label for="admin-name">Nama administrator</label>
                <input id="admin-name" class="form-control" type="text" name="nama_admin" maxlength="50" required value="<?= e($admin['nama_admin']) ?>">
            </div>
            <div class="form-group">
                <label for="admin-user">Username</label>
                <input id="admin-user" class="form-control" type="text" name="user_admin" maxlength="50" autocomplete="username" required value="<?= e($admin['user_admin']) ?>">
            </div>
            <div class="form-group">
                <label for="admin-password">Password baru <span class="label-optional">opsional</span></label>
                <input id="admin-password" class="form-control" type="password" name="pass_admin" minlength="10" autocomplete="new-password" placeholder="Minimal 10 karakter">
            </div>
            <button type="submit" name="simpan" class="btn btn-primary">Simpan perubahan</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
