<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin', 'cashier']);

$adminId = (int) $_SESSION['admin'];
$stmt = mysqli_prepare($conn, 'SELECT nama_admin, user_admin, pass_admin, gender FROM admin WHERE id_admin = ?');
mysqli_stmt_bind_param($stmt, 'i', $adminId);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$admin) {
    http_response_code(404);
    exit('Akun administrator tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan']) || isset($_POST['remove_photo']))) {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
    } else {
        $name = is_string($_POST['nama_admin'] ?? null) ? trim($_POST['nama_admin']) : '';
        $username = is_string($_POST['user_admin'] ?? null) ? trim($_POST['user_admin']) : '';
        $newPassword = is_string($_POST['pass_admin'] ?? null) ? (string) $_POST['pass_admin'] : '';
        $currentPassword = is_string($_POST['current_password'] ?? null) ? (string) $_POST['current_password'] : '';
        $gender = is_string($_POST['gender'] ?? null) ? (string) $_POST['gender'] : '';
        $removePhoto = isset($_POST['remove_photo']);
        $photo = null;
        $hasNewPhoto = isset($_FILES['profile_photo']) && (int) ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $errorMessage = '';

        if ($name === '' || $username === '' || $currentPassword === '') {
            $errorMessage = 'Lengkapi nama, username, dan password saat ini.';
        } elseif (!in_array($gender, ['male', 'female', 'unspecified'], true)) {
            $errorMessage = 'Pilih avatar gender yang valid.';
        } elseif (!password_verify($currentPassword, (string) $admin['pass_admin'])) {
            $errorMessage = 'Password saat ini tidak sesuai.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 10) {
            $errorMessage = 'Password baru minimal 10 karakter.';
        } elseif ($removePhoto && $hasNewPhoto) {
            $errorMessage = 'Pilih antara mengunggah atau menghapus foto.';
        } else {
            if ($hasNewPhoto) {
                try {
                    $photo = normalize_profile_photo($_FILES['profile_photo']);
                } catch (InvalidArgumentException $error) {
                    $errorMessage = $error->getMessage();
                } catch (RuntimeException $error) {
                    error_log('Profile photo processing failed: ' . $error->getMessage());
                    $errorMessage = 'Foto profil gagal diproses. Coba file lain.';
                }
            }

            if ($errorMessage === '') {
                mysqli_begin_transaction($conn);
                try {
                    if ($removePhoto) {
                        db_execute($conn, 'UPDATE admin SET gender = ?, profile_photo = NULL, profile_photo_updated_at = NULL WHERE id_admin = ?', 'si', [$gender, $adminId]);
                        audit_event($conn, 'profile_photo_removed', 'admin', $adminId, 'Foto profil dihapus; avatar gender digunakan.');
                    } else {
                        $assignments = ['nama_admin = ?', 'user_admin = ?', 'gender = ?'];
                        $types = 'sss';
                        $params = [$name, $username, $gender];
                        if ($newPassword !== '') {
                            $assignments[] = 'pass_admin = ?';
                            $types .= 's';
                            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                        }
                        if ($photo !== null) {
                            $assignments[] = 'profile_photo = ?';
                            $assignments[] = 'profile_photo_updated_at = NOW()';
                            $types .= 's';
                            $params[] = $photo;
                        }
                        $types .= 'i';
                        $params[] = $adminId;
                        db_execute($conn, 'UPDATE admin SET ' . implode(', ', $assignments) . ' WHERE id_admin = ?', $types, $params);
                        audit_event($conn, $photo !== null ? 'profile_photo_updated' : 'profile_updated', 'admin', $adminId, 'Profil akun diperbarui.');
                    }
                    mysqli_commit($conn);
                    if (!$removePhoto) {
                        $_SESSION['nama_admin'] = $name;
                    }
                    $_SESSION['gender'] = $gender;
                    flash($removePhoto ? 'Foto dihapus. Avatar gender digunakan kembali.' : 'Profil berhasil diperbarui.');
                    header('Location: adminedit.php');
                    exit;
                } catch (mysqli_sql_exception $error) {
                    mysqli_rollback($conn);
                    if ($error->getCode() === 1062) {
                        $errorMessage = 'Username sudah digunakan.';
                    } else {
                        error_log('Account profile update failed: ' . $error->getMessage());
                        $errorMessage = 'Pengaturan akun gagal diperbarui. Periksa log server.';
                    }
                }
            }
        }
        if ($errorMessage !== '') {
            flash($errorMessage, 'danger');
        }
    }
    header('Location: adminedit.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<section class="card profile-card">
    <div class="card-header"><h6>Profil dan identitas</h6></div>
    <div class="card-body">
        <p class="form-intro">Atur avatar gender atau unggah foto profil. Foto hanya dapat dilihat setelah masuk ke akun.</p>
        <form method="post" action="adminedit.php" class="profile-form profile-form-wide" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="profile-avatar-editor">
                <img class="profile-avatar profile-avatar-xl" src="avatar.php?id=<?= $adminId ?>" alt="Avatar profil" data-profile-avatar-preview>
                <div class="profile-avatar-actions">
                    <label for="profile-photo">Foto profil <span class="label-optional">opsional</span></label>
                    <input id="profile-photo" class="form-control" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG, atau WebP · maksimal 2 MB. Foto akan dipotong menjadi persegi.</small>
                    <button class="btn btn-secondary btn-sm" type="submit" name="remove_photo" value="1" formnovalidate>Gunakan avatar gender</button>
                </div>
            </div>
            <div class="form-group">
                <label for="admin-name">Nama</label>
                <input id="admin-name" class="form-control" type="text" name="nama_admin" maxlength="50" required value="<?= e($admin['nama_admin']) ?>">
            </div>
            <div class="form-group">
                <label for="admin-user">Username</label>
                <input id="admin-user" class="form-control" type="text" name="user_admin" maxlength="50" autocomplete="username" required value="<?= e($admin['user_admin']) ?>">
            </div>
            <div class="form-group">
                <label for="profile-gender">Avatar default</label>
                <select id="profile-gender" class="form-control" name="gender" required>
                    <option value="unspecified" <?= $admin['gender'] === 'unspecified' ? 'selected' : '' ?>>Netral / tidak ingin menentukan</option>
                    <option value="female" <?= $admin['gender'] === 'female' ? 'selected' : '' ?>>Perempuan</option>
                    <option value="male" <?= $admin['gender'] === 'male' ? 'selected' : '' ?>>Laki-laki</option>
                </select>
            </div>
            <div class="form-group">
                <label for="current-password">Password saat ini</label>
                <div class="password-field">
                    <input id="current-password" class="form-control" type="password" name="current_password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" data-password-toggle="current-password" aria-label="Tampilkan password saat ini" aria-pressed="false">Lihat</button>
                </div>
            </div>
            <div class="form-group">
                <label for="admin-password">Password baru <span class="label-optional">opsional</span></label>
                <div class="password-field">
                    <input id="admin-password" class="form-control" type="password" name="pass_admin" minlength="10" autocomplete="new-password" placeholder="Minimal 10 karakter">
                    <button class="password-toggle" type="button" data-password-toggle="admin-password" aria-label="Tampilkan password baru" aria-pressed="false">Lihat</button>
                </div>
            </div>
            <button type="submit" name="simpan" class="btn btn-primary">Simpan profil</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
