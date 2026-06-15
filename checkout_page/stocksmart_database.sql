CREATE DATABASE IF NOT EXISTS stocksmart;
USE stocksmart;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS products;

CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    sku VARCHAR(50),
    stock INT DEFAULT 0,
    price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    image_path VARCHAR(255),
    status VARCHAR(20),
    is_combo TINYINT(1) DEFAULT 0
);

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_code VARCHAR(50),
    payment_method VARCHAR(100),
    subtotal DECIMAL(10,2),
    discount_total DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    order_status VARCHAR(50) DEFAULT 'Open Amount',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    item_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE payment_methods (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(100) NOT NULL,
    image_path VARCHAR(255)
);

INSERT INTO products
(product_name, category, sku, stock, price, discount, image_path, status, is_combo)
VALUES

('Butter 500g','Dairy','DAIRY-001',4,120,0,'assets/butter.png','Low Stock',0),

('Cooking Oil 1L','Cooking','COOK-002',9,220,0,'assets/oil.png','Low Stock',0),

('Cheese Slice (10)','Dairy','DAIRY-003',6,180,0,'assets/cheese.png','Low Stock',0),

('Yoghurt 400g','Dairy','DAIRY-004',14,85,0,'assets/yoghurt.png','In Stock',0),

('Tomato Paste 200g','Grocery','GROC-005',7,60,0,'assets/tomato.png','Low Stock',0),

('Mineral Water 1L','Beverages','BEV-006',18,30,0,'assets/water.png','In Stock',0),

('Brown Bread 400g','Bakery','BAK-007',25,70,0,'assets/bread.png','In Stock',0),

('Basmati Rice 1kg','Grocery','GROC-008',32,150,0,'assets/rice.png','In Stock',0),

('Burger, Fries & Softdrink','Combo','BFT-CMB-162',10,1064,0,'assets/burger-combo.png','In Stock',1);

INSERT INTO payment_methods 
(method_name, image_path) VALUES
('Card Payment', 'assets/card.png'),
('QR Scan', 'assets/qr.png'),
('Contactless', 'assets/contactless.png'),
('Nepal Pay Wallet', 'assets/nepalpay.png');

INSERT INTO orders 
(receipt_code, payment_method, subtotal, discount_total, total_amount, order_status)
VALUES
('00061000001074', NULL, 3766, 0, 3766, 'Open Amount');

INSERT INTO order_items 
(order_id, product_id, quantity, item_price) VALUES
(1, 1, 2, 350),
(1, 2, 1, 632),
(1, 3, 1, 434),
(1, 4, 1, 602),
(1, 5, 1, 434),
(1, 6, 1, 1064);