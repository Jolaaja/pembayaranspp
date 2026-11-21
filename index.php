<?php
include __DIR__ . '/function/function_rupiah.php';
require_once __DIR__ . '/app.php';
require_login();
include __DIR__ . '/koneksi.php';
include __DIR__ . '/header.php';

$totalRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jumlah FROM pembayaran WHERE ket = 'LUNAS'"));
$incomeRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS jumlah FROM pembayaran WHERE ket = 'LUNAS'"));
$todayRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) AS jumlah FROM pembayaran WHERE tglbayar = CURDATE() AND ket = 'LUNAS'"));
$totalspp = (int) $totalRow['jumlah'];
$totaltrx = (float) $incomeRow['jumlah'];
$harini = (float) $todayRow['jumlah'];
?>
<section class="dashboard-welcome">
    <div>
        <span class="eyebrow">DASHBOARD KEUANGAN</span>
        <h2>Selamat datang, <?= e($_SESSION['nama_admin'] ?? 'Administrator') ?></h2>
        <p>Berikut ringkasan pembayaran sekolah Anda.</p>
    </div>
    <a class="btn btn-primary" href="pembayaran.php">Buat transaksi <span aria-hidden="true">→</span></a>
</section>

<div class="row dashboard-stats">
    <div class="col-xl-4 col-md-6 mb-4">
        <article class="card stat-card border-left-primary h-100">
            <div class="card-body">
                <div class="stat-top"><span class="stat-icon">↗</span><span class="stat-tag">SEPANJANG WAKTU</span></div>
                <div class="stat-label">Total transaksi lunas</div>
                <div class="stat-value"><?= number_format($totalspp, 0, ',', '.') ?><span class="stat-unit">transaksi</span></div>
                <div class="stat-foot">Seluruh pembayaran yang sudah dikonfirmasi</div>
            </div>
        </article>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <article class="card stat-card border-left-success h-100">
            <div class="card-body">
                <div class="stat-top"><span class="stat-icon">Rp</span><span class="stat-tag">AKUMULASI</span></div>
                <div class="stat-label">Total pemasukan</div>
                <div class="stat-value"><?= format_rupiah($totaltrx) ?></div>
                <div class="stat-foot">Akumulasi dana dari pembayaran lunas</div>
            </div>
        </article>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <article class="card stat-card border-left-success h-100">
            <div class="card-body">
                <div class="stat-top"><span class="stat-icon">◷</span><span class="stat-tag">HARI INI</span></div>
                <div class="stat-label">Pemasukan hari ini</div>
                <div class="stat-value"><?= format_rupiah($harini) ?></div>
                <div class="stat-foot">Pembayaran diterima pada <?= date('d/m/Y') ?></div>
            </div>
        </article>
    </div>
</div>

<section class="dashboard-bottom">
    <div class="dashboard-note">
        <span class="note-mark">S</span>
        <div><strong>Administrasi rapi, layanan lebih baik.</strong><p>Gunakan menu pembayaran untuk mencari siswa dan memperbarui status SPP.</p></div>
    </div>
    <div class="quick-actions">
        <a href="datasiswa.php"><span>♙</span>Lihat siswa</a>
        <a href="laporan.php"><span>▤</span>Buat laporan</a>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
