<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Backup hanya dapat dijalankan melalui command line.\n");
}

$projectRoot = realpath(dirname(__DIR__));
$backupDirectory = $argv[1] ?? getenv('BACKUP_DIR');
if (!$backupDirectory) {
    fwrite(STDERR, "Pemakaian: php database\\backup.php <folder-backup-di-luar-project>\n");
    exit(2);
}
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Folder backup tidak dapat dibuat.');
}
$resolvedDirectory = realpath($backupDirectory);
if ($resolvedDirectory === false
    || strcasecmp($resolvedDirectory, $projectRoot) === 0
    || strncasecmp(rtrim($resolvedDirectory, '\\/') . DIRECTORY_SEPARATOR, rtrim($projectRoot, '\\/') . DIRECTORY_SEPARATOR, strlen(rtrim($projectRoot, '\\/') . DIRECTORY_SEPARATOR)) === 0) {
    fwrite(STDERR, "Simpan backup di luar folder web project.\n");
    exit(2);
}

$database = getenv('DB_NAME') ?: 'webspp';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    throw new RuntimeException('Nama database tidak valid.');
}
$timestamp = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
$backupPath = $resolvedDirectory . DIRECTORY_SEPARATOR . $database . '-' . $timestamp . '.sql';
$dumpBinary = getenv('MYSQLDUMP_BIN') ?: 'mysqldump';
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3308';
$user = getenv('DB_USER') ?: 'root';
$env = getenv();
if ($env === false) {
    $env = [];
}
$env['MYSQL_PWD'] = getenv('DB_PASS') ?: '';
$command = [
    $dumpBinary,
    '--no-defaults',
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $user,
    '--single-transaction',
    '--routines',
    '--triggers',
    '--events',
    $database,
];
$pipes = [];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $backupPath, 'wb'], 2 => ['pipe', 'w']], $pipes, null, $env);
if (!is_resource($process)) {
    throw new RuntimeException('mysqldump tidak dapat dijalankan.');
}
fclose($pipes[0]);
$errorOutput = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    if (is_file($backupPath)) {
        unlink($backupPath);
    }
    fwrite(STDERR, trim((string) $errorOutput) . "\n");
    exit($exitCode);
}

echo "Backup database selesai: $backupPath\n";
