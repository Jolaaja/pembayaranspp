<?php

if (session_status() === PHP_SESSION_NONE) {
    $isProduction = strtolower((string) (getenv('APP_ENV') ?: 'development')) === 'production';
    if ($isProduction) {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
    }
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', ($isProduction || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (strtolower((string) (getenv('APP_ENV') ?: 'development')) === 'production'
        && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
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

function is_valid_payment_date(string $value): bool
{
    return is_valid_iso_date($value) && $value <= (new DateTimeImmutable('today'))->format('Y-m-d');
}

function normalize_indonesian_mobile(string $phone): ?string
{
    $phone = trim($phone);
    if (preg_match('/\A.{1,30}\z/us', $phone) !== 1
        || preg_match('/\A\+?[0-9\s().-]+\z/u', $phone) !== 1) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', trim($phone));
    if (!is_string($digits) || $digits === '') {
        return null;
    }

    if (strncmp($digits, '62', 2) === 0) {
        $digits = substr($digits, 2);
    } elseif ($digits[0] === '0') {
        $digits = substr($digits, 1);
    }

    return preg_match('/\A8[0-9]{7,12}\z/', $digits) === 1 ? '+62' . $digits : null;
}

function build_spp_schedule(string $firstMonth): array
{
    $year = (int) substr($firstMonth, 0, 4);
    if (preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/', $firstMonth) !== 1
        || $year < 1900 || $year > 9996) {
        throw new InvalidArgumentException('Bulan pertama jadwal SPP tidak valid.');
    }

    $startMonth = DateTimeImmutable::createFromFormat('!Y-m-d', $firstMonth . '-01');
    if (!$startMonth || $startMonth->format('Y-m') !== $firstMonth) {
        throw new InvalidArgumentException('Bulan pertama jadwal SPP tidak valid.');
    }

    $monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    $schedule = [];
    for ($offset = 0; $offset < 36; $offset++) {
        $month = $startMonth->modify('+' . $offset . ' months');
        $schedule[] = [
            'due_date' => $month->setDate((int) $month->format('Y'), (int) $month->format('n'), 10)->format('Y-m-d'),
            'label' => $monthNames[(int) $month->format('n') - 1] . ' ' . $month->format('Y'),
        ];
    }

    return $schedule;
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

function validate_school_logo(array $upload): array
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
            ? 'Pilih logo terlebih dahulu.'
            : 'Logo gagal diunggah. Pilih file lain dan coba lagi.');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File logo tidak valid.');
    }
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran logo maksimal 2 MB.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Fitur validasi logo memerlukan ekstensi GD.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($temporaryPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Format logo harus JPG, PNG, atau WebP.');
    }

    $imageInfo = getimagesize($temporaryPath);
    if ($imageInfo === false || $imageInfo['mime'] !== $mime
        || $imageInfo[0] < 1 || $imageInfo[1] < 1
        || $imageInfo[0] > 4000 || $imageInfo[1] > 4000
        || $imageInfo[0] * $imageInfo[1] > 8000000) {
        throw new InvalidArgumentException('Dimensi logo terlalu besar atau file tidak dapat dibaca.');
    }

    $contents = file_get_contents($temporaryPath);
    if ($contents === false) {
        throw new RuntimeException('Logo tidak dapat dibaca.');
    }
    $image = @imagecreatefromstring($contents);
    if ($image === false) {
        throw new InvalidArgumentException('File logo tidak dapat diproses.');
    }
    imagedestroy($image);

    return ['data' => $contents, 'mime' => $mime];
}

function is_valid_school_website(string $website): bool
{
    if ($website === '') {
        return true;
    }
    if (strlen($website) > 254) {
        return false;
    }

    $parts = parse_url($website);
    return filter_var($website, FILTER_VALIDATE_URL) !== false
        && is_array($parts)
        && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !empty($parts['host']);
}

function record_school_identity_version(
    mysqli $conn,
    string $changeType = 'updated',
    ?int $restoredFrom = null
): int {
    if (!in_array($changeType, ['updated', 'restored'], true)) {
        throw new InvalidArgumentException('Jenis perubahan identitas sekolah tidak valid.');
    }

    $adminId = isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null;
    $stmt = db_execute(
        $conn,
        'INSERT INTO school_identity_history (
            school_name, school_address, school_phone, school_email, school_npsn,
            principal_name, school_website, logo_data, logo_mime, changed_by,
            change_type, restored_from
         )
         SELECT school_name, school_address, school_phone, school_email, school_npsn,
                principal_name, school_website, logo_data, logo_mime, ?, ?, ?
         FROM school_settings WHERE settings_id = 1',
        'isi',
        [$adminId, $changeType, $restoredFrom]
    );
    mysqli_stmt_close($stmt);
    $versionId = (int) mysqli_insert_id($conn);
    if ($versionId < 1) {
        throw new RuntimeException('Versi identitas sekolah gagal disimpan.');
    }

    trim_school_identity_history($conn);

    return $versionId;
}

