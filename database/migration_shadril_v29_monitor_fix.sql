-- ============================================================
-- Shadril Shopping Store - V29 Functional Monitor Fix
-- MySQL / InfinityFree
-- IMPORTANT: Select if0_43082226_inventory before running.
-- This migration is additive and does not delete store data.
-- ============================================================

CREATE TABLE IF NOT EXISTS system_health_log (
    health_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    result VARCHAR(30) NOT NULL DEFAULT 'INFO',
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (health_id),
    KEY idx_health_created_at (created_at),
    KEY idx_health_action (action),
    KEY idx_health_result (result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fingerprint CHAR(64) NOT NULL,
    component VARCHAR(120) NOT NULL,
    severity ENUM('INFO','WARNING','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH',
    message TEXT NOT NULL,
    details TEXT NULL,
    status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'OPEN',
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME NULL,
    resolved_at DATETIME NULL,
    PRIMARY KEY (alert_id),
    UNIQUE KEY uq_system_alert_fingerprint (fingerprint),
    KEY idx_system_alert_status (status),
    KEY idx_system_alert_severity (severity),
    KEY idx_system_alert_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify the two required monitoring tables.
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('system_health_log','system_alerts')
ORDER BY TABLE_NAME;
