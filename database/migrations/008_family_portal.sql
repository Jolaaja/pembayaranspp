CREATE TABLE portal_accounts (
    id_portal_account BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    account_type ENUM('guardian', 'student') NOT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_portal_account),
    UNIQUE KEY uq_portal_account_username (username),
    KEY idx_portal_account_status_type (status, account_type),
    CONSTRAINT fk_portal_account_creator
        FOREIGN KEY (created_by) REFERENCES admin (id_admin)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_account_students (
    id_portal_account BIGINT UNSIGNED NOT NULL,
    id_siswa INT NOT NULL,
    linked_by INT DEFAULT NULL,
    linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_portal_account, id_siswa),
    KEY idx_portal_account_students_student (id_siswa),
    CONSTRAINT fk_portal_link_account
        FOREIGN KEY (id_portal_account) REFERENCES portal_accounts (id_portal_account)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_portal_link_student
        FOREIGN KEY (id_siswa) REFERENCES siswa (id_siswa)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_portal_link_actor
        FOREIGN KEY (linked_by) REFERENCES admin (id_admin)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_account_events (
    id_event BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_portal_account BIGINT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(40) NOT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_event),
    KEY idx_portal_account_events_account_time (id_portal_account, created_at),
    KEY idx_portal_account_events_type_time (event_type, created_at),
    CONSTRAINT fk_portal_event_account
        FOREIGN KEY (id_portal_account) REFERENCES portal_accounts (id_portal_account)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
