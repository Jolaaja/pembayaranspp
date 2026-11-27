<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Konversi password hanya dapat dijalankan melalui command line.\n");
}

require_once __DIR__ . '/../app.php';
include __DIR__ . '/../koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$updated = 0;
mysqli_begin_transaction($conn);
try {
    $result = mysqli_query($conn, 'SELECT id_admin, pass_admin FROM admin FOR UPDATE');
    while ($account = mysqli_fetch_assoc($result)) {
        $plainPassword = (string) $account['pass_admin'];
        if ($plainPassword === '' || password_get_info($plainPassword)['algo'] !== null) {
            continue;
        }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        db_execute(
            $conn,
            'UPDATE admin SET pass_admin = ? WHERE id_admin = ?',
            'si',
            [$hash, (int) $account['id_admin']]
        );
        $updated++;
    }
    mysqli_commit($conn);
} catch (mysqli_sql_exception $error) {
    mysqli_rollback($conn);
    error_log('Legacy admin password hashing failed: ' . $error->getMessage());
    fwrite(STDERR, "Konversi password gagal. Periksa log server; perubahan telah dibatalkan.\n");
    exit(1);
}

echo "Konversi selesai. Password plaintext diperbarui: $updated\n";
