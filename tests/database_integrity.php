<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Tes hanya dapat dijalankan melalui command line.\n");
}

require_once __DIR__ . '/../app.php';
include __DIR__ . '/../koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect_column_type(mysqli $conn, string $table, string $column, string $expectedType): void
{
    $stmt = db_execute(
        $conn,
        'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        'ss',
        [$table, $column]
    );
    $metadata = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    expect_true(($metadata['DATA_TYPE'] ?? null) === $expectedType, "$table.$column harus bertipe $expectedType.");
}

$admins = mysqli_query($conn, "SELECT id_admin, role FROM admin WHERE role = 'admin' ORDER BY id_admin LIMIT 1");
$admin = mysqli_fetch_assoc($admins);
if (!$admin) {
    throw new RuntimeException('Tes memerlukan sedikitnya satu akun administrator.');
}

expect_true(is_valid_iso_date('2026-01-31'), 'Tanggal ISO yang valid harus diterima.');
expect_true(!is_valid_iso_date('2026-02-30'), 'Tanggal kalender yang tidak valid harus ditolak.');
expect_column_type($conn, 'angkatan', 'biaya', 'decimal');
expect_column_type($conn, 'pembayaran', 'jumlah', 'decimal');
expect_column_type($conn, 'pembayaran', 'jatuhtempo', 'date');
expect_column_type($conn, 'pembayaran', 'tglbayar', 'date');