function trim_school_identity_history(mysqli $conn): void
{
    $stmt = db_execute(
        $conn,
        'DELETE FROM school_identity_history
         WHERE id_version NOT IN (
             SELECT id_version FROM (
                 SELECT id_version FROM school_identity_history
                 ORDER BY id_version DESC LIMIT 20
             ) AS recent_versions
         )'
    );
    mysqli_stmt_close($stmt);
}

function fetch_school_settings(mysqli $conn): array
{
    $result = mysqli_query(
        $conn,
        'SELECT school_name, school_address, school_phone, school_email, school_npsn,
                principal_name, school_website, logo_mime, updated_at
         FROM school_settings WHERE settings_id = 1'
    );
    $settings = mysqli_fetch_assoc($result);
    if (!$settings) {
        throw new RuntimeException('Pengaturan sekolah belum tersedia. Jalankan migrasi database terbaru.');
    }
    return $settings;
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

function fetch_student_page(
    mysqli $conn,
    string $search,
    string $sort,
    string $direction,
    int $page,
    int $perPage,
    bool $includeAddress = false
): array {
    $sortColumns = [
        'nama' => 's.nama',
        'nisn' => 's.nisn',
        'angkatan' => 'a.nama_angkatan',
        'kelas' => 'k.nama_kelas',
        'jurusan' => 'j.nama_jurusan',
    ];
    if (!isset($sortColumns[$sort]) || !in_array($direction, ['asc', 'desc'], true)) {
        throw new InvalidArgumentException('Pengurutan daftar siswa tidak valid.');
    }
    if ($page < 1 || $perPage < 1 || $perPage > 100) {
        throw new InvalidArgumentException('Halaman daftar siswa tidak valid.');
    }

    $fromSql = "FROM siswa s
        JOIN angkatan a ON a.id_angkatan = s.id_angkatan
        JOIN jurusan j ON j.id_jurusan = s.id_jurusan
        JOIN kelas k ON k.id_kelas = s.id_kelas
        WHERE s.status = 'aktif'";
    $types = '';
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, "\\_%") . '%';
        $fromSql .= ' AND (s.nisn LIKE ? OR s.nama LIKE ? OR a.nama_angkatan LIKE ? OR j.nama_jurusan LIKE ? OR k.nama_kelas LIKE ?)';
        $types = 'sssss';
        $params = [$like, $like, $like, $like, $like];
    }

    $countStmt = db_execute($conn, 'SELECT COUNT(*) AS total ' . $fromSql, $types, $params);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    mysqli_stmt_close($countStmt);
    $total = (int) ($countRow['total'] ?? 0);
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;
    $addressColumn = $includeAddress ? ', s.alamat' : '';
    $orderDirection = strtoupper($direction);
    $studentsStmt = db_execute(
        $conn,
        'SELECT s.id_siswa, s.nisn, s.nama, a.nama_angkatan, j.nama_jurusan, k.nama_kelas' . $addressColumn . ' '
            . $fromSql . ' ORDER BY ' . $sortColumns[$sort] . ' ' . $orderDirection . ', s.nisn ASC LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );
    $students = mysqli_stmt_get_result($studentsStmt);
    mysqli_stmt_close($studentsStmt);

    return [
        'rows' => $students,
        'total' => $total,
        'page' => $page,
        'last_page' => $lastPage,
        'per_page' => $perPage,
    ];
}

