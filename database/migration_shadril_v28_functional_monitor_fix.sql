-- V28: Functional monitor fixes
-- Run this in the SAME InfinityFree database used by the application if System Tests reports missing system_alerts.
CREATE TABLE IF NOT EXISTS system_alerts (
 alert_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 fingerprint CHAR(64) NOT NULL UNIQUE,
 component VARCHAR(120) NOT NULL,
 severity ENUM('INFO','WARNING','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH',
 message TEXT NOT NULL,
 details TEXT NULL,
 status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'OPEN',
 first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 notified_at DATETIME NULL,
 resolved_at DATETIME NULL,
 INDEX(status), INDEX(severity), INDEX(last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_health_log (
 health_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(120) NOT NULL,
 result VARCHAR(30) NOT NULL DEFAULT 'INFO',
 details TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(created_at), INDEX(action), INDEX(result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
