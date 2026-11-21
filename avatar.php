<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$targetId = filter_var($_GET['id'] ?? $_SESSION['admin'], FILTER_VALIDATE_INT);
if (!$targetId || $targetId < 1) {
    http_response_code(400);
    exit;
}
if ($targetId !== (int) $_SESSION['admin']) {
    require_role(['admin']);
}

$stmt = db_execute($conn, 'SELECT gender, profile_photo FROM admin WHERE id_admin = ?', 'i', [$targetId]);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$account) {
    http_response_code(404);
    exit;
}

header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if ($account['profile_photo'] !== null && $account['profile_photo'] !== '') {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($account['profile_photo']));
    echo $account['profile_photo'];
    exit;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
$gender = $account['gender'];
if ($gender === 'male') {
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="60" fill="#dcefe8"/><path d="M17 120c3-25 18-38 43-38s40 13 43 38" fill="#376f65"/><path d="M36 51c0-20 10-31 25-31s24 12 24 31v10c0 17-11 29-25 29S36 78 36 61z" fill="#d6a57d"/><path d="M35 52c-4-20 5-38 26-38 20 0 29 15 25 36-4-3-7-8-9-13-8 7-20 10-39 9l-3 6z" fill="#344b49"/><path d="M44 70c5 4 10 6 16 6s11-2 16-6" fill="none" stroke="#ad7359" stroke-linecap="round" stroke-width="2"/></svg>';
} elseif ($gender === 'female') {
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="60" fill="#f3e5e9"/><path d="M17 120c3-25 18-38 43-38s40 13 43 38" fill="#8e5970"/><path d="M32 54c0-23 11-37 29-37 18 0 28 14 28 37v28H32z" fill="#4a3c48"/><path d="M38 52c0-19 9-30 23-30s23 11 23 30v10c0 17-10 29-23 29S38 79 38 62z" fill="#edc0a3"/><path d="M34 53c-2-22 8-36 27-36 20 0 30 16 25 39-5-3-9-10-10-17-10 8-22 12-42 11z" fill="#4a3c48"/><path d="M45 70c5 4 10 6 16 6s11-2 16-6" fill="none" stroke="#c28f79" stroke-linecap="round" stroke-width="2"/></svg>';
} else {
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="60" fill="#e5eae7"/><path d="M17 120c3-25 18-38 43-38s40 13 43 38" fill="#637b76"/><circle cx="60" cy="51" r="25" fill="#c6a88f"/><path d="M36 48c1-17 10-28 25-28 13 0 22 10 23 27-7-3-13-8-17-14-7 8-17 13-31 15z" fill="#596963"/><path d="M45 70c5 4 10 6 15 6s11-2 16-6" fill="none" stroke="#a78670" stroke-linecap="round" stroke-width="2"/></svg>';
}
exit;
