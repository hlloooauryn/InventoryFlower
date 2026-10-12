-- Migration 001: Inventory Management System
-- Forward path - preserves existing product data
-- Run: mysql -u root < migrations/001_inventory_system.sql

USE flower_shop;

-- 1. Users table
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. User profiles (one-to-one with users)
CREATE TABLE IF NOT EXISTS user_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Categories table
CREATE TABLE IF NOT EXISTS categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Seed categories from existing hardcoded values
INSERT IGNORE INTO categories (name) VALUES
  ('Bunga Potong'), ('Bunga Hias'), ('Tanaman Hias'), ('Buket'), ('Aksesori');

-- 5. Expand products table - add new columns first
ALTER TABLE products
  ADD COLUMN sku VARCHAR(50) NOT NULL DEFAULT '' AFTER id,
  ADD COLUMN category_id INT NULL AFTER name,
  ADD COLUMN minimum_stock INT NOT NULL DEFAULT 5 AFTER stock;

-- 6. Widen name column
ALTER TABLE products MODIFY COLUMN name VARCHAR(150) NOT NULL;

-- 7. Remove stock upper bound constraint if exists
-- MariaDB/MySQL may not support named CHECK drop, so we handle gracefully
SET @has_check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = 'flower_shop' AND TABLE_NAME = 'products' AND CONSTRAINT_TYPE = 'CHECK');
-- We'll just modify the column to effectively remove the CHECK
ALTER TABLE products MODIFY COLUMN stock INT NOT NULL DEFAULT 0;

-- 8. Migrate category string -> category_id FK
UPDATE products p
  JOIN categories c ON c.name = p.category
  SET p.category_id = c.id;

-- 9. Handle products with categories not in seed (assign to first category)
UPDATE products SET category_id = 1 WHERE category_id IS NULL;

-- 10. Generate SKU for existing products
UPDATE products SET sku = CONCAT('FL-', LPAD(id, 5, '0')) WHERE sku = '';

-- 11. Add unique constraint on SKU
ALTER TABLE products ADD UNIQUE KEY uk_sku (sku);

-- 12. Make category_id NOT NULL and add FK
ALTER TABLE products MODIFY COLUMN category_id INT NOT NULL;
ALTER TABLE products
  ADD CONSTRAINT fk_product_category
  FOREIGN KEY (category_id) REFERENCES categories(id)
  ON UPDATE CASCADE ON DELETE RESTRICT;

-- 13. Drop old category varchar column
ALTER TABLE products DROP COLUMN category;

-- 14. Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  contact VARCHAR(100),
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 15. Junction table for many-to-many products <-> suppliers
CREATE TABLE IF NOT EXISTS product_suppliers (
  product_id INT NOT NULL,
  supplier_id INT NOT NULL,
  supplier_price DECIMAL(12,2),
  PRIMARY KEY (product_id, supplier_id),
  FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. Stock movements (transaction history)
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('IN', 'OUT') NOT NULL,
  quantity INT NOT NULL,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 17. Seed default admin user (password: admin123)
INSERT INTO users (username, password_hash, role) VALUES
  ('admin', '$2y$12$XQ1XqfvdL/EFw6diMHD3Ken27E1OHe.kge.JG5VbtRUC4akEd7k3S', 'admin');

INSERT INTO user_profiles (user_id, full_name) VALUES
  (LAST_INSERT_ID(), 'Administrator');
