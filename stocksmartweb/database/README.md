# StockSmart Database Setup

> **Warning:** `../stocksmart.sql` begins with `DROP DATABASE IF EXISTS stocksmart; CREATE DATABASE stocksmart;`
> — it always targets the literal database named `stocksmart`, no matter what database your client session is
> connected to. Piping it into a differently-named database (e.g. for a clean-install test) does **not**
> redirect it — it drops and recreates the real `stocksmart` database first. Never run this file against an
> environment containing real data without a fresh backup, and never assume redirecting your SQL client's
> target database is enough to sandbox it.

Run these SQL files against a `stocksmart` MySQL/MariaDB database, **in this exact order**:

1. `../stocksmart.sql` — base schema (roles, users, products, inventory, orders, etc.) + seed data
2. `production_upgrade.sql` — RBAC (permissions/role_permissions), user_sessions, password_reset_tokens,
   system_settings, notifications, purchase_orders/purchase_order_items
3. `migrations/001_auth_security.sql` — login lockout, OTP/email verification columns, `pending` user status
4. `migrations/002_sales.sql` — sales returns/refunds (`returns`, `return_items`), `partially_refunded` order status
5. `migrations/003_checkout.sql` — barcode column on `products` for POS search
6. `migrations/004_remove_otp_verification.sql` — removes the OTP columns `001` added (registration activates
   accounts immediately now, see `register.php`); migrates any leftover `pending` accounts to `active`

All files are idempotent (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`) and safe
to re-run. Using phpMyAdmin or the CLI:

```bash
mysql -u root stocksmart < stocksmart.sql
mysql -u root stocksmart < database/production_upgrade.sql
mysql -u root stocksmart < database/migrations/001_auth_security.sql
mysql -u root stocksmart < database/migrations/002_sales.sql
mysql -u root stocksmart < database/migrations/003_checkout.sql
mysql -u root stocksmart < database/migrations/004_remove_otp_verification.sql
```

Any future schema change should be added as `database/migrations/00N_description.sql`, written to be
re-runnable, and listed here in order.

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
