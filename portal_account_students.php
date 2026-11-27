<?php
require_once __DIR__ . '/app.php';
include_once __DIR__ . '/koneksi.php';
require_role(['admin']);

$accountId = filter_var($_GET['id'] ?? $_POST['id_portal_account'] ?? null, FILTER_VALIDATE_INT);
if (!$accountId || $accountId < 1) {
    http_response_code(400);
    exit('Akun portal tidak valid.');
}
$accountStmt = db_execute(
    $conn,
    'SELECT id_portal_account, full_name, username, account_type, status
     FROM portal_accounts WHERE id_portal_account = ? LIMIT 1',
    'i',
    [$accountId]
);
$portalAccount = mysqli_fetch_assoc(mysqli_stmt_get_result($accountStmt));
if (!$portalAccount) {
    http_response_code(404);
    exit('Akun portal tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
    } elseif (isset($_POST['link_student'])) {
        $studentId = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        if (!$studentId) {
            flash('Akun siswa hanya boleh ditautkan ke satu siswa.', 'danger');
        } else {
            mysqli_begin_transaction($conn);
            try {
                $lockedAccount = db_execute(
                    $conn,
                    'SELECT account_type FROM portal_accounts WHERE id_portal_account = ? FOR UPDATE',
                    'i',
                    [$accountId]
                );
                $lockedRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lockedAccount));
                $countStmt = db_execute(
                    $conn,
                    'SELECT COUNT(*) AS total FROM portal_account_students WHERE id_portal_account = ?',
                    'i',
                    [$accountId]
                );
                $linkedCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
                if (!$lockedRow || ($lockedRow['account_type'] === 'student' && $linkedCount > 0)) {
                    mysqli_rollback($conn);
                    flash('Akun siswa hanya boleh ditautkan ke satu siswa.', 'danger');
                } else {
                    $activeStudent = db_execute(
                        $conn,
                        "SELECT id_siswa FROM siswa WHERE id_siswa = ? AND status = 'aktif' LIMIT 1",
                        'i',
                        [$studentId]
                    );
                    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($activeStudent))) {
                        mysqli_rollback($conn);
                        flash('Siswa tidak ditemukan atau sudah diarsipkan.', 'danger');
                    } else {
                        db_execute(
                            $conn,
                            'INSERT INTO portal_account_students (id_portal_account, id_siswa, linked_by) VALUES (?, ?, ?)',
                            'iii',
                            [$accountId, $studentId, (int) $_SESSION['admin']]
                        );
                        audit_event($conn, 'portal_student_linked', 'portal_account', $accountId, 'Siswa ditautkan ke akun portal.');
                        db_execute(
                            $conn,
                            'UPDATE portal_accounts SET auth_version = auth_version + 1 WHERE id_portal_account = ?',
                            'i',
                            [$accountId]
                        );
                        mysqli_commit($conn);
                        flash('Siswa berhasil ditautkan.');
                    }
                }
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                if ($error->getCode() === 1062) {
                    flash('Siswa tersebut sudah ditautkan ke akun ini.', 'warning');
                } else {
                    throw $error;
                }
            }
        }
    } elseif (isset($_POST['unlink_student'])) {
        $studentId = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        if ($portalAccount['account_type'] === 'student' || !$studentId) {
            flash('Akun harus tetap memiliki siswa tertaut; akun siswa hanya boleh memiliki satu siswa.', 'danger');
        } else {
            mysqli_begin_transaction($conn);
            try {
                db_execute(
                    $conn,
                    'SELECT id_portal_account FROM portal_accounts WHERE id_portal_account = ? FOR UPDATE',
                    'i',
                    [$accountId]
                );
                $countStmt = db_execute(
                    $conn,
                    'SELECT COUNT(*) AS total FROM portal_account_students WHERE id_portal_account = ?',
                    'i',
                    [$accountId]
                );
                $linkedCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
                if ($linkedCount <= 1) {
                    mysqli_rollback($conn);
                    flash('Akun orang tua/wali harus tetap memiliki setidaknya satu siswa tertaut.', 'danger');
                } else {
                    $unlink = db_execute(
                        $conn,
                        'DELETE FROM portal_account_students WHERE id_portal_account = ? AND id_siswa = ?',
                        'ii',
                        [$accountId, $studentId]
                    );
                    if (mysqli_stmt_affected_rows($unlink) !== 1) {
                        mysqli_rollback($conn);
                        flash('Tautan siswa tidak ditemukan.', 'warning');
                    } else {
                        audit_event($conn, 'portal_student_unlinked', 'portal_account', $accountId, 'Tautan siswa dihapus dari akun portal.');
                        db_execute(
                            $conn,
                            'UPDATE portal_accounts SET auth_version = auth_version + 1 WHERE id_portal_account = ?',
                            'i',
                            [$accountId]
                        );
                        mysqli_commit($conn);
                        flash('Tautan siswa dihapus dan sesi pengguna dicabut.');
                    }
                }
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                throw $error;
            }
        }
    } elseif (isset($_POST['replace_student'])) {
        $studentId = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        if (!$studentId || $portalAccount['account_type'] !== 'student') {
            flash('Penggantian tautan hanya berlaku untuk akun siswa.', 'danger');
        } else {
            mysqli_begin_transaction($conn);
            try {
                $lockedAccount = db_execute(
                    $conn,
                    'SELECT account_type FROM portal_accounts WHERE id_portal_account = ? FOR UPDATE',
                    'i',
                    [$accountId]
                );
                $lockedRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lockedAccount));
                $countStmt = db_execute(
                    $conn,
                    'SELECT COUNT(*) AS total FROM portal_account_students WHERE id_portal_account = ?',
                    'i',
                    [$accountId]
                );
                $linkedCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
                $activeStudent = db_execute(
                    $conn,
                    "SELECT id_siswa FROM siswa WHERE id_siswa = ? AND status = 'aktif' LIMIT 1",
                    'i',
                    [$studentId]
                );
                if (!$lockedRow || $lockedRow['account_type'] !== 'student' || $linkedCount > 1
                    || !mysqli_fetch_assoc(mysqli_stmt_get_result($activeStudent))) {
                    mysqli_rollback($conn);
                    flash('Akun siswa atau pilihan siswa tidak valid.', 'danger');
                } else {
                    if ($linkedCount === 0) {
                        db_execute(
                            $conn,
                            'INSERT INTO portal_account_students (id_portal_account, id_siswa, linked_by) VALUES (?, ?, ?)',
                            'iii',
                            [$accountId, $studentId, (int) $_SESSION['admin']]
                        );
                    } else {
                        db_execute(
                            $conn,
                            'UPDATE portal_account_students
                             SET id_siswa = ?, linked_by = ?, linked_at = CURRENT_TIMESTAMP
                             WHERE id_portal_account = ?',
                            'iii',
                            [$studentId, (int) $_SESSION['admin'], $accountId]
                        );
                    }
                    db_execute(
                        $conn,
                        'UPDATE portal_accounts SET auth_version = auth_version + 1 WHERE id_portal_account = ?',
                        'i',
                        [$accountId]
                    );
                    audit_event($conn, 'portal_student_relinked', 'portal_account', $accountId, 'Siswa pada akun portal diperbarui; sesi pengguna dicabut.');
                    mysqli_commit($conn);
                    flash('Tautan siswa diperbarui dan sesi pengguna dicabut. Pengguna perlu masuk kembali.');
                }
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                if ($error->getCode() === 1062) {
                    flash('Siswa tersebut sudah ditautkan ke akun ini.', 'warning');
                } else {
                    throw $error;
                }
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $temporaryPassword = is_string($_POST['temporary_password'] ?? null) ? $_POST['temporary_password'] : '';
        if (strlen($temporaryPassword) < 12 || strlen($temporaryPassword) > 72) {
            flash('Password sementara harus terdiri dari 12 hingga 72 karakter.', 'danger');
        } else {
            $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            mysqli_begin_transaction($conn);
            try {
                db_execute(
                    $conn,
                    'UPDATE portal_accounts
                     SET password_hash = ?, must_change_password = 1, auth_version = auth_version + 1
                     WHERE id_portal_account = ?',
                    'si',
                    [$hash, $accountId]
                );
                audit_event($conn, 'portal_password_reset', 'portal_account', $accountId, 'Password portal diatur ulang oleh admin.');
                mysqli_commit($conn);
                flash('Password diatur ulang. Berikan password sementara secara aman; sesi aktif pengguna sudah dicabut.');
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                error_log('Portal password reset failed: ' . $error->getMessage());
                flash('Password gagal diatur ulang. Periksa log server.', 'danger');
            }
        }
    }
    header('Location: portal_account_students.php?id=' . $accountId);
    exit;
}

