-- Shadril Shopping Store V23 additive analytics + health schema
-- Run in the existing store database. No existing rows are deleted by this migration.
ALTER TABLE products ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE transactions ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE order_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS system_health_log (
  health_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  result VARCHAR(30) NOT NULL DEFAULT 'INFO',
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_at), INDEX(action), INDEX(result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS security_events (
  event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(80) NOT NULL,
  severity ENUM('INFO','WARNING','HIGH','CRITICAL') NOT NULL DEFAULT 'INFO',
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_at), INDEX(event_type), INDEX(severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
