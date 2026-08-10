<?php
/**
 * ============================================================================
 *  StockSmart — Stock & Expiry Status (helpers/stock_status.php)
 * ============================================================================
 *  THE single source of truth for two questions the whole app kept answering
 *  differently:
 *
 *    1. "Does this product need restocking, and how badly?"
 *    2. "Is this batch expired or about to expire?"
 *
 *  Before this file existed the answers diverged per screen:
 *    - products.html      : stock <  reorder_level, on SUM(quantity_on_hand)
 *    - inventory.html     : available <= reorder_level, on on_hand - reserved
 *    - api/dashboard.php  : available <= reorder_level, on on_hand - reserved
 *    - helpers/notifications.php : stock <= reorder_level, on on_hand only
 *  …so a product sitting exactly ON its reorder level was "In Stock" on the
 *  Products page and "Low Stock" in the sidebar badge, the dashboard widget
 *  and the notification bell at the same time. Expiry was worse: alerts fired
 *  at 30 days, the dashboard panel listed 14 days, and the inventory stat card
 *  counted 7.
 *
 *  Everything now routes through the constants and functions below. The JS
 *  mirror in assets/js/stock-status.js implements the identical rules for
 *  client-side rendering — change one, change the other (both files say so).
 *
 *  --- STOCK RULES -----------------------------------------------------------
 *  "available" is ALWAYS quantity_on_hand - quantity_reserved, summed across
 *  every warehouse. Reserved units are promised to an order, so counting them
 *  as sellable is what let the Products page under-report shortages.
 *
 *    available <= 0                       -> Out of Stock  (critical)
 *    available <= reorder_level * 0.4     -> Critical      (critical)
 *    available <= reorder_level           -> Low Stock     (warning)
 *    otherwise                            -> In Stock      (ok)
 *
 *  The 0.4 ratio is not invented here — it is the same one dashboard.html
 *  already used to flag a low-stock row red, promoted to a shared rule. The
 *  absolute threshold is always the product's own products.reorder_level
 *  column; there are no magic per-screen numbers left.
 *
 *  "Needs restocking" (the sidebar badge, the inventory Restock filter, the
 *  dashboard low-stock widget) means available <= reorder_level, which covers
 *  Out of Stock + Critical + Low Stock.
 *
 *  --- EXPIRY RULES ----------------------------------------------------------
 *  Expiry lives on product_batches.expiry_date, never on products: two batches
 *  of the same product legitimately expire on different dates, and collapsing
 *  them onto the product would misreport every batch. NULL means the batch
 *  does not expire (non-perishable) and never alerts.
 *
 *    expiry_date IS NULL                            -> No Expiry
 *    expiry_date <  CURDATE()                       -> Expired       (critical)
 *    expiry_date <= CURDATE() + INTERVAL 2 MONTH    -> Expiring Soon (warning)
 *    otherwise                                      -> Valid
 *
 *  The window is calendar months via MySQL's INTERVAL 2 MONTH / PHP's
 *  DateTime "+2 months", not a 60-day approximation, so it lands on the same
 *  day-of-month two months out regardless of month length.
 *
 *  Only batches with quantity > 0 ever alert — an emptied batch is history,
 *  not a warning.
 * ========================================================================== */

declare(strict_types=1);

/** Fraction of reorder_level at or below which stock is "Critical" rather than "Low". */
const STOCK_CRITICAL_RATIO = 0.4;

/** How far ahead an expiry date starts producing warnings. Calendar months. */
const EXPIRY_WARNING_MONTHS = 2;

/* ============================================================================
 *  STOCK
 * ========================================================================== */

/**
 * Classifies an availability figure against a product's own reorder level.
 *
 * @return array{key:string,label:string,severity:string,cls:string}
 *   key      stable machine value: out_of_stock|critical|low_stock|in_stock
 *   label    human text shown in the UI
 *   severity maps onto the alerts.severity enum: critical|warning|info
 *   cls      CSS suffix shared by products.html / inventory.html / dashboard
 */
function stock_state(int $available, int $reorderLevel): array
{
    if ($available <= 0) {
        return ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'severity' => 'critical', 'cls' => 'out'];
    }
    if ($reorderLevel > 0 && $available <= (int) ceil($reorderLevel * STOCK_CRITICAL_RATIO)) {
        return ['key' => 'critical', 'label' => 'Critical', 'severity' => 'critical', 'cls' => 'critical'];
    }
    if ($available <= $reorderLevel) {
        return ['key' => 'low_stock', 'label' => 'Low Stock', 'severity' => 'warning', 'cls' => 'low'];
    }
    return ['key' => 'in_stock', 'label' => 'In Stock', 'severity' => 'info', 'cls' => 'in'];
}

/**
 * True when a product should appear in restocking alerts — i.e. Out of Stock,
 * Critical or Low Stock. This is the definition behind the sidebar badge, the
 * inventory page's Restock filter and the dashboard low-stock widget.
 */
function stock_needs_restock(int $available, int $reorderLevel): bool
{
    return $available <= $reorderLevel;
}

