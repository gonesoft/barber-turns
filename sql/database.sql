-- Barber Turns bootstrap schema for DreamHost MySQL
-- Run once to provision core tables and seed default settings/barbers.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oauth_provider ENUM('google', 'apple', 'local') NOT NULL DEFAULT 'local',
    oauth_id VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    role ENUM('viewer', 'frontdesk', 'admin', 'owner') NOT NULL DEFAULT 'viewer',
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY provider_lookup (oauth_provider, oauth_id),
    UNIQUE KEY email_unique (email),
    UNIQUE KEY users_username_unique (username)
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
    shop_name VARCHAR(120) NOT NULL DEFAULT 'Finest Cutz Dominican Barbershop',
    theme ENUM('light', 'dark') NOT NULL DEFAULT 'light',
    logo_url VARCHAR(255) DEFAULT NULL,
    tv_token CHAR(64) NOT NULL,
    poll_interval_ms INT UNSIGNED NOT NULL DEFAULT 3000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (id, shop_name, theme, logo_url, tv_token, poll_interval_ms)
VALUES (1, 'Finest Cutz Dominican Barbershop', 'light', NULL, SHA2(CONCAT(UUID(), RAND()), 256), 3000)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Optional demo barbers (comment out if not needed)
INSERT INTO barbers (name, status, position)
VALUES
    ('Christian Mora', 'available', 1),
    ('Oscar Mateo', 'available', 2),
    ('Stefanie', 'available', 3),
    ('Cesar El Maestro', 'available', 3),
    ('Jesha El Menor', 'available', 3),
    ('Mohammad Dali', 'available', 3),
    ('Christ730', 'available', 3),
    ('Jefrey', 'available', 3),
    ('Junior Gonzalez', 'available', 3),
    ('Jose F Guerrero', 'available', 3),
    ('Elicenny', 'available', 3),
    ('Richard Nunez', 'available', 3)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO users (oauth_provider, oauth_id, email, name, username, password_hash, role)
VALUES (
    'local',
    NULL,
    'christian@x.com',
    'Christian Mora',
    'christian',
    '$argon2id$v=19$m=65536,t=4,p=1$dXBRRjJWZDZqSFQ1c0FQOQ$fVDOeGasHRseavi3+C+Y/L12VIJ+yh8hL1h8Nk+XlJY',
    'owner'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO users (oauth_provider, oauth_id, email, name, username, password_hash, role)
VALUES (
    'local',
    NULL,
    'oscar@x.com',
    'Oscar Mateo',
    'Oscar',
    '$argon2id$v=19$m=65536,t=4,p=1$WmE4RTdTdFprZEo0aDBvVQ$lUEFr5+e6QTo1n5lLoyWBRaPIx6+iMRpYJJPmuqL7zI',
    'admin'
);

INSERT INTO users (oauth_provider, oauth_id, email, name, username, password_hash, role)
VALUES (
    'local',
    NULL,
    'sthepany@x.com',
    'Sthepany',
    'Sthepany',
    '$argon2id$v=19$m=65536,t=4,p=1$Wjk4eFFTRlRXSURqMUwydA$xKwic26VsjizTfD1Ic+UlnGHfFlTgBqaQuur/crFCFM',
    'owner'
);
