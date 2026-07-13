# StockSmart Database Setup

> **Warning:** `../stocksmart.sql` begins with `DROP DATABASE IF EXISTS stocksmart; CREATE DATABASE stocksmart;`
> — it always targets the literal database named `stocksmart`, no matter what database your client session is
> connected to. Piping it into a differently-named database (e.g. for a clean-install test) does **not**
> redirect it — it drops and recreates the real `stocksmart` database first. Never run this file against an
> environment containing real data without a fresh backup, and never assume redirecting your SQL client's
> target database is enough to sandbox it.

**Fresh install: `../stocksmart.sql` alone is enough.** As of 2026-07-13 it's a full re-export of the
current schema — every table, view, trigger, and column that `production_upgrade.sql` and
`migrations/001`–`004` added is already folded into it, along with the RBAC seed data (roles, permissions,
role_permissions) those files introduced. User seed data is reset to the original 5 curated demo accounts.

```bash
mysql -u root < stocksmart.sql
```

`production_upgrade.sql` and `migrations/*.sql` are kept for history and for **upgrading an existing database**
that was set up before 2026-07-13 (they're idempotent — `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`,
`INSERT IGNORE` — safe to re-run). If you have an older local database, apply them in this order:

1. `production_upgrade.sql` — RBAC (permissions/role_permissions), user_sessions, password_reset_tokens,
   system_settings, notifications, purchase_orders/purchase_order_items
2. `migrations/001_auth_security.sql` — login lockout, OTP/email verification columns, `pending` user status
3. `migrations/002_sales.sql` — sales returns/refunds (`returns`, `return_items`), `partially_refunded` order status
4. `migrations/003_checkout.sql` — barcode column on `products` for POS search
5. `migrations/004_remove_otp_verification.sql` — removes the OTP columns `001` added (registration activates
   accounts immediately now, see `register.php`); migrates any leftover `pending` accounts to `active`

```bash
mysql -u root stocksmart < database/production_upgrade.sql
mysql -u root stocksmart < database/migrations/001_auth_security.sql
mysql -u root stocksmart < database/migrations/002_sales.sql
mysql -u root stocksmart < database/migrations/003_checkout.sql
mysql -u root stocksmart < database/migrations/004_remove_otp_verification.sql
```

Any future schema change should be added as `database/migrations/00N_description.sql`, written to be
re-runnable, and listed here in order — and periodically folded back into `stocksmart.sql` so a fresh install
stays a single file.

## Composer dependencies

Export features (PDF/Excel reports and receipts) require `dompdf/dompdf` and `phpoffice/phpspreadsheet`:

```bash
composer install
```

## Environment variables

`db.php` reads connection settings from environment variables (falling back to XAMPP defaults for local dev):

- `STOCKSMART_DB_HOST` (default `127.0.0.1`)
- `STOCKSMART_DB_PORT` (default `3306`)
- `STOCKSMART_DB_NAME` (default `stocksmart`)
- `STOCKSMART_DB_USER` (default `root`)
- `STOCKSMART_DB_PASS` (default empty)

`helpers/mailer.php` reads `MAIL_DRIVER` (`log` for local dev — password-reset links are written to
`logs/mail.log` instead of sent; set to `smtp` plus real SMTP credentials in production). It's only used by
`forgot-password.php` — registration no longer requires email verification.
