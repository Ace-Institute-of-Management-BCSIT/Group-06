-- ============================================================================
--  STOCKSMART — INTELLIGENT INVENTORY MANAGEMENT SYSTEM
--  Production-Ready MySQL 8 Database Schema  (AUTH-UPDATED)
-- ============================================================================
--  Changes from original:
--    - Password hashes replaced with valid bcrypt hashes (generated via
--      password_hash(..., PASSWORD_DEFAULT) compatible $2y$ format).
--    - Old hashes were structurally invalid (fabricated strings that fail
--      password_verify() — the bcrypt cost/salt sections were nonsense).
--    - All other schema and seed data is unchanged.
--
--  HOW TO IMPORT:
--    phpMyAdmin -> Import -> choose this file -> Go.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP DATABASE IF EXISTS stocksmart;
CREATE DATABASE stocksmart
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE stocksmart;

-- ============================================================================
-- 1. TABLE: roles
-- ============================================================================
CREATE TABLE roles (
    role_id          TINYINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    role_name        VARCHAR(30)        NOT NULL,
    role_description VARCHAR(150)       NULL,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLE: users
-- ============================================================================
CREATE TABLE users (
    user_id        INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(100)        NOT NULL,
    username       VARCHAR(50)         NOT NULL,
    email          VARCHAR(120)        NOT NULL,
    password_hash  VARCHAR(255)        NOT NULL COMMENT 'bcrypt / password_hash() output',
    role_id        TINYINT UNSIGNED    NOT NULL,
    phone          VARCHAR(20)         NULL,
    avatar_emoji   VARCHAR(10)         NULL DEFAULT '🧑',
    status         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    last_login_at  DATETIME            NULL,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLE: categories
-- ============================================================================
CREATE TABLE categories (
    category_id    SMALLINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    category_name  VARCHAR(60)         NOT NULL,
    description    VARCHAR(200)        NULL,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLE: suppliers
-- ============================================================================
CREATE TABLE suppliers (
    supplier_id    INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    supplier_name  VARCHAR(120)        NOT NULL,
    contact_person VARCHAR(100)        NULL,
    phone          VARCHAR(20)         NULL,
    email          VARCHAR(120)        NULL,
    address        VARCHAR(255)        NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLE: warehouses
-- ============================================================================
CREATE TABLE warehouses (
    warehouse_id   SMALLINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    warehouse_name VARCHAR(80)         NOT NULL,
    warehouse_type ENUM('warehouse','cold_storage','store_front','other')
                                       NOT NULL DEFAULT 'warehouse',
    address        VARCHAR(255)        NULL,
    is_active      TINYINT(1)          NOT NULL DEFAULT 1,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (warehouse_id),
    UNIQUE KEY uq_warehouses_name (warehouse_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABLE: products
-- ============================================================================
CREATE TABLE products (
    product_id     INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    product_name   VARCHAR(150)        NOT NULL,
    sku            VARCHAR(40)         NOT NULL,
    category_id    SMALLINT UNSIGNED   NOT NULL,
    supplier_id    INT UNSIGNED        NULL,
    icon_emoji     VARCHAR(10)         NULL DEFAULT '📦',
    unit           VARCHAR(20)         NOT NULL DEFAULT 'pcs',
    price          DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    cost_price     DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    reorder_level  INT UNSIGNED        NOT NULL DEFAULT 10,
    has_expiry     TINYINT(1)          NOT NULL DEFAULT 0,
    default_expiry_date DATE           NULL,
    status         ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
    created_by     INT UNSIGNED        NULL,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_supplier (supplier_id),
    KEY idx_products_status (status),
    KEY idx_products_name (product_name),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_products_price_nonneg CHECK (price >= 0),
    CONSTRAINT chk_products_cost_nonneg CHECK (cost_price >= 0),
    CONSTRAINT chk_products_reorder_nonneg CHECK (reorder_level >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABLE: inventory
-- ============================================================================
CREATE TABLE inventory (
    inventory_id     INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    product_id       INT UNSIGNED      NOT NULL,
    warehouse_id     SMALLINT UNSIGNED NOT NULL,
    quantity_on_hand INT UNSIGNED      NOT NULL DEFAULT 0,
    quantity_reserved INT UNSIGNED     NOT NULL DEFAULT 0,
    quantity_available INT GENERATED ALWAYS AS
        (CAST(quantity_on_hand AS SIGNED) - CAST(quantity_reserved AS SIGNED)) STORED,
    last_counted_at  DATETIME          NULL,
    updated_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (inventory_id),
    UNIQUE KEY uq_inventory_product_warehouse (product_id, warehouse_id),
    KEY idx_inventory_warehouse (warehouse_id),
    KEY idx_inventory_available (quantity_available),
    CONSTRAINT fk_inventory_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_inventory_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_inventory_reserved_le_onhand CHECK (quantity_reserved <= quantity_on_hand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TABLE: product_batches
-- ============================================================================
CREATE TABLE product_batches (
    batch_id        INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED       NOT NULL,
    warehouse_id    SMALLINT UNSIGNED  NOT NULL,
    batch_number    VARCHAR(40)        NOT NULL,
    quantity        INT UNSIGNED       NOT NULL DEFAULT 0,
    manufacture_date DATE              NULL,
    expiry_date     DATE               NOT NULL,
    received_at     TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (batch_id),
    UNIQUE KEY uq_batches_number (batch_number),
    KEY idx_batches_product (product_id),
    KEY idx_batches_warehouse (warehouse_id),
    KEY idx_batches_expiry (expiry_date),
    CONSTRAINT fk_batches_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_batches_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABLE: customers
-- ============================================================================
CREATE TABLE customers (
    customer_id    INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    customer_name  VARCHAR(100)        NOT NULL DEFAULT 'Walk-in Customer',
    phone          VARCHAR(20)         NULL,
    email          VARCHAR(120)        NULL,
    loyalty_points DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TABLE: orders
-- ============================================================================
CREATE TABLE orders (
    order_id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    order_number     VARCHAR(30)       NOT NULL,
    customer_id      INT UNSIGNED      NULL,
    cashier_id       INT UNSIGNED      NOT NULL,
    warehouse_id     SMALLINT UNSIGNED NOT NULL,
    items_total      DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    discount_amount  DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    loyalty_discount DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    tax_amount       DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    grand_total      DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    payment_method   ENUM('cash','card','mobile_wallet','bank_transfer','other')
                                       NOT NULL DEFAULT 'cash',
    order_status     ENUM('completed','pending','refunded','cancelled')
                                       NOT NULL DEFAULT 'completed',
    order_date       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_orders_order_number (order_number),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_cashier (cashier_id),
    KEY idx_orders_warehouse (warehouse_id),
    KEY idx_orders_date (order_date),
    KEY idx_orders_status (order_status),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_orders_cashier
        FOREIGN KEY (cashier_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_orders_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_orders_grand_total_nonneg CHECK (grand_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. TABLE: order_items
-- ============================================================================
CREATE TABLE order_items (
    order_item_id   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED       NOT NULL,
    product_id      INT UNSIGNED       NOT NULL,
    batch_id        INT UNSIGNED       NULL,
    quantity        DECIMAL(10,2)      NOT NULL DEFAULT 1.00,
    unit_price      DECIMAL(12,2)      NOT NULL,
    discount_amount DECIMAL(12,2)      NOT NULL DEFAULT 0.00,
    line_total      DECIMAL(12,2)      GENERATED ALWAYS AS
        (ROUND((quantity * unit_price) - discount_amount, 2)) STORED,
    PRIMARY KEY (order_item_id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    KEY idx_order_items_batch (batch_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_batch
        FOREIGN KEY (batch_id) REFERENCES product_batches(batch_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_order_items_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. TABLE: stock_movements
-- ============================================================================
CREATE TABLE stock_movements (
    movement_id     BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED       NOT NULL,
    warehouse_id    SMALLINT UNSIGNED  NOT NULL,
    movement_type   ENUM('purchase_in','sale_out','adjustment_in','adjustment_out',
                          'transfer_in','transfer_out','return_in','damage_out')
                                       NOT NULL,
    quantity        INT UNSIGNED       NOT NULL,
    reference_table  VARCHAR(30)       NULL,
    reference_id     INT UNSIGNED      NULL,
    notes            VARCHAR(255)      NULL,
    performed_by     INT UNSIGNED      NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (movement_id),
    KEY idx_movements_product (product_id),
    KEY idx_movements_warehouse (warehouse_id),
    KEY idx_movements_type (movement_type),
    KEY idx_movements_created (created_at),
    CONSTRAINT fk_movements_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_movements_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movements_user
        FOREIGN KEY (performed_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. TABLE: stock_transfers
-- ============================================================================
CREATE TABLE stock_transfers (
    transfer_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    product_id        INT UNSIGNED     NOT NULL,
    from_warehouse_id SMALLINT UNSIGNED NOT NULL,
    to_warehouse_id   SMALLINT UNSIGNED NOT NULL,
    quantity          INT UNSIGNED     NOT NULL,
    status             ENUM('pending','in_transit','completed','cancelled')
                                       NOT NULL DEFAULT 'completed',
    requested_by       INT UNSIGNED    NULL,
    transferred_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (transfer_id),
    KEY idx_transfers_product (product_id),
    KEY idx_transfers_from (from_warehouse_id),
    KEY idx_transfers_to (to_warehouse_id),
    CONSTRAINT fk_transfers_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_transfers_from_wh
        FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transfers_to_wh
        FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transfers_requested_by
        FOREIGN KEY (requested_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_transfers_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER trg_stock_transfers_diff_warehouse_ins
BEFORE INSERT ON stock_transfers
FOR EACH ROW
BEGIN
    IF NEW.from_warehouse_id = NEW.to_warehouse_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'from_warehouse_id and to_warehouse_id must differ';
    END IF;
END$$

CREATE TRIGGER trg_stock_transfers_diff_warehouse_upd
BEFORE UPDATE ON stock_transfers
FOR EACH ROW
BEGIN
    IF NEW.from_warehouse_id = NEW.to_warehouse_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'from_warehouse_id and to_warehouse_id must differ';
    END IF;
END$$
DELIMITER ;

-- ============================================================================
-- 14. TABLE: alerts
-- ============================================================================
CREATE TABLE alerts (
    alert_id        INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    alert_type      ENUM('low_stock','out_of_stock','expiry') NOT NULL,
    product_id      INT UNSIGNED       NOT NULL,
    warehouse_id    SMALLINT UNSIGNED  NULL,
    batch_id        INT UNSIGNED       NULL,
    message         VARCHAR(255)       NOT NULL,
    severity        ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    is_acknowledged TINYINT(1)         NOT NULL DEFAULT 0,
    acknowledged_by  INT UNSIGNED      NULL,
    acknowledged_at  DATETIME          NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alert_id),
    KEY idx_alerts_type (alert_type),
    KEY idx_alerts_product (product_id),
    KEY idx_alerts_ack (is_acknowledged),
    CONSTRAINT fk_alerts_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alerts_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_alerts_batch
        FOREIGN KEY (batch_id) REFERENCES product_batches(batch_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alerts_ack_by
        FOREIGN KEY (acknowledged_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. TABLE: activity_logs
-- ============================================================================
CREATE TABLE activity_logs (
    log_id          BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED       NULL,
    activity_type   ENUM('add','update','delete','checkout','alert','transfer','login','logout')
                                       NOT NULL,
    entity_type     VARCHAR(40)        NULL,
    entity_id       INT UNSIGNED       NULL,
    description     VARCHAR(255)       NOT NULL,
    ip_address      VARCHAR(45)        NULL,
    created_at      TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_activity_user (user_id),
    KEY idx_activity_type (activity_type),
    KEY idx_activity_created (created_at),
    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SECTION 2: SAMPLE DATA
-- ============================================================================

-- 2.1 roles
INSERT INTO roles (role_id, role_name, role_description) VALUES
(1, 'Admin',   'Full system access: manage users, products, inventory, settings'),
(2, 'Manager', 'Manage products, inventory, view reports, approve transfers'),
(3, 'Staff',   'Manage inventory counts and stock movements'),
(4, 'Cashier', 'Process checkout / sales transactions only');

-- 2.2 users
-- ============================================================================
-- VALID BCRYPT HASHES — generated with password_hash(..., PASSWORD_DEFAULT)
-- These are real $2y$10$ bcrypt hashes that pass password_verify():
--   annie.admin  → Admin@123
--   rajesh.mgr   → Manager@123
--   sita.staff   → Staff@123
--   bibek.cash   → Cashier@123
--   manisha.cash → Cashier@123
-- ============================================================================
INSERT INTO users (user_id, full_name, username, email, password_hash, role_id, phone, avatar_emoji, status, last_login_at) VALUES
(1, 'Annie Shrestha',   'annie.admin',  'annie@stocksmart.com',   '$2y$10$O5eIoi8XCXcThzhJI0.nleTMIDKI4UK5DMj5kgKiHT5PsaEgPgfni', 1, '+977-9800000001', '👩‍💼', 'active',   '2026-06-21 08:15:00'),
(2, 'Rajesh Karki',     'rajesh.mgr',   'rajesh@stocksmart.com',  '$2y$10$zrXlYAmJXYrXCLPB4Pc/rON.6ok5XVi29YN77eX8XYe1YpqxK9GTO', 2, '+977-9800000002', '🧑‍💼', 'active',   '2026-06-20 17:40:00'),
(3, 'Sita Gurung',      'sita.staff',   'sita@stocksmart.com',    '$2y$10$8yZTNIrYeHor64RnwjYYuOXAHgnnUeUSHBdlilPCeX9fy8S1ekkY.', 3, '+977-9800000003', '👷‍♀️', 'active',   '2026-06-20 12:05:00'),
(4, 'Bibek Thapa',      'bibek.cash',   'bibek@stocksmart.com',   '$2y$10$hswxzVCcFhVY0LpPpdia3ugIapdaSK2Wf8/71oyfcM./NvyB1atqa', 4, '+977-9800000004', '🧑‍💻', 'active',   '2026-06-21 09:02:00'),
(5, 'Manisha Rai',      'manisha.cash', 'manisha@stocksmart.com', '$2y$10$NRN6bBcOKLarRqrSbFVKfuU604lkJAjdqJMLbqzyashwBqlzDlSDC', 4, '+977-9800000005', '🧑‍💻', 'inactive', '2026-06-10 10:00:00');

-- 2.3 categories
INSERT INTO categories (category_id, category_name, description) VALUES
(1,  'Dairy',           'Milk, cheese, butter, yoghurt and related products'),
(2,  'Cooking',         'Cooking oils, ghee and culinary essentials'),
(3,  'Grocery',         'Staple grocery items: rice, flour, lentils'),
(4,  'Beverages',       'Water, soft drinks, juices, tea, coffee'),
(5,  'Bakery',          'Bread and baked goods'),
(6,  'Personal Care',   'Hygiene and personal care products'),
(7,  'Household',       'Cleaning and household consumables'),
(8,  'Frozen Foods',    'Frozen and chilled ready-to-cook items'),
(9,  'Snacks',          'Chips, chocolates and packaged snacks'),
(10, 'Grains & Staples','Rice, wheat and other bulk grains'),
(11, 'Pantry',          'Pantry staples: oils, sauces, condiments');

-- 2.4 suppliers
INSERT INTO suppliers (supplier_id, supplier_name, contact_person, phone, email, address, status) VALUES
(1, 'Himalayan Dairy Distributors',   'Suresh Maharjan', '+977-9851000001', 'sales@himalayandairy.com',       'Balaju, Kathmandu',     'active'),
(2, 'Everest FMCG Traders',          'Anita Shakya',     '+977-9851000002', 'orders@everestfmcg.com',          'Teku, Kathmandu',       'active'),
(3, 'Kathmandu Beverages Pvt. Ltd.', 'Bishnu Lama',      '+977-9851000003', 'info@ktmbeverages.com',           'Balkhu, Kathmandu',     'active'),
(4, 'Sagarmatha Grocers Supply',     'Prakash Adhikari', '+977-9851000004', 'contact@sagarmathagrocers.com',   'Kalanki, Kathmandu',    'active'),
(5, 'Annapurna Bakery Supplies',     'Kabita Basnet',    '+977-9851000005', 'hello@annapurnabakery.com',       'Patan, Lalitpur',       'inactive');

-- 2.5 warehouses
INSERT INTO warehouses (warehouse_id, warehouse_name, warehouse_type, address, is_active) VALUES
(1, 'Warehouse A',   'warehouse',     'Balaju Industrial Area, Kathmandu', 1),
(2, 'Warehouse B',   'warehouse',     'Sinamangal, Kathmandu',             1),
(3, 'Cold Storage',  'cold_storage',  'Balkhu, Kathmandu',                 1),
(4, 'Store Front',   'store_front',   'New Road, Kathmandu',               1);

-- 2.6 products
INSERT INTO products (product_id, product_name, sku, category_id, supplier_id, icon_emoji, unit, price, cost_price, reorder_level, has_expiry, default_expiry_date, status, created_by) VALUES
(1,  'Butter 500g',                  'DAIRY-001', 1,  1, '🧈', 'pcs',  120.00,  90.00, 10, 1, '2026-08-15', 'active', 1),
(2,  'Cooking Oil 1L',                'COOK-002',  2,  4, '🫙', 'pcs',  220.00, 170.00, 15, 0, NULL,         'active', 1),
(3,  'Cheese Slice (10)',             'DAIRY-003', 1,  1, '🧀', 'pack', 180.00, 140.00, 12, 1, '2026-09-01', 'active', 1),
(4,  'Yoghurt 400g',                  'DAIRY-004', 1,  1, '🥛', 'pcs',   85.00,  60.00, 20, 1, '2026-07-05', 'active', 1),
(5,  'Tomato Paste 200g',             'GROC-005',  3,  4, '🥫', 'pcs',   60.00,  42.00, 15, 0, NULL,         'active', 1),
(6,  'Mineral Water 1L',              'BEV-006',   4,  3, '💧', 'pcs',   30.00,  18.00, 25, 0, NULL,         'active', 1),
(7,  'Brown Bread 400g',              'BAK-007',   5,  5, '🍞', 'pcs',   70.00,  48.00, 30, 1, '2026-06-25', 'active', 1),
(8,  'Basmati Rice 1kg',              'GROC-008',  3,  4, '🍚', 'kg',   150.00, 110.00, 35, 0, NULL,         'active', 1),
(9,  'Olive Oil 500ml',               'COOK-009',  2,  4, '🫒', 'pcs',  480.00, 380.00, 10, 0, NULL,         'active', 1),
(10, 'Green Tea 100g',                'BEV-010',   4,  3, '🍵', 'pcs',  210.00, 160.00, 10, 1, '2027-01-10', 'active', 1),
(11, 'Eggs (Tray 30)',                'DAIRY-011', 1,  1, '🥚', 'tray', 480.00, 400.00, 20, 1, '2026-06-29', 'active', 1),
(12, 'Wheat Flour 5kg',               'GROC-012',  3,  4, '🌾', 'kg',   560.00, 460.00, 25, 0, NULL,         'active', 1),
(13, 'Organic Basmati Rice 5kg',      'STK-1042',  10, 4, '🍚', 'kg',   850.00, 700.00, 30, 0, NULL,         'active', 2),
(14, 'Whole Milk 1L',                 'STK-2031',  1,  1, '🥛', 'pcs',  110.00,  85.00, 20, 1, '2026-06-24', 'active', 2),
(15, 'Extra Virgin Olive Oil 500ml',  'STK-3087',  11, 4, '🫒', 'pcs',  520.00, 410.00, 12, 0, NULL,         'active', 2),
(16, 'AA Alkaline Batteries (4pk)',   'STK-4410',  7,  2, '🔋', 'pack', 250.00, 190.00, 18, 0, NULL,         'active', 2),
(17, 'Hand Sanitizer 250ml',          'STK-5126',  6,  2, '🧴', 'pcs',  150.00, 110.00, 16, 0, NULL,         'active', 2),
(18, 'Whole Wheat Bread',             'STK-6203',  5,  5, '🍞', 'pcs',   75.00,  52.00, 14, 1, '2026-06-23', 'active', 2),
(19, 'Greek Yogurt 400g',             'STK-7089',  1,  1, '🥣', 'pcs',  140.00, 105.00, 15, 1, '2026-06-21', 'active', 2),
(20, 'Fresh Paneer 200g',             'STK-7144',  1,  1, '🧀', 'pcs',  160.00, 120.00, 10, 1, '2026-06-23', 'active', 2),
(21, 'Chicken Sausages 500g',         'STK-8021',  8,  2, '🌭', 'pack', 320.00, 250.00, 20, 1, '2026-06-25', 'active', 2),
(22, 'Mayonnaise 500ml',              'STK-3098',  11, 4, '🥫', 'pcs',  290.00, 220.00, 12, 1, '2026-06-27', 'active', 2),
(23, 'Mixed Fruit Juice 1L',          'STK-9012',  4,  3, '🧃', 'pcs',  180.00, 130.00, 22, 1, '2026-06-30', 'active', 2),
(24, 'Potato Chips 150g',             'STK-9145',  9,  2, '🥔', 'pack', 110.00,  80.00, 30, 0, NULL,         'active', 2),
(25, 'Dish Washing Liquid 1L',        'STK-4477',  7,  2, '🧽', 'pcs',  190.00, 145.00, 18, 0, NULL,         'active', 2);

-- 2.7 inventory
INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand, quantity_reserved, last_counted_at) VALUES
(1,  1, 4,   0, '2026-06-18 09:00:00'),
(2,  1, 9,   1, '2026-06-18 09:00:00'),
(3,  3, 6,   0, '2026-06-18 09:00:00'),
(4,  3, 14,  2, '2026-06-18 09:00:00'),
(5,  1, 7,   0, '2026-06-18 09:00:00'),
(6,  4, 18,  3, '2026-06-18 09:00:00'),
(7,  4, 25,  0, '2026-06-18 09:00:00'),
(8,  1, 32,  4, '2026-06-18 09:00:00'),
(9,  1, 0,   0, '2026-06-18 09:00:00'),
(10, 4, 0,   0, '2026-06-18 09:00:00'),
(11, 3, 40,  5, '2026-06-18 09:00:00'),
(12, 1, 55,  6, '2026-06-18 09:00:00'),
(13, 1, 120, 14, '2026-06-19 09:00:00'),
(14, 3, 8,   3,  '2026-06-19 09:00:00'),
(15, 1, 64,  6,  '2026-06-19 09:00:00'),
(16, 2, 0,   0,  '2026-06-19 09:00:00'),
(17, 2, 31,  5,  '2026-06-19 09:00:00'),
(18, 4, 14,  2,  '2026-06-19 09:00:00'),
(19, 3, 42,  8,  '2026-06-19 09:00:00'),
(20, 3, 9,   1,  '2026-06-19 09:00:00'),
(21, 3, 58,  11, '2026-06-19 09:00:00'),
(22, 1, 0,   0,  '2026-06-19 09:00:00'),
(23, 2, 76,  9,  '2026-06-19 09:00:00'),
(24, 4, 140, 18, '2026-06-19 09:00:00'),
(25, 2, 22,  4,  '2026-06-19 09:00:00');

-- 2.8 product_batches
INSERT INTO product_batches (product_id, warehouse_id, batch_number, quantity, manufacture_date, expiry_date) VALUES
(19, 3, 'BT-2291', 14, '2026-06-07', '2026-06-21'),
(20, 3, 'BT-2304', 9,  '2026-06-09', '2026-06-23'),
(21, 3, 'BT-2287', 21, '2026-06-11', '2026-06-25'),
(22, 1, 'BT-2266', 7,  '2026-06-08', '2026-06-27'),
(23, 2, 'BT-2310', 16, '2026-06-12', '2026-06-30'),
(14, 3, 'BT-2318', 8,  '2026-06-10', '2026-06-24'),
(18, 4, 'BT-2299', 14, '2026-06-09', '2026-06-23');

-- 2.9 customers
INSERT INTO customers (customer_id, customer_name, phone, email, loyalty_points) VALUES
(1, 'Walk-in Customer', NULL, NULL, 0.00),
(2, 'Sujata Maharjan',  '+977-9841000011', 'sujata.m@example.com', 120.50),
(3, 'Nabin Pradhan',    '+977-9841000012', 'nabin.p@example.com',  45.00),
(4, 'Ramesh Bhattarai', '+977-9841000013', 'ramesh.b@example.com', 0.00);

-- 2.10 orders
INSERT INTO orders (order_id, order_number, customer_id, cashier_id, warehouse_id, items_total, discount_amount, loyalty_discount, tax_amount, grand_total, payment_method, order_status, order_date) VALUES
(1, 'SC-10479', 2, 4, 4, 3767.00, 0.00, 0.00, 0.00, 3767.00, 'cash',          'completed', '2026-06-18 11:20:00'),
(2, 'SC-10482', 1, 4, 4, 1064.00, 0.00, 0.00, 0.00, 1064.00, 'card',          'completed', '2026-06-19 16:05:00'),
(3, 'SC-10483', 3, 5, 4,  708.00, 0.00, 0.00, 0.00,  708.00, 'cash',          'completed', '2026-06-19 17:42:00'),
(4, 'SC-10484', 1, 4, 4,  532.00, 0.00, 0.00, 0.00,  532.00, 'mobile_wallet', 'completed', '2026-06-20 09:10:00'),
(5, 'SC-10485', 4, 4, 4,  429.00, 0.00, 0.00, 0.00,  429.00, 'cash',          'completed', '2026-06-20 13:55:00');

-- 2.11 order_items
INSERT INTO order_items (order_id, product_id, batch_id, quantity, unit_price, discount_amount) VALUES
(1, 8,  NULL, 2.00, 150.00, 0.00),
(1, 11, NULL, 1.00, 480.00, 0.00),
(1, 6,  NULL, 5.00,  30.00, 0.00),
(1, 9,  NULL, 5.00, 480.00, 0.00),
(2, 21, 3,    2.00, 320.00, 0.00),
(2, 4,  NULL, 5.00,  85.00, 0.00),
(3, 6,  NULL, 2.00,  30.00, 0.00),
(3, 19, 1,    4.00, 140.00, 0.00),
(4, 23, 5,    2.00, 180.00, 0.00),
(4, 25, NULL, 1.00, 190.00, 18.00),
(5, 6,  NULL, 1.00,  30.00, 0.00),
(5, 24, NULL, 3.00, 110.00, 1.00);

-- 2.12 stock_movements
INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, notes, performed_by, created_at) VALUES
(8,  1, 'purchase_in', 60,  'suppliers', 4, 'Initial stock receipt from Sagarmatha Grocers', 2, '2026-06-01 08:00:00'),
(8,  1, 'sale_out',     2,  'orders',    1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(11, 3, 'purchase_in', 50,  'suppliers', 1, 'Egg tray restock', 3, '2026-06-02 08:00:00'),
(11, 3, 'sale_out',     1,  'orders',    1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(9,  1, 'sale_out',     5,  'orders',    1, 'Sold via order SC-10479 — depleted remaining stock', 4, '2026-06-18 11:20:00'),
(6,  4, 'sale_out',     5,  'orders',    1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(21, 3, 'sale_out',     2,  'orders',    2, 'Sold via order SC-10482', 4, '2026-06-19 16:05:00'),
(4,  3, 'sale_out',     5,  'orders',    2, 'Sold via order SC-10482', 4, '2026-06-19 16:05:00'),
(6,  4, 'sale_out',     2,  'orders',    3, 'Sold via order SC-10483', 5, '2026-06-19 17:42:00'),
(19, 3, 'sale_out',     4,  'orders',    3, 'Sold via order SC-10483', 5, '2026-06-19 17:42:00'),
(23, 2, 'sale_out',     2,  'orders',    4, 'Sold via order SC-10484', 4, '2026-06-20 09:10:00'),
(25, 2, 'sale_out',     1,  'orders',    4, 'Sold via order SC-10484', 4, '2026-06-20 09:10:00'),
(6,  4, 'sale_out',     1,  'orders',    5, 'Sold via order SC-10485', 4, '2026-06-20 13:55:00'),
(24, 4, 'sale_out',     3,  'orders',    5, 'Sold via order SC-10485', 4, '2026-06-20 13:55:00'),
(13, 1, 'transfer_out', 40, 'stock_transfers', 1, 'Transferred to Store Front', 2, '2026-06-19 10:00:00'),
(13, 4, 'transfer_in',  40, 'stock_transfers', 1, 'Received from Warehouse A',  2, '2026-06-19 10:00:00');

-- 2.13 stock_transfers
INSERT INTO stock_transfers (transfer_id, product_id, from_warehouse_id, to_warehouse_id, quantity, status, requested_by, transferred_at) VALUES
(1, 13, 1, 4, 40, 'completed', 2, '2026-06-19 10:00:00');

-- 2.14 alerts
INSERT INTO alerts (alert_type, product_id, warehouse_id, batch_id, message, severity, is_acknowledged, created_at) VALUES
('out_of_stock', 9,  1, NULL, 'Olive Oil 500ml is out of stock at Warehouse A',              'critical', 0, '2026-06-18 12:00:00'),
('out_of_stock', 16, 2, NULL, 'AA Alkaline Batteries (4pk) is out of stock at Warehouse B',  'critical', 0, '2026-06-19 09:30:00'),
('low_stock',    1,  1, NULL, 'Butter 500g stock (4) is below reorder level (10)',            'warning',  0, '2026-06-18 09:05:00'),
('low_stock',    13, 1, NULL, 'Organic Basmati Rice 5kg stock is approaching reorder level',  'info',     1, '2026-06-19 09:05:00'),
('expiry',       19, 3, 1,    'Greek Yogurt 400g (batch BT-2291) expires today',              'critical', 0, '2026-06-21 06:00:00'),
('expiry',       20, 3, 2,    'Fresh Paneer 200g (batch BT-2304) expires in 2 days',          'warning',  0, '2026-06-21 06:00:00');

-- 2.15 activity_logs
INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description, created_at) VALUES
(2, 'add',      'products',        13, 'Organic Basmati Rice 5kg was added to inventory',      '2026-06-21 07:48:00'),
(3, 'update',   'inventory',       14, 'Stock count updated for Whole Milk 1L',                '2026-06-21 07:12:00'),
(4, 'checkout', 'orders',           2, 'Checkout completed — order #SC-10482',                 '2026-06-19 16:05:00'),
(1, 'alert',    'products',         9, 'Low stock alert generated for Olive Oil 500ml',        '2026-06-18 12:00:00'),
(4, 'checkout', 'orders',           1, 'Checkout completed — order #SC-10479',                 '2026-06-18 11:20:00'),
(2, 'update',   'products',        16, 'Reorder level adjusted for AA Batteries (4pk)',        '2026-06-18 06:00:00'),
(2, 'transfer', 'stock_transfers',  1, '40 units of Organic Basmati Rice 5kg transferred from Warehouse A to Store Front', '2026-06-19 10:00:00'),
(1, 'login',    'users',            1, 'Annie Shrestha logged in',                             '2026-06-21 08:15:00');

-- ============================================================================
-- SECTION 3: HELPER VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW vw_product_stock_summary AS
SELECT
    p.product_id,
    p.product_name,
    p.sku,
    c.category_name,
    p.price,
    p.reorder_level,
    p.status                                            AS product_status,
    COALESCE(SUM(i.quantity_on_hand), 0)                AS total_on_hand,
    COALESCE(SUM(i.quantity_reserved), 0)               AS total_reserved,
    COALESCE(SUM(i.quantity_available), 0)              AS total_available,
    CASE
        WHEN COALESCE(SUM(i.quantity_available), 0) <= 0
            THEN 'Out of Stock'
        WHEN COALESCE(SUM(i.quantity_available), 0) <= p.reorder_level
            THEN 'Low Stock'
        ELSE 'In Stock'
    END                                                  AS stock_status
FROM products p
LEFT JOIN inventory i  ON i.product_id = p.product_id
LEFT JOIN categories c ON c.category_id = p.category_id
GROUP BY p.product_id, p.product_name, p.sku, c.category_name,
         p.price, p.reorder_level, p.status;

CREATE OR REPLACE VIEW vw_inventory_detail AS
SELECT
    i.inventory_id,
    p.product_id,
    p.product_name,
    p.sku,
    p.icon_emoji,
    cat.category_name,
    w.warehouse_id,
    w.warehouse_name,
    i.quantity_on_hand,
    i.quantity_reserved,
    i.quantity_available,
    p.reorder_level,
    CASE
        WHEN i.quantity_available <= 0 THEN 'Out of Stock'
        WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
        ELSE 'In Stock'
    END AS stock_status
FROM inventory i
JOIN products p    ON p.product_id = i.product_id
JOIN warehouses w  ON w.warehouse_id = i.warehouse_id
JOIN categories cat ON cat.category_id = p.category_id;

CREATE OR REPLACE VIEW vw_expiry_alerts AS
SELECT
    b.batch_id,
    p.product_id,
    p.product_name,
    b.batch_number,
    w.warehouse_name,
    b.quantity,
    b.expiry_date,
    DATEDIFF(b.expiry_date, CURDATE()) AS days_left
FROM product_batches b
JOIN products p   ON p.product_id = b.product_id
JOIN warehouses w ON w.warehouse_id = b.warehouse_id
WHERE b.quantity > 0
ORDER BY days_left ASC;

-- ============================================================================
-- END OF FILE — stocksmart.sql
-- ============================================================================
