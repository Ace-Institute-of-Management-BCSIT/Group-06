-- stocksmart_inventory_database.sql
-- This SQL file creates the StockSmart inventory database and sample data.
-- Import this file in phpMyAdmin.

CREATE DATABASE IF NOT EXISTS stocksmart_db;
USE stocksmart_db;

DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS inventory_stock;
DROP TABLE IF EXISTS inventory_locations;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin', 'Admin', 'Staff') DEFAULT 'Staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(120),
    address VARCHAR(255)
);

CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    product_image VARCHAR(20),
    category_id INT,
    supplier_id INT,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

CREATE TABLE inventory_locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    location_type VARCHAR(80)
);

CREATE TABLE inventory_stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    in_stock INT NOT NULL DEFAULT 0,
    reserved INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(location_id) ON DELETE CASCADE
);

CREATE TABLE inventory_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    movement_type ENUM('Stock In', 'Stock Out', 'Adjustment') NOT NULL,
    quantity INT NOT NULL,
    movement_note VARCHAR(255),
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(location_id) ON DELETE CASCADE
);

INSERT INTO users (full_name, email, password_hash, role) VALUES
('Admin User', 'admin@stocksmart.com', 'admin123', 'Super Admin');

INSERT INTO categories (category_name) VALUES
('Dairy'),
('Grocery'),
('Beverages'),
('Bakery'),
('Cooking'),
('Others');

INSERT INTO suppliers (supplier_name, phone, email, address) VALUES
('Fresh Farm Suppliers', '9800000001', 'freshfarm@email.com', 'Kathmandu'),
('Daily Grocery Traders', '9800000002', 'grocery@email.com', 'Lalitpur'),
('Bakery House Supply', '9800000003', 'bakery@email.com', 'Bhaktapur');

INSERT INTO inventory_locations (location_name, location_type) VALUES
('Main Warehouse', 'Warehouse'),
('Cold Storage', 'Storage'),
('Outlet Store 1', 'Retail Outlet'),
('Outlet Store 2', 'Retail Outlet');

INSERT INTO products 
(product_name, sku, product_image, category_id, supplier_id, unit_cost, selling_price, reorder_level)
VALUES
('Butter 500g', 'DAIRY-001', 'BTR', 1, 1, 350.00, 450.00, 10),
('Cooking Oil 1L', 'COOK-002', 'OIL', 5, 2, 220.00, 300.00, 10),
('Cheese Slice 10 pcs', 'DAIRY-003', 'CHS', 1, 1, 180.00, 250.00, 10),
('Yoghurt 400g', 'DAIRY-004', 'YOG', 1, 1, 80.00, 120.00, 10),
('Tomato Paste 200g', 'GROC-005', 'TOM', 2, 2, 90.00, 140.00, 10),
('Mineral Water 1L', 'BEV-006', 'WTR', 3, 2, 20.00, 35.00, 20),
('Sliced Bread 400g', 'BAKE-007', 'BRD', 4, 3, 70.00, 120.00, 15),
('Paneer 250g', 'DAIRY-008', 'PNR', 1, 1, 150.00, 220.00, 10),
('Rice 5kg', 'GROC-009', 'RCE', 2, 2, 650.00, 850.00, 15),
('Soft Drink 1L', 'BEV-010', 'DRK', 3, 2, 85.00, 130.00, 20);

INSERT INTO inventory_stock (product_id, location_id, in_stock, reserved) VALUES
(1, 1, 4, 1),
(2, 1, 9, 0),
(3, 1, 6, 2),
(4, 2, 14, 3),
(5, 1, 7, 0),
(6, 3, 120, 10),
(7, 1, 30, 4),
(8, 2, 11, 1),
(9, 1, 65, 8),
(10, 4, 45, 5);

INSERT INTO inventory_movements 
(product_id, location_id, movement_type, quantity, movement_note)
VALUES
(1, 1, 'Stock Out', 6, 'Sold from main warehouse'),
(2, 1, 'Stock In', 20, 'New supplier delivery'),
(3, 1, 'Stock Out', 4, 'Sold items'),
(4, 2, 'Stock In', 25, 'Cold storage restock'),
(6, 3, 'Stock In', 100, 'Outlet stock update');
