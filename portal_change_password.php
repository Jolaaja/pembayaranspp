<?php
require_once __DIR__ . '/portal.php';
include_once __DIR__ . '/koneksi.php';
$account = portal_require_login($conn, true);
$schoolSettings = fetch_school_settings($conn);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        if (!password_verify($currentPassword, (string) $account['password_hash'])) {
            $errorMessage = 'Password saat ini tidak sesuai.';
        } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
            $errorMessage = 'Password baru harus terdiri dari 12 hingga 72 karakter.';
        } elseif (!hash_equals($newPassword, $confirmation)) {
            $errorMessage = 'Konfirmasi password tidak sama.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            mysqli_begin_transaction($conn);
            try {
                $update = db_execute(
                    $conn,
                    'UPDATE portal_accounts
                     SET password_hash = ?, must_change_password = 0, auth_version = auth_version + 1
                     WHERE id_portal_account = ? AND auth_version = ?',
                    'sii',
                    [$newHash, (int) $account['id_portal_account'], (int) $account['auth_version']]
                );
                if (mysqli_stmt_affected_rows($update) !== 1) {
                    throw new RuntimeException('Akun berubah saat password diperbarui.');
                }
                portal_record_event($conn, (int) $account['id_portal_account'], 'password_changed');
                mysqli_commit($conn);
                session_regenerate_id(true);
                $_SESSION['portal_auth_version'] = (int) $account['auth_version'] + 1;
                $_SESSION['portal_last_activity'] = time();
                header('Location: portal_dashboard.php');
                exit;
            } catch (mysqli_sql_exception | RuntimeException $error) {
                mysqli_rollback($conn);
                error_log('Portal password update failed: ' . $error->getMessage());
                $errorMessage = 'Password gagal diperbarui. Coba lagi atau hubungi sekolah.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153a3a">
    <title>Ganti password | <?= e($schoolSettings['school_name']) ?></title>
    <link rel="icon" href="img/logo-spp.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css?v=<?= (int) filemtime(__DIR__ . '/css/modern.css') ?>">
</head>
<body class="portal-auth-page">
<main class="portal-auth-card">
    <a class="portal-school-brand" href="portal_dashboard.php">
        <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
        <span><strong><?= e($schoolSettings['school_name']) ?></strong><small>PORTAL KELUARGA SEKOLAH</small></span>
    </a>
    <span class="eyebrow">KEAMANAN AKUN</span>
    <h1><?= (int) $account['must_change_password'] === 1 ? 'Buat password pribadi' : 'Ganti password' ?></h1>
    <p class="portal-auth-description">Gunakan password minimal 12 karakter. Password awal yang diberikan sekolah tidak dapat digunakan kembali setelah diganti.</p>
    <?php if ($errorMessage !== ''): ?><div class="login-error" role="alert"><?= e($errorMessage) ?></div><?php endif; ?>
    <form method="post" class="portal-login-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="current-password">Password saat ini</label>
        <input id="current-password" class="form-control" type="password" name="current_password" autocomplete="current-password" required>
        <label for="new-password">Password baru</label>
        <input id="new-password" class="form-control" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required>
        <label for="confirm-password">Ulangi password baru</label>
        <input id="confirm-password" class="form-control" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required>
        <button type="submit" class="btn btn-primary">Simpan password</button>
    </form>
    <?php if ((int) $account['must_change_password'] !== 1): ?><a class="portal-staff-link" href="portal_dashboard.php">Kembali ke portal</a><?php endif; ?>
</main>
</body>
</html>
