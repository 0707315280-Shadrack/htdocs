-- SHADRIL SHOPPING STORE v3 UPGRADE
-- Run this AFTER selecting if0_43082226_inventory in phpMyAdmin.
-- It adds new marketplace fields/tables without deleting existing data.

CREATE TABLE IF NOT EXISTS product_reviews (
 review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id INT UNSIGNED NOT NULL,
 customer_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment TEXT NULL,
 status ENUM('PENDING','APPROVED','HIDDEN') NOT NULL DEFAULT 'PENDING',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_product_customer_review (product_id, customer_id),
 FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wishlists (
 wishlist_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_wishlist (customer_id, product_id),
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
 FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS store_messages (
 message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id INT UNSIGNED NULL,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(150) NOT NULL,
 phone VARCHAR(50) NULL,
 subject VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 status ENUM('NEW','READ','RESOLVED') NOT NULL DEFAULT 'NEW',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coupons (
 coupon_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 discount_type ENUM('PERCENT','FIXED') NOT NULL DEFAULT 'PERCENT',
 discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
 minimum_order DECIMAL(12,2) NOT NULL DEFAULT 0,
 expires_at DATETIME NULL,
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS store_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value TEXT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add new customer fields only if they do not already exist.
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='email_verified')=0,'ALTER TABLE customers ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='verification_token')=0,'ALTER TABLE customers ADD COLUMN verification_token VARCHAR(128) NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='verification_expires_at')=0,'ALTER TABLE customers ADD COLUMN verification_expires_at DATETIME NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='google_id')=0,'ALTER TABLE customers ADD COLUMN google_id VARCHAR(120) NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add new product fields only if they do not already exist.
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='image_url')=0,'ALTER TABLE products ADD COLUMN image_url VARCHAR(500) NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='compare_at_price')=0,'ALTER TABLE products ADD COLUMN compare_at_price DECIMAL(12,2) NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='brand')=0,'ALTER TABLE products ADD COLUMN brand VARCHAR(120) NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='is_featured')=0,'ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='rating')=0,'ALTER TABLE products ADD COLUMN rating DECIMAL(3,2) NOT NULL DEFAULT 0','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='review_count')=0,'ALTER TABLE products ADD COLUMN review_count INT UNSIGNED NOT NULL DEFAULT 0','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO categories(name) VALUES ('Phones'),('Fashion'),('Food & Groceries'),('Televisions'),('Home Appliances'),('Beauty & Personal Care'),('Baby Products'),('Sports & Fitness');

-- Demo product images for common existing products. Admin can replace these later.
UPDATE products SET image_url='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Tech'),is_featured=1 WHERE LOWER(product_name) LIKE '%phone%' AND (image_url IS NULL OR image_url='');
UPDATE products SET image_url='https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Computer'),is_featured=1 WHERE LOWER(product_name) LIKE '%laptop%' AND (image_url IS NULL OR image_url='');
UPDATE products SET image_url='https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Office'),is_featured=1 WHERE LOWER(product_name) LIKE '%keyboard%' AND (image_url IS NULL OR image_url='');
UPDATE products SET image_url='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Fashion') WHERE LOWER(product_name) LIKE '%shirt%' OR LOWER(product_name) LIKE '%cloth%';
UPDATE products SET image_url='https://images.unsplash.com/photo-1597362925123-77861d3fbac7?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Fresh Market') WHERE LOWER(product_name) LIKE '%cabbage%' OR LOWER(product_name) LIKE '%food%';
UPDATE products SET image_url='https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Tech'),is_featured=1 WHERE LOWER(product_name) LIKE '%mouse%' AND (image_url IS NULL OR image_url='');
UPDATE products SET image_url='https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=700&q=80',brand=COALESCE(brand,'Tech') WHERE LOWER(product_name) LIKE '%computer%' AND (image_url IS NULL OR image_url='');
UPDATE products p SET category_id=(SELECT category_id FROM categories WHERE name='Phones' LIMIT 1) WHERE p.category_id IS NULL AND LOWER(p.product_name) LIKE '%phone%';
UPDATE products p SET category_id=(SELECT category_id FROM categories WHERE name='Computers' LIMIT 1) WHERE p.category_id IS NULL AND (LOWER(p.product_name) LIKE '%laptop%' OR LOWER(p.product_name) LIKE '%computer%' OR LOWER(p.product_name) LIKE '%keyboard%' OR LOWER(p.product_name) LIKE '%mouse%');
UPDATE products p SET category_id=(SELECT category_id FROM categories WHERE name='Food & Groceries' LIMIT 1) WHERE p.category_id IS NULL AND (LOWER(p.product_name) LIKE '%cabbage%' OR LOWER(p.product_name) LIKE '%food%' OR LOWER(p.product_name) LIKE '%rice%' OR LOWER(p.product_name) LIKE '%sugar%' OR LOWER(p.product_name) LIKE '%oil%');

INSERT INTO store_settings(setting_key,setting_value) VALUES
('store_name','Shadril Shopping Store'),
('support_phone','+254707315280'),
('support_email','otumas18@gmail.com'),
('currency','KES')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
