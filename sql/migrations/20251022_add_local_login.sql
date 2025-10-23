-- Phase L: Add local login fields to users table
ALTER TABLE users
    MODIFY oauth_provider ENUM('google', 'apple', 'local') NOT NULL DEFAULT 'local',
    MODIFY oauth_id VARCHAR(255) DEFAULT NULL,
    ADD COLUMN username VARCHAR(100) DEFAULT NULL AFTER name,
    ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER username,
    ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER password_hash,
    ADD UNIQUE KEY users_username_unique (username);
