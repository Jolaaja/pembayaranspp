CREATE TABLE school_settings (
    settings_id TINYINT UNSIGNED NOT NULL,
    school_name VARCHAR(100) NOT NULL DEFAULT 'Portal Pembayaran Sekolah',
    school_address VARCHAR(255) DEFAULT NULL,
    school_phone VARCHAR(30) DEFAULT NULL,
    school_email VARCHAR(254) DEFAULT NULL,
    logo_data MEDIUMBLOB DEFAULT NULL,
    logo_mime ENUM('image/jpeg', 'image/png', 'image/webp') DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (settings_id),
    CONSTRAINT chk_school_settings_singleton CHECK (settings_id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO school_settings (settings_id, school_name)
VALUES (1, 'Portal Pembayaran Sekolah');
