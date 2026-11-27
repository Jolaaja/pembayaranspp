<?php
require_once __DIR__ . '/portal.php';
include_once __DIR__ . '/koneksi.php';

if (isset($_SESSION['portal_account_id'])) {
    portal_require_login($conn);
    header('Location: portal_dashboard.php');
    exit;
}

$schoolSettings = fetch_school_settings($conn);
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_validate()) {
        $loginError = 'Sesi login tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $rateKey = 'portal:' . $username;
        if ($username === '' || $password === '' || login_is_rate_limited($conn, $rateKey)) {
            $loginError = 'Login tidak tersedia sementara. Periksa kredensial atau coba lagi dalam 15 menit.';
            portal_record_event($conn, null, 'login_blocked');
        } else {
            $stmt = db_execute(
                $conn,
                'SELECT id_portal_account, full_name, password_hash, status, auth_version, must_change_password
                 FROM portal_accounts WHERE username = ? LIMIT 1',
                's',
                [$username]
            );
            $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $valid = $account
                && $account['status'] === 'active'
                && password_verify($password, (string) $account['password_hash']);

            if ($valid) {
                clear_login_attempts($conn, $rateKey);
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['portal_account_id'] = (int) $account['id_portal_account'];
                $_SESSION['portal_auth_version'] = (int) $account['auth_version'];
                $_SESSION['portal_last_activity'] = time();
                portal_record_event($conn, (int) $account['id_portal_account'], 'login_success');
                header('Location: ' . ((int) $account['must_change_password'] === 1
                    ? 'portal_change_password.php'
                    : 'portal_dashboard.php'));
                exit;
            }

            record_login_failure($conn, $rateKey);
            portal_record_event($conn, null, 'login_failure');
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
    <title>Portal keluarga | <?= e($schoolSettings['school_name']) ?></title>
    <link rel="icon" href="img/logo-spp.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/sb-admin-2.min.css">
    <link rel="stylesheet" href="css/modern.css?v=<?= (int) filemtime(__DIR__ . '/css/modern.css') ?>">
</head>
<body class="portal-auth-page">
<main class="portal-auth-card">
    <a class="portal-school-brand" href="portal_login.php">
        <img src="school-logo.php?v=<?= (int) strtotime($schoolSettings['updated_at']) ?>" alt="">
        <span><strong><?= e($schoolSettings['school_name']) ?></strong><small>PORTAL KELUARGA SEKOLAH</small></span>
    </a>
    <span class="eyebrow">AKSES ORANG TUA, WALI, DAN SISWA</span>
    <h1>Informasi pembayaran, lebih mudah dipantau.</h1>
    <p class="portal-auth-description">Masuk untuk melihat jadwal tagihan dan riwayat pembayaran siswa yang telah ditautkan oleh sekolah.</p>
    <?php if (isset($_GET['expired'])): ?>
        <div class="login-error login-warning" role="status">Sesi Anda berakhir atau akses akun berubah. Silakan masuk kembali.</div>
    <?php endif; ?>
    <?php if ($loginError !== ''): ?>
        <div class="login-error" role="alert"><?= e($loginError) ?></div>
    <?php endif; ?>
    <form method="post" action="portal_login.php" class="portal-login-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="portal-username">Username</label>
        <input id="portal-username" class="form-control" name="username" maxlength="50" autocomplete="username" required autofocus>
        <label for="portal-password">Password</label>
        <input id="portal-password" class="form-control" type="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="btn btn-primary" name="login">Masuk ke portal</button>
    </form>
    <p class="portal-account-help">Belum memiliki akun? Hubungi pihak sekolah. Akun dibuat dan ditautkan oleh petugas untuk menjaga privasi data.</p>
    <a class="portal-staff-link" href="loginauth.php">Masuk sebagai petugas sekolah</a>
</main>
</body>
</html>
