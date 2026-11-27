ALTER TABLE siswa
    ADD COLUMN guardian_name VARCHAR(100) DEFAULT NULL AFTER alamat,
    ADD COLUMN guardian_phone VARCHAR(30) DEFAULT NULL AFTER guardian_name;

ALTER TABLE pembayaran_events
    ADD COLUMN payment_method ENUM('cash', 'bank_transfer', 'qris', 'other') DEFAULT NULL AFTER event_type,
    ADD COLUMN payment_reference VARCHAR(100) DEFAULT NULL AFTER reference_no;
