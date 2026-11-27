# Aplikasi Pembayaran SPP Sekolah

Aplikasi PHP dan MySQL untuk mengelola siswa, data sekolah, pembayaran SPP,
riwayat transaksi, laporan operasional, dan akun petugas. Antarmuka responsif
menggunakan aset lokal; menu, modal, dan pencarian tabel tidak memerlukan
direktori `vendor`.
Identitas Portal SPP memakai logo vektor lokal di `img/logo-spp.svg`.

## Persyaratan

- PHP 8.0 atau lebih baru
- MySQL 8 atau MariaDB yang mendukung InnoDB dan foreign key
- Ekstensi PHP `mysqli`
- Ekstensi PHP `gd` dan `fileinfo` untuk foto profil
- Klien `mysql` dan `mysqldump` untuk backup/restore

## Instalasi dan migrasi

1. Letakkan proyek di document root dan pastikan MySQL/MariaDB aktif.
2. Atur kredensial database. Nilai bawaan aplikasi: `localhost`, port `3308`,
   database `webspp`, user `root`, tanpa password.
3. Jalankan migrasi melalui PowerShell dari folder proyek:

   ```powershell
   php database\migrate.php
   ```

   Migrasi mencatat versi yang sudah diterapkan dan aman dijalankan kembali.
   Migrasi `003_financial_integrity.sql` mengubah nominal menjadi `DECIMAL`,
   tanggal menjadi `DATE`, menambah role, arsip siswa, foreign key, audit log,
   dan riwayat pembayaran. Migrasi `004_profile_appearance.sql` menambahkan
   preferensi avatar gender dan penyimpanan foto profil. Pemeriksaan awal
   menghentikan migrasi finansial jika menemukan data lama yang invalid atau
   duplikat; perbaiki data atau pulihkan backup terlebih dahulu. Migrasi tidak
   menghapus data lama.

   Konfigurasi koneksi dapat diberikan melalui environment variable
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.
   Jika database belum memiliki akun admin, migrator meminta informasi admin
   pertama di terminal. Input password pada prompt terminal terlihat, jadi
   jalankan dari terminal privat.

4. Buka aplikasi melalui Laragon atau web server lokal.

   Untuk menjalankan server pengembangan PHP dari terminal:

   ```powershell
   php artisan serve
   ```

   Buka `http://127.0.0.1:8000`. Perintah ini adalah runner server lokal
   sederhana, bukan Laravel Artisan. Gunakan `php artisan serve --port=8080`
   jika port 8000 sedang digunakan.

`webspp.sql` dan `database/webspp.sql` adalah skema dasar lama untuk instalasi
baru. Setelah mengimpor salah satunya, tetap jalankan migrasi CLI. Jangan impor
skema ke database yang sudah berisi data.

## Fitur operasional

- Admin dapat mengelola data sekolah, pengguna, role, dan profil. Kasir dapat
  memproses pembayaran serta membuka laporan.
- Pembayaran diproses dengan transaksi database dan penguncian baris. Pembatalan
  menyimpan peristiwa reversal, bukan menghapus riwayat. Riwayat ditampilkan
  sebagai linimasa dengan status terkini dan tautan ke slip pembayaran.
- Setiap siswa baru mendapat 36 jadwal SPP dengan jatuh tempo tanggal 10 mulai
  bulan berjalan. Penghapusan siswa mengarsipkannya agar histori tetap tersedia.
- Akun dapat memilih avatar default netral, perempuan, atau laki-laki, serta
  mengunggah foto profil JPG/PNG/WebP hingga 2 MB. Foto dipotong dan dinormalisasi
  menjadi JPEG, disimpan di database, dan hanya disajikan kepada pengguna login.
- Dashboard menampilkan tren dan ringkasan pemasukan yang dapat difilter untuk
  bulan berjalan, semester berjalan, atau enam bulan terakhir. Direktori dan
  tabel dilengkapi pencarian, pengurutan, serta halaman navigasi; jadwal
  pembayaran beradaptasi menjadi kartu pada layar kecil.
- Status pembayaran, notifikasi, dan fokus keyboard menggunakan penanda visual
  yang konsisten dan mudah dibedakan.
- Laporan dapat difilter berdasarkan rentang tanggal, menampilkan rekap kelas,
  rekap bulanan, dan tunggakan siswa aktif. Slip serta laporan cetak menyertakan
  logo lokal, ringkasan, dan ruang tanda tangan; ekspor Excel menggunakan CSV.
- Direktori siswa memfilter, mengurutkan, dan membatasi hasil di server agar
  penggunaan data besar tetap ringan.
- Admin dapat meninjau aktivitas dan riwayat perubahan role di menu Aktivitas.
- Login dibatasi setelah lima percobaan dalam 15 menit; sesi tidak aktif berakhir
  setelah 30 menit.

## Backup dan restore

Jalankan backup ke folder privat di luar document root dan folder proyek:

```powershell
php database\backup.php 'C:\laragon\backup\webspp'
```

Jika binary MySQL tidak ada di `PATH`, atur `MYSQLDUMP_BIN` untuk backup dan
`MYSQL_BIN` untuk restore. File backup berisi data siswa dan akun (password
tersimpan sebagai hash); lindungi dan jangan masukkan file backup ke Git.

Restore hanya diizinkan ke database kosong yang sudah dibuat. Contoh, buat
database baru bernama `webspp_restore` di MySQL/phpMyAdmin, lalu arahkan koneksi
ke database itu:

```powershell
$env:DB_NAME = 'webspp_restore'
php database\restore.php 'C:\laragon\backup\webspp-YYYYMMDD-HHMMSS.sql' --confirm
```

Gunakan hanya backup yang dipercaya. Restore tidak menimpa tabel yang sudah ada.

## Pengujian

Setelah migrasi, jalankan tes integritas database. Tes membuat fixture dalam
transaksi lalu membatalkannya sehingga data uji tidak tertinggal:

```powershell
php tests\database_integrity.php
```

## Hak Cipta

Copyright (c) 2026 Adi Saputra.