<?php

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3308';
$dbName = getenv('DB_NAME') ?: 'webspp';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

if (strtolower((string) (getenv('APP_ENV') ?: 'development')) === 'production') {
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredSetting) {
        if (getenv($requiredSetting) === false || trim((string) getenv($requiredSetting)) === '') {
            throw new RuntimeException('Konfigurasi database produksi belum lengkap: ' . $requiredSetting);
        }
    }
    if (strtolower($dbUser) === 'root' || trim($dbPass) === '') {
        throw new RuntimeException('Produksi memerlukan akun database khusus dengan password non-kosong.');
    }
}

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, (int) $dbPort);

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    throw new RuntimeException('Database gagal terkoneksi. Periksa konfigurasi dan log server.');
}

mysqli_set_charset($conn, 'utf8mb4');