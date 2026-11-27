<?php
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';

try {
    $filters = parse_payment_reminder_filters($_GET);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    exit($error->getMessage());
}
$result = fetch_payment_reminders($conn, $filters['status'], $filters['search'], $filters['days_ahead'], 1, null);
$filename = 'pengingat-spp-' . $filters['status'] . '-' . date('Ymd') . '.csv';

function csv_safe_reminder_value($value): string
{
    $value = (string) $value;
    return preg_match('/^[\s=+\-@]/u', $value) ? "'" . $value : $value;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new RuntimeException('Tidak dapat menyiapkan file ekspor.');
}
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['NISN', 'Nama siswa', 'Kelas', 'Periode SPP', 'Jatuh tempo', 'Status', 'Jumlah (Rp)'], ',', '"', '\\');
foreach ($result['rows'] as $row) {
    $daysUntil = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($row['jatuhtempo']))->format('%r%a');
    $status = $daysUntil < 0
        ? 'Terlambat ' . abs($daysUntil) . ' hari'
        : ($daysUntil === 0 ? 'Jatuh tempo hari ini' : 'Jatuh tempo dalam ' . $daysUntil . ' hari');
    fputcsv($output, [
        csv_safe_reminder_value($row['nisn']),
        csv_safe_reminder_value($row['nama']),
        csv_safe_reminder_value($row['nama_kelas']),
        csv_safe_reminder_value($row['bulan']),
        $row['jatuhtempo'],
        csv_safe_reminder_value($status),
        number_format((float) $row['jumlah'], 2, '.', ''),
    ], ',', '"', '\\');
}
fclose($output);
