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
$action = trim((string) filter_input(INPUT_POST, 'act', FILTER_UNSAFE_RAW));
if (!$idspp || $nisn === '' || !in_array($action, ['bayar', 'batal'], true)) {
    http_response_code(400);
    exit('Data transaksi tidak valid.');
}

$paymentMethod = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
$paymentReference = is_string($_POST['payment_reference'] ?? null) ? trim($_POST['payment_reference']) : '';
$paymentNote = is_string($_POST['payment_note'] ?? null) ? trim($_POST['payment_note']) : '';
$paymentDate = is_string($_POST['payment_date'] ?? null) ? $_POST['payment_date'] : '';
if ($action === 'bayar') {
    $validMethod = in_array($paymentMethod, ['cash', 'bank_transfer', 'qris', 'other'], true);
    $validReference = preg_match('/\A.{0,100}\z/us', $paymentReference) === 1;
    $validNote = preg_match('/\A.{0,255}\z/us', $paymentNote) === 1;
    $validDate = is_valid_payment_date($paymentDate);
    if (!$validMethod || !$validReference || !$validNote || !$validDate) {
        flash('Periksa metode, referensi, catatan, dan tanggal pembayaran. Tanggal tidak boleh melewati hari ini.', 'danger');
        header('Location: pembayaran.php?nisn=' . rawurlencode($nisn));
        exit;
    }
}

mysqli_begin_transaction($conn);
try {
    $paymentQuery = db_execute(
        $conn,
        "SELECT p.* FROM pembayaran p JOIN siswa s ON s.id_siswa = p.id_siswa
         WHERE p.idspp = ? AND s.nisn = ? AND s.status = 'aktif' FOR UPDATE",
        'is',
        [$idspp, $nisn]
    );
    $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentQuery));
    if (!$payment) {
        mysqli_rollback($conn);
        http_response_code(404);
        exit('Pembayaran tidak cocok dengan siswa aktif.');
    }

    $adminId = (int) $_SESSION['admin'];
    if ($action === 'bayar') {
        if ($payment['ket'] === 'LUNAS') {
            mysqli_rollback($conn);
            flash('Pembayaran ini sudah lunas.');
        } else {
            $receipt = 'SPP-' . date('Ymd') . '-' . str_pad((string) $idspp, 5, '0', STR_PAD_LEFT);
            db_execute(
                $conn,
                "UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = 'LUNAS', id_admin = ? WHERE idspp = ?",
                'ssii',
                [$receipt, $paymentDate, $adminId, $idspp]
            );
            db_execute(
                $conn,
                "INSERT INTO pembayaran_events
                    (idspp, id_admin, event_type, payment_method, reference_no, payment_reference, amount, payment_date, details)
                 VALUES (?, ?, 'paid', ?, ?, ?, ?, ?, ?)",
                'iisssdss',
                [$idspp, $adminId, $paymentMethod, $receipt, $paymentReference !== '' ? $paymentReference : null, $payment['jumlah'], $paymentDate, $paymentNote !== '' ? $paymentNote : 'Pembayaran SPP dikonfirmasi.']
            );
            audit_event($conn, 'payment_paid', 'pembayaran', $idspp, 'Pembayaran SPP dikonfirmasi.');
            mysqli_commit($conn);
            flash('Pembayaran berhasil diproses.');
        }
    } elseif ($payment['ket'] !== 'LUNAS') {
        mysqli_rollback($conn);
        flash('Pembayaran ini belum lunas atau sudah dibatalkan.', 'warning');
    } else {
        $paidEventStmt = db_execute(
            $conn,
            "SELECT payment_method, payment_reference
             FROM pembayaran_events WHERE idspp = ? AND event_type = 'paid'
             ORDER BY id_event DESC LIMIT 1",
            'i',
            [$idspp]
        );
        $paidEvent = mysqli_fetch_assoc(mysqli_stmt_get_result($paidEventStmt)) ?: [];
        mysqli_stmt_close($paidEventStmt);
        db_execute(
            $conn,
            'UPDATE pembayaran SET nobayar = NULL, tglbayar = NULL, ket = NULL, id_admin = NULL WHERE idspp = ?',
            'i',
            [$idspp]
        );
        db_execute(
            $conn,
            "INSERT INTO pembayaran_events
                (idspp, id_admin, event_type, payment_method, reference_no, payment_reference, amount, payment_date, details)
             VALUES (?, ?, 'reversed', ?, ?, ?, ?, ?, ?)",
            'iisssdss',
            [$idspp, $adminId, $paidEvent['payment_method'] ?? null, $payment['nobayar'], $paidEvent['payment_reference'] ?? null, $payment['jumlah'], $payment['tglbayar'], 'Pembayaran dibatalkan.']
        );
        audit_event($conn, 'payment_reversed', 'pembayaran', $idspp, 'Pembayaran SPP dibatalkan.');
        mysqli_commit($conn);
        flash('Pembayaran berhasil dibatalkan. Riwayat transaksi tetap tersimpan.');
    }
} catch (mysqli_sql_exception $error) {
    mysqli_rollback($conn);
    error_log('Payment transaction failed: ' . $error->getMessage());
    flash('Transaksi gagal disimpan. Periksa koneksi database dan coba lagi.', 'danger');
}

header('Location: pembayaran.php?nisn=' . rawurlencode($nisn));
exit;
