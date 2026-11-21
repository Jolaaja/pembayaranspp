<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';

if (isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_validate()) {
        $loginError = 'Sesi login tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $user = trim((string) ($_POST['user'] ?? ''));
        $pass = (string) ($_POST['pass'] ?? '');
        $stmt = mysqli_prepare($conn, 'SELECT id_admin, nama_admin, pass_admin FROM admin WHERE user_admin = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $user);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $passwordHash = (string) ($admin['pass_admin'] ?? '');
        $valid = $admin && (password_verify($pass, $passwordHash) || hash_equals($passwordHash, $pass));

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin'] = (int) $admin['id_admin'];
            $_SESSION['nama_admin'] = $admin['nama_admin'];

            if (password_get_info($passwordHash)['algo'] === null) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $update = mysqli_prepare($conn, 'UPDATE admin SET pass_admin = ? WHERE id_admin = ?');
                mysqli_stmt_bind_param($update, 'si', $hash, $_SESSION['admin']);
                mysqli_stmt_execute($update);
            }
            header('Location: index.php');
            exit;
        }
        $loginError = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153a3a">
    <title>Masuk | Portal Pembayaran Sekolah</title>
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body>
<main class="login-page">
    <div class="login-shell">
        <section class="login-brand-panel">
            <a class="brand-lockup" href="loginauth.php">
                <span class="brand-mark">S</span>
                <span class="brand-copy"><strong>SEKOLAH</strong><small>PORTAL KEUANGAN</small></span>
            </a>
            <div>
                <span class="eyebrow">ADMINISTRASI LEBIH MUDAH</span>
                <h1>Semua pembayaran, lebih tertata.</h1>
                <p>Kelola pembayaran sekolah dan data siswa melalui ruang kerja yang aman dan sederhana.</p>
            </div>
            <div class="login-brand-note">PORTAL ADMINISTRATOR &nbsp;·&nbsp; <?= date('Y') ?></div>
        </section>
        <section class="login-form-panel">
            <span class="eyebrow">SELAMAT DATANG KEMBALI</span>
            <h2>Masuk ke akun</h2>
            <p>Gunakan akun administrator sekolah untuk melanjutkan.</p>
            <?php if ($loginError !== ''): ?>
                <div class="login-error" role="alert"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form method="post" action="loginauth.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="login-user">Username</label>
                <input id="login-user" class="form-control" type="text" name="user" autocomplete="username" required autofocus>
                <label for="login-password">Password</label>
                <input id="login-password" class="form-control" type="password" name="pass" autocomplete="current-password" required>
                <button type="submit" name="login" class="btn btn-primary">Masuk ke dashboard <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
