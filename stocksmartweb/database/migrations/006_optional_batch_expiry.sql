-- ============================================================================
--  StockSmart migration 006: make batch expiry dates optional.
-- ============================================================================
--  WHY
--  Expiry is tracked per batch (product_batches.expiry_date), which is the
--  right model — two batches of the same product legitimately expire on
--  different dates, so a single expiry column on `products` would misreport
--  every one of them. That part of the schema needed no change.
--
--  What DID block the feature: expiry_date was declared NOT NULL. The Add
--  Product / Add Batch UI now lets a user record stock for a non-perishable
--  item (rice, detergent, hardware) and leave expiry blank, and NOT NULL
--  would either reject that or force an invented placeholder date — which
--  would then surface as a fake expiry alert. NULL is given a precise
--  meaning here: "this batch does not expire", and it never alerts.
--
--  SAFETY ON THE VPS
--  NOT NULL -> NULL is a widening change. Every existing row already holds a
--  real date and keeps it; nothing is rewritten, defaulted or dropped, and no
--  row can become invalid. The column type (DATE) is unchanged.
--
--  Guarded by INFORMATION_SCHEMA so re-running is a genuine no-op rather than
--  a pointless table rebuild, matching the idempotency style of migration 005.
--
--  NO INDEX CHANGES: idx_batches_expiry (expiry_date), idx_batches_product
--  (product_id), idx_alerts_type and idx_alerts_ack already exist and cover
--  every query the new expiry/restock alert code issues. Nothing is added
--  that the workload does not actually need.
-- ============================================================================

SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_schema = @dbname
          AND table_name   = 'product_batches'
          AND column_name  = 'expiry_date'
          AND IS_NULLABLE  = 'NO'
    ) > 0,
    "ALTER TABLE product_batches MODIFY COLUMN expiry_date DATE NULL DEFAULT NULL",
    "SELECT 'product_batches.expiry_date is already nullable' AS note"
));
PREPARE alterIfNeeded FROM @preparedStatement;
EXECUTE alterIfNeeded;
DEALLOCATE PREPARE alterIfNeeded;
