<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function require_login(): void
{
    if (!isset($_SESSION['admin'])) {
        header('Location: loginauth.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $arguments = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = $value;
            $arguments[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $arguments);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show spp-alert" role="alert">'
            . e($flash['message'])
            . '<button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>';
    }
}
