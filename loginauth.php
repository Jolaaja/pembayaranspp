<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
$schoolSettings = fetch_school_settings($conn);

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
        if ($user === '' || $pass === '' || login_is_rate_limited($conn, $user)) {
            $loginError = 'Login tidak tersedia sementara. Periksa kredensial atau coba lagi dalam 15 menit.';
            audit_event($conn, 'login_blocked', 'account', null, 'Percobaan login dibatasi.');
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT id_admin, nama_admin, pass_admin, role FROM admin WHERE user_admin = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $user);
            mysqli_stmt_execute($stmt);
            $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $passwordHash = (string) ($admin['pass_admin'] ?? '');
            $valid = $admin && (password_verify($pass, $passwordHash) || hash_equals($passwordHash, $pass));

            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['admin'] = (int) $admin['id_admin'];
                $_SESSION['nama_admin'] = $admin['nama_admin'];
                $_SESSION['role'] = $admin['role'];
                $_SESSION['last_activity'] = time();
                clear_login_attempts($conn, $user);

                if (password_get_info($passwordHash)['algo'] === null) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $update = mysqli_prepare($conn, 'UPDATE admin SET pass_admin = ? WHERE id_admin = ?');
                    mysqli_stmt_bind_param($update, 'si', $hash, $_SESSION['admin']);
                    mysqli_stmt_execute($update);
                }
                audit_event($conn, 'login_success', 'admin', (int) $admin['id_admin'], 'Administrator berhasil masuk.');
                header('Location: index.php');
                exit;
            }

            record_login_failure($conn, $user);
            audit_event($conn, 'login_failure', 'account', null, 'Kredensial login tidak valid.');
            $loginError = 'Username atau password salah.';
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
    <title>Masuk | <?= e($schoolSettings['school_name']) ?></title>
    <link rel="icon" href="img/logo-spp.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css?v=<?= (int) filemtime(__DIR__ . '/css/modern.css') ?>">
</head>
<body>
<main class="login-page">
    <div class="login-shell">
        <section class="login-brand-panel">
            <a class="brand-lockup" href="loginauth.php">
                <img class="brand-mark" src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
                <span class="brand-copy"><strong><?= e($schoolSettings['school_name']) ?></strong><small>KEUANGAN SEKOLAH</small></span>
            </a>
            <div class="login-intro">
                <span class="eyebrow">RUANG KERJA SEKOLAH</span>
                <h1>Keuangan sekolah, lebih teratur.</h1>
                <p>Pembayaran, data siswa, dan laporan sekolah dalam satu ruang kerja yang aman.</p>
            </div>
            <svg class="login-art" viewBox="0 0 360 150" fill="none" aria-hidden="true">
                <path d="M7 133h346" stroke="rgba(220,244,231,.18)"/>
                <rect x="37" y="78" width="48" height="55" rx="8" fill="rgba(223,244,233,.16)"/>
                <rect x="102" y="52" width="48" height="81" rx="8" fill="rgba(223,244,233,.24)"/>
                <rect x="167" y="31" width="48" height="102" rx="8" fill="rgba(223,244,233,.34)"/>
                <path d="m25 69 70-22 60 14 66-39 62 18 42-29" stroke="#d7efdf" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/>
                <circle cx="283" cy="11" r="6" fill="#d7efdf"/>
                <circle cx="95" cy="47" r="5" fill="#d7efdf"/>
                <circle cx="155" cy="61" r="5" fill="#d7efdf"/>
                <circle cx="221" cy="22" r="5" fill="#d7efdf"/>
            </svg>
            <div class="login-brand-note">AMAN · RINGKAS · TERPUSAT &nbsp;·&nbsp; <?= date('Y') ?></div>
        </section>
        <section class="login-form-panel">
            <div class="login-form-heading">
                <span class="login-access-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg></span>
                <span class="eyebrow">AKSES PETUGAS SEKOLAH</span>
            </div>
            <h2>Selamat datang kembali</h2>
            <p>Masuk menggunakan akun administrator atau kasir.</p>
            <?php if (isset($_GET['expired'])): ?>
                <div class="login-error login-warning" role="status">Sesi Anda berakhir. Silakan masuk kembali.</div>
            <?php endif; ?>
            <?php if ($loginError !== ''): ?>
                <div class="login-error" role="alert"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form method="post" action="loginauth.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="login-user">Username</label>
                <input id="login-user" class="form-control" type="text" name="user" autocomplete="username" placeholder="Masukkan username" required autofocus>
                <label for="login-password">Password</label>
                <div class="password-field">
                    <input id="login-password" class="form-control" type="password" name="pass" autocomplete="current-password" placeholder="Masukkan password" required>
                    <button class="password-toggle" type="button" data-password-toggle="login-password" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><span data-password-label>Lihat</span></button>
                </div>
                <button type="submit" name="login" class="btn btn-primary">Masuk ke dashboard <span aria-hidden="true">→</span></button>
            </form>
            <div class="login-security-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg><span>Akses terbatas untuk petugas sekolah. Jangan bagikan password Anda. Admin yang lupa password dapat meminta operator server menjalankan pemulihan lewat terminal.</span></div>
        </section>
    </div>
</main>
<script src="js/app.js?v=<?= (int) filemtime(__DIR__ . '/js/app.js') ?>" defer></script>
</body>
</html>
