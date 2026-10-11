-- SHADRIL SHOPPING STORE v4 MARKETPLACE CATALOG
-- Run this AFTER migration_shadril_v3.sql has completed.
-- Select if0_43082226_inventory in phpMyAdmin first.
-- This migration is safe to run more than once because products use unique SKU codes.

INSERT IGNORE INTO categories(name) VALUES
('Phones'),('Fashion'),('Food & Groceries'),('Televisions'),('Home Appliances'),
('Computers'),('Beauty & Personal Care'),('Baby Products'),('Sports & Fitness'),('Electronics');

INSERT IGNORE INTO products
(sku_code,product_name,quantity_in_stock,reorder_level,unit_price,category_id,description,status,image_url,compare_at_price,brand,is_featured)
VALUES
('SH-PH-001','Samsung Galaxy A55 5G',12,3,49999,(SELECT category_id FROM categories WHERE name='Phones' LIMIT 1),'6.6-inch display, 5G connectivity, premium camera system and long-lasting battery.','ACTIVE','https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=85',55999,'Samsung',1),
('SH-PH-002','iPhone 15',8,2,109999,(SELECT category_id FROM categories WHERE name='Phones' LIMIT 1),'Modern smartphone with advanced camera, strong performance and USB-C charging.','ACTIVE','https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=800&q=85',119999,'Apple',1),
('SH-TV-001','55-inch 4K Smart TV',6,2,64999,(SELECT category_id FROM categories WHERE name='Televisions' LIMIT 1),'4K smart television with streaming apps, vivid display and modern slim design.','ACTIVE','https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=800&q=85',74999,'Shadril Home',1),
('SH-EL-001','Digital Microwave Oven',9,2,6370,(SELECT category_id FROM categories WHERE name='Home Appliances' LIMIT 1),'Compact microwave oven for fast everyday cooking and reheating.','ACTIVE','https://images.unsplash.com/photo-1585659722983-3a675dabf23d?auto=format&fit=crop&w=800&q=85',10999,'HomePro',1),
('SH-EL-002','2-in-1 Blender Grinder',15,4,1799,(SELECT category_id FROM categories WHERE name='Home Appliances' LIMIT 1),'Compact blender and grinder for kitchen preparation.','ACTIVE','https://images.unsplash.com/photo-1570222094114-d054a817e56b?auto=format&fit=crop&w=800&q=85',2300,'FreshMix',1),
('SH-PC-001','Business Laptop 15-inch',7,2,85000,(SELECT category_id FROM categories WHERE name='Computers' LIMIT 1),'Professional laptop suitable for business, study, coding and everyday productivity.','ACTIVE','https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=85',95000,'BusinessTech',1),
('SH-PC-002','Wireless Keyboard',20,5,3500,(SELECT category_id FROM categories WHERE name='Computers' LIMIT 1),'Comfortable wireless keyboard for office and home use.','ACTIVE','https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=800&q=85',4500,'OfficePro',0),
('SH-PC-003','Wireless Mouse',25,6,2500,(SELECT category_id FROM categories WHERE name='Computers' LIMIT 1),'Reliable wireless mouse with ergonomic design for work and study.','ACTIVE','https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=800&q=85',3200,'OfficePro',0),
('SH-FS-001','Men''s Beige Cargo Trousers',18,4,1549,(SELECT category_id FROM categories WHERE name='Fashion' LIMIT 1),'Comfortable cotton cargo trousers for everyday wear.','ACTIVE','https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=85',2999,'Shadril Fashion',1),
('SH-FS-002','3-Piece Baseball Cap Set',24,5,599,(SELECT category_id FROM categories WHERE name='Fashion' LIMIT 1),'Adjustable caps suitable for casual everyday style.','ACTIVE','https://images.unsplash.com/photo-1521369909029-2afed882baee?auto=format&fit=crop&w=800&q=85',893,'UrbanStyle',0),
('SH-FS-003','Classic Cotton T-Shirt',30,8,999,(SELECT category_id FROM categories WHERE name='Fashion' LIMIT 1),'Soft everyday cotton T-shirt with a clean modern fit.','ACTIVE','https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=85',1499,'UrbanStyle',0),
('SH-FD-001','Fresh Cabbage',40,10,230,(SELECT category_id FROM categories WHERE name='Food & Groceries' LIMIT 1),'Fresh farm cabbage for household cooking.','ACTIVE','https://images.unsplash.com/photo-1597362925123-77861d3fbac7?auto=format&fit=crop&w=800&q=85',280,'Shadril Fresh',1),
('SH-FD-002','Premium Rice 5kg',35,8,950,(SELECT category_id FROM categories WHERE name='Food & Groceries' LIMIT 1),'Quality rice packed for everyday family meals.','ACTIVE','https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=800&q=85',1100,'Shadril Fresh',0),
('SH-FD-003','Cooking Oil 1L',28,8,320,(SELECT category_id FROM categories WHERE name='Food & Groceries' LIMIT 1),'Everyday cooking oil for home meals.','ACTIVE','https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=800&q=85',390,'Shadril Fresh',0),
('SH-BT-001','Moisturizing Body Lotion',22,5,750,(SELECT category_id FROM categories WHERE name='Beauty & Personal Care' LIMIT 1),'Daily moisturizing care for soft and healthy-looking skin.','ACTIVE','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=800&q=85',950,'CarePlus',0),
('SH-BB-001','Baby Diapers Pack',16,4,1350,(SELECT category_id FROM categories WHERE name='Baby Products' LIMIT 1),'Comfortable absorbent diapers for babies.','ACTIVE','https://images.unsplash.com/photo-1604917018611-1c9a4c8c1b5b?auto=format&fit=crop&w=800&q=85',1600,'BabyCare',0),
('SH-SP-001','Adjustable Dumbbells',10,2,4500,(SELECT category_id FROM categories WHERE name='Sports & Fitness' LIMIT 1),'Adjustable weights for home workouts and strength training.','ACTIVE','https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?auto=format&fit=crop&w=800&q=85',5200,'FitLife',0),
('SH-EL-003','Bluetooth Portable Speaker',14,3,2999,(SELECT category_id FROM categories WHERE name='Electronics' LIMIT 1),'Portable wireless speaker with clear sound for home and outdoor use.','ACTIVE','https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=800&q=85',3999,'SoundPro',1)
ON DUPLICATE KEY UPDATE product_name=VALUES(product_name);

INSERT INTO store_settings(setting_key,setting_value) VALUES
('store_tagline','Shop smart. Live better.'),
('delivery_message','Kenya-wide delivery and order tracking.'),
('founder_name','Shadrack Otuma'),
('founder_role','Founder & Developer')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
