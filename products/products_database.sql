CREATE DATABASE IF NOT EXISTS stocksmart;
USE stocksmart;

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