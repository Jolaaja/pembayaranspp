<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);

$identityKeys = [
    'school_name', 'school_address', 'school_phone', 'school_email',
    'school_npsn', 'principal_name', 'school_website', 'logo_data', 'logo_mime',
];
$sameIdentity = static function (array $left, array $right) use ($identityKeys): bool {
    foreach ($identityKeys as $key) {
        if (($left[$key] ?? null) !== ($right[$key] ?? null)) {
            return false;
        }
    }
    return true;
};
$fetchIdentity = static function (bool $forUpdate = false) use ($conn): array {
    $sql = 'SELECT school_name, school_address, school_phone, school_email, school_npsn,
                   principal_name, school_website, logo_data, logo_mime
            FROM school_settings WHERE settings_id = 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = db_execute($conn, $sql);
    $identity = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$identity) {
        throw new RuntimeException('Pengaturan sekolah belum tersedia.');
    }
    return $identity;
};
$updateIdentity = static function (array $identity) use ($conn): void {
    $stmt = db_execute(
        $conn,
        'UPDATE school_settings
         SET school_name = ?, school_address = ?, school_phone = ?, school_email = ?,
             school_npsn = ?, principal_name = ?, school_website = ?, logo_data = ?, logo_mime = ?
         WHERE settings_id = 1',
        'sssssssss',
        [
            $identity['school_name'],
            $identity['school_address'],
            $identity['school_phone'],
            $identity['school_email'],
            $identity['school_npsn'],
            $identity['principal_name'],
            $identity['school_website'],
            $identity['logo_data'],
            $identity['logo_mime'],
        ]
    );
    mysqli_stmt_close($stmt);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        flash('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
        header('Location: identitassekolah.php');
        exit;
    }

    if (isset($_POST['restore_version_id'])) {
        $rawVersionId = is_string($_POST['restore_version_id']) ? $_POST['restore_version_id'] : '';
        $versionId = filter_var($rawVersionId, FILTER_VALIDATE_INT);
        if (!$versionId || $versionId < 1) {
            flash('Versi identitas yang dipilih tidak valid.', 'danger');
            header('Location: identitassekolah.php');
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $currentIdentity = $fetchIdentity(true);
            $historyStmt = db_execute(
                $conn,
                'SELECT school_name, school_address, school_phone, school_email, school_npsn,
                        principal_name, school_website, logo_data, logo_mime
                 FROM school_identity_history WHERE id_version = ? FOR UPDATE',
                'i',
                [$versionId]
            );
            $version = mysqli_fetch_assoc(mysqli_stmt_get_result($historyStmt));
            mysqli_stmt_close($historyStmt);
            if (!$version) {
                throw new RuntimeException('Versi identitas tidak ditemukan atau sudah kedaluwarsa.');
            }

            if ($sameIdentity($currentIdentity, $version)) {
                mysqli_commit($conn);
                flash('Versi tersebut sudah menjadi identitas sekolah saat ini.');
                header('Location: identitassekolah.php');
                exit;
            }

            $updateIdentity($version);
            record_school_identity_version($conn, 'restored', (int) $versionId);
            audit_event(
                $conn,
                'school_identity_restored',
                'school',
                1,
                'Identitas sekolah dipulihkan dari versi #' . $versionId . '.'
            );
            mysqli_commit($conn);
            flash('Versi identitas sekolah berhasil dipulihkan.');
            header('Location: identitassekolah.php');
            exit;
        } catch (mysqli_sql_exception | RuntimeException $error) {
            mysqli_rollback($conn);
            error_log('School identity restore failed: ' . $error->getMessage());
            flash('Identitas sekolah gagal dipulihkan. Periksa log server.', 'danger');
            header('Location: identitassekolah.php');
            exit;
        }
    }

    $name = is_string($_POST['school_name'] ?? null) ? trim($_POST['school_name']) : '';
    $address = is_string($_POST['school_address'] ?? null) ? trim($_POST['school_address']) : '';
    $phone = is_string($_POST['school_phone'] ?? null) ? trim($_POST['school_phone']) : '';
    $email = is_string($_POST['school_email'] ?? null) ? trim($_POST['school_email']) : '';
    $npsn = is_string($_POST['school_npsn'] ?? null) ? trim($_POST['school_npsn']) : '';
    $principalName = is_string($_POST['principal_name'] ?? null) ? trim($_POST['principal_name']) : '';
    $website = is_string($_POST['school_website'] ?? null) ? trim($_POST['school_website']) : '';
    $removeLogo = isset($_POST['remove_logo']);
    $hasLogo = isset($_FILES['school_logo'])
        && (int) ($_FILES['school_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $errorMessage = '';
    $logo = null;
    $validName = preg_match('/\A.{1,100}\z/us', $name) === 1;
    $validAddress = preg_match('/\A.{0,255}\z/us', $address) === 1;
    $validPhone = preg_match('/\A[0-9+().\s-]{0,30}\z/u', $phone) === 1
        && ($phone === '' || preg_match('/[0-9]/', $phone) === 1);
    $validEmail = $email === '' || (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
    $validNpsn = $npsn === '' || preg_match('/\A[0-9]{8}\z/', $npsn) === 1;
    $validPrincipalName = preg_match('/\A.{0,100}\z/us', $principalName) === 1;
    $validWebsite = is_valid_school_website($website);

    if (!$validName || !$validAddress || !$validPhone || !$validEmail
        || !$validNpsn || !$validPrincipalName || !$validWebsite) {
        $errorMessage = 'Periksa format nama, alamat, telepon, email, NPSN (8 digit), kepala sekolah, dan situs web.';
    } elseif ($removeLogo && $hasLogo) {
        $errorMessage = 'Pilih antara mengunggah atau menghapus logo.';
    } elseif ($hasLogo) {
        try {
            $logo = validate_school_logo($_FILES['school_logo']);
        } catch (InvalidArgumentException $error) {
            $errorMessage = $error->getMessage();
        } catch (RuntimeException $error) {
            error_log('School logo processing failed: ' . $error->getMessage());
            $errorMessage = 'Logo gagal diproses. Periksa file dan konfigurasi server.';
        }
    }

    if ($errorMessage !== '') {
        flash($errorMessage, 'danger');
        header('Location: identitassekolah.php');
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $currentIdentity = $fetchIdentity(true);
        $identity = [
            'school_name' => $name,
            'school_address' => $address !== '' ? $address : null,
            'school_phone' => $phone !== '' ? $phone : null,
            'school_email' => $email !== '' ? $email : null,
            'school_npsn' => $npsn !== '' ? $npsn : null,
            'principal_name' => $principalName !== '' ? $principalName : null,
            'school_website' => $website !== '' ? $website : null,
            'logo_data' => $currentIdentity['logo_data'],
            'logo_mime' => $currentIdentity['logo_mime'],
        ];
        if ($removeLogo) {
            $identity['logo_data'] = null;
            $identity['logo_mime'] = null;
        } elseif ($logo !== null) {
            $identity['logo_data'] = $logo['data'];
            $identity['logo_mime'] = $logo['mime'];
        }

        if ($sameIdentity($currentIdentity, $identity)) {
            mysqli_commit($conn);
            flash('Tidak ada perubahan identitas untuk disimpan.');
            header('Location: identitassekolah.php');
            exit;
        }

        $updateIdentity($identity);
        record_school_identity_version($conn);
        audit_event($conn, 'school_identity_updated', 'school', 1, 'Identitas sekolah diperbarui.');
        mysqli_commit($conn);
        flash('Identitas sekolah berhasil diperbarui.');
        header('Location: identitassekolah.php');
        exit;
    } catch (mysqli_sql_exception | RuntimeException $error) {
        mysqli_rollback($conn);
        error_log('School identity update failed: ' . $error->getMessage());
        flash('Identitas sekolah gagal diperbarui. Periksa log server.', 'danger');
        header('Location: identitassekolah.php');
        exit;
    }
}

$settings = fetch_school_settings($conn);
$historyStmt = db_execute(
    $conn,
    'SELECT h.id_version, h.school_name, h.school_address, h.school_phone, h.school_email,
            h.school_npsn, h.principal_name, h.school_website, h.logo_mime, h.change_type,
            h.restored_from, h.changed_at, a.nama_admin
     FROM school_identity_history h
     LEFT JOIN admin a ON a.id_admin = h.changed_by
     ORDER BY h.id_version DESC LIMIT 20'
);
$historyResult = mysqli_stmt_get_result($historyStmt);
$history = [];
while ($row = mysqli_fetch_assoc($historyResult)) {
    $history[] = $row;
}
mysqli_stmt_close($historyStmt);
include __DIR__ . '/header.php';
?>
<section class="card school-settings-card">
    <div class="card-header"><h6>Identitas sekolah</h6></div>
    <div class="card-body">
        <p class="form-intro">Informasi ini digunakan pada halaman masuk, navigasi, bukti pembayaran, dan laporan cetak. Pratinjau di bawah berubah saat Anda mengetik.</p>
        <form method="post" class="school-settings-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="school-logo-editor">
                <img class="school-logo-preview" src="school-logo.php?v=<?= e((string) strtotime($settings['updated_at'])) ?>" alt="Pratinjau logo sekolah" data-school-logo-preview>
                <div class="school-logo-controls">
                    <label for="school-logo">Logo sekolah <span class="label-optional">opsional</span></label>
                    <input id="school-logo" class="form-control" type="file" name="school_logo" accept="image/jpeg,image/png,image/webp" data-school-logo-input>
                    <small>JPG, PNG, atau WebP · maksimal 2 MB, lebar/tinggi maksimal 4.000 px dan total maksimal 8 megapiksel. SVG tidak diterima agar unggahan tetap aman.</small>
                    <small data-school-logo-feedback aria-live="polite">Logo saat ini: <?= $settings['logo_mime'] ? 'logo khusus' : 'logo Portal SPP' ?>.</small>
                    <label class="school-logo-default-option"><input type="checkbox" name="remove_logo" value="1" data-school-remove-logo> Gunakan logo Portal SPP</label>
                </div>
            </div>
            <div class="school-settings-grid">
                <div class="form-group school-settings-full">
                    <label for="school-name">Nama sekolah</label>
                    <input id="school-name" class="form-control" type="text" name="school_name" maxlength="100" required value="<?= e($settings['school_name']) ?>" data-school-preview-input="name">
                </div>
                <div class="form-group school-settings-full">
                    <label for="school-address">Alamat</label>
                    <textarea id="school-address" class="form-control" name="school_address" maxlength="255" rows="3" data-school-preview-input="address"><?= e($settings['school_address']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="school-phone">Nomor telepon</label>
                    <input id="school-phone" class="form-control" type="tel" name="school_phone" maxlength="30" autocomplete="tel" data-school-preview-input="phone" value="<?= e($settings['school_phone']) ?>">
                </div>
                <div class="form-group">
                    <label for="school-email">Email</label>
                    <input id="school-email" class="form-control" type="email" name="school_email" maxlength="254" autocomplete="email" data-school-preview-input="email" value="<?= e($settings['school_email']) ?>">
                </div>
                <div class="form-group">
                    <label for="school-npsn">NPSN <span class="label-optional">opsional</span></label>
                    <input id="school-npsn" class="form-control" type="text" name="school_npsn" maxlength="8" minlength="8" pattern="[0-9]{8}" inputmode="numeric" autocomplete="off" placeholder="8 digit" value="<?= e($settings['school_npsn']) ?>" data-school-preview-input="npsn">
                    <small>Nomor Pokok Sekolah Nasional, tepat 8 digit.</small>
                </div>
                <div class="form-group">
                    <label for="principal-name">Kepala sekolah <span class="label-optional">opsional</span></label>
                    <input id="principal-name" class="form-control" type="text" name="principal_name" maxlength="100" autocomplete="name" value="<?= e($settings['principal_name']) ?>" data-school-preview-input="principal">
                </div>
                <div class="form-group school-settings-full">
                    <label for="school-website">Situs resmi <span class="label-optional">opsional</span></label>
                    <input id="school-website" class="form-control" type="url" name="school_website" maxlength="254" autocomplete="url" placeholder="https://sekolah.sch.id" value="<?= e($settings['school_website']) ?>" data-school-preview-input="website">
                    <small>Gunakan alamat lengkap dengan http:// atau https://.</small>
                </div>
            </div>
            <section class="card school-preview-card">
                <div class="card-header"><h6>Pratinjau identitas</h6><small>Perubahan terlihat langsung; pilih Simpan untuk menerapkannya</small></div>
                <div class="card-body school-preview-grid">
                    <article class="school-preview-panel school-preview-login">
                        <span class="school-preview-label">HALAMAN MASUK</span>
                        <div class="school-preview-lockup">
                            <img src="school-logo.php?v=<?= e((string) strtotime($settings['updated_at'])) ?>" alt="" data-school-logo-preview>
                            <span>
                                <strong data-school-preview-value="name"><?= e($settings['school_name']) ?></strong>
                                <small data-school-preview-value="login-subtitle" data-school-preview-empty="KEUANGAN SEKOLAH"><?= $settings['school_npsn'] ? 'NPSN ' . e($settings['school_npsn']) . ' · ' : '' ?>KEUANGAN SEKOLAH</small>
                            </span>
                        </div>
                    </article>
                    <article class="school-preview-panel school-preview-sidebar">
                        <span class="school-preview-label">NAVIGASI APLIKASI</span>
                        <div class="school-preview-lockup">
                            <img src="school-logo.php?v=<?= e((string) strtotime($settings['updated_at'])) ?>" alt="" data-school-logo-preview>
                            <span>
                                <strong data-school-preview-value="name"><?= e($settings['school_name']) ?></strong>
                                <small data-school-preview-value="npsn" data-school-preview-prefix="NPSN " data-school-preview-empty="PORTAL · KEUANGAN"><?= $settings['school_npsn'] ? 'NPSN ' . e($settings['school_npsn']) : 'PORTAL · KEUANGAN' ?></small>
                            </span>
                        </div>
                    </article>
                    <article class="school-preview-panel school-preview-document">
                        <span class="school-preview-label">LAPORAN CETAK · IDENTITAS JUGA DIPAKAI PADA SLIP</span>
                        <div class="school-preview-document-head">
                            <img src="school-logo.php?v=<?= e((string) strtotime($settings['updated_at'])) ?>" alt="" data-school-logo-preview>
                            <span>
                                <strong data-school-preview-value="name"><?= e($settings['school_name']) ?></strong>
                                <small data-school-preview-value="address" data-school-preview-empty="Alamat belum diisi"><?= e($settings['school_address'] ?: 'Alamat belum diisi') ?></small>
                                <small>NPSN <span data-school-preview-value="document-npsn" data-school-preview-empty="belum diisi"><?= e($settings['school_npsn'] ?: 'belum diisi') ?></span></small>
                            </span>
                        </div>
                        <div class="school-preview-document-contact">
                            <span data-school-preview-value="phone" data-school-preview-empty="Telepon belum diisi"><?= e($settings['school_phone'] ?: 'Telepon belum diisi') ?></span>
                            <span data-school-preview-value="email" data-school-preview-empty="Email belum diisi"><?= e($settings['school_email'] ?: 'Email belum diisi') ?></span>
                            <span data-school-preview-value="document-website" data-school-preview-empty="Situs resmi belum diisi"><?= e($settings['school_website'] ?: 'Situs resmi belum diisi') ?></span>
                        </div>
                        <div class="school-preview-signature">Mengetahui, <strong data-school-preview-value="principal" data-school-preview-empty="Nama kepala sekolah"><?= e($settings['principal_name'] ?: 'Nama kepala sekolah') ?></strong></div>
                    </article>
                </div>
            </section>
            <button type="submit" name="save_settings" value="1" class="btn btn-primary">Simpan identitas</button>
        </form>
    </div>
</section>
<section class="card school-history-card">
    <div class="card-header"><h6>Riwayat identitas</h6><small><?= count($history) ?> dari 20 versi tersimpan</small></div>
    <div class="card-body">
        <p class="form-intro">Versi lama mencakup data kontak dan logo. Pemulihan akan membuat versi baru sehingga perubahan ini juga dapat dibatalkan.</p>
        <?php if (!$history): ?>
            <div class="empty-state">Belum ada riwayat identitas sekolah.</div>
        <?php else: ?>
            <ol class="school-history-list">
                <?php foreach ($history as $index => $version): ?>
                    <?php
                    $isCurrentVersion = $index === 0;
                    $changeLabel = $version['change_type'] === 'initial'
                        ? 'Versi awal'
                        : ($version['change_type'] === 'restored' ? 'Pemulihan versi' : 'Perubahan identitas');
                    $changedBy = $version['nama_admin'] ?: ($version['change_type'] === 'initial' ? 'Migrasi awal' : 'Akun tidak tersedia');
                    ?>
                    <li class="school-history-item">
                        <div class="school-history-main">
                            <strong><?= e($version['school_name']) ?></strong>
                            <?php if ($isCurrentVersion): ?><span class="school-history-current">Saat ini</span><?php endif; ?>
                            <small><?= e($changeLabel) ?> · <?= e(date('d/m/Y H:i', strtotime($version['changed_at']))) ?> · <?= e($changedBy) ?></small>
                            <small><?= e($version['school_address'] ?: 'Alamat belum diisi') ?> · <?= e($version['school_phone'] ?: 'Telepon belum diisi') ?> · <?= e($version['school_email'] ?: 'Email belum diisi') ?> · <?= e($version['school_website'] ?: 'Situs resmi belum diisi') ?></small>
                            <small>NPSN <?= e($version['school_npsn'] ?: '—') ?> · <?= e($version['logo_mime'] ? 'Logo khusus' : 'Logo Portal SPP') ?><?= $version['principal_name'] ? ' · Kepala sekolah: ' . e($version['principal_name']) : '' ?><?= $version['restored_from'] ? ' · Dipulihkan dari versi #' . (int) $version['restored_from'] : '' ?></small>
                        </div>
                        <?php if (!$isCurrentVersion): ?>
                            <form method="post" class="school-history-restore" data-school-restore-form data-school-restore-name="<?= e($version['school_name']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="restore_version_id" value="<?= (int) $version['id_version'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Pulihkan versi</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
