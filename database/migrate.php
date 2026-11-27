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

if (strtolower((string) (getenv('APP_ENV') ?: 'development')) === 'production') {
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredSetting) {
        if (getenv($requiredSetting) === false || trim((string) getenv($requiredSetting)) === '') {
            fwrite(STDERR, "Konfigurasi database produksi belum lengkap: $requiredSetting\n");
            exit(1);
        }
    }
    if (strtolower($username) === 'root' || trim($password) === '') {
        fwrite(STDERR, "Produksi memerlukan akun database khusus dengan password non-kosong.\n");
        exit(1);
    }
}

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

function verify_financial_migration_preconditions(mysqli $server): void
{
    $checks = [
        'nominal angkatan tidak valid' => "SELECT COUNT(*) AS total FROM angkatan WHERE TRIM(biaya) NOT REGEXP '^[0-9]+([.][0-9]{1,2})?$' OR CAST(TRIM(biaya) AS DECIMAL(65,2)) > 9999999999.99",
        'nominal pembayaran tidak valid' => "SELECT COUNT(*) AS total FROM pembayaran WHERE TRIM(jumlah) NOT REGEXP '^[0-9]+([.][0-9]{1,2})?$' OR CAST(TRIM(jumlah) AS DECIMAL(65,2)) > 9999999999.99",
        'jatuh tempo bukan tanggal ISO valid' => "SELECT COUNT(*) AS total FROM pembayaran WHERE jatuhtempo NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' OR STR_TO_DATE(jatuhtempo, '%Y-%m-%d') IS NULL OR STR_TO_DATE(jatuhtempo, '%Y-%m-%d') < '1000-01-01' OR DATE_FORMAT(STR_TO_DATE(jatuhtempo, '%Y-%m-%d'), '%Y-%m-%d') <> jatuhtempo",
        'tanggal bayar bukan tanggal ISO valid' => "SELECT COUNT(*) AS total FROM pembayaran WHERE tglbayar IS NOT NULL AND tglbayar <> '' AND (tglbayar NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' OR STR_TO_DATE(tglbayar, '%Y-%m-%d') IS NULL OR STR_TO_DATE(tglbayar, '%Y-%m-%d') < '1000-01-01' OR DATE_FORMAT(STR_TO_DATE(tglbayar, '%Y-%m-%d'), '%Y-%m-%d') <> tglbayar)",
        'status lunas tidak lengkap' => "SELECT COUNT(*) AS total FROM pembayaran WHERE ket = 'LUNAS' AND (nobayar IS NULL OR nobayar = '' OR tglbayar IS NULL OR tglbayar = '')",
        'username admin duplikat' => 'SELECT COUNT(*) AS total FROM (SELECT user_admin FROM admin GROUP BY user_admin HAVING COUNT(*) > 1) AS duplicates_found',
        'NISN siswa duplikat' => 'SELECT COUNT(*) AS total FROM (SELECT nisn FROM siswa GROUP BY nisn HAVING COUNT(*) > 1) AS duplicates_found',
        'jadwal pembayaran duplikat' => 'SELECT COUNT(*) AS total FROM (SELECT id_siswa, jatuhtempo FROM pembayaran GROUP BY id_siswa, jatuhtempo HAVING COUNT(*) > 1) AS duplicates_found',
        'nomor pembayaran duplikat' => "SELECT COUNT(*) AS total FROM (SELECT nobayar FROM pembayaran WHERE nobayar IS NOT NULL AND nobayar <> '' GROUP BY nobayar HAVING COUNT(*) > 1) AS duplicates_found",
        'siswa tanpa angkatan/jurusan/kelas valid' => 'SELECT COUNT(*) AS total FROM siswa s LEFT JOIN angkatan a ON a.id_angkatan=s.id_angkatan LEFT JOIN jurusan j ON j.id_jurusan=s.id_jurusan LEFT JOIN kelas k ON k.id_kelas=s.id_kelas WHERE a.id_angkatan IS NULL OR j.id_jurusan IS NULL OR k.id_kelas IS NULL',
        'pembayaran tanpa siswa/admin valid' => 'SELECT COUNT(*) AS total FROM pembayaran p LEFT JOIN siswa s ON s.id_siswa=p.id_siswa LEFT JOIN admin a ON a.id_admin=p.id_admin WHERE s.id_siswa IS NULL OR (p.id_admin IS NOT NULL AND a.id_admin IS NULL)',
    ];

    $issues = [];
    foreach ($checks as $description => $sql) {
        $result = mysqli_query($server, $sql);
        $total = (int) mysqli_fetch_assoc($result)['total'];
        if ($total > 0) {
            $issues[] = "$description: $total baris";
        }
    }

    if ($issues) {
        throw new RuntimeException(
            "Migrasi finansial dihentikan agar data tidak berubah keliru:\n- " .
            implode("\n- ", $issues) .
            "\nPerbaiki data bermasalah atau pulihkan backup, lalu jalankan migrasi lagi."
        );
    }
}

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

    if ($version === '003_financial_integrity.sql') {
        verify_financial_migration_preconditions($server);
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
