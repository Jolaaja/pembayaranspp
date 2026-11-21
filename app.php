<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_valid_iso_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function normalize_profile_photo(array $upload): string
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
            ? 'Pilih foto terlebih dahulu.'
            : 'Foto gagal diunggah. Pilih file lain dan coba lagi.');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File foto tidak valid.');
    }
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 2 MB.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Fitur pemrosesan foto memerlukan ekstensi GD.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($temporaryPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Format foto harus JPG, PNG, atau WebP.');
    }

    $imageInfo = getimagesize($temporaryPath);
    if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1
        || $imageInfo[0] > 6000 || $imageInfo[1] > 6000
        || $imageInfo[0] * $imageInfo[1] > 12000000) {
        throw new InvalidArgumentException('Dimensi foto terlalu besar atau file tidak dapat dibaca.');
    }

    $contents = file_get_contents($temporaryPath);
    if ($contents === false) {
        throw new RuntimeException('Foto tidak dapat dibaca.');
    }
    $source = @imagecreatefromstring($contents);
    if ($source === false) {
        throw new InvalidArgumentException('File foto tidak dapat diproses.');
    }

    $avatar = imagecreatetruecolor(512, 512);
    if ($avatar === false) {
        imagedestroy($source);
        throw new RuntimeException('Gagal menyiapkan gambar profil.');
    }
    $background = imagecolorallocate($avatar, 255, 255, 255);
    imagefill($avatar, 0, 0, $background);

    try {
        $side = min($imageInfo[0], $imageInfo[1]);
        $sourceX = (int) floor(($imageInfo[0] - $side) / 2);
        $sourceY = (int) floor(($imageInfo[1] - $side) / 2);
        if (!imagecopyresampled($avatar, $source, 0, 0, $sourceX, $sourceY, 512, 512, $side, $side)) {
            throw new RuntimeException('Gagal memproses gambar profil.');
        }

        ob_start();
        $saved = imagejpeg($avatar, null, 86);
        $photo = ob_get_clean();
        if (!$saved || !is_string($photo) || $photo === '') {
            throw new RuntimeException('Gagal menyimpan gambar profil.');
        }
        return $photo;
    } finally {
        imagedestroy($source);
        imagedestroy($avatar);
    }
}

function require_login(): void
{
    if (!isset($_SESSION['admin'])) {
        header('Location: loginauth.php');
        exit;
    }

    if (!in_array($_SESSION['role'] ?? null, ['admin', 'cashier'], true)) {
        $_SESSION = [];
        session_destroy();
        header('Location: loginauth.php?expired=1');
        exit;
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_destroy();
        header('Location: loginauth.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

function require_role(array $allowedRoles): void
{
    require_login();
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof mysqli) {
        require_once __DIR__ . '/koneksi.php';
        $conn = $conn ?? ($GLOBALS['conn'] ?? null);
    }
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Koneksi database tidak tersedia.');
    }

    $stmt = db_execute($conn, 'SELECT nama_admin, role FROM admin WHERE id_admin = ?', 'i', [(int) $_SESSION['admin']]);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$account) {
        $_SESSION = [];
        session_destroy();
        header('Location: loginauth.php');
        exit;
    }

    $_SESSION['role'] = $account['role'];
    $_SESSION['nama_admin'] = $account['nama_admin'];
    $role = (string) $account['role'];
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}

function audit_event(mysqli $conn, string $event, ?string $entityType = null, ?int $entityId = null, ?string $message = null): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $ipHash = hash('sha256', $ip);
    $adminId = isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null;
    db_execute(
        $conn,
        'INSERT INTO admin_audit_log (id_admin, event_type, entity_type, entity_id, message, ip_hash) VALUES (?, ?, ?, ?, ?, ?)',
        'ississ',
        [$adminId, $event, $entityType, $entityId, $message, $ipHash]
    );
}

function login_attempt_keys(string $username): array
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    return [
        hash('sha256', strtolower(trim($username))),
        hash('sha256', $ip),
    ];
}

function login_is_rate_limited(mysqli $conn, string $username): bool
{
    [$usernameKey, $ipKey] = login_attempt_keys($username);
    db_execute($conn, 'DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $stmt = db_execute(
        $conn,
        'SELECT COUNT(*) AS total FROM admin_login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (username_key = ? OR ip_key = ?)',
        'ss',
        [$usernameKey, $ipKey]
    );
    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] >= 5;
}

function record_login_failure(mysqli $conn, string $username): void
{
    [$usernameKey, $ipKey] = login_attempt_keys($username);
    db_execute($conn, 'INSERT INTO admin_login_attempts (username_key, ip_key) VALUES (?, ?)', 'ss', [$usernameKey, $ipKey]);
}

function clear_login_attempts(mysqli $conn, string $username): void
{
    [$usernameKey, $ipKey] = login_attempt_keys($username);
    db_execute($conn, 'DELETE FROM admin_login_attempts WHERE username_key = ? OR ip_key = ?', 'ss', [$usernameKey, $ipKey]);
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
