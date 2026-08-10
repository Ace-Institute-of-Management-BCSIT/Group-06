<?php
/**
 * ============================================================================
 *  StockSmart — Alert Destinations (helpers/alert_routes.php)
 * ============================================================================
 *  Every URL an alert can navigate to, defined exactly once.
 *
 *  Alerts surface in four places, and each one used to build its own links:
 *    - the sidebar ALERTS section        (partials/sidebar.php)
 *    - the notification bell dropdown    (api/notifications.php + nav-guard.js)
 *    - the dashboard stat cards          (dashboard.html)
 *    - the dashboard low-stock / expiry panels (dashboard.html)
 *  Four copies of 'inventory.php?filter=restock' and 'expiry.php?batch=' meant
 *  renaming a page or changing a query parameter required finding all of them,
 *  and the stat cards had simply never been given links at all.
 *
 *  PHP callers use the functions below. The browser gets the SAME values —
 *  not a re-typed copy — because page_renderer.php serialises
 *  alert_routes_for_js() into window.STOCKSMART_ROUTES on every page. There is
 *  no second list of URLs in JavaScript to drift out of sync.
 *
 *  Note what these functions deliberately do NOT do: they never encode the
 *  low-stock or expiry RULE. A restock link says "show me the restock view";
 *  the receiving page resolves that through helpers/stock_status.php, the same
 *  way the badge counting it does. The query logic is never smuggled into a URL.
 * ========================================================================== */

declare(strict_types=1);

/**
 * THE route table. Every alert destination in the application is one of these
 * six strings and is written down exactly once, here.
 *
 * `{id}` is the record-id placeholder. Keeping the parameterised routes as
 * templates (rather than as string concatenation inside each function) is what
 * lets the browser receive the very same definitions — see
 * alert_routes_for_js() — instead of a hand-copied second list.
 */
const ALERT_ROUTE_TEMPLATES = [
    // Products/inventory filtered to everything at or below its reorder level.
    // Resolved by inventory.html against StockStatus.needsRestock().
    'restock'         => 'inventory.php?filter=restock',
    // The same restock set on the Products page (products.html reads ?filter=restock).
    'restockProducts' => 'products.php?filter=restock',
    // Batches expired or expiring inside the shared warning window; expiry.php
    // resolves ?status=alerting through helpers/stock_status.php.
    'expiry'          => 'expiry.php?status=alerting',
    // One product, highlighted in the Products table.
    'product'         => 'products.php?product={id}',
    // One batch, highlighted on the Expiry page.
    'batch'           => 'expiry.php?batch={id}',
    // Every batch belonging to one product.
    'productBatches'  => 'expiry.php?product={id}',
];

function alert_route(string $name, ?int $id = null): string
{
    $template = ALERT_ROUTE_TEMPLATES[$name] ?? '';
    if ($template === '') {
        return '';
    }
    return $id === null ? $template : str_replace('{id}', (string) $id, $template);
}

function alert_route_restock(): string
{
    return alert_route('restock');
}

function alert_route_restock_products(): string
{
    return alert_route('restockProducts');
}

function alert_route_expiry(): string
{
    return alert_route('expiry');
}

function alert_route_product(int $productId): string
{
    return alert_route('product', $productId);
}

function alert_route_batch(int $batchId): string
{
    return alert_route('batch', $batchId);
}

function alert_route_product_batches(int $productId): string
{
    return alert_route('productBatches', $productId);
}

/**
 * The route table, handed to the browser as window.STOCKSMART_ROUTES by
 * page_renderer.php. Parameterised entries arrive with their `{id}` intact;
 * pages fill it via the `ssRoute()` helper (see products.html/dashboard.html).
 *
 * Per-record links inside API responses are still resolved SERVER-side (see
 * api/notifications.php), because only the server can check that the product
 * or batch still exists before emitting a link to it.
 *
 * @return array<string, string>
 */
function alert_routes_for_js(): array
{
    return ALERT_ROUTE_TEMPLATES;
}
