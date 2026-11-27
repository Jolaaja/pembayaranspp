<?php
include __DIR__ . '/koneksi.php';

$stmt = mysqli_prepare($conn, 'SELECT logo_data, logo_mime FROM school_settings WHERE settings_id = 1');
mysqli_stmt_execute($stmt);
$settings = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$settings) {
    http_response_code(404);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');
if ($settings['logo_data'] === null || $settings['logo_data'] === '') {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    readfile(__DIR__ . '/img/logo-spp.svg');
    exit;
}

if (!in_array($settings['logo_mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    throw new RuntimeException('Format logo sekolah tersimpan tidak valid.');
}

header('Content-Type: ' . $settings['logo_mime']);
header('Content-Length: ' . strlen($settings['logo_data']));
echo $settings['logo_data'];
