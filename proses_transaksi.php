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

if ($act === 'bayar') {
    $stmt = mysqli_prepare($conn, 'UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = ?, id_admin = ? WHERE idspp = ?');
    $nobayar = 'SPP-' . date('Ymd') . '-' . str_pad((string) $idspp, 5, '0', STR_PAD_LEFT);
    $tglbayar = date('Y-m-d');
    $ket = 'LUNAS';
    $idAdmin = (int) $_SESSION['admin'];
    mysqli_stmt_bind_param($stmt, 'sssii', $nobayar, $tglbayar, $ket, $idAdmin, $idspp);
    $ok = mysqli_stmt_execute($stmt);
    flash($ok ? 'Pembayaran berhasil diproses.' : 'Pembayaran gagal diproses.', $ok ? 'success' : 'danger');
} else {
    $stmt = mysqli_prepare($conn, 'UPDATE pembayaran SET nobayar = NULL, tglbayar = NULL, ket = NULL, id_admin = NULL WHERE idspp = ?');
    mysqli_stmt_bind_param($stmt, 'i', $idspp);
    $ok = mysqli_stmt_execute($stmt);
    flash($ok ? 'Pembayaran berhasil dibatalkan.' : 'Pembayaran gagal dibatalkan.', $ok ? 'success' : 'danger');
}

header('Location: pembayaran.php?nisn=' . rawurlencode($nisn));
exit;