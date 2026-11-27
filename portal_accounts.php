<?php
require_once __DIR__ . '/app.php';
include_once __DIR__ . '/koneksi.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
        header('Location: portal_accounts.php');
        exit;
    }

    if (isset($_POST['create_account'])) {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = is_string($_POST['temporary_password'] ?? null) ? $_POST['temporary_password'] : '';
        $type = (string) ($_POST['account_type'] ?? '');
        $studentId = filter_var($_POST['id_siswa'] ?? null, FILTER_VALIDATE_INT);
        $validIdentity = preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/', $username) === 1;
        if ($name === '' || strlen($name) > 100 || !$validIdentity || strlen($password) < 12 || strlen($password) > 72
            || !in_array($type, ['guardian', 'student'], true) || !$studentId) {
            flash('Lengkapi nama, username (3–50 karakter), password sementara (minimal 12 karakter), jenis akun, dan siswa.', 'danger');
        } else {
            $studentStmt = db_execute(
                $conn,
                "SELECT id_siswa FROM siswa WHERE id_siswa = ? AND status = 'aktif' LIMIT 1",
                'i',
                [$studentId]
            );
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($studentStmt))) {
                flash('Siswa tidak ditemukan atau sudah diarsipkan.', 'danger');
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    db_execute(
                        $conn,
                        'INSERT INTO portal_accounts
                         (full_name, username, password_hash, account_type, created_by)
                         VALUES (?, ?, ?, ?, ?)',
                        'ssssi',
                        [$name, $username, $hash, $type, (int) $_SESSION['admin']]
                    );
                    $accountId = (int) mysqli_insert_id($conn);
                    db_execute(
                        $conn,
                        'INSERT INTO portal_account_students (id_portal_account, id_siswa, linked_by)
                         VALUES (?, ?, ?)',
                        'iii',
                        [$accountId, $studentId, (int) $_SESSION['admin']]
                    );
                    audit_event($conn, 'portal_account_created', 'portal_account', $accountId, 'Akun portal dibuat dan ditautkan ke siswa.');
                    mysqli_commit($conn);
                    flash('Akun portal berhasil dibuat. Berikan username dan password sementara kepada penerima secara aman.');
                    header('Location: portal_account_students.php?id=' . $accountId);
                    exit;
                } catch (mysqli_sql_exception $error) {
                    mysqli_rollback($conn);
                    if ($error->getCode() === 1062) {
                        flash('Username portal sudah digunakan.', 'danger');
                    } else {
                        error_log('Portal account creation failed: ' . $error->getMessage());
                        flash('Akun portal gagal dibuat. Periksa log server.', 'danger');
                    }
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $accountId = filter_var($_POST['id_portal_account'] ?? null, FILTER_VALIDATE_INT);
        if (!$accountId) {
            flash('Akun portal tidak valid.', 'danger');
        } else {
            mysqli_begin_transaction($conn);
            try {
                $update = db_execute(
                    $conn,
                    "UPDATE portal_accounts
                     SET status = IF(status = 'active', 'disabled', 'active'),
                         auth_version = auth_version + 1
                     WHERE id_portal_account = ?",
                    'i',
                    [$accountId]
                );
                if (mysqli_stmt_affected_rows($update) !== 1) {
                    mysqli_rollback($conn);
                    flash('Akun portal tidak ditemukan.', 'warning');
                } else {
                    audit_event($conn, 'portal_account_status_changed', 'portal_account', $accountId, 'Status akun portal diperbarui.');
                    mysqli_commit($conn);
                    flash('Status akun portal berhasil diperbarui.');
                }
            } catch (mysqli_sql_exception $error) {
                mysqli_rollback($conn);
                error_log('Portal account status update failed: ' . $error->getMessage());
                flash('Status akun portal gagal diperbarui. Periksa log server.', 'danger');
            }
        }
    }

    header('Location: portal_accounts.php');
    exit;
}

$studentSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (preg_match('/\A.{0,100}\z/us', $studentSearch) !== 1) {
    http_response_code(400);
    exit('Pencarian siswa tidak valid.');
}
$studentRows = db_execute(
    $conn,
    "SELECT s.id_siswa, s.nisn, s.nama, k.nama_kelas
     FROM siswa s JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE s.status = 'aktif' AND (? = '' OR s.nisn LIKE ? OR s.nama LIKE ?)
     ORDER BY s.nama ASC LIMIT 50",
    'sss',
    [$studentSearch, '%' . $studentSearch . '%', '%' . $studentSearch . '%']
);
$students = mysqli_stmt_get_result($studentRows);

