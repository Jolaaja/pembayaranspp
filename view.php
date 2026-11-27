<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';
require_role(['admin']);

$entities = [
    'id_siswa' => ["SELECT * FROM siswa WHERE id_siswa = ? AND status = 'aktif'", 'editdatasiswa.php'],
    'id_kelas' => ['SELECT * FROM kelas WHERE id_kelas = ?', 'editdatakelas.php'],
    'id_jurusan' => ['SELECT * FROM jurusan WHERE id_jurusan = ?', 'editdatajurusan.php'],
    'id_angkatan' => ['SELECT * FROM angkatan WHERE id_angkatan = ?', 'editdataangkatan.php'],
];
$entity = null;
$entityKey = null;
$id = null;
foreach ($entities as $key => $definition) {
    if (isset($_POST[$key])) {
        $entityKey = $key;
        $entity = $definition;
        $id = filter_var($_POST[$key], FILTER_VALIDATE_INT);
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$entity || !$id) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$stmt = mysqli_prepare($conn, $entity[0]);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$record) {
    http_response_code(404);
    exit('Data tidak ditemukan.');
}
$csrfInput = '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';

if ($entityKey === 'id_siswa') {
    $selects = [
        'id_angkatan' => ['angkatan', 'nama_angkatan', 'id_angkatan'],
        'id_kelas' => ['kelas', 'nama_kelas', 'id_kelas'],
        'id_jurusan' => ['jurusan', 'nama_jurusan', 'id_jurusan'],
    ];
    ?>
    <form action="<?= e($entity[1]) ?>" method="post" class="entity-form">
        <?= $csrfInput ?>
        <input type="hidden" name="id_siswa" value="<?= (int) $record['id_siswa'] ?>">
        <div class="form-group"><label>NISN</label><input class="form-control" value="<?= e($record['nisn']) ?>" disabled></div>
        <div class="form-group"><label for="student-name">Nama siswa</label><input id="student-name" class="form-control" type="text" name="nama" maxlength="50" value="<?= e($record['nama']) ?>" required></div>
        <?php foreach ($selects as $field => [$table, $labelColumn, $idColumn]): ?>
            <?php $options = mysqli_query($conn, 'SELECT * FROM ' . $table . ' ORDER BY ' . $labelColumn); ?>
            <div class="form-group"><label for="<?= e($field) ?>"><?= e(ucfirst(str_replace('_', ' ', $field))) ?></label>
                <select id="<?= e($field) ?>" class="form-control" name="<?= e($field) ?>" required>
                    <?php while ($option = mysqli_fetch_assoc($options)): ?>
                        <option value="<?= (int) $option[$idColumn] ?>" <?= (int) $record[$field] === (int) $option[$idColumn] ? 'selected' : '' ?>><?= e($option[$labelColumn]) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="form-group"><label for="student-address">Alamat</label><textarea id="student-address" class="form-control" name="alamat" required><?= e($record['alamat']) ?></textarea></div>
        <div class="form-group"><label for="student-guardian-name">Nama orang tua/wali <span class="label-optional">opsional</span></label><input id="student-guardian-name" class="form-control" type="text" name="guardian_name" maxlength="100" value="<?= e($record['guardian_name'] ?? '') ?>"></div>
        <div class="form-group"><label for="student-guardian-phone">Nomor WhatsApp orang tua/wali <span class="label-optional">opsional</span></label><input id="student-guardian-phone" class="form-control" type="tel" name="guardian_phone" maxlength="30" autocomplete="tel" placeholder="08xx atau +628xx" value="<?= e($record['guardian_phone'] ?? '') ?>"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-primary" name="edit">Simpan perubahan</button></div>
    </form>
    <?php
} elseif ($entityKey === 'id_angkatan') {
    ?>
    <form action="<?= e($entity[1]) ?>" method="post" class="entity-form">
        <?= $csrfInput ?><input type="hidden" name="id_angkatan" value="<?= (int) $record['id_angkatan'] ?>">
        <div class="form-group"><label for="year-name">Nama angkatan</label><input id="year-name" class="form-control" name="nama_angkatan" maxlength="50" value="<?= e($record['nama_angkatan']) ?>" required></div>
        <div class="form-group"><label for="year-fee">Biaya SPP (Rupiah)</label><input id="year-fee" class="form-control" type="number" min="0" step="1" name="biaya" value="<?= e($record['biaya']) ?>" required></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-primary" name="edit">Simpan perubahan</button></div>
    </form>
    <?php
} else {
    $column = $entityKey === 'id_kelas' ? 'nama_kelas' : 'nama_jurusan';
    ?>
    <form action="<?= e($entity[1]) ?>" method="post" class="entity-form">
        <?= $csrfInput ?><input type="hidden" name="<?= e($entityKey) ?>" value="<?= (int) $record[$entityKey] ?>">
        <div class="form-group"><label for="master-name">Nama</label><input id="master-name" class="form-control" name="<?= e($column) ?>" maxlength="50" value="<?= e($record[$column]) ?>" required></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-primary" name="edit">Simpan perubahan</button></div>
    </form>
    <?php
}
