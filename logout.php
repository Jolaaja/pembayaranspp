<?php
require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    http_response_code(400);
    exit('Permintaan logout tidak valid.');
}

if (isset($_SESSION['admin'])) {
    include __DIR__ . '/koneksi.php';
    audit_event($conn, 'logout', 'admin', (int) $_SESSION['admin'], 'Administrator keluar dari sistem.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: loginauth.php');
exit;
