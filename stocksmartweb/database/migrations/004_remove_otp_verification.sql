-- StockSmart migration 004: remove the email-OTP verification system.
-- Registration now activates accounts immediately (see register.php) — no
-- OTP is ever generated, so otp_code/otp_expires_at (added by
-- 001_auth_security.sql) are permanently unused. email_verified_at and the
-- 'pending' status value are left in place: email_verified_at is still
-- written for every account (self-registered and admin-created, see
-- api/users.php) as a general "when was this confirmed" audit timestamp,
-- and 'pending' remains a valid value of the general-purpose status enum
-- even though nothing sets it anymore. Idempotent.
USE stocksmart;

-- Any account left stuck in 'pending' from the old OTP flow can now log in
-- immediately, same as everyone else.
UPDATE users
SET status = 'active', email_verified_at = COALESCE(email_verified_at, NOW())
WHERE status = 'pending';

ALTER TABLE users
    DROP COLUMN IF EXISTS otp_code,
    DROP COLUMN IF EXISTS otp_expires_at;
