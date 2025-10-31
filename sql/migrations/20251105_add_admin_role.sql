-- Phase M: Allow admin role alongside owner/frontdesk/viewer
ALTER TABLE users
    MODIFY role ENUM('viewer', 'frontdesk', 'admin', 'owner') NOT NULL DEFAULT 'viewer';
