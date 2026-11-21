<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Restore hanya dapat dijalankan melalui command line.\n");
}

$backupPath = $argv[1] ?? '';
$confirmed = in_array('--confirm', $argv, true);
if ($backupPath === '' || !$confirmed || !is_file($backupPath)) {
    fwrite(STDERR, "Pemakaian: php database\\restore.php <backup.sql> --confirm\n");
    fwrite(STDERR, "Restore hanya berjalan pada database target yang benar-benar kosong.\n");
    exit(2);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = getenv('DB_HOST') ?: 'localhost';
$port = (int) (getenv('DB_PORT') ?: '3308');
$database = getenv('DB_NAME') ?: 'webspp';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    throw new RuntimeException('Nama database tidak valid.');
}
$conn = mysqli_connect($host, $username, $password, $database, $port);
mysqli_set_charset($conn, 'utf8mb4');

$tables = mysqli_query($conn, 'SHOW FULL TABLES');
if (mysqli_num_rows($tables) > 0) {
    fwrite(STDERR, "Restore dibatalkan: database target sudah berisi tabel. Buat database kosong dan arahkan DB_NAME ke database tersebut.\n");
    exit(2);
}

$dump = file_get_contents($backupPath);
if ($dump === false || trim($dump) === '') {
    throw new RuntimeException('File backup kosong atau tidak dapat dibaca.');
}

$mysqlBinary = getenv('MYSQL_BIN') ?: 'mysql';
$env = getenv();
if ($env === false) {
    $env = [];
}
$env['MYSQL_PWD'] = $password;
$command = [
    $mysqlBinary,
    '--no-defaults',
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $username,
    $database,
];
$pipes = [];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
if (!is_resource($process)) {
    throw new RuntimeException('mysql client tidak dapat dijalankan.');
}
$input = fopen($backupPath, 'rb');
if ($input === false) {
    fclose($pipes[0]);
    proc_close($process);
    throw new RuntimeException('File backup tidak dapat dibuka.');
}
$copiedBytes = stream_copy_to_stream($input, $pipes[0]);
fclose($input);
fclose($pipes[0]);
if ($copiedBytes === false || $copiedBytes === 0) {
    proc_terminate($process);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    throw new RuntimeException('File backup gagal dikirim ke mysql client.');
}
$output = stream_get_contents($pipes[1]);
$errorOutput = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    fwrite(STDERR, trim((string) $errorOutput) . "\n");
    exit($exitCode);
}

echo "Restore berhasil ke database kosong: $database\n";