/**
 * SQL expression for available stock across every warehouse, for use inside a
 * query that GROUP BYs products and LEFT JOINs inventory under alias $alias.
 * Kept here so no query re-invents "did we subtract reserved or not".
 */
function sql_available_stock(string $alias = 'i'): string
{
    return "(COALESCE(SUM({$alias}.quantity_on_hand), 0) - COALESCE(SUM({$alias}.quantity_reserved), 0))";
}

/* ============================================================================
 *  EXPIRY
 * ========================================================================== */

/**
 * SQL for the far edge of the warning window: the latest expiry date that
 * still counts as "expiring soon".
 */
function sql_expiry_horizon(): string
{
    return 'DATE_ADD(CURDATE(), INTERVAL ' . EXPIRY_WARNING_MONTHS . ' MONTH)';
}

/**
 * SQL predicate for "this batch belongs in expiry alerts" — already expired OR
 * expiring within the warning window, and still holding stock. $col is the
 * qualified expiry column (e.g. "b.expiry_date"), $qtyCol the quantity column.
 *
 * NULL expiry (non-perishable) is excluded by the IS NOT NULL test.
 */
function sql_expiry_alerting(string $col = 'b.expiry_date', string $qtyCol = 'b.quantity'): string
{
    return "({$qtyCol} > 0 AND {$col} IS NOT NULL AND {$col} <= " . sql_expiry_horizon() . ')';
}

/**
 * Classifies one batch expiry date.
 *
 * @param ?string $expiryDate Y-m-d, or null for a non-perishable batch.
 * @return array{key:string,label:string,severity:string,cls:string,daysLeft:?int}
 *   key      expired|expiring_soon|valid|none
 *   daysLeft whole days until expiry; negative once expired, null when none.
 */
function expiry_state(?string $expiryDate): array
{
    if ($expiryDate === null || $expiryDate === '' || $expiryDate === '0000-00-00') {
        return ['key' => 'none', 'label' => 'No Expiry', 'severity' => 'info', 'cls' => 'none', 'daysLeft' => null];
    }

    $today = new DateTimeImmutable('today');
    $expiry = DateTimeImmutable::createFromFormat('!Y-m-d', substr($expiryDate, 0, 10));
    if ($expiry === false) {
        return ['key' => 'none', 'label' => 'No Expiry', 'severity' => 'info', 'cls' => 'none', 'daysLeft' => null];
    }

    $daysLeft = (int) $today->diff($expiry)->format('%r%a');
    $horizon = $today->modify('+' . EXPIRY_WARNING_MONTHS . ' months');

    if ($expiry < $today) {
        return ['key' => 'expired', 'label' => 'Expired', 'severity' => 'critical', 'cls' => 'expired', 'daysLeft' => $daysLeft];
    }
    if ($expiry <= $horizon) {
        return ['key' => 'expiring_soon', 'label' => 'Expiring Soon', 'severity' => 'warning', 'cls' => 'soon', 'daysLeft' => $daysLeft];
    }
    return ['key' => 'valid', 'label' => 'Valid', 'severity' => 'info', 'cls' => 'valid', 'daysLeft' => $daysLeft];
}

/** True when a batch should appear in expiry alerts (expired or within the window). */
function expiry_is_alerting(?string $expiryDate, int $quantity): bool
{
    if ($quantity <= 0) {
        return false;
    }
    return in_array(expiry_state($expiryDate)['key'], ['expired', 'expiring_soon'], true);
}

/**
 * Human phrasing used by alert messages and notification text, e.g.
 *   "expired on 05 Aug 2026"  /  "expires on 15 Oct 2026 (in 21 days)"
 */
function expiry_phrase(?string $expiryDate): string
{
    $state = expiry_state($expiryDate);
    if ($state['key'] === 'none') {
        return 'has no expiry date';
    }

    $pretty = date('d M Y', (int) strtotime((string) $expiryDate));
    if ($state['key'] === 'expired') {
        return 'expired on ' . $pretty;
    }

    $days = (int) $state['daysLeft'];
    if ($days === 0) {
        return 'expires today (' . $pretty . ')';
    }
    return 'expires on ' . $pretty . ' (in ' . $days . ' day' . ($days === 1 ? '' : 's') . ')';
}

/**
 * Validates a user-supplied expiry date from a form.
 * Empty string / null is valid and means "non-perishable".
 *
 * @return array{0:?string,1:?string} [normalisedDate|null, errorMessage|null]
 */
function expiry_parse_input(mixed $raw): array
{
    if ($raw === null) {
        return [null, null];
    }
    $value = trim((string) $raw);
    if ($value === '') {
        return [null, null];
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return [null, 'Expiry date must be a valid date in YYYY-MM-DD format.'];
    }

    // A batch expiring more than a human lifetime out is a typo, not a date.
    if ($date > new DateTimeImmutable('+50 years')) {
        return [null, 'Expiry date is unrealistically far in the future — please check it.'];
    }

    return [$date->format('Y-m-d'), null];
}
