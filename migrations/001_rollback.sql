-- Migration 001 ROLLBACK: Revert Inventory Management System
-- WARNING: Destructive - drops tables and data
-- Run: mysql -u root < migrations/001_rollback.sql

USE flower_shop;

-- Drop in reverse dependency order
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS product_suppliers;
DROP TABLE IF EXISTS suppliers;

-- Restore category varchar column before dropping FK
ALTER TABLE products ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT '' AFTER name;
UPDATE products p JOIN categories c ON c.id = p.category_id SET p.category = c.name;

-- Drop FK and new columns
ALTER TABLE products DROP FOREIGN KEY fk_product_category;
ALTER TABLE products DROP KEY uk_sku;
ALTER TABLE products DROP COLUMN category_id;
ALTER TABLE products DROP COLUMN sku;
ALTER TABLE products DROP COLUMN minimum_stock;

-- Restore original column constraints
ALTER TABLE products MODIFY COLUMN name VARCHAR(23) NOT NULL;
ALTER TABLE products MODIFY COLUMN stock INT NOT NULL CHECK (stock >= 0 AND stock <= 20);

-- Drop remaining tables
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS users;
