<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate()) {
    flash('Sesi formulir tidak valid. Coba lagi.', 'danger');
    header('Location: adminusers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim((string) ($_POST['nama_admin'] ?? ''));
    $username = trim((string) ($_POST['user_admin'] ?? ''));
    $password = (string) ($_POST['pass_admin'] ?? '');
    $role = (string) ($_POST['role'] ?? '');

    if ($name === '' || $username === '' || strlen($password) < 10 || !in_array($role, ['admin', 'cashier'], true)) {
        flash('Lengkapi nama, username, role, dan password minimal 10 karakter.', 'danger');
    } else {
        $existing = db_execute($conn, 'SELECT id_admin FROM admin WHERE user_admin = ? LIMIT 1', 's', [$username]);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($existing))) {
            flash('Username sudah digunakan.', 'danger');
        } else {
            mysqli_begin_transaction($conn);
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                db_execute($conn, 'INSERT INTO admin (nama_admin, user_admin, pass_admin, role) VALUES (?, ?, ?, ?)', 'ssss', [$name, $username, $passwordHash, $role]);
                $newId = mysqli_insert_id($conn);
                audit_event($conn, 'admin_created', 'admin', $newId, 'Akun pengguna baru dibuat.');
                mysqli_commit($conn);
                flash('Akun pengguna berhasil dibuat.');
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                if ($error->getCode() === 1062) {
                    flash('Username sudah digunakan.', 'danger');
                } else {
                    error_log('Admin creation failed: ' . $error->getMessage());
                    flash('Akun pengguna gagal dibuat. Periksa log server.', 'danger');
                }
            }
        }
    }
    header('Location: adminusers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $targetId = filter_var($_POST['id_admin'] ?? null, FILTER_VALIDATE_INT);
    $role = (string) ($_POST['role'] ?? '');
    if (!$targetId || !in_array($role, ['admin', 'cashier'], true)) {
        flash('Data role tidak valid.', 'danger');
    } elseif ($targetId === (int) $_SESSION['admin'] && $role !== 'admin') {
        flash('Anda tidak dapat menurunkan role akun yang sedang digunakan.', 'danger');
    } else {
        mysqli_begin_transaction($conn);
        try {
            $usersResult = mysqli_query($conn, 'SELECT id_admin, role FROM admin FOR UPDATE');
            $adminCount = 0;
            $currentRole = null;
            while ($userRow = mysqli_fetch_assoc($usersResult)) {
                if ($userRow['role'] === 'admin') {
                    $adminCount++;
                }
                if ((int) $userRow['id_admin'] === $targetId) {
                    $currentRole = $userRow['role'];
                }
            }

            if ($currentRole === null) {
                mysqli_rollback($conn);
                flash('Pengguna tidak ditemukan.', 'danger');
            } elseif ($currentRole === 'admin' && $role !== 'admin' && $adminCount <= 1) {
                mysqli_rollback($conn);
                flash('Sistem harus selalu memiliki minimal satu administrator.', 'danger');
            } else {
                db_execute($conn, 'UPDATE admin SET role = ? WHERE id_admin = ?', 'si', [$role, $targetId]);
                audit_event($conn, 'admin_role_changed', 'admin', $targetId, 'Role pengguna diperbarui.');
                mysqli_commit($conn);
                flash('Role pengguna berhasil diperbarui.');
            }
        } catch (mysqli_sql_exception $error) {
            mysqli_rollback($conn);
            error_log('Admin role update failed: ' . $error->getMessage());
            flash('Role pengguna gagal diperbarui. Periksa log server.', 'danger');
        }
    }
    header('Location: adminusers.php');
    exit;
}

include __DIR__ . '/header.php';
$admins = mysqli_query($conn, 'SELECT id_admin, nama_admin, user_admin, role FROM admin ORDER BY nama_admin');
?>
<section class="card">
    <div class="card-header"><h6>Tambah pengguna</h6></div>
    <div class="card-body">
        <form method="post" class="user-create-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label for="user-name">Nama</label><input id="user-name" class="form-control" name="nama_admin" maxlength="50" required></div>
            <div class="form-group"><label for="user-login">Username</label><input id="user-login" class="form-control" name="user_admin" maxlength="50" autocomplete="off" required></div>
            <div class="form-group"><label for="user-password">Password awal</label><input id="user-password" class="form-control" type="password" name="pass_admin" minlength="10" autocomplete="new-password" required></div>
            <div class="form-group"><label for="user-role">Hak akses</label><select id="user-role" class="form-control" name="role" required><option value="cashier">Kasir — pembayaran dan laporan</option><option value="admin">Administrator — semua fitur</option></select></div>
            <button type="submit" class="btn btn-primary" name="create_user">Buat akun</button>
        </form>
    </div>
</section>

<section class="card">
    <div class="card-header"><h6>Pengguna terdaftar</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Pengguna</th><th>Username</th><th>Hak akses</th><th>Perbarui role</th></tr></thead>
                <tbody>
                <?php while ($user = mysqli_fetch_assoc($admins)): ?>
                    <tr>
                        <td><div class="user-identity"><img class="profile-avatar profile-avatar-small" src="avatar.php?id=<?= (int) $user['id_admin'] ?>" alt=""><span><strong><?= e($user['nama_admin']) ?></strong><?= (int) $user['id_admin'] === (int) $_SESSION['admin'] ? '<small>Akun Anda</small>' : '' ?></span></div></td>
                        <td><?= e($user['user_admin']) ?></td>
                        <td><?= $user['role'] === 'admin' ? 'Administrator' : 'Kasir' ?></td>
                        <td>
                            <form method="post" class="role-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id_admin" value="<?= (int) $user['id_admin'] ?>">
                                <select class="form-control" name="role" aria-label="Role <?= e($user['nama_admin']) ?>">
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="cashier" <?= $user['role'] === 'cashier' ? 'selected' : '' ?>>Kasir</option>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm" name="update_role">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <p class="form-intro mb-0">Pengguna dapat mengatur gender avatar dan foto profil melalui menu profil. Akun tidak dihapus agar jejak audit tetap utuh.</p>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
