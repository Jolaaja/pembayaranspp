<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$idspp = filter_input(INPUT_GET, 'idspp', FILTER_VALIDATE_INT);
if (!$idspp) {
    http_response_code(400);
    exit('ID pembayaran tidak valid.');
}

$sql = 'SELECT p.*, s.nisn, s.nama, k.nama_kelas, j.nama_jurusan
        FROM pembayaran p
        JOIN siswa s ON s.id_siswa = p.id_siswa
        JOIN kelas k ON k.id_kelas = s.id_kelas
        JOIN jurusan j ON j.id_jurusan = s.id_jurusan
        WHERE p.idspp = ? AND p.ket = "LUNAS"';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $idspp);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$data) {
    http_response_code(404);
    exit('Data pembayaran tidak ditemukan.');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Pembayaran SPP</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 720px; margin: 30px auto; color: #24324a; }
        .header { text-align: center; border-bottom: 2px solid #5b5fc7; padding-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td { padding: 9px 4px; border-bottom: 1px solid #e7eaf2; }
        td:first-child { width: 38%; color: #667085; }
        .status { display:inline-block; background:#dcfce7; color:#166534; padding:6px 12px; border-radius:20px; font-weight:bold; }
        .footer { text-align:right; margin-top:40px; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Cetak</button>
    <div class="header">
        <h2>BUKTI PEMBAYARAN SPP</h2>
        <div>TAHUN AJARAN <?= e(substr((string) $data['bulan'], -4)) ?></div>
    </div>
    <table>
        <tr><td>No. Bukti</td><td><strong><?= e($data['nobayar']) ?></strong></td></tr>
        <tr><td>NISN</td><td><?= e($data['nisn']) ?></td></tr>
        <tr><td>Nama Siswa</td><td><?= e($data['nama']) ?></td></tr>
        <tr><td>Kelas / Jurusan</td><td><?= e($data['nama_kelas']) ?> / <?= e($data['nama_jurusan']) ?></td></tr>
        <tr><td>Bulan</td><td><?= e($data['bulan']) ?></td></tr>
        <tr><td>Tanggal Bayar</td><td><?= e(date('d/m/Y', strtotime((string) $data['tglbayar']))) ?></td></tr>
        <tr><td>Jumlah</td><td><strong>Rp <?= number_format((float) $data['jumlah'], 0, ',', '.') ?></strong></td></tr>
        <tr><td>Status</td><td><span class="status">LUNAS</span></td></tr>
    </table>
    <div class="footer">
        Dicetak: <?= date('d/m/Y H:i') ?>
    </div>
</body>
</html>
