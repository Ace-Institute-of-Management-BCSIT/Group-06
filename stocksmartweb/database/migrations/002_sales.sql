-- StockSmart migration 002: sales returns, refunds, and void support.
-- Import after 001_auth_security.sql. Idempotent.
USE stocksmart;

ALTER TABLE orders
    MODIFY COLUMN order_status ENUM('completed','pending','refunded','partially_refunded','cancelled')
                                NOT NULL DEFAULT 'completed';

CREATE TABLE IF NOT EXISTS returns (
    return_id       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED       NOT NULL,
    processed_by    INT UNSIGNED       NULL,
    reason          VARCHAR(255)       NULL,
    refund_amount   DECIMAL(12,2)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (return_id),
    KEY idx_returns_order (order_id),
    CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_returns_user FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS return_items (
    return_item_id  INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    return_id       INT UNSIGNED       NOT NULL,
    order_item_id   INT UNSIGNED       NOT NULL,
    product_id      INT UNSIGNED       NOT NULL,
    quantity        DECIMAL(10,2)      NOT NULL,
    unit_price      DECIMAL(12,2)      NOT NULL,
    line_refund     DECIMAL(12,2)      NOT NULL,
    PRIMARY KEY (return_item_id),
    KEY idx_return_items_return (return_id),
    CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES returns(return_id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE RESTRICT,
    CONSTRAINT fk_return_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
