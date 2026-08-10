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

## Applying migrations — use the runner, not `mysql <` by hand

```bash
php database/migrate.php            # apply everything pending
php database/migrate.php --status   # show what's applied / pending, change nothing
```

The runner records every applied file in a `schema_migrations` table and only runs what is missing, so it is
safe to run on every deploy. Connection settings come from the same environment/`.env` the app uses — there is
nothing to edit per environment.

**First run against an existing database (including the production VPS).** Migrations `001`–`004` are recorded
as applied *without being executed*, because a database that already has the schema already contains their
changes (`stocksmart.sql` folds them in, and the VPS ran them long ago). This is a correctness requirement, not
an optimisation: `004` runs `UPDATE users SET status='active' WHERE status='pending'`, and `005` later
reintroduced `pending` as the OTP-registration default — replaying `004` would silently activate accounts that
are legitimately awaiting verification. `005` onward run normally; `005` is fully guarded by
`INFORMATION_SCHEMA` checks, so it is a no-op where it has already been applied.

The runner also **ignores the `USE stocksmart;` line** that migrations `001`–`005` begin with. That line would
hijack the connection and target the wrong database on any deployment that named it something other than
`stocksmart`; the runner always stays on the database `db.php` connected to.

A `GET_LOCK` named lock serialises concurrent runs, and a failure stops the run without recording the failed
file, so it is retried after you fix it rather than being skipped.

**Automatic mode (optional).** Set `DB_AUTO_MIGRATE=true` in `.env` and the app applies pending migrations
itself, once, on the first request after new migration files are deployed — guarded by a marker file (so the
steady-state cost is one small `file_get_contents` and *zero* queries) and the same named lock. It is off by
default; the explicit command above is more predictable and reports failures to your terminal. See
`helpers/migrator.php`.

## Migration history

`production_upgrade.sql` and `migrations/*.sql` are all idempotent (`CREATE TABLE IF NOT EXISTS`,
`ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`, `INFORMATION_SCHEMA` guards).

1. `production_upgrade.sql` — RBAC (permissions/role_permissions), user_sessions, password_reset_tokens,
   system_settings, notifications, purchase_orders/purchase_order_items
2. `migrations/001_auth_security.sql` — login lockout, OTP/email verification columns, `pending` user status
3. `migrations/002_sales.sql` — sales returns/refunds (`returns`, `return_items`), `partially_refunded` order status
4. `migrations/003_checkout.sql` — barcode column on `products` for POS search
5. `migrations/004_remove_otp_verification.sql` — removes the OTP columns `001` added; migrates leftover
   `pending` accounts to `active`
6. `migrations/005_otp_and_2fa.sql` — reintroduces OTP registration columns plus Auth-App (TOTP) 2FA columns,
   and makes `pending` the default user status again
7. `migrations/006_optional_batch_expiry.sql` — relaxes `product_batches.expiry_date` from `NOT NULL` to
   `NULL`, so a batch can be recorded as non-perishable ("No Expiry") instead of being forced to carry an
   invented date that would then raise a false expiry alert. Widening only: every existing row keeps its date,
   nothing is rewritten or dropped.

Any future schema change should be added as `database/migrations/00N_description.sql`, written to be
re-runnable, and listed here in order — and periodically folded back into `stocksmart.sql` so a fresh install
stays a single file. **Never edit or renumber a migration that has already shipped**; the runner keys the
ledger on the numeric prefix.

## Where stock and expiry rules live

`helpers/stock_status.php` is the single source of truth for "is this product low on stock?" and "is this batch
expiring?", with a browser twin at `assets/js/stock-status.js` for client-side table rendering. Queries that
need the rules in SQL should use its `sql_available_stock()` / `sql_expiry_alerting()` helpers rather than
writing the comparison inline — that inlining is exactly what let five screens disagree about the same
product.

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
