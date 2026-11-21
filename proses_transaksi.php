<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$idspp = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nisn = trim((string) filter_input(INPUT_POST, 'nisn', FILTER_UNSAFE_RAW));
$act = trim((string) filter_input(INPUT_POST, 'act', FILTER_UNSAFE_RAW));

if (!$idspp || $nisn === '' || !in_array($act, ['bayar', 'batal'], true)) {
    http_response_code(400);
    exit('Data transaksi tidak valid.');
}

$paymentMatch = db_execute($conn, 'SELECT p.idspp FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa WHERE p.idspp = ? AND s.nisn = ?', 'is', [$idspp, $nisn]);
if (mysqli_num_rows(mysqli_stmt_get_result($paymentMatch)) !== 1) {
    http_response_code(404);
    exit('Pembayaran tidak cocok dengan siswa.');
}

if ($act === 'bayar') {
    $stmt = mysqli_prepare($conn, "UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = ?, id_admin = ? WHERE idspp = ? AND (ket IS NULL OR ket <> 'LUNAS')");
    $nobayar = 'SPP-' . date('Ymd') . '-' . str_pad((string) $idspp, 5, '0', STR_PAD_LEFT);
    $tglbayar = date('Y-m-d');
    $ket = 'LUNAS';
    $idAdmin = (int) $_SESSION['admin'];
    mysqli_stmt_bind_param($stmt, 'sssii', $nobayar, $tglbayar, $ket, $idAdmin, $idspp);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) === 1;
    flash($ok ? 'Pembayaran berhasil diproses.' : 'Pembayaran sudah diproses atau tidak ditemukan.', $ok ? 'success' : 'warning');
} else {
    $stmt = mysqli_prepare($conn, "UPDATE pembayaran SET nobayar = NULL, tglbayar = NULL, ket = NULL, id_admin = NULL WHERE idspp = ? AND ket = 'LUNAS'");
    mysqli_stmt_bind_param($stmt, 'i', $idspp);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) === 1;
    flash($ok ? 'Pembayaran berhasil dibatalkan.' : 'Pembayaran tidak ditemukan atau sudah batal.', $ok ? 'success' : 'warning');
}

header('Location: pembayaran.php?nisn=' . rawurlencode($nisn));
exit;