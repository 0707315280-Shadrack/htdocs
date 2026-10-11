-- SHADRIL SHOPPING STORE v5
-- Run this once AFTER migration_shadril_v3.sql and migration_shadril_v4.sql.
-- Select if0_43082226_inventory in phpMyAdmin first.
-- This adds protected administrator management. Customers remain in the separate customers table.

ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO users (username, password_hash, role, is_super_admin)
VALUES ('SHADRACK OTUMA', '$2y$12$8rrsTG3uW7SrLMHbmKHB2OXmhYNLcEwrHvi/IuKoHAOfhIr3FCiXu', 'Admin', 1)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    role = 'Admin',
    is_super_admin = 1;

-- Keep the original admin account available, but make the named owner account the protected super administrator.
UPDATE users SET is_super_admin = 0 WHERE username <> 'SHADRACK OTUMA';
