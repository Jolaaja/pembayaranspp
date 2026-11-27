<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Tes hanya dapat dijalankan melalui command line.\n");
}

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../portal.php';
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
expect_true(is_valid_payment_date(date('Y-m-d')), 'Tanggal pembayaran hari ini harus diterima.');
expect_true(is_valid_payment_date(date('Y-m-d', strtotime('-1 day'))), 'Tanggal pembayaran lampau harus diterima.');
expect_true(!is_valid_payment_date(date('Y-m-d', strtotime('+1 day'))), 'Tanggal pembayaran mendatang harus ditolak.');
expect_true(normalize_indonesian_mobile('081234567890') === '+6281234567890', 'Nomor ponsel lokal harus dinormalisasi ke format internasional.');
expect_true(normalize_indonesian_mobile('+6281234567890') === '+6281234567890', 'Nomor ponsel internasional yang valid harus tetap konsisten.');
expect_true(normalize_indonesian_mobile('0215550100') === null, 'Nomor telepon non-ponsel tidak boleh menjadi tautan WhatsApp.');
expect_true(normalize_indonesian_mobile('call 081234567890') === null, 'Teks selain format nomor tidak boleh dinormalisasi sebagai telepon.');
expect_true(is_valid_school_website('') && is_valid_school_website('https://sekolah.sch.id'), 'Situs sekolah kosong atau berprotokol HTTPS harus diterima.');
expect_true(
    !is_valid_school_website('javascript:alert(1)')
        && !is_valid_school_website('ftp://sekolah.sch.id')
        && !is_valid_school_website('https://user:secret@sekolah.sch.id'),
    'Situs sekolah harus menolak skema non-web dan kredensial yang tidak aman.'
);
$testSchedule = build_spp_schedule('2026-03');
expect_true(count($testSchedule) === 36 && $testSchedule[0]['due_date'] === '2026-03-10' && $testSchedule[35]['due_date'] === '2029-02-10', 'Jadwal SPP harus membuat 36 periode dari bulan pilihan dengan jatuh tempo tanggal 10.');
$invalidScheduleRejected = false;
try {
    build_spp_schedule('2026-13');
} catch (InvalidArgumentException $error) {
    $invalidScheduleRejected = true;
}
expect_true($invalidScheduleRejected, 'Bulan pertama jadwal SPP yang tidak valid harus ditolak.');
expect_column_type($conn, 'angkatan', 'biaya', 'decimal');
expect_column_type($conn, 'pembayaran', 'jumlah', 'decimal');
expect_column_type($conn, 'pembayaran', 'jatuhtempo', 'date');
expect_column_type($conn, 'pembayaran', 'tglbayar', 'date');
expect_column_type($conn, 'admin', 'gender', 'enum');
expect_column_type($conn, 'admin', 'profile_photo', 'mediumblob');
expect_column_type($conn, 'school_settings', 'logo_data', 'mediumblob');
expect_column_type($conn, 'school_settings', 'school_npsn', 'varchar');
expect_column_type($conn, 'school_settings', 'principal_name', 'varchar');
expect_column_type($conn, 'school_settings', 'school_website', 'varchar');
expect_column_type($conn, 'school_identity_history', 'logo_data', 'mediumblob');
expect_column_type($conn, 'school_identity_history', 'change_type', 'enum');
expect_column_type($conn, 'siswa', 'guardian_name', 'varchar');
expect_column_type($conn, 'siswa', 'guardian_phone', 'varchar');
expect_column_type($conn, 'pembayaran_events', 'payment_method', 'enum');
expect_column_type($conn, 'pembayaran_events', 'payment_reference', 'varchar');
expect_column_type($conn, 'portal_accounts', 'username', 'varchar');
expect_column_type($conn, 'portal_accounts', 'password_hash', 'varchar');
expect_column_type($conn, 'portal_accounts', 'auth_version', 'int');
expect_column_type($conn, 'portal_account_students', 'id_portal_account', 'bigint');
expect_column_type($conn, 'portal_account_students', 'id_siswa', 'int');
$schoolSettings = fetch_school_settings($conn);
expect_true(trim($schoolSettings['school_name']) !== '', 'Pengaturan sekolah harus memiliki nama yang dapat digunakan.');

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

    $currentSchoolIdentity = fetch_school_settings($conn);
    $schoolVersionId = record_school_identity_version($conn);
    $schoolVersionStmt = db_execute(
        $conn,
        'SELECT school_name, school_npsn, principal_name, school_website, logo_data, logo_mime
         FROM school_identity_history WHERE id_version = ?',
        'i',
        [$schoolVersionId]
    );
    $schoolVersion = mysqli_fetch_assoc(mysqli_stmt_get_result($schoolVersionStmt));
    $currentLogoStmt = db_execute($conn, 'SELECT logo_data, logo_mime FROM school_settings WHERE settings_id = 1');
    $currentLogo = mysqli_fetch_assoc(mysqli_stmt_get_result($currentLogoStmt));
    expect_true(
        $schoolVersion['school_name'] === $currentSchoolIdentity['school_name']
            && $schoolVersion['school_npsn'] === $currentSchoolIdentity['school_npsn']
            && $schoolVersion['principal_name'] === $currentSchoolIdentity['principal_name']
            && $schoolVersion['school_website'] === $currentSchoolIdentity['school_website']
            && $schoolVersion['logo_data'] === $currentLogo['logo_data']
            && $schoolVersion['logo_mime'] === $currentLogo['logo_mime'],
        'Versi identitas harus menyimpan seluruh detail sekolah untuk pemulihan.'
    );
    $originalNpsn = $currentSchoolIdentity['school_npsn'];
    $originalPrincipal = $currentSchoolIdentity['principal_name'];
    $originalWebsite = $currentSchoolIdentity['school_website'];
    db_execute(
        $conn,
        'UPDATE school_settings SET school_npsn = ?, principal_name = ?, school_website = ? WHERE settings_id = 1',
        'sss',
        ['87654321', 'Kepala Sekolah Uji', 'https://history-test.example']
    );
    $changedSchoolVersionId = record_school_identity_version($conn);
    $changedSchoolVersionStmt = db_execute(
        $conn,
        'SELECT school_npsn, principal_name, school_website
         FROM school_identity_history WHERE id_version = ?',
        'i',
        [$changedSchoolVersionId]
    );
    $changedSchoolVersion = mysqli_fetch_assoc(mysqli_stmt_get_result($changedSchoolVersionStmt));
    expect_true(
        $changedSchoolVersion['school_npsn'] === '87654321'
            && $changedSchoolVersion['principal_name'] === 'Kepala Sekolah Uji'
            && $changedSchoolVersion['school_website'] === 'https://history-test.example',
        'Perubahan NPSN, kepala sekolah, dan situs harus masuk ke versi identitas.'
    );
    db_execute(
        $conn,
        'UPDATE school_settings SET school_npsn = ?, principal_name = ?, school_website = ? WHERE settings_id = 1',
        'sss',
        [$originalNpsn, $originalPrincipal, $originalWebsite]
    );
    $restoredSchoolVersionId = record_school_identity_version($conn, 'restored', $changedSchoolVersionId);
    $restoredSchoolVersionStmt = db_execute(
        $conn,
        'SELECT school_npsn, principal_name, school_website, restored_from
         FROM school_identity_history WHERE id_version = ?',
        'i',
        [$restoredSchoolVersionId]
    );
    $restoredSchoolVersion = mysqli_fetch_assoc(mysqli_stmt_get_result($restoredSchoolVersionStmt));
    expect_true(
        $restoredSchoolVersion['school_npsn'] === $originalNpsn
            && $restoredSchoolVersion['principal_name'] === $originalPrincipal
            && $restoredSchoolVersion['school_website'] === $originalWebsite
            && (int) $restoredSchoolVersion['restored_from'] === $changedSchoolVersionId,
        'Pemulihan harus menyimpan kembali data sekolah dan mencatat versi sumber.'
    );
    for ($historyIndex = 0; $historyIndex < 21; $historyIndex++) {
        db_execute(
            $conn,
            "INSERT INTO school_identity_history (school_name, change_type) VALUES (?, 'updated')",
            's',
            ['__history_limit_' . $historyIndex]
        );
    }
    $latestSchoolVersionId = record_school_identity_version($conn);
    $historyCount = db_execute($conn, 'SELECT COUNT(*) AS total FROM school_identity_history');
    expect_true(
        (int) mysqli_fetch_assoc(mysqli_stmt_get_result($historyCount))['total'] === 20
            && $latestSchoolVersionId > 0,
        'Riwayat identitas harus mempertahankan maksimal 20 versi terbaru.'
    );

    db_execute($conn, 'INSERT INTO kelas (nama_kelas) VALUES (?)', 's', ['__integration_test_class']);
    $classId = mysqli_insert_id($conn);
    db_execute($conn, 'INSERT INTO jurusan (nama_jurusan) VALUES (?)', 's', ['__integration_test_major']);
    $majorId = mysqli_insert_id($conn);
    db_execute($conn, 'INSERT INTO angkatan (nama_angkatan, biaya) VALUES (?, ?)', 'ss', ['__integration_test_cohort', '98765.43']);
    $cohortId = mysqli_insert_id($conn);
    $nisn = 'T' . bin2hex(random_bytes(8));
    db_execute(
        $conn,
        'INSERT INTO siswa (nisn, nama, id_angkatan, id_jurusan, id_kelas, alamat, guardian_name, guardian_phone)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'ssiiisss',
        [$nisn, 'Siswa Uji', $cohortId, $majorId, $classId, 'Data uji sementara', 'Wali Uji', '+6281234567890']
    );
    $studentId = mysqli_insert_id($conn);
    $secondStudentNisn = 'T' . bin2hex(random_bytes(8));
    db_execute(
        $conn,
        'INSERT INTO siswa (nisn, nama, id_angkatan, id_jurusan, id_kelas, alamat, guardian_name, guardian_phone)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'ssiiisss',
        [$secondStudentNisn, 'Siswa Uji Kedua', $cohortId, $majorId, $classId, 'Data uji sementara', null, null]
    );
    $secondStudentId = mysqli_insert_id($conn);
    $portalPasswordHash = password_hash('Portal-Test-Password-2026!', PASSWORD_DEFAULT);
    db_execute(
        $conn,
        "INSERT INTO portal_accounts (full_name, username, password_hash, account_type, created_by, must_change_password)
         VALUES (?, ?, ?, 'guardian', ?, 0)",
        'sssi',
        ['Wali Portal Uji', 'portal-test-' . bin2hex(random_bytes(5)), $portalPasswordHash, $adminId]
    );
    $firstPortalAccountId = (int) mysqli_insert_id($conn);
    db_execute(
        $conn,
        "INSERT INTO portal_accounts (full_name, username, password_hash, account_type, created_by, must_change_password)
         VALUES (?, ?, ?, 'guardian', ?, 0)",
        'sssi',
        ['Wali Portal Kedua', 'portal-test-' . bin2hex(random_bytes(5)), $portalPasswordHash, $adminId]
    );
    $secondPortalAccountId = (int) mysqli_insert_id($conn);
    db_execute(
        $conn,
        'INSERT INTO portal_account_students (id_portal_account, id_siswa, linked_by) VALUES (?, ?, ?)',
        'iii',
        [$firstPortalAccountId, $studentId, $adminId]
    );
    db_execute(
        $conn,
        'INSERT INTO portal_account_students (id_portal_account, id_siswa, linked_by) VALUES (?, ?, ?)',
        'iii',
        [$secondPortalAccountId, $secondStudentId, $adminId]
    );
    $firstPortalStudents = portal_fetch_students($conn, $firstPortalAccountId);
    expect_true(
        count($firstPortalStudents) === 1 && (int) $firstPortalStudents[0]['id_siswa'] === $studentId,
        'Akun portal hanya boleh menampilkan siswa yang ditautkan secara eksplisit.'
    );
    $paymentNameMatches = search_payment_students($conn, 'Siswa Uji');
    expect_true(count($paymentNameMatches) === 2, 'Pencarian pembayaran harus menemukan nama siswa yang cocok.');
    $paymentNisnMatches = search_payment_students($conn, $nisn);
    expect_true(count($paymentNisnMatches) === 1 && $paymentNisnMatches[0]['nisn'] === $nisn, 'Pencarian pembayaran harus menerima NISN.');
    $directoryPage = fetch_student_page($conn, 'Siswa Uji', 'nama', 'asc', 1, 1);
    $firstDirectoryStudent = mysqli_fetch_assoc($directoryPage['rows']);
    expect_true($directoryPage['total'] === 2 && $directoryPage['page'] === 1 && $firstDirectoryStudent['nama'] === 'Siswa Uji', 'Direktori siswa harus memfilter dan mengurutkan hasil melalui database.');
    $secondDirectoryPage = fetch_student_page($conn, 'Siswa Uji', 'nama', 'asc', 2, 1);
    $secondDirectoryStudent = mysqli_fetch_assoc($secondDirectoryPage['rows']);
    expect_true($secondDirectoryPage['total'] === 2 && $secondDirectoryStudent['nama'] === 'Siswa Uji Kedua', 'Pagination direktori harus mengembalikan halaman berikutnya dan total hasil yang benar.');
    $managementDirectoryPage = fetch_student_page($conn, 'Siswa Uji', 'nama', 'asc', 999, 1, true);
    $managementDirectoryStudent = mysqli_fetch_assoc($managementDirectoryPage['rows']);
    expect_true($managementDirectoryPage['page'] === 2 && isset($managementDirectoryStudent['alamat']), 'Halaman di luar jangkauan harus dibatasi dan data alamat hanya disertakan pada daftar pengelolaan.');
    $reverseDirectoryPage = fetch_student_page($conn, 'Siswa Uji', 'nama', 'desc', 1, 1);
    expect_true(mysqli_fetch_assoc($reverseDirectoryPage['rows'])['nama'] === 'Siswa Uji Kedua', 'Pengurutan direktori harus mengikuti arah yang dipilih.');
    $invalidDirectorySortRejected = false;
    try {
        fetch_student_page($conn, '', 'alamat', 'asc', 1, 25);
    } catch (InvalidArgumentException $error) {
        $invalidDirectorySortRejected = true;
    }
    expect_true($invalidDirectorySortRejected, 'Direktori harus menolak kolom pengurutan yang tidak diizinkan.');
    $dueDate = date('Y-m-d', strtotime('-1 day'));
    db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [$studentId, $dueDate, 'Januari 2026', '98765.43']);
    $paymentId = mysqli_insert_id($conn);
    db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [$secondStudentId, $dueDate, 'Januari 2026', '12345.00']);
    $secondStudentPaymentId = mysqli_insert_id($conn);
    $firstPortalPayments = portal_fetch_student_payments($conn, $firstPortalAccountId, $studentId);
    $unauthorizedPortalPayments = portal_fetch_student_payments($conn, $firstPortalAccountId, $secondStudentId);
    expect_true(
        count($firstPortalPayments) === 1
            && (int) $firstPortalPayments[0]['idspp'] === $paymentId
            && $unauthorizedPortalPayments === [],
        'Akun portal tidak boleh membaca jadwal siswa yang tidak ditautkan, meskipun ID siswa diketahui.'
    );
    $secondPortalPayments = portal_fetch_student_payments($conn, $secondPortalAccountId, $secondStudentId);
    expect_true(
        count($secondPortalPayments) === 1
            && (int) $secondPortalPayments[0]['idspp'] === $secondStudentPaymentId,
        'Akun portal tertaut harus dapat membaca jadwal milik siswanya.'
    );
    $_SESSION['portal_account_id'] = $firstPortalAccountId;
    $_SESSION['portal_auth_version'] = 1;
    $_SESSION['portal_last_activity'] = time();
    $_SERVER['PHP_SELF'] = 'portal_dashboard.php';
    ob_start();
    include __DIR__ . '/../portal_dashboard.php';
    $portalDashboardHtml = ob_get_clean();
    expect_true(
        strpos($portalDashboardHtml, 'Tagihan &amp; riwayat pembayaran') !== false
            && strpos($portalDashboardHtml, 'Siswa Uji') !== false
            && strpos($portalDashboardHtml, 'Siswa Uji Kedua') === false,
        'Dashboard portal harus merender data siswa tertaut tanpa menampilkan siswa lain.'
    );
    unset($_SESSION['portal_account_id'], $_SESSION['portal_auth_version'], $_SESSION['portal_last_activity']);
    $overdueReminders = fetch_payment_reminders($conn, 'overdue', $nisn, 7, 1);
    expect_true(
        $overdueReminders['total'] === 1
            && (int) $overdueReminders['rows'][0]['idspp'] === $paymentId
            && (float) $overdueReminders['amount'] === 98765.43
            && $overdueReminders['rows'][0]['guardian_phone'] === '+6281234567890',
        'Daftar pengingat harus menyaring tunggakan siswa dan menghitung nominalnya.'
    );
    $upcomingDueDate = date('Y-m-d', strtotime('+5 days'));
    db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [$studentId, $upcomingDueDate, 'Februari 2026', '98765.43']);
    $upcomingPaymentId = mysqli_insert_id($conn);
    $upcomingReminders = fetch_payment_reminders($conn, 'upcoming', $nisn, 7, 1);
    expect_true(
        $upcomingReminders['total'] === 1
            && (int) $upcomingReminders['rows'][0]['idspp'] === $upcomingPaymentId,
        'Pengingat jatuh tempo harus memasukkan jadwal dalam rentang yang dipilih.'
    );
    $allReminders = fetch_payment_reminders($conn, 'all', $nisn, 7, 2, 1);
    expect_true($allReminders['total'] === 2 && $allReminders['page'] === 2 && count($allReminders['rows']) === 1, 'Pengingat harus membatasi halaman tanpa kehilangan total hasil.');
    $invalidReminderFiltersRejected = false;
    try {
        parse_payment_reminder_filters(['jenis' => 'unknown', 'hari' => '999', 'page' => '1']);
    } catch (InvalidArgumentException $error) {
        $invalidReminderFiltersRejected = true;
    }
    expect_true($invalidReminderFiltersRejected, 'Filter pengingat di luar batas harus ditolak.');

    $foreignKeyRejected = false;
    try {
        db_execute($conn, 'INSERT INTO pembayaran (id_siswa, jatuhtempo, bulan, jumlah) VALUES (?, ?, ?, ?)', 'isss', [2147483647, $dueDate, 'Invalid', '100.00']);
    } catch (mysqli_sql_exception $error) {
        $foreignKeyRejected = true;
    }
    expect_true($foreignKeyRejected, 'Foreign key harus menolak pembayaran tanpa siswa.');

    $paymentDate = date('Y-m-d');
    db_execute($conn, "UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = 'LUNAS', id_admin = ? WHERE idspp = ?", 'ssii', ['TEST-' . $paymentId, $paymentDate, $adminId, $paymentId]);
    db_execute(
        $conn,
        "INSERT INTO pembayaran_events
            (idspp, id_admin, event_type, payment_method, reference_no, payment_reference, amount, payment_date, details)
         VALUES (?, ?, 'paid', ?, ?, ?, ?, ?, ?)",
        'iisssdss',
        [$paymentId, $adminId, 'bank_transfer', 'TEST-' . $paymentId, 'REF-TEST-123', '98765.43', $paymentDate, 'Pembayaran uji']
    );
    $paymentMetadata = db_execute(
        $conn,
        'SELECT payment_method, payment_reference FROM pembayaran_events WHERE idspp = ? AND event_type = ?',
        'is',
        [$paymentId, 'paid']
    );
    $metadataRow = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentMetadata));
    expect_true($metadataRow['payment_method'] === 'bank_transfer' && $metadataRow['payment_reference'] === 'REF-TEST-123', 'Metode dan referensi pembayaran harus tersimpan pada riwayat transaksi.');
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
    db_execute(
        $conn,
        "INSERT INTO pembayaran_events
            (idspp, id_admin, event_type, payment_method, reference_no, payment_reference, amount, payment_date, details)
         VALUES (?, ?, 'reversed', ?, ?, ?, ?, ?, ?)",
        'iisssdss',
        [$paymentId, $adminId, 'bank_transfer', 'TEST-' . $paymentId, 'REF-TEST-123', '98765.43', $paymentDate, 'Pembatalan uji']
    );
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
    expect_true(
        portal_fetch_students($conn, $firstPortalAccountId) === []
            && portal_fetch_student_payments($conn, $firstPortalAccountId, $studentId) === [],
        'Akun portal tidak boleh melihat jadwal siswa setelah siswa diarsipkan.'
    );
    expect_true(search_payment_students($conn, $nisn) === [], 'Pencarian pembayaran tidak boleh menampilkan siswa yang diarsipkan.');

    mysqli_rollback($conn);
    echo "Database integrity tests passed; all fixture data rolled back.\n";
} catch (Throwable $error) {
    mysqli_rollback($conn);
    throw $error;
} finally {
    $_SESSION = [];
    session_destroy();
}
