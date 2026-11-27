ALTER TABLE school_settings
    ADD COLUMN school_npsn VARCHAR(8) DEFAULT NULL AFTER school_email,
    ADD COLUMN principal_name VARCHAR(100) DEFAULT NULL AFTER school_npsn,
    ADD COLUMN school_website VARCHAR(254) DEFAULT NULL AFTER principal_name;

CREATE TABLE school_identity_history (
    id_version BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_name VARCHAR(100) NOT NULL,
    school_address VARCHAR(255) DEFAULT NULL,
    school_phone VARCHAR(30) DEFAULT NULL,
    school_email VARCHAR(254) DEFAULT NULL,
    school_npsn VARCHAR(8) DEFAULT NULL,
    principal_name VARCHAR(100) DEFAULT NULL,
    school_website VARCHAR(254) DEFAULT NULL,
    logo_data MEDIUMBLOB DEFAULT NULL,
    logo_mime ENUM('image/jpeg', 'image/png', 'image/webp') DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    change_type ENUM('initial', 'updated', 'restored') NOT NULL,
    restored_from BIGINT UNSIGNED DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_version),
    KEY idx_school_identity_history_changed_at (changed_at),
    KEY idx_school_identity_history_actor (changed_by),
    CONSTRAINT fk_school_identity_history_actor
        FOREIGN KEY (changed_by) REFERENCES admin (id_admin)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO school_identity_history (
    school_name, school_address, school_phone, school_email, school_npsn,
    principal_name, school_website, logo_data, logo_mime, changed_by, change_type
)
SELECT school_name, school_address, school_phone, school_email, school_npsn,
       principal_name, school_website, logo_data, logo_mime, NULL, 'initial'
FROM school_settings
WHERE settings_id = 1;
