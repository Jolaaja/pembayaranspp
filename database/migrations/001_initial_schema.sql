CREATE TABLE IF NOT EXISTS admin (
    id_admin INT NOT NULL AUTO_INCREMENT,
    nama_admin VARCHAR(50) NOT NULL,
    user_admin VARCHAR(50) NOT NULL,
    pass_admin VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS angkatan (
    id_angkatan INT NOT NULL AUTO_INCREMENT,
    nama_angkatan VARCHAR(50) NOT NULL,
    biaya VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_angkatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jurusan (
    id_jurusan INT NOT NULL AUTO_INCREMENT,
    nama_jurusan VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_jurusan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelas (
    id_kelas INT NOT NULL AUTO_INCREMENT,
    nama_kelas VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_kelas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siswa (
    id_siswa INT NOT NULL AUTO_INCREMENT,
    nisn VARCHAR(50) NOT NULL,
    nama VARCHAR(50) NOT NULL,
    id_angkatan INT NOT NULL,
    id_jurusan INT NOT NULL,
    id_kelas INT NOT NULL,
    alamat TEXT NOT NULL,
    PRIMARY KEY (id_siswa),
    KEY idx_siswa_nisn (nisn),
    KEY idx_siswa_angkatan (id_angkatan),
    KEY idx_siswa_jurusan (id_jurusan),
    KEY idx_siswa_kelas (id_kelas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pembayaran (
    idspp INT NOT NULL AUTO_INCREMENT,
    id_siswa INT NOT NULL,
    jatuhtempo VARCHAR(50) NOT NULL,
    bulan VARCHAR(50) NOT NULL,
    nobayar VARCHAR(50) DEFAULT NULL,
    tglbayar VARCHAR(50) DEFAULT NULL,
    jumlah VARCHAR(50) NOT NULL,
    ket VARCHAR(50) DEFAULT NULL,
    id_admin INT DEFAULT NULL,
    PRIMARY KEY (idspp),
    KEY idx_pembayaran_siswa (id_siswa),
    KEY idx_pembayaran_tanggal_status (tglbayar, ket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
