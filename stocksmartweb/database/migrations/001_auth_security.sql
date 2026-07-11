-- StockSmart migration 001: authentication + security hardening.
-- Import after stocksmart.sql and database/production_upgrade.sql. Idempotent.
USE stocksmart;

ALTER TABLE users
    MODIFY COLUMN status ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_attempts,
    ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10) NULL AFTER locked_until,
    ADD COLUMN IF NOT EXISTS otp_expires_at DATETIME NULL AFTER otp_code,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER otp_expires_at;

ALTER TABLE activity_logs
    MODIFY COLUMN activity_type ENUM('add','update','delete','checkout','alert','transfer','login','logout','security')
                                 NOT NULL;