function search_payment_students(mysqli $conn, string $search, int $limit = 10): array
{
    if (preg_match('/\A.{1,100}\z/us', $search) !== 1 || $limit < 1 || $limit > 50) {
        throw new InvalidArgumentException('Pencarian siswa untuk pembayaran tidak valid.');
    }

    $like = '%' . addcslashes($search, "\\_%") . '%';
    $stmt = db_execute(
        $conn,
        "SELECT s.id_siswa, s.nisn, s.nama, a.nama_angkatan, a.biaya, j.nama_jurusan, k.nama_kelas
         FROM siswa s JOIN angkatan a ON a.id_angkatan = s.id_angkatan
         JOIN jurusan j ON j.id_jurusan = s.id_jurusan
         JOIN kelas k ON k.id_kelas = s.id_kelas
         WHERE s.status = 'aktif' AND (s.nisn LIKE ? OR s.nama LIKE ?)
         ORDER BY CASE WHEN s.nisn = ? THEN 0 ELSE 1 END, s.nama ASC
         LIMIT ?",
        'sssi',
        [$like, $like, $search, $limit + 1]
    );
    $result = mysqli_stmt_get_result($stmt);
    $matches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $matches[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $matches;
}

function fetch_payment_reminders(
    mysqli $conn,
    string $status,
    string $search,
    int $daysAhead,
    int $page,
    ?int $perPage = 25
): array {
    if (!in_array($status, ['overdue', 'upcoming', 'all'], true)) {
        throw new InvalidArgumentException('Jenis pengingat pembayaran tidak valid.');
    }
    if ($daysAhead < 1 || $daysAhead > 30 || $page < 1
        || ($perPage !== null && ($perPage < 1 || $perPage > 100))) {
        throw new InvalidArgumentException('Rentang pengingat atau halaman tidak valid.');
    }

    $where = "s.status = 'aktif' AND p.ket IS NULL";
    $types = '';
    $params = [];
    if ($status === 'overdue') {
        $where .= ' AND p.jatuhtempo < CURDATE()';
    } elseif ($status === 'upcoming') {
        $dueThrough = (new DateTimeImmutable('today'))->modify('+' . $daysAhead . ' days')->format('Y-m-d');
        $where .= ' AND p.jatuhtempo BETWEEN CURDATE() AND ?';
        $types .= 's';
        $params[] = $dueThrough;
    }
    if ($search !== '') {
        $like = '%' . addcslashes($search, "\\_%") . '%';
        $where .= ' AND (s.nisn LIKE ? OR s.nama LIKE ? OR k.nama_kelas LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $fromSql = 'FROM pembayaran p
        JOIN siswa s ON s.id_siswa = p.id_siswa
        JOIN kelas k ON k.id_kelas = s.id_kelas
        WHERE ' . $where;
    $summaryStmt = db_execute(
        $conn,
        'SELECT COUNT(*) AS total, COALESCE(SUM(p.jumlah), 0) AS amount ' . $fromSql,
        $types,
        $params
    );
    $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStmt));
    mysqli_stmt_close($summaryStmt);

    $total = (int) ($summary['total'] ?? 0);
    $lastPage = $perPage === null ? 1 : max(1, (int) ceil($total / $perPage));
    $page = min($page, $lastPage);
    $rowsSql = 'SELECT p.idspp, p.bulan, p.jumlah, p.jatuhtempo,
                       s.id_siswa, s.nisn, s.nama, s.guardian_name, s.guardian_phone, k.nama_kelas
                ' . $fromSql . '
                ORDER BY p.jatuhtempo ASC, s.nama ASC, p.idspp ASC';
    $rowsParams = $params;
    if ($perPage !== null) {
        $rowsSql .= ' LIMIT ? OFFSET ?';
        $types .= 'ii';
        array_push($rowsParams, $perPage, ($page - 1) * $perPage);
    }
    $rowsStmt = db_execute(
        $conn,
        $rowsSql,
        $types,
        $rowsParams
    );
    $result = mysqli_stmt_get_result($rowsStmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($rowsStmt);

    return [
        'rows' => $rows,
        'total' => $total,
        'amount' => (float) ($summary['amount'] ?? 0),
        'page' => $page,
        'last_page' => $lastPage,
        'per_page' => $perPage,
    ];
}

function parse_payment_reminder_filters(array $query): array
{
    $status = $query['jenis'] ?? 'overdue';
    $search = $query['q'] ?? '';
    $daysAhead = filter_var($query['hari'] ?? '7', FILTER_VALIDATE_INT);
    $page = filter_var($query['page'] ?? '1', FILTER_VALIDATE_INT);
    if (!is_string($status) || !in_array($status, ['overdue', 'upcoming', 'all'], true)
        || !is_string($search) || preg_match('/\A.{0,100}\z/us', $search) !== 1
        || $daysAhead === false || $daysAhead < 1 || $daysAhead > 30
        || $page === false || $page < 1) {
        throw new InvalidArgumentException('Filter pengingat pembayaran tidak valid.');
    }

    return [
        'status' => $status,
        'search' => trim($search),
        'days_ahead' => $daysAhead,
        'page' => $page,
    ];
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
        $isUrgent = in_array($flash['type'], ['danger', 'warning'], true);
        $role = $isUrgent ? 'alert' : 'status';
        $live = $isUrgent ? 'assertive' : 'polite';
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show spp-alert" role="' . $role . '" aria-live="' . $live . '" aria-atomic="true">'
            . e($flash['message'])
            . '<button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>';
    }
}
