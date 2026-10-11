-- Shadril Shopping Store V22 additive professional schema
-- Run against the existing store database. The PHP bootstrap also creates these safely when possible.
CREATE TABLE IF NOT EXISTS store_config (
  config_key VARCHAR(60) PRIMARY KEY,
  config_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO store_config(config_key,config_value) VALUES
('customer_care_phone','+254707315280'),
('customer_care_whatsapp','254707315280'),
('support_email','otumas18@gmail.com'),
('store_tagline','Shop smart. Live better.'),
('cart_reminder_hours','24'),
('order_email_enabled','1');
CREATE TABLE IF NOT EXISTS store_social_links (
  link_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform VARCHAR(40) NOT NULL UNIQUE,
  url VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO store_social_links(platform,url) VALUES
('facebook',''),('twitter',''),('linkedin',''),('instagram',''),('tiktok','');
CREATE TABLE IF NOT EXISTS admin_audit_log (
  audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(user_id),INDEX(created_at),INDEX(action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS support_tickets (
  ticket_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
  priority ENUM('LOW','NORMAL','HIGH') NOT NULL DEFAULT 'NORMAL',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(customer_id),INDEX(status),INDEX(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS support_replies (
  reply_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  sender_type ENUM('CUSTOMER','ADMIN') NOT NULL,
  sender_id INT UNSIGNED NOT NULL DEFAULT 0,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(ticket_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
