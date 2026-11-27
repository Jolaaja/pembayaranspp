<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Pemulihan password hanya dapat dijalankan melalui command line.\n");
}

$username = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
if ($username === '') {
    fwrite(STDERR, "Pemakaian: php database\\reset_admin_password.php <username-admin>\n");
    exit(2);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../koneksi.php';

$lookup = mysqli_prepare($conn, "SELECT id_admin FROM admin WHERE user_admin = ? AND role = 'admin' LIMIT 1");
mysqli_stmt_bind_param($lookup, 's', $username);
mysqli_stmt_execute($lookup);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
mysqli_stmt_close($lookup);
if (!$admin) {
    fwrite(STDERR, "Akun administrator tidak ditemukan.\n");
    exit(1);
}

echo "Reset password administrator \"$username\"? Ketik RESET untuk melanjutkan: ";
if (strtoupper(trim((string) fgets(STDIN))) !== 'RESET') {
    echo "Pemulihan dibatalkan.\n";
    exit(0);
}

echo "Password baru (input terlihat di terminal): ";
$newPassword = rtrim((string) fgets(STDIN), "\r\n");
echo "Ulangi password baru: ";
$passwordConfirmation = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($newPassword) < 10) {
    fwrite(STDERR, "Password baru harus minimal 10 karakter.\n");
    exit(1);
}
if (!hash_equals($newPassword, $passwordConfirmation)) {
    fwrite(STDERR, "Konfirmasi password tidak cocok.\n");
    exit(1);
}

$adminId = (int) $admin['id_admin'];
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
mysqli_begin_transaction($conn);
try {
    $update = mysqli_prepare($conn, 'UPDATE admin SET pass_admin = ? WHERE id_admin = ?');
    mysqli_stmt_bind_param($update, 'si', $passwordHash, $adminId);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);

    $usernameKey = hash('sha256', strtolower($username));
    $clearAttempts = mysqli_prepare($conn, 'DELETE FROM admin_login_attempts WHERE username_key = ?');
    mysqli_stmt_bind_param($clearAttempts, 's', $usernameKey);
    mysqli_stmt_execute($clearAttempts);
    mysqli_stmt_close($clearAttempts);

    $actorAdminId = null;
    $eventType = 'admin_password_reset';
    $entityType = 'admin';
    $message = 'Password administrator diatur ulang melalui command line.';
    $audit = mysqli_prepare($conn, 'INSERT INTO admin_audit_log (id_admin, event_type, entity_type, entity_id, message) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($audit, 'issis', $actorAdminId, $eventType, $entityType, $adminId, $message);
    mysqli_stmt_execute($audit);
    mysqli_stmt_close($audit);
    mysqli_commit($conn);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    throw $error;
}

echo "Password administrator berhasil diatur ulang.\n";
