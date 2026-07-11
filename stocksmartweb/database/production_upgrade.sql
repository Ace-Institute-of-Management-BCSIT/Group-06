-- StockSmart production upgrade. Import after stocksmart.sql in phpMyAdmin.
-- It is idempotent and preserves existing inventory and order data.
USE stocksmart;

UPDATE products SET icon_emoji = NULL;
UPDATE users SET avatar_emoji = NULL WHERE avatar_emoji REGEXP '[^A-Za-z]';

INSERT IGNORE INTO roles (role_name, role_description) VALUES
('Super Admin', 'Unrestricted system administration'),
('Inventory Staff', 'Inventory, purchasing and warehouse operations'),
('Viewer', 'Read-only business visibility');

CREATE TABLE IF NOT EXISTS permissions (
    permission_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_key VARCHAR(80) NOT NULL,
    permission_name VARCHAR(120) NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    PRIMARY KEY (permission_id),
    UNIQUE KEY uq_permissions_key (permission_key),
    KEY idx_permissions_module (module_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id TINYINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (permission_key, permission_name, module_name) VALUES
('dashboard.view','View dashboard','dashboard'), ('products.view','View products','products'),
('products.manage','Manage products','products'), ('inventory.view','View inventory','inventory'),
('inventory.manage','Manage inventory','inventory'), ('checkout.use','Use checkout','checkout'),
('sales.view','View sales','sales'), ('sales.manage','Manage sales and refunds','sales'),
('purchases.view','View purchases','purchases'), ('purchases.manage','Manage purchase orders','purchases'),
('suppliers.view','View suppliers','suppliers'), ('suppliers.manage','Manage suppliers','suppliers'),
('customers.view','View customers','customers'), ('customers.manage','Manage customers','customers'),
('reports.view','View reports','reports'), ('alerts.view','View alerts','alerts'),
('notifications.view','View notifications','notifications'), ('users.manage','Manage users','users'),
('roles.manage','Manage roles and permissions','roles'), ('settings.manage','Manage settings','settings'),
('logs.view','View audit logs','logs');

-- Super Admin and Admin retain complete access; narrower roles receive only
-- operational permissions. Existing Staff is kept for backwards compatibility.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name IN ('Super Admin', 'Admin');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name = 'Manager' AND p.permission_key IN
('dashboard.view','products.view','products.manage','inventory.view','inventory.manage','sales.view','purchases.view','purchases.manage','suppliers.view','suppliers.manage','customers.view','customers.manage','reports.view','alerts.view','notifications.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name IN ('Staff','Inventory Staff') AND p.permission_key IN
('dashboard.view','products.view','inventory.view','inventory.manage','purchases.view','purchases.manage','suppliers.view','alerts.view','notifications.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name = 'Cashier' AND p.permission_key IN
('dashboard.view','products.view','inventory.view','checkout.use','sales.view','customers.view','customers.manage','notifications.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name = 'Viewer' AND p.permission_key IN
('dashboard.view','products.view','inventory.view','sales.view','purchases.view','suppliers.view','customers.view','reports.view','alerts.view','notifications.view');

CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id), KEY idx_user_sessions_user (user_id), KEY idx_user_sessions_expires (expires_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id), UNIQUE KEY uq_reset_token_hash (token_hash), KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('company_name','StockSmart'), ('currency','NPR'), ('timezone','Asia/Kathmandu'),
('invoice_prefix','INV-'), ('receipt_footer','Thank you for your business.'), ('default_tax_rate','0');

CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    notification_type VARCHAR(40) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id), KEY idx_notifications_user_read (user_id, read_at), KEY idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_orders (
    purchase_order_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_number VARCHAR(30) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id SMALLINT UNSIGNED NOT NULL,
    status ENUM('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date DATE NULL, notes VARCHAR(500) NULL, created_by INT UNSIGNED NULL,
    ordered_at DATETIME NULL, received_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (purchase_order_id), UNIQUE KEY uq_purchase_orders_number (po_number),
    KEY idx_purchase_supplier_status (supplier_id, status),
    CONSTRAINT fk_purchase_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    purchase_order_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
    ordered_quantity DECIMAL(10,2) NOT NULL, received_quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL, tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (purchase_order_item_id), UNIQUE KEY uq_po_item_product (purchase_order_id, product_id),
    CONSTRAINT fk_purchase_item_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(purchase_order_id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_item_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
