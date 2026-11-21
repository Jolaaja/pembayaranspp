<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$awal = filter_input(INPUT_GET, 'awal', FILTER_UNSAFE_RAW);
$akhir = filter_input(INPUT_GET, 'akhir', FILTER_UNSAFE_RAW);
if (!$awal || !$akhir || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $awal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $akhir) || $awal > $akhir) {
    http_response_code(400);
    exit('Rentang tanggal laporan tidak valid.');
}

$stmt = mysqli_prepare($conn, 'SELECT p.*, s.nisn, s.nama, k.nama_kelas
    FROM pembayaran p
    JOIN siswa s ON s.id_siswa = p.id_siswa
    JOIN kelas k ON k.id_kelas = s.id_kelas
    WHERE p.ket = "LUNAS" AND p.tglbayar BETWEEN ? AND ?
    ORDER BY p.tglbayar DESC, p.idspp DESC');
mysqli_stmt_bind_param($stmt, 'ss', $awal, $akhir);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
$total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
    $total += (float) $row['jumlah'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Pembayaran SPP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color:#24324a; }
        h2 { text-align:center; }
        .period { text-align:center; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #dfe3ec; padding:8px; font-size:13px; }
        th { background:#f1f3f8; }
        .right { text-align:right; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Cetak</button>
    <h2>LAPORAN PEMBAYARAN SPP</h2>
    <div class="period"><?= e(date('d/m/Y', strtotime($awal))) ?> s.d. <?= e(date('d/m/Y', strtotime($akhir))) ?></div>
    <table>
        <thead><tr><th>No</th><th>Tanggal</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Bulan</th><th>Jumlah</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= e(date('d/m/Y', strtotime((string) $row['tglbayar']))) ?></td>
                <td><?= e($row['nisn']) ?></td>
                <td><?= e($row['nama']) ?></td>
                <td><?= e($row['nama_kelas']) ?></td>
                <td><?= e($row['bulan']) ?></td>
                <td class="right">Rp <?= number_format((float) $row['jumlah'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="6" class="right"><strong>Total</strong></td><td class="right"><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></td></tr></tfoot>
    </table>
</body>
</html>
