CREATE DATABASE IF NOT EXISTS stocksmart_db;
USE stocksmart_db;

DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin','Admin','Staff') DEFAULT 'Staff',
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
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    category_id INT,
    supplier_id INT,
    unit VARCHAR(30) DEFAULT 'units',
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    cost_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    expiry_date DATE,
    batch_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

CREATE TABLE stock_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
    quantity INT NOT NULL,
    note VARCHAR(255),
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(120),
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
    sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    alert_type ENUM('LOW_STOCK','EXPIRY','RESTOCK') NOT NULL,
    message VARCHAR(255) NOT NULL,
    status ENUM('New','Pending','Resolved') DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

INSERT INTO users (full_name, email, password_hash, role) VALUES
('Admin User', 'admin@stocksmart.com', 'admin123', 'Super Admin');

INSERT INTO categories (category_name) VALUES
('Dairy'), ('Grains'), ('Cooking'), ('Bakery'), ('Poultry'), ('Staples'), ('Drinks');

INSERT INTO suppliers (supplier_name, phone, email, address) VALUES
('Fresh Farm Suppliers', '9800000001', 'freshfarm@email.com', 'Kathmandu'),
('Daily Grocery Traders', '9800000002', 'grocery@email.com', 'Lalitpur'),
('Bakery House Supply', '9800000003', 'bakery@email.com', 'Bhaktapur');

INSERT INTO products
(product_name, category_id, supplier_id, unit, stock_quantity, reorder_level, cost_price, selling_price, expiry_date, batch_no)
VALUES
('Butter 500g', 1, 1, 'units', 4, 10, 350, 450, CURDATE() + INTERVAL 4 DAY, 'BC-101'),
('Cooking Oil 1L', 3, 2, 'units', 9, 10, 220, 300, CURDATE() + INTERVAL 90 DAY, 'BC-102'),
('Cheese Slice 10 pcs', 1, 1, 'units', 6, 10, 180, 250, CURDATE() + INTERVAL 6 DAY, 'BC-103'),
('Yoghurt 400g', 1, 1, 'units', 14, 10, 80, 120, CURDATE() + INTERVAL 8 DAY, 'BC-201'),
('Tomato Paste 200g', 6, 2, 'units', 7, 10, 90, 140, CURDATE() + INTERVAL 70 DAY, 'BC-108'),
('Mineral Water 1L', 7, 2, 'units', 18, 10, 20, 35, CURDATE() + INTERVAL 200 DAY, 'BC-301'),
('Cream Cheese 200g', 1, 1, 'units', 14, 10, 200, 300, CURDATE() + INTERVAL 1 DAY, 'BC-204'),
('Fresh Orange Juice 1L', 7, 1, 'units', 22, 10, 130, 200, CURDATE() + INTERVAL 3 DAY, 'BC-198'),
('Sliced Bread 400g', 4, 3, 'units', 18, 10, 70, 120, CURDATE() + INTERVAL 7 DAY, 'BC-211'),
('Paneer 250g', 1, 1, 'units', 11, 10, 150, 220, CURDATE() + INTERVAL 9 DAY, 'BC-209');

INSERT INTO sales (user_id, customer_name, total_amount, sale_date) VALUES
(1, 'Walk-in Customer', 1200, NOW()),
(1, 'Walk-in Customer', 3600, NOW()),
(1, 'Walk-in Customer', 2400, NOW()),
(1, 'Walk-in Customer', 1500, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'Walk-in Customer', 1800, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'Walk-in Customer', 4500, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'Walk-in Customer', 1000, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES
(1, 1, 3, 400, 1200),
(2, 2, 12, 300, 3600),
(3, 3, 8, 300, 2400),
(4, 9, 15, 100, 1500),
(5, 4, 15, 120, 1800),
(6, 10, 20, 225, 4500),
(7, 6, 20, 50, 1000);

INSERT INTO alerts (product_id, alert_type, message, status) VALUES
(1, 'LOW_STOCK', 'Butter 500g is critically low.', 'New'),
(2, 'LOW_STOCK', 'Cooking Oil 1L is low in stock.', 'New'),
(3, 'LOW_STOCK', 'Cheese Slice is critically low.', 'New'),
(5, 'LOW_STOCK', 'Tomato Paste is critically low.', 'New'),
(7, 'EXPIRY', 'Cream Cheese 200g expires soon.', 'Pending'),
(8, 'EXPIRY', 'Fresh Orange Juice 1L expires soon.', 'Pending'),
(4, 'EXPIRY', 'Yoghurt 400g expires soon.', 'Pending'),
(9, 'EXPIRY', 'Sliced Bread 400g expires soon.', 'Pending'),
(10, 'EXPIRY', 'Paneer 250g expires soon.', 'Pending');

USE stocksmart_db;

CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(120),
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sale_items (
    sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    alert_type ENUM('LOW_STOCK','EXPIRY','RESTOCK') NOT NULL,
    message VARCHAR(255) NOT NULL,
    status ENUM('New','Pending','Resolved') DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO sales (user_id, customer_name, total_amount, sale_date) VALUES
(1, 'Walk-in Customer', 1200, NOW()),
(1, 'Walk-in Customer', 3600, NOW()),
(1, 'Walk-in Customer', 2400, NOW());

INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES
(1, 1, 3, 400, 1200),
(2, 2, 12, 300, 3600),
(3, 3, 8, 300, 2400);

INSERT INTO alerts (product_id, alert_type, message, status) VALUES
(1, 'LOW_STOCK', 'Butter 500g is critically low.', 'New'),
(2, 'LOW_STOCK', 'Cooking Oil 1L is low in stock.', 'New'),
(3, 'LOW_STOCK', 'Cheese Slice is critically low.', 'New');