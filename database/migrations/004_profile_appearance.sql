ALTER TABLE admin
    ADD COLUMN gender ENUM('male', 'female', 'unspecified') NOT NULL DEFAULT 'unspecified' AFTER role,
    ADD COLUMN profile_photo MEDIUMBLOB DEFAULT NULL AFTER gender,
    ADD COLUMN profile_photo_updated_at DATETIME DEFAULT NULL AFTER profile_photo;
