<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = is_string($_POST['school_name'] ?? null) ? trim($_POST['school_name']) : '';
    $address = is_string($_POST['school_address'] ?? null) ? trim($_POST['school_address']) : '';
    $phone = is_string($_POST['school_phone'] ?? null) ? trim($_POST['school_phone']) : '';
    $email = is_string($_POST['school_email'] ?? null) ? trim($_POST['school_email']) : '';
    $removeLogo = isset($_POST['remove_logo']);
    $hasLogo = isset($_FILES['school_logo'])
        && (int) ($_FILES['school_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $errorMessage = '';
    $logo = null;
    $validName = preg_match('/\A.{1,100}\z/us', $name) === 1;
    $validAddress = preg_match('/\A.{0,255}\z/us', $address) === 1;
    $validPhone = preg_match('/\A.{0,30}\z/us', $phone) === 1;
    $validEmail = $email === '' || (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false);

    if (!csrf_validate()) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!$validName || !$validAddress || !$validPhone || !$validEmail) {
        $errorMessage = 'Periksa nama, alamat, telepon, dan email. Panjang tiap kolom harus sesuai.';
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

    if ($errorMessage === '') {
        mysqli_begin_transaction($conn);
        try {
            if ($removeLogo) {
                db_execute(
                    $conn,
                    'UPDATE school_settings
                     SET school_name = ?, school_address = ?, school_phone = ?, school_email = ?,
                         logo_data = NULL, logo_mime = NULL
                     WHERE settings_id = 1',
                    'ssss',
                    [$name, $address, $phone, $email]
                );
            } elseif ($logo !== null) {
                db_execute(
                    $conn,
                    'UPDATE school_settings
                     SET school_name = ?, school_address = ?, school_phone = ?, school_email = ?,
                         logo_data = ?, logo_mime = ?
                     WHERE settings_id = 1',
                    'ssssss',
                    [$name, $address, $phone, $email, $logo['data'], $logo['mime']]
                );
            } else {
                db_execute(
                    $conn,
                    'UPDATE school_settings
                     SET school_name = ?, school_address = ?, school_phone = ?, school_email = ?
                     WHERE settings_id = 1',
                    'ssss',
                    [$name, $address, $phone, $email]
                );
            }
            audit_event($conn, 'school_identity_updated', 'school', 1, 'Identitas sekolah diperbarui.');
            mysqli_commit($conn);
            flash('Identitas sekolah berhasil diperbarui.');
            header('Location: identitassekolah.php');
            exit;
        } catch (mysqli_sql_exception $error) {
            mysqli_rollback($conn);
            error_log('School identity update failed: ' . $error->getMessage());
            $errorMessage = 'Identitas sekolah gagal diperbarui. Periksa log server.';
        }
    }

    flash($errorMessage, 'danger');
    header('Location: identitassekolah.php');
    exit;
}

$settings = fetch_school_settings($conn);
include __DIR__ . '/header.php';
?>
<section class="card school-settings-card">
    <div class="card-header"><h6>Identitas sekolah</h6></div>
    <div class="card-body">
        <p class="form-intro">Informasi ini digunakan pada halaman aplikasi, bukti pembayaran, dan laporan cetak.</p>
        <form method="post" class="school-settings-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="school-logo-editor">
                <img class="school-logo-preview" src="school-logo.php?v=<?= e((string) strtotime($settings['updated_at'])) ?>" alt="Logo sekolah saat ini" data-school-logo-preview>
                <div class="school-logo-controls">
                    <label for="school-logo">Logo sekolah <span class="label-optional">opsional</span></label>
                    <input id="school-logo" class="form-control" type="file" name="school_logo" accept="image/jpeg,image/png,image/webp" data-school-logo-input>
                    <small>JPG, PNG, atau WebP · maksimal 2 MB. SVG tidak diterima agar unggahan tetap aman.</small>
                    <button class="btn btn-secondary btn-sm" type="submit" name="remove_logo" value="1">Gunakan logo Portal SPP</button>
                </div>
            </div>
            <div class="school-settings-grid">
                <div class="form-group school-settings-full">
                    <label for="school-name">Nama sekolah</label>
                    <input id="school-name" class="form-control" type="text" name="school_name" maxlength="100" required value="<?= e($settings['school_name']) ?>">
                </div>
                <div class="form-group school-settings-full">
                    <label for="school-address">Alamat</label>
                    <textarea id="school-address" class="form-control" name="school_address" maxlength="255" rows="3"><?= e($settings['school_address']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="school-phone">Nomor telepon</label>
                    <input id="school-phone" class="form-control" type="tel" name="school_phone" maxlength="30" autocomplete="tel" value="<?= e($settings['school_phone']) ?>">
                </div>
                <div class="form-group">
                    <label for="school-email">Email</label>
                    <input id="school-email" class="form-control" type="email" name="school_email" maxlength="254" autocomplete="email" value="<?= e($settings['school_email']) ?>">
                </div>
            </div>
            <button type="submit" name="save_settings" value="1" class="btn btn-primary">Simpan identitas</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
