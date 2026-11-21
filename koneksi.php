<?php

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3308';
$dbName = getenv('DB_NAME') ?: 'webspp';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, (int) $dbPort);

if (!$conn) {
    throw new RuntimeException('Database gagal terkoneksi');
}

mysqli_set_charset($conn, 'utf8mb4');