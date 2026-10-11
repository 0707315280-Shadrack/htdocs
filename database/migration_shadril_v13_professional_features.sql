-- SHADRIL V13 PROFESSIONAL FEATURES
-- Run in the existing if0_43082226_inventory database. Additive only.

CREATE TABLE IF NOT EXISTS customer_private_messages (
 message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id INT UNSIGNED NOT NULL,
 sender_type ENUM('CUSTOMER','ADMIN') NOT NULL,
 sender_id INT UNSIGNED NOT NULL DEFAULT 0,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(customer_id,created_at), INDEX(customer_id,is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @db=DATABASE();
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customers ADD COLUMN last_seen_at DATETIME NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='customers' AND COLUMN_NAME='last_seen_at'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE product_reviews ADD COLUMN admin_reply TEXT NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='product_reviews' AND COLUMN_NAME='admin_reply'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE product_reviews ADD COLUMN admin_reply_at DATETIME NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='product_reviews' AND COLUMN_NAME='admin_reply_at'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE product_reviews ADD COLUMN admin_reply_by INT NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='product_reviews' AND COLUMN_NAME='admin_reply_by'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
