<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Migration hanya dapat dijalankan melalui command line.\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST') ?: 'localhost';
$port = (int) (getenv('DB_PORT') ?: '3308');
$database = getenv('DB_NAME') ?: 'webspp';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nama database hanya boleh berisi huruf, angka, dan underscore.\n");
    exit(1);
}

$server = mysqli_connect($host, $username, $password, '', $port);
mysqli_set_charset($server, 'utf8mb4');
mysqli_query($server, "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($server, $database);

mysqli_query($server, 'CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$migrationFiles = glob(__DIR__ . '/migrations/*.sql');
if ($migrationFiles === false) {
    throw new RuntimeException('Gagal membaca folder migrasi.');
}
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $migrationFile) {
    $version = basename($migrationFile);
    $check = mysqli_prepare($server, 'SELECT version FROM schema_migrations WHERE version = ?');
    mysqli_stmt_bind_param($check, 's', $version);
    mysqli_stmt_execute($check);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
        echo "Lewati $version (sudah diterapkan).\n";
        continue;
    }

    $contents = file_get_contents($migrationFile);
    if ($contents === false) {
        throw new RuntimeException("Gagal membaca migrasi $version.");
    }
    $statements = array_filter(array_map('trim', explode(';', $contents)));
    foreach ($statements as $statement) {
        mysqli_query($server, $statement);
    }

    $record = mysqli_prepare($server, 'INSERT INTO schema_migrations (version) VALUES (?)');
    mysqli_stmt_bind_param($record, 's', $version);
    mysqli_stmt_execute($record);
    echo "Berhasil menerapkan $version.\n";
}

$result = mysqli_query($server, 'SELECT COUNT(*) AS total FROM admin');
$adminCount = (int) mysqli_fetch_assoc($result)['total'];
if ($adminCount === 0) {
    echo "\nBelum ada admin. Buat akun admin pertama sekarang? [Y/n]: ";
    $answer = strtolower(trim((string) fgets(STDIN)));
    if ($answer === '' || $answer === 'y' || $answer === 'yes') {
        echo "Nama admin: ";
        $adminName = trim((string) fgets(STDIN));
        echo "Username admin: ";
        $adminUsername = trim((string) fgets(STDIN));
        echo "Password admin (input akan terlihat): ";
        $adminPassword = trim((string) fgets(STDIN));

        if ($adminName === '' || $adminUsername === '' || $adminPassword === '') {
            fwrite(STDERR, "Nama, username, dan password wajib diisi; akun tidak dibuat.\n");
            exit(1);
        }

        $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $insert = mysqli_prepare($server, 'INSERT INTO admin (nama_admin, user_admin, pass_admin) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($insert, 'sss', $adminName, $adminUsername, $hash);
        mysqli_stmt_execute($insert);
        echo "Akun admin berhasil dibuat.\n";
    } else {
        echo "Akun admin belum dibuat. Jalankan migrasi lagi untuk membuatnya.\n";
    }
}

echo "\nMigrasi selesai. Database: $database\n";
