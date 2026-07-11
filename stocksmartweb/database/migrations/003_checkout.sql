-- StockSmart migration 003: barcode support for POS product search.
-- Import after 002_sales.sql. Idempotent.
USE stocksmart;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(64) NULL UNIQUE AFTER sku;
