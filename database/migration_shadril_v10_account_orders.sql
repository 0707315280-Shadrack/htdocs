-- SHADRIL V10 ADDITIVE UPGRADE
-- Select the existing store database before running. Run once.
-- This migration ADDS functionality only. It does not delete existing products, customers, orders or admin accounts.

CREATE TABLE IF NOT EXISTS password_reset_otps (
  reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(customer_id), INDEX(expires_at),
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wishlists (
  wishlist_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_product (customer_id, product_id),
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS seller_follows (
  follow_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  seller_name VARCHAR(150) NOT NULL DEFAULT 'Shadril Shopping Store',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_follow (customer_id, seller_name),
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customer_messages (
  message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
  INDEX(customer_id, is_read)
);

CREATE TABLE IF NOT EXISTS recently_viewed (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_recent (customer_id, product_id),
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS coupon_redemptions (
  redemption_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NULL,
  redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_customer (coupon_id, customer_id),
  FOREIGN KEY(coupon_id) REFERENCES coupons(coupon_id) ON DELETE CASCADE,
  FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
  FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE SET NULL
);

ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NULL, ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0;

-- Ensure delivered orders have a final status-history entry when V10 is installed.
INSERT INTO order_status_history(order_id,status,notes)
SELECT o.order_id,o.status,'Order status synchronised during V10 upgrade'
FROM orders o
LEFT JOIN order_status_history h ON h.order_id=o.order_id AND h.status=o.status
WHERE h.id IS NULL;
