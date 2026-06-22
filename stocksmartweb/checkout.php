<?php
// ============================================================
//  checkout.php — POS Payment + Cart Update Handler (FIXED)
// ============================================================
//
//  HOW THE FIX WORKS:
//  The original code posted cart changes to 'api/cart.php'
//  but that file is in the same folder as checkout.php, not
//  inside an 'api/' subfolder — so every request got a 404
//  and the session was never updated. Items came back on reload
//  because the session still had them.
//
//  Fix: this file handles cart updates itself. When JS sends
//  a POST with ?action=cart_update, PHP processes it at the top
//  and returns JSON. Same file = no path issues ever.
// ============================================================

require_once __DIR__ . '/auth.php';   // session guard — redirects to login.html if not logged in
require_once __DIR__ . '/cart_helper.php';

// ── AJAX: cart update (POST ?action=cart_update) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_GET['action'] ?? '') === 'cart_update') {

    header('Content-Type: application/json; charset=utf-8');

    $body      = json_decode(file_get_contents('php://input'), true);
    $productId = (int)  ($body['productId'] ?? 0);
    $quantity  = (float)($body['quantity']  ?? 0);

    if ($productId <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'productId required']);
        exit;
    }

    // Make sure cart is initialised (won't re-seed — see cart_helper.php)
    cart_init($pdo);

    // For a removal (qty=0) skip the stock check entirely
    if ($quantity > 0) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(i.quantity_available), 0) AS available
            FROM   products p
            LEFT JOIN inventory i ON i.product_id = p.product_id
            WHERE  p.product_id = :pid AND p.status = 'active'
            GROUP BY p.product_id
        ");
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }

        $available = (float)$row['available'];
        if ($quantity > $available) {
            $quantity = $available;
        }
        if ($quantity <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Out of stock']);
            exit;
        }
    }

    cart_set_quantity($productId, $quantity);
    session_write_close(); // release file-lock immediately

    echo json_encode([
        'ok'        => true,
        'itemCount' => count($_SESSION['cart'] ?? []),
        'quantity'  => max(0, $quantity),
    ]);
    exit;
}

// ── AJAX: reset demo cart (POST ?action=reset_demo) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_GET['action'] ?? '') === 'reset_demo') {

    header('Content-Type: application/json; charset=utf-8');

    // Wipe the initialized flag so cart_init() re-seeds fresh
    unset($_SESSION['cart_initialized']);
    unset($_SESSION['cart']);

    cart_init($pdo);  // re-seeds the 5 sample products

    session_write_close();
    echo json_encode(['ok' => true, 'itemCount' => count($_SESSION['cart'] ?? [])]);
    exit;
}

// ── Normal page render ────────────────────────────────────────
$currency = "NPR";

cart_init($pdo);
$cartItems = cart_get_items($pdo);

$itemsTotal          = 0.00;
foreach ($cartItems as $item) {
    $itemsTotal += $item["price"];
}
$itemsCount          = count($cartItems);
$supplierDiscountPct = 0;
$supplierDiscount    = 0.00;
$loyaltyPoints       = 0.00;
$loyaltyDiscount     = 0.00;
$totalPayment        = $itemsTotal - $supplierDiscount - $loyaltyDiscount;
$receiptId           = "REC-" . date("Ymd") . "-" . str_pad((string)rand(0,9999), 4, "0", STR_PAD_LEFT);

