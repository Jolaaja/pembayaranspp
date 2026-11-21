UPDATE pembayaran SET nobayar = NULL WHERE nobayar = '';
UPDATE pembayaran SET tglbayar = NULL WHERE tglbayar = '';
UPDATE pembayaran SET ket = NULL WHERE ket = '';

ALTER TABLE admin
    ADD COLUMN role ENUM('admin', 'cashier') NOT NULL DEFAULT 'admin' AFTER pass_admin,
    ADD UNIQUE KEY uq_admin_username (user_admin);

ALTER TABLE angkatan
    MODIFY biaya DECIMAL(12,2) UNSIGNED NOT NULL;

ALTER TABLE siswa
    ADD COLUMN status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif' AFTER alamat,
    ADD UNIQUE KEY uq_siswa_nisn (nisn),
    ADD CONSTRAINT fk_siswa_angkatan FOREIGN KEY (id_angkatan) REFERENCES angkatan (id_angkatan) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_siswa_jurusan FOREIGN KEY (id_jurusan) REFERENCES jurusan (id_jurusan) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_siswa_kelas FOREIGN KEY (id_kelas) REFERENCES kelas (id_kelas) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE pembayaran
    MODIFY jatuhtempo DATE NOT NULL,
    MODIFY tglbayar DATE DEFAULT NULL,
    MODIFY jumlah DECIMAL(12,2) UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_pembayaran_siswa_jatuh_tempo (id_siswa, jatuhtempo),
    ADD UNIQUE KEY uq_pembayaran_nomor (nobayar),
    ADD CONSTRAINT fk_pembayaran_siswa FOREIGN KEY (id_siswa) REFERENCES siswa (id_siswa) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_pembayaran_admin FOREIGN KEY (id_admin) REFERENCES admin (id_admin) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE pembayaran_events (
    id_event BIGINT NOT NULL AUTO_INCREMENT,
    idspp INT NOT NULL,
    id_admin INT DEFAULT NULL,
    event_type ENUM('paid', 'reversed') NOT NULL,
    reference_no VARCHAR(50) DEFAULT NULL,
    amount DECIMAL(12,2) UNSIGNED NOT NULL,
    payment_date DATE DEFAULT NULL,
    details VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_event),
    KEY idx_payment_events_payment (idspp, created_at),
    KEY idx_payment_events_admin (id_admin),
    CONSTRAINT fk_payment_events_payment FOREIGN KEY (idspp) REFERENCES pembayaran (idspp) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_payment_events_admin FOREIGN KEY (id_admin) REFERENCES admin (id_admin) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pembayaran_events (idspp, id_admin, event_type, reference_no, amount, payment_date, details, created_at)
SELECT idspp, id_admin, 'paid', nobayar, jumlah, tglbayar, 'Status lunas sebelum audit transaksi diaktifkan', COALESCE(tglbayar, CURRENT_TIMESTAMP)
FROM pembayaran
WHERE ket = 'LUNAS';

CREATE TABLE admin_audit_log (
    id_audit BIGINT NOT NULL AUTO_INCREMENT,
    id_admin INT DEFAULT NULL,
    event_type VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) DEFAULT NULL,
    entity_id BIGINT DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_audit),
    KEY idx_admin_audit_actor_time (id_admin, created_at),
    KEY idx_admin_audit_event_time (event_type, created_at),
    CONSTRAINT fk_admin_audit_actor FOREIGN KEY (id_admin) REFERENCES admin (id_admin) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_login_attempts (
    id_attempt BIGINT NOT NULL AUTO_INCREMENT,
    username_key CHAR(64) NOT NULL,
    ip_key CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_attempt),
    KEY idx_login_attempt_username_time (username_key, attempted_at),
    KEY idx_login_attempt_ip_time (ip_key, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
