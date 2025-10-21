-- Barber Turns bootstrap schema for DreamHost MySQL
-- Run once to provision core tables and seed default settings/barbers.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oauth_provider ENUM('google', 'apple') NOT NULL,
    oauth_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('viewer', 'frontdesk', 'owner') NOT NULL DEFAULT 'viewer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY provider_lookup (oauth_provider, oauth_id),
    UNIQUE KEY email_unique (email)
);

CREATE TABLE IF NOT EXISTS barbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    status ENUM('available', 'busy_walkin', 'busy_appointment', 'inactive') NOT NULL DEFAULT 'available',
    position INT UNSIGNED NOT NULL DEFAULT 1,
    busy_since DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_barbers_position (position)
);

CREATE TABLE IF NOT EXISTS settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    shop_name VARCHAR(120) NOT NULL DEFAULT 'Your Barber Shop',
    theme ENUM('light', 'dark') NOT NULL DEFAULT 'light',
    logo_url VARCHAR(255) DEFAULT NULL,
    tv_token CHAR(64) NOT NULL,
    poll_interval_ms INT UNSIGNED NOT NULL DEFAULT 3000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (id, shop_name, theme, logo_url, tv_token, poll_interval_ms)
VALUES (1, 'Your Barber Shop', 'light', NULL, SHA2(CONCAT(UUID(), RAND()), 256), 3000)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Optional demo barbers (comment out if not needed)
INSERT INTO barbers (name, status, position)
VALUES
    ('Alex', 'available', 1),
    ('Blair', 'available', 2),
    ('Casey', 'available', 3)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
