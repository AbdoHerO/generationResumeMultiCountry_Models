-- Global profile: one per user, with French, English and Chinese versions of the CV content.
CREATE TABLE IF NOT EXISTS profiles (
    user_id INT UNSIGNED NOT NULL,
    data_json MEDIUMTEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A PDF import in progress: extracted and translated data the user reviews before it
-- replaces their profile. The PDF itself is never stored.
CREATE TABLE IF NOT EXISTS profile_imports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('processing', 'ready', 'failed', 'applied') NOT NULL DEFAULT 'processing',
    source_language VARCHAR(5) NULL,
    file_name VARCHAR(190) NOT NULL DEFAULT '',
    data_json MEDIUMTEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_profile_imports_user (user_id, created_at),
    CONSTRAINT fk_profile_imports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
