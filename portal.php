<?php
require_once __DIR__ . '/app.php';

function portal_record_event(mysqli $conn, ?int $accountId, string $eventType): void
{
    if (preg_match('/\A[a-z_]{1,40}\z/', $eventType) !== 1) {
        throw new InvalidArgumentException('Jenis aktivitas portal tidak valid.');
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    db_execute(
        $conn,
        'INSERT INTO portal_account_events (id_portal_account, event_type, ip_hash) VALUES (?, ?, ?)',
        'iss',
        [$accountId, $eventType, hash('sha256', $ip)]
    );
}

function portal_require_login(mysqli $conn, bool $allowPasswordChange = false): array
{
    $accountId = filter_var($_SESSION['portal_account_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$accountId || !isset($_SESSION['portal_auth_version'])) {
        header('Location: portal_login.php');
        exit;
    }

    $stmt = db_execute(
        $conn,
        'SELECT id_portal_account, full_name, username, password_hash, account_type,
                status, must_change_password, auth_version
         FROM portal_accounts WHERE id_portal_account = ? LIMIT 1',
        'i',
        [$accountId]
    );
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$account
        || $account['status'] !== 'active'
        || (int) $account['auth_version'] !== (int) $_SESSION['portal_auth_version']) {
        $_SESSION = [];
        session_destroy();
        header('Location: portal_login.php?expired=1');
        exit;
    }

    $now = time();
    if (isset($_SESSION['portal_last_activity']) && $now - (int) $_SESSION['portal_last_activity'] > 1800) {
        $_SESSION = [];
        session_destroy();
        header('Location: portal_login.php?expired=1');
        exit;
    }
    $_SESSION['portal_last_activity'] = $now;

    if ((int) $account['must_change_password'] === 1
        && !$allowPasswordChange
        && basename((string) ($_SERVER['PHP_SELF'] ?? '')) !== 'portal_change_password.php') {
        header('Location: portal_change_password.php');
        exit;
    }
    return $account;
}

function portal_fetch_students(mysqli $conn, int $accountId): array
{
    $stmt = db_execute(
        $conn,
        "SELECT s.id_siswa, s.nisn, s.nama, s.status, k.nama_kelas
         FROM portal_account_students links
         JOIN siswa s ON s.id_siswa = links.id_siswa
         JOIN kelas k ON k.id_kelas = s.id_kelas
         WHERE links.id_portal_account = ? AND s.status = 'aktif'
         ORDER BY s.nama ASC, s.id_siswa ASC",
        'i',
        [$accountId]
    );
    $result = mysqli_stmt_get_result($stmt);
    $students = [];
    while ($student = mysqli_fetch_assoc($result)) {
        $students[] = $student;
    }
    mysqli_stmt_close($stmt);
    return $students;
}

function portal_fetch_student_payments(mysqli $conn, int $accountId, int $studentId): array
{
    $stmt = db_execute(
        $conn,
        "SELECT p.idspp, p.bulan, p.jatuhtempo, p.jumlah, p.ket, p.nobayar, p.tglbayar
         FROM pembayaran p
         JOIN siswa s ON s.id_siswa = p.id_siswa
         JOIN portal_account_students links
           ON links.id_siswa = s.id_siswa AND links.id_portal_account = ?
         WHERE s.id_siswa = ? AND s.status = 'aktif'
         ORDER BY p.jatuhtempo DESC, p.idspp DESC",
        'ii',
        [$accountId, $studentId]
    );
    $result = mysqli_stmt_get_result($stmt);
    $payments = [];
    while ($payment = mysqli_fetch_assoc($result)) {
        $payments[] = $payment;
    }
    mysqli_stmt_close($stmt);
    return $payments;
}
