-- Import this only if the tables do not already exist.
-- Select the InfinityFree database first in phpMyAdmin.

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'Staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku_code VARCHAR(50) NOT NULL UNIQUE,
    product_name VARCHAR(150) NOT NULL,
    quantity_in_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('IN','OUT') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaction_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
);

INSERT INTO users (username, password_hash, role)
SELECT 'admin', '$2y$12$FO8o1DnwKHom0wCawRpk/OfD0u3b/gclqOcH7q02b2vr78dJL3wua', 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

INSERT INTO products (sku_code, product_name, quantity_in_stock, reorder_level, unit_price)
SELECT 'SKU-001', 'Laptop Computer', 15, 5, 85000.00
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku_code = 'SKU-001');

INSERT INTO products (sku_code, product_name, quantity_in_stock, reorder_level, unit_price)
SELECT 'SKU-002', 'Wireless Mouse', 32, 10, 2500.00
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku_code = 'SKU-002');

INSERT INTO products (sku_code, product_name, quantity_in_stock, reorder_level, unit_price)
SELECT 'SKU-003', 'Keyboard', 8, 5, 3500.00
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku_code = 'SKU-003');
