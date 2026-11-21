# Aplikasi Pembayaran SPP Sekolah

Aplikasi pembayaran SPP berbasis PHP dan MySQL untuk mengelola data siswa,
kelas, jurusan, angkatan, transaksi pembayaran, dan laporan.

## Persyaratan

- PHP 8.0 atau lebih baru
- MySQL/MariaDB
- Ekstensi PHP `mysqli`

## Instalasi

1. Pindahkan folder proyek ke document root web server.
2. Pastikan MySQL/MariaDB Laragon aktif.
3. Jalankan migrasi dari PowerShell di folder proyek:

```powershell
php database\migrate.php
```

Migrasi membuat database bila belum ada, membuat tabel yang belum tersedia, dan
mencatat versi migrasi agar aman dijalankan kembali. Migrasi tidak menghapus
tabel atau data yang sudah ada. Saat diminta, buat akun admin pertama; input
password tampil di terminal. Jika password database bukan default, atur
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS` di sesi terminal
sebelum menjalankan perintah.

Alternatifnya, buat database `webspp` dan import `database/webspp.sql` melalui
phpMyAdmin. Untuk update struktur database yang sudah ada, tetap gunakan
migrasi CLI.

4. Konfigurasi database pada [koneksi.php](koneksi.php) atau melalui
   environment variable:

```text
DB_HOST=localhost
DB_PORT=3308
DB_NAME=webspp
DB_USER=root
DB_PASS=
```

Contoh konfigurasi tersedia pada [.env.example](.env.example). Perlu diperhatikan
bahwa `.env.example` bukan pembaca konfigurasi otomatis; gunakan environment
variable milik Laragon, Apache, Nginx, atau terminal.

5. Jalankan aplikasi dari browser melalui Laragon atau web server lokal.

> Migrasi hanya memulihkan struktur tabel, bukan data yang sudah terhapus.
> Untuk pemulihan data lama, diperlukan backup database (misalnya ekspor SQL).

## Hak Cipta

Copyright (c) 2026 Adi Saputra.