$accountsResult = mysqli_query(
    $conn,
    'SELECT id_portal_account, full_name, username, account_type, status, created_at
     FROM portal_accounts ORDER BY created_at DESC, id_portal_account DESC'
);
$accounts = [];
while ($row = mysqli_fetch_assoc($accountsResult)) {
    $row['students'] = [];
    $accounts[(int) $row['id_portal_account']] = $row;
}
$linksResult = mysqli_query(
    $conn,
    'SELECT links.id_portal_account, s.nama, s.nisn, s.status
     FROM portal_account_students links
     JOIN siswa s ON s.id_siswa = links.id_siswa
     ORDER BY s.nama'
);
while ($link = mysqli_fetch_assoc($linksResult)) {
    $accountId = (int) $link['id_portal_account'];
    if (isset($accounts[$accountId])) {
        $accounts[$accountId]['students'][] = $link;
    }
}
include __DIR__ . '/header.php';
?>
<section class="card portal-admin-intro">
    <div class="card-body">
        <h2>Kelola akses orang tua, wali, dan siswa</h2>
        <p>Akun dibuat oleh admin dan ditautkan secara manual. Pengguna hanya dapat melihat tagihan serta riwayat siswa yang ditautkan; portal tidak menerima pembayaran.</p>
        <p class="mb-0"><strong>Privasi:</strong> verifikasi identitas penerima sebelum memberikan kredensial. Password sementara harus dibagikan lewat saluran privat dan wajib diganti saat login pertama.</p>
    </div>
</section>
<section class="card">
    <div class="card-header"><h6>Buat akun portal</h6></div>
    <div class="card-body">
        <form method="get" class="portal-student-search">
            <div class="form-group">
                <label for="portal-student-search">Cari siswa aktif untuk ditautkan</label>
                <input id="portal-student-search" class="form-control" type="search" name="q" value="<?= e($studentSearch) ?>" maxlength="100" placeholder="Cari nama atau NISN">
            </div>
            <button type="submit" class="btn btn-secondary">Cari siswa</button>
        </form>
        <form method="post" class="portal-account-create">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label for="portal-full-name">Nama penerima akun</label><input id="portal-full-name" class="form-control" name="full_name" maxlength="100" required></div>
            <div class="form-group"><label for="portal-username">Username</label><input id="portal-username" class="form-control" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="off" required></div>
            <div class="form-group"><label for="portal-password">Password sementara</label><input id="portal-password" class="form-control" type="password" name="temporary_password" minlength="12" maxlength="72" autocomplete="new-password" required><small>12–72 karakter. Pengguna wajib menggantinya saat login pertama.</small></div>
            <div class="form-group"><label for="portal-type">Jenis akun</label><select id="portal-type" class="form-control" name="account_type" required><option value="guardian">Orang tua/wali</option><option value="student">Siswa</option></select></div>
            <div class="form-group portal-student-option">
                <label for="portal-student-id">Siswa yang ditautkan</label>
                <select id="portal-student-id" class="form-control" name="id_siswa" required>
                    <option value="" selected disabled><?= $studentSearch === '' ? 'Cari nama atau NISN terlebih dahulu' : 'Pilih siswa aktif' ?></option>
                    <?php while ($student = mysqli_fetch_assoc($students)): ?>
                        <option value="<?= (int) $student['id_siswa'] ?>"><?= e($student['nama']) ?> — <?= e($student['nisn']) ?> · <?= e($student['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <small>Wali dapat ditautkan ke beberapa siswa melalui halaman pengelolaan akun berikutnya.</small>
            </div>
            <button class="btn btn-primary" type="submit" name="create_account">Buat akun dan tautkan</button>
        </form>
    </div>
</section>
<section class="card">
    <div class="card-header"><h6>Akun portal terdaftar</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Nama / username</th><th>Jenis</th><th>Siswa tertaut</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $accountRow): ?>
                    <tr>
                        <td><strong><?= e($accountRow['full_name']) ?></strong><small class="d-block"><?= e($accountRow['username']) ?></small></td>
                        <td><?= $accountRow['account_type'] === 'guardian' ? 'Orang tua/wali' : 'Siswa' ?></td>
                        <td><?php foreach ($accountRow['students'] as $index => $linkedStudent): ?><?= $index > 0 ? '<br>' : '' ?><?= e($linkedStudent['nama']) ?> (<?= e($linkedStudent['nisn']) ?>)<?= $linkedStudent['status'] === 'aktif' ? '' : ' — nonaktif' ?><?php endforeach; ?></td>
                        <td><span class="portal-status <?= $accountRow['status'] === 'active' ? 'is-active' : 'is-disabled' ?>"><?= $accountRow['status'] === 'active' ? 'Aktif' : 'Dinonaktifkan' ?></span></td>
                        <td class="portal-admin-actions">
                            <a class="btn btn-secondary btn-sm" href="portal_account_students.php?id=<?= (int) $accountRow['id_portal_account'] ?>">Kelola tautan</a>
                            <form method="post" onsubmit="return confirm('Ubah status akun ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id_portal_account" value="<?= (int) $accountRow['id_portal_account'] ?>">
                                <button class="btn btn-sm <?= $accountRow['status'] === 'active' ? 'btn-danger' : 'btn-primary' ?>" type="submit" name="toggle_status"><?= $accountRow['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$accounts): ?><tr><td colspan="5">Belum ada akun portal.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