$linkedStmt = db_execute(
    $conn,
    'SELECT s.id_siswa, s.nisn, s.nama, s.status, k.nama_kelas
     FROM portal_account_students links
     JOIN siswa s ON s.id_siswa = links.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE links.id_portal_account = ?
     ORDER BY s.nama',
    'i',
    [$accountId]
);
$linkedStudents = mysqli_stmt_get_result($linkedStmt);
$linkedCountStmt = db_execute(
    $conn,
    'SELECT COUNT(*) AS total FROM portal_account_students WHERE id_portal_account = ?',
    'i',
    [$accountId]
);
$linkedCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($linkedCountStmt))['total'];
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (preg_match('/\A.{0,100}\z/us', $search) !== 1) {
    http_response_code(400);
    exit('Pencarian siswa tidak valid.');
}
$availableStmt = db_execute(
    $conn,
    "SELECT s.id_siswa, s.nisn, s.nama, k.nama_kelas
     FROM siswa s JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE s.status = 'aktif'
       AND (? = '' OR s.nisn LIKE ? OR s.nama LIKE ?)
       AND NOT EXISTS (
           SELECT 1 FROM portal_account_students links
           WHERE links.id_portal_account = ? AND links.id_siswa = s.id_siswa
       )
     ORDER BY s.nama LIMIT 30",
    'sssi',
    [$search, '%' . $search . '%', '%' . $search . '%', $accountId]
);
$availableStudents = mysqli_stmt_get_result($availableStmt);
include __DIR__ . '/header.php';
?>
<section class="card portal-admin-intro">
    <div class="card-body">
        <h2><?= e($portalAccount['full_name']) ?> <small>(<?= e($portalAccount['username']) ?>)</small></h2>
        <p><?= $portalAccount['account_type'] === 'guardian' ? 'Akun orang tua/wali dapat melihat semua siswa yang ditautkan.' : 'Akun siswa hanya boleh melihat satu data siswa miliknya.' ?></p>
    </div>