$adminId = (int) $admin['id_admin'];
$_SESSION['admin'] = $adminId;
mysqli_begin_transaction($conn);
try {
    unset($_SESSION['admin']);
    audit_event($conn, 'login_failure_test', 'account', null, 'Verifikasi aktor audit anonim.');
    $anonymousAuditId = mysqli_insert_id($conn);
    $anonymousAudit = db_execute($conn, 'SELECT id_admin IS NULL AS actor_is_null FROM admin_audit_log WHERE id_audit = ?', 'i', [$anonymousAuditId]);
    expect_true((int) mysqli_fetch_assoc(mysqli_stmt_get_result($anonymousAudit))['actor_is_null'] === 1, 'Audit tanpa sesi harus menyimpan aktor sebagai NULL.');
    $_SESSION['admin'] = $adminId;

    db_execute($conn, 'INSERT INTO kelas (nama_kelas) VALUES (?)', 's', ['__integration_test_class']);
    $classId = mysqli_insert_id($conn);
    db_execute($conn, 'INSERT INTO jurusan (nama_jurusan) VALUES (?)', 's', ['__integration_test_major']);
    $majorId = mysqli_insert_id($conn);
    db_execute($conn, 'INSERT INTO angkatan (nama_angkatan, biaya) VALUES (?, ?)', 'ss', ['__integration_test_cohort', '98765.43']);
    $cohortId = mysqli_insert_id($conn);
    $nisn = 'T' . bin2hex(random_bytes(8));
    db_execute(
        $conn,
        'INSERT INTO siswa (nisn, nama, id_angkatan, id_jurusan, id_kelas, alamat) VALUES (?, ?, ?, ?, ?, ?)',
        'ssiiis',
        [$nisn, 'Siswa Uji', $cohortId, $majorId, $classId, 'Data uji sementara']
    );
    $studentId = mysqli_insert_id($conn);
    $dueDate = date('Y-m-d', strtotime('-1 day'));
    db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [$studentId, $dueDate, 'Januari 2026', '98765.43']);
    $paymentId = mysqli_insert_id($conn);

    $foreignKeyRejected = false;
    try {
        db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [2147483647, $dueDate, 'Invalid', '100.00']);
    } catch (mysqli_sql_exception $error) {
        $foreignKeyRejected = true;
    }
    expect_true($foreignKeyRejected, 'Foreign key harus menolak pembayaran tanpa siswa.');

    $paymentDate = date('Y-m-d');
    db_execute($conn, "UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = 'LUNAS', id_admin = ? WHERE idspp = ?", 'ssii', ['TEST-' . $paymentId, $paymentDate, $adminId, $paymentId]);
    db_execute($conn, "INSERT INTO pembayaran_events (idspp, id_admin, event_type, amount, payment_date, details) VALUES (?, ?, 'paid', ?, ?, ?)", 'iidss', [$paymentId, $adminId, '98765.43', $paymentDate, 'Pembayaran uji']);
    audit_event($conn, 'payment_paid', 'pembayaran', $paymentId, 'Aktivitas uji transaksi.');

    $report = db_execute($conn, "SELECT COUNT(*) AS transactions, SUM(jumlah) AS income FROM pembayaran WHERE ket = 'LUNAS' AND idspp = ?", 'i', [$paymentId]);
    $reportRow = mysqli_fetch_assoc(mysqli_stmt_get_result($report));
    expect_true((int) $reportRow['transactions'] === 1 && (float) $reportRow['income'] === 98765.43, 'Rekap pembayaran lunas harus menjumlahkan nominal dengan benar.');
    $classReport = db_execute(
        $conn,
        "SELECT COUNT(*) AS transactions, SUM(p.jumlah) AS income
         FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa JOIN kelas k ON k.id_kelas = s.id_kelas
         WHERE p.ket = 'LUNAS' AND p.idspp = ? AND k.id_kelas = ?",
        'ii',
        [$paymentId, $classId]
    );
    $classReportRow = mysqli_fetch_assoc(mysqli_stmt_get_result($classReport));
    expect_true((int) $classReportRow['transactions'] === 1 && (float) $classReportRow['income'] === 98765.43, 'Rekap pemasukan per kelas harus mengelompokkan transaksi dengan benar.');
    $monthlyReport = db_execute(
        $conn,
        "SELECT COUNT(*) AS transactions FROM pembayaran
         WHERE ket = 'LUNAS' AND idspp = ? AND DATE_FORMAT(tglbayar, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')",
        'i',
        [$paymentId]
    );
    expect_true((int) mysqli_fetch_assoc(mysqli_stmt_get_result($monthlyReport))['transactions'] === 1, 'Rekap pemasukan bulanan harus mengelompokkan berdasarkan tanggal bayar.');

    db_execute($conn, 'UPDATE pembayaran SET nobayar = NULL, tglbayar = NULL, ket = NULL, id_admin = NULL WHERE idspp = ?', 'i', [$paymentId]);
    db_execute($conn, "INSERT INTO pembayaran_events (idspp, id_admin, event_type, amount, payment_date, details) VALUES (?, ?, 'reversed', ?, ?, ?)", 'iidss', [$paymentId, $adminId, '98765.43', $paymentDate, 'Pembatalan uji']);
    audit_event($conn, 'payment_reversed', 'pembayaran', $paymentId, 'Aktivitas uji pembatalan.');
    $events = db_execute($conn, 'SELECT COUNT(*) AS total FROM pembayaran_events WHERE idspp = ?', 'i', [$paymentId]);
    expect_true((int) mysqli_fetch_assoc(mysqli_stmt_get_result($events))['total'] === 2, 'Pembayaran dan pembatalan harus tersimpan sebagai dua peristiwa.');
    $overdue = db_execute(
        $conn,
        "SELECT COUNT(*) AS total, SUM(p.jumlah) AS amount FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
         WHERE p.idspp = ? AND s.status = 'aktif' AND p.ket IS NULL AND p.jatuhtempo < CURDATE()",
        'i',
        [$paymentId]
    );
    $overdueRow = mysqli_fetch_assoc(mysqli_stmt_get_result($overdue));
    expect_true((int) $overdueRow['total'] === 1 && (float) $overdueRow['amount'] === 98765.43, 'Laporan tunggakan harus menghitung jadwal aktif yang terlambat.');
    db_execute($conn, "UPDATE siswa SET status = 'nonaktif' WHERE id_siswa = ?", 'i', [$studentId]);
    $archivedOverdue = db_execute(
        $conn,
        "SELECT COUNT(*) AS total FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
         WHERE p.idspp = ? AND s.status = 'aktif' AND p.ket IS NULL AND p.jatuhtempo < CURDATE()",
        'i',
        [$paymentId]
    );
    expect_true((int) mysqli_fetch_assoc(mysqli_stmt_get_result($archivedOverdue))['total'] === 0, 'Siswa yang diarsipkan tidak boleh masuk daftar tunggakan aktif.');

    mysqli_rollback($conn);
    echo "Database integrity tests passed; all fixture data rolled back.\n";
} catch (Throwable $error) {
    mysqli_rollback($conn);
    throw $error;
} finally {
    $_SESSION = [];
    session_destroy();
}
