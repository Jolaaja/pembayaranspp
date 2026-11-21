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
            $paymentDate = date('Y-m-d');
            db_execute(
                $conn,
                "UPDATE pembayaran SET nobayar = ?, tglbayar = ?, ket = 'LUNAS', id_admin = ? WHERE idspp = ?",
                'ssii',
                [$receipt, $paymentDate, $adminId, $idspp]
            );
            db_execute(
                $conn,
                "INSERT INTO pembayaran_events (idspp, id_admin, event_type, reference_no, amount, payment_date, details)
                 VALUES (?, ?, 'paid', ?, ?, ?, ?)",
                'iisdss',
                [$idspp, $adminId, $receipt, $payment['jumlah'], $paymentDate, 'Pembayaran SPP dikonfirmasi.']
            );
            audit_event($conn, 'payment_paid', 'pembayaran', $idspp, 'Pembayaran SPP dikonfirmasi.');
            mysqli_commit($conn);
            flash('Pembayaran berhasil diproses.');
        }
    } elseif ($payment['ket'] !== 'LUNAS') {
        mysqli_rollback($conn);
        flash('Pembayaran ini belum lunas atau sudah dibatalkan.', 'warning');
    } else {
        db_execute(
            $conn,
            'UPDATE pembayaran SET nobayar = NULL, tglbayar = NULL, ket = NULL, id_admin = NULL WHERE idspp = ?',
            'i',
            [$idspp]
        );
        db_execute(
            $conn,
            "INSERT INTO pembayaran_events (idspp, id_admin, event_type, reference_no, amount, payment_date, details)
             VALUES (?, ?, 'reversed', ?, ?, ?, ?)",
            'iisdss',
            [$idspp, $adminId, $payment['nobayar'], $payment['jumlah'], $payment['tglbayar'], 'Pembayaran dibatalkan.']
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