</section>
<section class="card">
    <div class="card-header"><h6>Siswa yang ditautkan</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table"><thead><tr><th>Siswa</th><th>NISN</th><th>Kelas</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
            <?php while ($student = mysqli_fetch_assoc($linkedStudents)): ?>
                <tr>
                    <td><?= e($student['nama']) ?></td><td><?= e($student['nisn']) ?></td><td><?= e($student['nama_kelas']) ?></td>
                    <td><?= $student['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?></td>
                    <td><?php if ($portalAccount['account_type'] === 'guardian' && $linkedCount > 1): ?><form method="post" onsubmit="return confirm('Hapus tautan siswa dari akun ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_portal_account" value="<?= $accountId ?>"><input type="hidden" name="id_siswa" value="<?= (int) $student['id_siswa'] ?>"><button class="btn btn-danger btn-sm" name="unlink_student">Lepas tautan</button></form><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endwhile; if ($linkedCount === 0): ?><tr><td colspan="5">Belum ada siswa tertaut.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div>
</section>
<?php if (in_array($portalAccount['account_type'], ['guardian', 'student'], true)): ?>
<section class="card">
    <div class="card-header"><h6><?= $portalAccount['account_type'] === 'guardian' ? 'Tautkan siswa tambahan' : 'Ganti tautan siswa' ?></h6></div>
    <div class="card-body">
        <form method="get" class="portal-student-search"><input type="hidden" name="id" value="<?= $accountId ?>"><div class="form-group"><label for="link-search">Cari nama atau NISN</label><input id="link-search" class="form-control" name="q" value="<?= e($search) ?>" maxlength="100"></div><button class="btn btn-secondary" type="submit">Cari</button></form>
        <div class="portal-link-results">
            <?php while ($student = mysqli_fetch_assoc($availableStudents)): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_portal_account" value="<?= $accountId ?>"><input type="hidden" name="id_siswa" value="<?= (int) $student['id_siswa'] ?>">
                    <span><?= e($student['nama']) ?> — <?= e($student['nisn']) ?> · <?= e($student['nama_kelas']) ?></span><button class="btn btn-primary btn-sm" type="submit" name="<?= $portalAccount['account_type'] === 'guardian' ? 'link_student' : 'replace_student' ?>"><?= $portalAccount['account_type'] === 'guardian' ? 'Tautkan' : 'Ganti siswa' ?></button>
                </form>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<section class="card">
    <div class="card-header"><h6>Atur ulang password</h6></div>
    <div class="card-body">
        <p>Pengguna akan diminta membuat password pribadi saat login berikutnya. Password sementara harus disampaikan secara privat.</p>
        <form method="post" class="portal-reset-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_portal_account" value="<?= $accountId ?>">
            <div class="form-group"><label for="reset-password">Password sementara baru</label><input id="reset-password" class="form-control" type="password" name="temporary_password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
            <button class="btn btn-secondary" type="submit" name="reset_password">Atur ulang password</button>
        </form>
    </div>
</section>
<p><a class="btn btn-secondary" href="portal_accounts.php">Kembali ke akun portal</a></p>
<?php include __DIR__ . '/footer.php'; ?>
