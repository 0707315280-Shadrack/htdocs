-- SHADRIL SHOPPING STORE: READ-ONLY DATABASE DIAGNOSTIC
-- SAFE: SELECT statements only. This file does not insert, update, delete,
-- create, alter, or drop any database object or payment/order record.
-- In phpMyAdmin, select the existing if0_43082226_inventory database,
-- open the SQL tab, paste this file, and Run. Share the displayed results.

SELECT DATABASE() AS selected_database;

SELECT required.table_name AS required_table,
       CASE WHEN actual.table_name IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS table_status
FROM (
  SELECT 'products' AS table_name UNION ALL SELECT 'categories' UNION ALL
  SELECT 'customers' UNION ALL SELECT 'users' UNION ALL SELECT 'orders' UNION ALL
  SELECT 'order_items' UNION ALL SELECT 'transactions' UNION ALL
  SELECT 'store_config' UNION ALL SELECT 'system_health_log' UNION ALL
  SELECT 'system_alerts' UNION ALL SELECT 'support_tickets' UNION ALL
  SELECT 'support_replies' UNION ALL SELECT 'product_reviews' UNION ALL
  SELECT 'customer_private_messages' UNION ALL SELECT 'mpesa_payments' UNION ALL
  SELECT 'mpesa_callback_logs' UNION ALL SELECT 'mpesa_stk_payments' UNION ALL
  SELECT 'admin_payment_notifications'
) required
LEFT JOIN information_schema.tables actual
  ON actual.table_schema = DATABASE() AND actual.table_name = required.table_name
ORDER BY required_table;

SELECT column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'mpesa_payments'
ORDER BY ordinal_position;

SELECT column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'orders'
  AND column_name IN ('order_id','customer_id','status','total_amount','payment_method','coupon_code','discount_amount','tracking_location','tracking_eta')
ORDER BY ordinal_position;

SELECT 'products' AS item, COUNT(*) AS row_count FROM products
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'orders', COUNT(*) FROM orders
UNION ALL SELECT 'customers', COUNT(*) FROM customers
UNION ALL SELECT 'mpesa_payments', COUNT(*) FROM mpesa_payments;

SELECT status, payment_method, COUNT(*) AS order_count
FROM orders
GROUP BY status, payment_method
ORDER BY status, payment_method;

-- Deliberately excludes phone numbers, callback payloads, passwords and secrets.
SELECT id, order_id, customer_id, amount, status, result_code,
       callback_received, created_at, updated_at
FROM mpesa_payments
ORDER BY id DESC
LIMIT 10;
