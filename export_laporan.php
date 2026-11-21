<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

$rawStart = $_GET['awal'] ?? '';
$rawEnd = $_GET['akhir'] ?? '';
$awal = is_string($rawStart) ? trim($rawStart) : '';
$akhir = is_string($rawEnd) ? trim($rawEnd) : '';
if (!is_valid_iso_date($awal) || !is_valid_iso_date($akhir) || $awal > $akhir) {
    http_response_code(400);
    exit('Rentang tanggal laporan tidak valid.');
}

$stmt = db_execute(
    $conn,
    "SELECT p.tglbayar, p.nobayar, s.nisn, s.nama, k.nama_kelas, p.bulan, p.jumlah
     FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
     JOIN kelas k ON k.id_kelas = s.id_kelas
     WHERE p.ket = 'LUNAS' AND p.tglbayar BETWEEN ? AND ?
     ORDER BY p.tglbayar DESC, p.idspp DESC",
    'ss',
    [$awal, $akhir]
);
$result = mysqli_stmt_get_result($stmt);

function csv_safe_value($value): string
{
    $value = (string) $value;
    return preg_match('/^[\s=+\-@]/u', $value) ? "'" . $value : $value;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan-spp-' . $awal . '-sd-' . $akhir . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');
$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new RuntimeException('Tidak dapat menyiapkan file ekspor.');
}
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Tanggal', 'No. Pembayaran', 'NISN', 'Nama Siswa', 'Kelas', 'Bulan SPP', 'Jumlah (Rp)'], ',', '"', '\\');
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, array_map('csv_safe_value', array_values($row)), ',', '"', '\\');
}
fclose($output);
