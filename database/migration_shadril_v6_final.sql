-- SHADRIL SHOPPING STORE V6 FINAL REPAIR
-- Select if0_43082226_inventory before running.
-- Do NOT import database.sql.

CREATE TABLE IF NOT EXISTS customers (
  customer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
  order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(50) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  status ENUM('PENDING','CONFIRMED','PROCESSING','PACKED','DISPATCHED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_address VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH_ON_DELIVERY',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
  order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- Your existing SHADRACK OTUMA account is promoted; no duplicate account is inserted.
UPDATE users SET role='Admin', is_super_admin=1, password_hash='$2y$12$6tc41Ydnyborp3p2TNakt.iLrUS6piHBJcQtv4Dikzw6d0uT1NbKy'
WHERE LOWER(username)='shadrack otuma';

-- Keep the main administrator as the only super administrator.
UPDATE users SET is_super_admin=0 WHERE LOWER(username)<>'shadrack otuma';
UPDATE users SET is_super_admin=1, role='Admin' WHERE LOWER(username)='shadrack otuma';