function money($v) {
    return number_format($v, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS Payment — Checkout</title>
<style>
  :root{
    --navy-900:#0b1530;
    --navy-800:#11204a;
    --navy-700:#16307a;
    --navy-600:#1d3f9e;
    --accent:#5b6ef5;
    --sidebar-w:264px;
    --shadow-md:0 4px 16px rgba(15,18,38,.07), 0 1px 3px rgba(15,18,38,.04);
    --navy:#16307a;
    --navy-dark:#11204a;
    --blue-accent:#5b6ef5;
    --accent-light:#8b97ff;
    --highlight:#8b97ff;
    --light-blue:#e8eaff;
    --white:#ffffff;
    --bg:#f5f6fb;
    --border:#e8e9f3;
    --grey-text:#7c8299;
    --text-600:#50546b;
    --text-400:#9599ab;
    --dark-text:#0f1226;
    --red:#d8413f;
    --red-bg:#fcecec;
    --amber:#c9790f;
    --amber-bg:#fdf3e3;
    --green:#169e63;
    --green-bg:#e9f9f0;
    --purple:#8454e8;
    --radius:14px;
    --shadow:0 10px 30px rgba(11,21,48,0.16);
    --ease:cubic-bezier(.22,1,.36,1);
    font-family:'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html,body{height:100%;}
  body{
    background:var(--bg);
    min-height:100vh;
    display:flex;
    align-items:flex-start;
    justify-content:center;
    padding:30px 12px;
    margin-left:var(--sidebar-w);
    width:calc(100% - var(--sidebar-w));
    box-sizing:border-box;
  }
  :focus-visible{outline:2.5px solid var(--accent);outline-offset:2px;}

  /* ── Sidebar ── */
  .sidebar{
    width:var(--sidebar-w);min-height:100vh;position:fixed;top:0;left:0;
    background:linear-gradient(195deg,var(--navy-900) 0%,var(--navy-800) 55%,var(--navy-700) 100%);
    color:#dce1f7;padding:26px 18px 22px;display:flex;flex-direction:column;
    z-index:40;transition:transform .3s var(--ease);
  }
  .brand{display:flex;align-items:center;gap:12px;padding:4px 8px 26px;
    margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,.08);
    text-decoration:none;color:inherit;cursor:pointer;transition:opacity .15s var(--ease);}
  .brand:hover{opacity:.88;}
  .brand-mark{width:38px;height:38px;border-radius:11px;
    background:linear-gradient(135deg,var(--accent),#9a5cf0);
    display:grid;place-items:center;box-shadow:0 4px 14px rgba(91,110,245,.45);flex-shrink:0;}
  .brand-mark svg{width:20px;height:20px;}
  .brand-text{font-size:18.5px;font-weight:800;color:#fff;letter-spacing:-.02em;}
  .brand-text span{color:var(--accent-light);font-weight:800;}
  .brand-sub{font-size:10.5px;color:#7986bd;letter-spacing:.05em;margin-top:1px;}
  .nav-scroll{flex:1;overflow-y:auto;}
  .menu-title{color:#5f6aa3;font-size:10.5px;font-weight:700;letter-spacing:.09em;margin:20px 10px 9px;}
  .menu{list-style:none;display:flex;flex-direction:column;gap:3px;}
  .menu-item{display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:10px 12px;border-radius:11px;font-size:13.6px;font-weight:500;color:#b6bee3;
    position:relative;transition:background .18s var(--ease),color .18s var(--ease);}
  .menu-item .left{display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit;flex:1;}
  .menu-item svg{width:18px;height:18px;opacity:.85;flex-shrink:0;}
  .menu-item:hover{background:rgba(255,255,255,.07);color:#fff;}
  .menu-item.active{background:linear-gradient(90deg,var(--accent) 0%,#7b6af2 100%);
    color:#fff;box-shadow:0 6px 16px rgba(91,110,245,.35);}
  .menu-item.active svg{opacity:1;}
  .badge{font-size:10.5px;font-weight:700;padding:2.5px 8px;border-radius:20px;color:#fff;line-height:1.4;}
  .badge.amber{background:var(--amber);}
  .badge.red{background:var(--red);}
  .sidebar-bottom{margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.08);}
  .profile-mini{display:flex;align-items:center;gap:11px;padding:8px;border-radius:12px;
    transition:background .18s var(--ease);}
  .profile-mini:hover{background:rgba(255,255,255,.06);}
  .avatar{width:38px;height:38px;border-radius:50%;
    background:linear-gradient(135deg,#7b6af2,#9a5cf0);
    display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
  .profile-mini b{font-size:13px;color:#fff;display:block;}
  .profile-mini p{font-size:11px;color:#8b96c9;margin-top:1px;}
  .profile-mini .chev{margin-left:auto;opacity:.6;}
  .sidebar-toggle{display:none;position:fixed;top:16px;left:16px;z-index:60;
    width:42px;height:42px;background:var(--navy-900);border-radius:11px;
    align-items:center;justify-content:center;box-shadow:var(--shadow-md);}
  .sidebar-toggle svg{width:20px;height:20px;color:#fff;}
  .sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(11,21,48,.45);z-index:35;}
  @media(max-width:880px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .sidebar-toggle{display:grid;}
    .sidebar-backdrop.show{display:block;}
    body{margin-left:0;width:100%;padding:80px 16px 40px;}
  }

  /* ── Receipt ── */
  .receipt{width:100%;max-width:720px;background:var(--white);border-radius:var(--radius);
    overflow:hidden;box-shadow:var(--shadow);}

  /* ── Items ── */
  .items{background:var(--navy);}
  .items-header{display:grid;grid-template-columns:0.9fr 2.4fr 1fr 0.7fr 0.9fr 0.3fr;
    padding:14px 22px;font-size:11px;letter-spacing:1px;color:#c3c9f5;
    text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,0.08);}
  .item-row{display:grid;grid-template-columns:0.9fr 2.4fr 1fr 0.7fr 0.9fr 0.3fr;
    align-items:center;padding:14px 22px;border-bottom:1px solid rgba(255,255,255,0.08);
    color:#fff;transition:background .2s ease;}
  .item-row:hover{background:rgba(255,255,255,0.05);}
  .item-name{font-weight:600;font-size:14.5px;}
  .item-sku{font-size:11px;color:#9aa3d6;margin-top:2px;}
  .item-img{font-size:26px;text-align:center;filter:drop-shadow(0 2px 3px rgba(0,0,0,.25));}
  .item-disc{color:#d6daf9;}
  .item-price{text-align:right;font-weight:600;}
  .qty-control{display:flex;align-items:center;gap:6px;}
  .qty-btn{width:22px;height:22px;border-radius:6px;border:1px solid rgba(255,255,255,0.25);
    background:rgba(255,255,255,0.08);color:#fff;font-size:14px;line-height:1;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:background .15s ease,transform .1s ease;}
  .qty-btn:hover{background:rgba(255,255,255,0.22);}
  .qty-btn:active{transform:scale(0.9);}
  .qty-value{min-width:26px;text-align:center;font-weight:700;color:#d6daf9;font-size:13px;}
  .remove-btn{text-align:center;color:#9aa3d6;cursor:pointer;font-size:13px;
    opacity:0;transition:opacity .15s ease,color .15s ease;}
  .item-row:hover .remove-btn{opacity:1;}
  .remove-btn:hover{color:var(--red);}
  .item-row.removing{opacity:0;transform:translateX(40px);
    transition:opacity .25s ease,transform .25s ease;}
  .empty-cart{text-align:center;padding:40px 20px;color:#c3c9f5;font-size:13px;}

  /* ── Payment methods ── */
  .payment-section{background:var(--navy-dark);padding:28px 26px 32px 26px;}
  .payment-title{text-align:center;color:#9aa3d6;font-size:11px;letter-spacing:3px;
    text-transform:uppercase;margin-bottom:18px;position:relative;}
  .payment-title::before,.payment-title::after{content:"";position:absolute;top:50%;
    width:35%;height:1px;background:rgba(255,255,255,0.15);}
  .payment-title::before{left:0;} .payment-title::after{right:0;}
  .methods{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
  @media(max-width:600px){.methods{grid-template-columns:repeat(2,1fr);}}
  .method-card{background:var(--white);border-radius:10px;padding:18px 10px 14px;
    text-align:center;cursor:pointer;border:2px solid transparent;
    transition:all .18s ease;user-select:none;}
  .method-card:hover{transform:translateY(-3px);box-shadow:0 8px 18px rgba(0,0,0,0.18);}
  .method-card.selected{border-color:var(--highlight);box-shadow:0 0 0 4px rgba(139,151,255,0.3);}
  .method-icon{font-size:30px;margin-bottom:10px;}
  .method-label{font-size:12.5px;font-weight:700;color:var(--dark-text);}
  .method-label.brand-pay{color:#e1372f;}
  .method-label .pay-badge{display:inline-block;background:#1665d8;color:#fff;
    font-size:11px;padding:1px 6px;border-radius:4px;margin-left:3px;}

  /* ── Totals ── */
  .totals{background:var(--navy);color:#fff;padding:18px 26px;font-size:13px;}
  .totals-row{display:flex;justify-content:space-between;align-items:center;
    padding:6px 0;color:#c3c9f5;}
  .totals-row.main{color:#fff;font-weight:700;font-size:15px;}
  .totals-row .tag{background:rgba(255,255,255,0.12);padding:1px 8px;
    border-radius:4px;font-size:10.5px;margin-left:8px;}
  .totals-row label{display:flex;align-items:center;gap:8px;}
  .mini-input{width:54px;background:rgba(255,255,255,0.1);
    border:1px solid rgba(255,255,255,0.25);border-radius:5px;
    color:#fff;font-size:12px;padding:3px 6px;text-align:center;}
  .mini-input:focus{outline:none;border-color:var(--highlight);background:rgba(255,255,255,0.18);}

  /* ── Footer ── */
  .footer{display:flex;justify-content:space-between;align-items:center;
    padding:24px 26px;background:#fff;flex-wrap:wrap;gap:14px;}
  .total-payment-label{font-size:13px;font-weight:800;letter-spacing:1px;color:var(--dark-text);}
  .total-payment-amount{font-size:28px;font-weight:800;color:var(--dark-text);margin-top:2px;}
  .total-payment-amount span{font-size:14px;font-weight:600;color:var(--grey-text);}
  .unpaid-block{text-align:right;}
  .unpaid-label{font-size:11px;font-weight:700;letter-spacing:1px;color:var(--red);text-transform:uppercase;}
  .unpaid-amount{font-size:28px;font-weight:800;color:var(--red);}
  .unpaid-amount span{font-size:14px;font-weight:600;color:#f29b96;}
  .pay-btn{width:100%;margin:0 26px 26px;padding:16px;border:none;border-radius:10px;
    background:linear-gradient(90deg,var(--blue-accent),var(--highlight));
    color:#fff;font-size:15px;font-weight:800;letter-spacing:1px;cursor:pointer;
    transition:filter .2s ease,transform .15s ease;}
  .pay-btn:hover{filter:brightness(1.07);}
  .pay-btn:active{transform:scale(0.99);}
  .pay-btn:disabled{background:#cfd8e0;cursor:not-allowed;}

  /* ── Modal ── */
  .modal-overlay{position:fixed;inset:0;background:rgba(15,25,40,0.55);
    display:none;align-items:center;justify-content:center;z-index:999;padding:16px;}
  .modal-overlay.show{display:flex;}
  .modal{background:#fff;border-radius:14px;padding:34px 30px;width:100%;max-width:360px;
    text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.3);animation:pop .25s ease;}
  @keyframes pop{from{transform:scale(.85);opacity:0;}to{transform:scale(1);opacity:1;}}
  .modal .icon{font-size:48px;margin-bottom:10px;}
  .modal h3{color:var(--dark-text);margin-bottom:8px;}
  .modal p{color:var(--grey-text);font-size:13.5px;margin-bottom:18px;}
  .modal button{background:var(--navy);color:#fff;border:none;padding:10px 26px;
    border-radius:8px;font-weight:700;cursor:pointer;}
  .spinner{width:42px;height:42px;border:4px solid #e0e8f0;border-top-color:var(--blue-accent);
    border-radius:50%;margin:0 auto 16px;animation:spin .8s linear infinite;}
  @keyframes spin{to{transform:rotate(360deg);}}
  .receipt-id{text-align:center;font-size:10.5px;color:#9aa9b8;padding:10px;
    background:#f5f7fa;letter-spacing:1px;}
</style>
</head>
<body>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
  </svg>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
  <a class="brand" href="homepage.html">
    <div class="brand-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>
      </svg>
    </div>
    <div>
      <div class="brand-text">Stock<span>Smart</span></div>
      <div class="brand-sub">INVENTORY OS</div>
    </div>
  </a>

  <div class="nav-scroll">
    <p class="menu-title">MAIN</p>
    <ul class="menu">
      <li class="menu-item"><a class="left" href="dashboard.html">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard</a></li>
      <li class="menu-item"><a class="left" href="products.html">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13V21"/></svg>
        Products</a></li>
      <li class="menu-item"><a class="left" href="inventory.html">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9z"/><path d="M9 13h6"/></svg>
        Inventory</a></li>
      <li class="menu-item active"><a class="left" href="checkout.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
        Checkout</a></li>
    </ul>

    <p class="menu-title">ALERTS</p>
    <ul class="menu">
      <li class="menu-item"><span class="left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        Restocking Alerts</span><span class="badge amber" id="badgeRestock">8</span></li>
      <li class="menu-item"><span class="left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Expiry Alerts</span><span class="badge red" id="badgeExpiry">16</span></li>
    </ul>
  </div>

  <div class="sidebar-bottom">
    <div class="profile-mini">
      <div class="avatar">AD</div>
      <div><b>Admin User</b><p>Super Admin</p></div>
      <svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </div>
  </div>
</aside>

<div class="receipt" id="receipt">

  <!-- ITEMS -->
  <div class="items">
    <div class="items-header">
      <span>Qty</span><span>Article / SKU</span><span></span>
      <span>Disc.</span><span style="text-align:right;">Price</span><span></span>
    </div>
    <div id="itemsList">
    <?php foreach ($cartItems as $item): ?>
      <div class="item-row"
           data-id="<?= $item['id'] ?>"
           data-unit="<?= $item['unit'] ?>"
           data-available="<?= $item['available'] ?? 0 ?>">
        <span class="qty-control">
          <button type="button" class="qty-btn minus">−</button>
          <span class="qty-value"><?= number_format($item["qty"], 2) ?></span>
          <button type="button" class="qty-btn plus">+</button>
        </span>
        <span>
          <div class="item-name"><?= htmlspecialchars($item["name"]) ?></div>
          <div class="item-sku"><?= htmlspecialchars($item["sku"]) ?></div>
        </span>
        <span class="item-img"><?= $item["img"] ?></span>
        <span class="item-disc"><?= $item["disc"] ?></span>
        <span class="item-price line-price"><?= money($item["price"]) ?></span>
        <span class="remove-btn" title="Remove item">✕</span>
      </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- PAYMENT METHODS -->
  <div class="payment-section">
    <div class="payment-title">Select Payment Details</div>
    <div class="methods" id="methods">
      <div class="method-card" data-method="Card Payment">
        <div class="method-icon">💳</div>
        <div class="method-label">Card Payment</div>
      </div>
      <div class="method-card" data-method="QR Scan">
        <div class="method-icon">📱</div>
        <div class="method-label">QR Scan</div>
      </div>
      <div class="method-card" data-method="Contactless">
        <div class="method-icon">📶</div>
        <div class="method-label">Contactless</div>
      </div>
      <div class="method-card" data-method="Nepal Pay Wallet">
        <div class="method-icon">🇳🇵</div>
        <div class="method-label brand-pay">NEPAL <span class="pay-badge">PAY</span></div>
        <div style="font-size:10.5px;color:var(--grey-text);margin-top:3px;">Nepal Pay Wallet</div>
      </div>
    </div>
  </div>

  <!-- TOTALS -->
  <div class="totals">
    <div class="totals-row main">
      <span>Items Total <span class="tag" id="itemsCountTag"><?= $itemsCount ?> items</span></span>
      <span id="itemsTotal" data-value="<?= $itemsTotal ?>"><?= money($itemsTotal) ?> <?= $currency ?></span>
    </div>
    <div class="totals-row">
      <label>Supplier Discount
        <input type="number" id="supplierPct" class="mini-input" min="0" max="100" step="1" value="<?= $supplierDiscountPct ?>">%
      </label>
      <span id="supplierDisc" data-value="<?= $supplierDiscount ?>"><?= money($supplierDiscount) ?> <?= $currency ?></span>
    </div>
    <div class="totals-row">
      <label>Loyalty Points
        <input type="number" id="loyaltyPts" class="mini-input" min="0" step="1" value="<?= $loyaltyPoints ?>">pts
      </label>
      <span id="loyaltyDisc" data-value="<?= $loyaltyDiscount ?>"><?= money($loyaltyDiscount) ?> <?= $currency ?></span>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <div>
      <div class="total-payment-label">TOTAL PAYMENT</div>
      <div class="total-payment-amount" id="totalPaymentDisplay"
           data-value="<?= $totalPayment ?>"><?= money($totalPayment) ?> <span><?= $currency ?></span></div>
    </div>
    <div class="unpaid-block">
      <div class="unpaid-label">Unpaid Balance · Open Amount</div>
      <div class="unpaid-amount" id="unpaidDisplay"
           data-value="<?= $totalPayment ?>"><?= money($totalPayment) ?> <span><?= $currency ?></span></div>
    </div>
  </div>

  <button class="pay-btn" id="payBtn" disabled>SELECT A PAYMENT METHOD</button>
  <div class="receipt-id">Receipt #<?= $receiptId ?> &nbsp;·&nbsp; <?= date("d M Y, H:i") ?></div>
  <div style="text-align:center;padding:10px 0 18px;">
    <button id="resetDemoBtn" style="
      background:none;border:1px solid #d0d5e8;color:#9aa3d6;
      font-size:11px;padding:5px 14px;border-radius:6px;cursor:pointer;
      transition:all .15s ease;">
      🔄 Reset Demo Cart
    </button>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modalBox"></div>
</div>

<script>
(function(){
  "use strict";

  const currency           = <?= json_encode($currency) ?>;

  // ── THE FIX: post back to THIS file with ?action=cart_update ──────────────
  // No more 'api/cart.php' — same file = no path confusion ever.
  const CART_URL = 'checkout.php?action=cart_update';

  const methods             = document.querySelectorAll('.method-card');
  const payBtn              = document.getElementById('payBtn');
  const modalOverlay        = document.getElementById('modalOverlay');
  const modalBox            = document.getElementById('modalBox');
  const itemsList           = document.getElementById('itemsList');
  const itemsTotalEl        = document.getElementById('itemsTotal');
  const itemsCountTag       = document.getElementById('itemsCountTag');
  const supplierPctInput    = document.getElementById('supplierPct');
  const supplierDiscEl      = document.getElementById('supplierDisc');
  const loyaltyPtsInput     = document.getElementById('loyaltyPts');
  const loyaltyDiscEl       = document.getElementById('loyaltyDisc');
  const totalPaymentDisplay = document.getElementById('totalPaymentDisplay');
  const unpaidDisplay       = document.getElementById('unpaidDisplay');
  const LOYALTY_RATE = 1;

  let selectedMethod = null;
  let isPaid = false;

  function fmt(n){ return Number(n).toLocaleString('en-US',{maximumFractionDigits:0}); }
  function round2(n){ return Math.round(n*100)/100; }

  // ── Recalculate totals ────────────────────────────────────────────────────
  function recalc(){
    const rows = itemsList.querySelectorAll('.item-row');
    let itemsTotal = 0;
    let itemCount  = rows.length;

    rows.forEach(row => {
      const unit      = parseFloat(row.dataset.unit);
      const qty       = parseFloat(row.querySelector('.qty-value').textContent);
      const lineTotal = round2(unit * qty);
      row.querySelector('.line-price').textContent = fmt(lineTotal);
      itemsTotal += lineTotal;
    });

    itemsTotal = round2(itemsTotal);
    itemsTotalEl.dataset.value = itemsTotal;
    itemsTotalEl.textContent   = fmt(itemsTotal) + ' ' + currency;
    itemsCountTag.textContent  = itemCount + (itemCount === 1 ? ' item' : ' items');

    const pct              = Math.min(100, Math.max(0, parseFloat(supplierPctInput.value)||0));
    supplierPctInput.value = pct;
    const supplierDiscount = round2(itemsTotal * (pct/100));
    supplierDiscEl.dataset.value = supplierDiscount;
    supplierDiscEl.textContent   = '-' + fmt(supplierDiscount) + ' ' + currency;

    let pts             = Math.max(0, parseFloat(loyaltyPtsInput.value)||0);
    let loyaltyDiscount = round2(pts * LOYALTY_RATE);
    const remaining     = itemsTotal - supplierDiscount;
    if (loyaltyDiscount > remaining){ loyaltyDiscount = remaining; pts = round2(loyaltyDiscount/LOYALTY_RATE); loyaltyPtsInput.value = pts; }
    loyaltyDiscEl.dataset.value = loyaltyDiscount;
    loyaltyDiscEl.textContent   = '-' + fmt(loyaltyDiscount) + ' (' + pts + ' pts) ' + currency;

    const total = Math.max(0, round2(itemsTotal - supplierDiscount - loyaltyDiscount));
    totalPaymentDisplay.dataset.value = total;
    totalPaymentDisplay.innerHTML     = fmt(total) + ' <span>' + currency + '</span>';

    if (!isPaid){
      unpaidDisplay.dataset.value = total;
      unpaidDisplay.innerHTML     = fmt(total) + ' <span>' + currency + '</span>';
    }

    if (itemCount === 0){
      itemsList.innerHTML    = '<div class="empty-cart">🛒 Cart is empty — nothing to pay for.</div>';
      payBtn.disabled        = true;
      payBtn.textContent     = 'NO ITEMS TO PAY';
    } else if (selectedMethod && !isPaid){
      payBtn.disabled    = false;
      payBtn.textContent = 'PAY WITH ' + selectedMethod.toUpperCase();
    }
  }

  // ── Save cart change to server ────────────────────────────────────────────
  // Uses fetch() with await so we know for certain the session was updated
  // before we remove the row from the DOM. No silent failures.
  async function saveCart(productId, quantity){
    const res = await fetch(CART_URL, {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify({productId, quantity}),
    });
    if (!res.ok){
      const data = await res.json().catch(()=>({}));
      throw new Error(data.error || 'Server error ' + res.status);
    }
    return res.json();
  }

  // ── Click handler (qty + / − and ✕ remove) ───────────────────────────────
  itemsList.addEventListener('click', async (e) => {
    const row = e.target.closest('.item-row');
    if (!row || row.dataset.busy) return;

    const productId = Number(row.dataset.id);

    // ── QTY buttons ──────────────────────────────────────────────────────
    if (e.target.classList.contains('qty-btn')){
      const qtyEl     = row.querySelector('.qty-value');
      const available = parseFloat(row.dataset.available || 'Infinity');
      let qty         = parseFloat(qtyEl.textContent);

      if (e.target.classList.contains('plus')){
        if (qty >= available) return;
        qty += 1;
      } else {
        qty -= 1;
      }

      if (qty <= 0){
        removeRow(row, productId);
        return;
      }

      // Optimistic update — revert on failure
      const oldQty = qtyEl.textContent;
      qtyEl.textContent = qty.toFixed(2);
      recalc();

      try {
        await saveCart(productId, qty);
      } catch(err){
        qtyEl.textContent = oldQty;
        recalc();
        console.error('qty update failed:', err);
      }
      return;
    }

    // ── Remove button ─────────────────────────────────────────────────────
    if (e.target.classList.contains('remove-btn')){
      removeRow(row, productId);
    }
  });

  // ── Remove a row ─────────────────────────────────────────────────────────
  // Waits for server confirmation BEFORE removing the DOM row.
  // If the server call fails, the row stays — no phantom removals.
  async function removeRow(row, productId){
    if (row.dataset.busy) return;
    row.dataset.busy = '1';

    const removeBtn      = row.querySelector('.remove-btn');
    removeBtn.textContent = '…';
    row.querySelectorAll('.qty-btn').forEach(b => b.disabled = true);

    try {
      await saveCart(productId, 0);  // tell server first
      const next = row.nextElementSibling;
      row.classList.add('removing');
      setTimeout(() => {
        if (next && next.classList.contains('combo-sub')) next.remove();
        row.remove();
        recalc();
      }, 230);
    } catch(err){
      // Server failed — restore the row
      removeBtn.textContent = '✕';
      row.querySelectorAll('.qty-btn').forEach(b => b.disabled = false);
      delete row.dataset.busy;
      console.error('remove failed:', err);
      alert('Could not remove item (' + err.message + '). Please try again.');
    }
  }

  // ── Discount / loyalty ────────────────────────────────────────────────────
  supplierPctInput.addEventListener('input', recalc);
  loyaltyPtsInput.addEventListener('input',  recalc);

  // ── Payment method selection ──────────────────────────────────────────────
  methods.forEach(card => {
    card.addEventListener('click', () => {
      if (isPaid) return;
      methods.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      selectedMethod = card.dataset.method;
      if (itemsList.querySelectorAll('.item-row').length > 0){
        payBtn.disabled    = false;
        payBtn.textContent = 'PAY WITH ' + selectedMethod.toUpperCase();
      }
    });
  });

  // ── Payment flow ──────────────────────────────────────────────────────────
  payBtn.addEventListener('click', () => {
    if (!selectedMethod || isPaid) return;
    const amount = totalPaymentDisplay.dataset.value;

    showModal(`<div class="spinner"></div>
      <h3>Processing Payment</h3>
      <p>Charging ${fmt(amount)} ${currency} via ${selectedMethod}...</p>`);

    const cartPayload = Array.from(
      itemsList.querySelectorAll('.item-row:not([data-locked])')
    ).map(row => ({
      productId: Number(row.dataset.id),
      quantity:  parseFloat(row.querySelector('.qty-value').textContent),
      unitPrice: parseFloat(row.dataset.unit),
    }));

    fetch('api/checkout.php', {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify({
        items:           cartPayload,
        itemsTotal:      parseFloat(itemsTotalEl.dataset.value),
        discountAmount:  parseFloat(supplierDiscEl.dataset.value),
        loyaltyDiscount: parseFloat(loyaltyDiscEl.dataset.value),
        grandTotal:      parseFloat(totalPaymentDisplay.dataset.value),
        paymentMethod:   selectedMethod,
      })
    })
    .then(res => res.json().then(data => ({ok: res.ok, data})))
    .then(({ok, data}) => {
      setTimeout(() => {
        if (!ok){
          showModal(`<div class="icon">⚠️</div><h3>Payment Failed</h3>
            <p>${data.error || 'Could not save this sale. Please try again.'}</p>
            <button id="closeModalBtn">Close</button>`);
          document.getElementById('closeModalBtn').addEventListener('click',
            () => modalOverlay.classList.remove('show'));
          return;
        }
        showModal(`<div class="icon">✅</div><h3>Payment Successful</h3>
          <p>${fmt(amount)} ${currency} paid via ${selectedMethod}.</p>
          <p style="opacity:.7;font-size:.85em;margin-top:4px;">Order ${data.orderNumber} saved.</p>
          <button id="closeModalBtn">Done</button>`);
        document.getElementById('closeModalBtn').addEventListener('click', () => {
          modalOverlay.classList.remove('show');
          isPaid = true;
          unpaidDisplay.innerHTML = '0 <span>' + currency + '</span>';
          document.querySelector('.unpaid-label').textContent = 'PAID IN FULL';
          document.querySelector('.unpaid-label').style.color = 'var(--green)';
          unpaidDisplay.style.color    = 'var(--green)';
          payBtn.disabled              = true;
          payBtn.textContent           = 'PAYMENT COMPLETE';
          supplierPctInput.disabled    = true;
          loyaltyPtsInput.disabled     = true;
          itemsList.style.pointerEvents = 'none';
          itemsList.style.opacity       = '0.85';
        });
      }, 1800);
    })
    .catch(() => {
      setTimeout(() => {
        showModal(`<div class="icon">⚠️</div><h3>Payment Failed</h3>
          <p>Network error: could not reach the server.</p>
          <button id="closeModalBtn">Close</button>`);
        document.getElementById('closeModalBtn').addEventListener('click',
          () => modalOverlay.classList.remove('show'));
      }, 1800);
    });
  });

  function showModal(html){ modalBox.innerHTML = html; modalOverlay.classList.add('show'); }
  modalOverlay.addEventListener('click', e => {
    if (e.target === modalOverlay && !modalBox.querySelector('.spinner'))
      modalOverlay.classList.remove('show');
  });

  recalc();

  // ── Mobile sidebar ────────────────────────────────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const toggle   = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (toggle && sidebar && backdrop){
    toggle.addEventListener('click',   () => sidebar.classList.toggle('open') || backdrop.classList.toggle('show'));
    backdrop.addEventListener('click', () => { sidebar.classList.remove('open'); backdrop.classList.remove('show'); });
  }
  // ── Reset demo cart ───────────────────────────────────────────────────────
  // Wipes the session cart and re-seeds the 5 sample products, then reloads
  // the page so everything renders fresh. Handy for demos.
  const resetBtn = document.getElementById('resetDemoBtn');
  if (resetBtn) {
    resetBtn.addEventListener('mouseover', () => resetBtn.style.borderColor = '#5b6ef5');
    resetBtn.addEventListener('mouseout',  () => resetBtn.style.borderColor = '#d0d5e8');
    resetBtn.addEventListener('click', async () => {
      resetBtn.textContent = '⏳ Resetting...';
      resetBtn.disabled = true;
      try {
        await fetch('checkout.php?action=reset_demo', { method: 'POST' });
      } catch(e) { /* ignore network errors — reload anyway */ }
      window.location.reload();
    });
  }

})();
</script>
</body>
</html>
