CREATE DATABASE IF NOT EXISTS nexora_ict9
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE nexora_ict9;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_lengkap VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teknisi') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    activity VARCHAR(200) NOT NULL,
    team TEXT NULL DEFAULT NULL,
    keterangan TEXT NOT NULL,
    foto VARCHAR(255) NULL DEFAULT NULL,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user_tanggal (user_id, tanggal),
    KEY idx_logs_tanggal (tanggal),
    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (nama_lengkap, username, password, role)
VALUES
    (
        'Administrator NEXORA',
        'admin',
        'admin123',
        'admin'
    ),
    (
        'Teknisi NEXORA',
        'teknisi',
        'teknisi123',
        'teknisi'
    );