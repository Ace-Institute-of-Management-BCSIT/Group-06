<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<?php
// inventory.php
// This page displays the StockSmart inventory dashboard using data from MySQL.

include 'db.php';

function getSingleValue($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return $row ? array_values($row)[0] : 0;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

/* Top statistic card values */
$totalItems = getSingleValue($conn, "SELECT COUNT(*) FROM products");

$inStock = getSingleValue($conn, "
    SELECT COUNT(*) 
    FROM products p
    JOIN inventory_stock s ON p.product_id = s.product_id
    WHERE (s.in_stock - s.reserved) > p.reorder_level
");

$lowStock = getSingleValue($conn, "
    SELECT COUNT(*) 
    FROM products p
    JOIN inventory_stock s ON p.product_id = s.product_id
    WHERE (s.in_stock - s.reserved) > 0
    AND (s.in_stock - s.reserved) <= p.reorder_level
");

$outOfStock = getSingleValue($conn, "
    SELECT COUNT(*) 
    FROM products p
    JOIN inventory_stock s ON p.product_id = s.product_id
    WHERE (s.in_stock - s.reserved) <= 0
");

$totalInventoryValue = getSingleValue($conn, "
    SELECT COALESCE(SUM(s.in_stock * p.unit_cost), 0)
    FROM products p
    JOIN inventory_stock s ON p.product_id = s.product_id
");

$restockingAlerts = $lowStock + $outOfStock;
$expiryAlerts = 16;

/* Inventory table query */
if ($search !== "") {
    $inventorySql = "
        SELECT 
            p.product_id,
            p.product_name,
            p.sku,
            p.product_image,
            p.reorder_level,
            c.category_name,
            l.location_name,
            s.in_stock,
            s.reserved,
            (s.in_stock - s.reserved) AS available,
            p.unit_cost
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        JOIN inventory_stock s ON p.product_id = s.product_id
        JOIN inventory_locations l ON s.location_id = l.location_id
        WHERE p.product_name LIKE ? OR p.sku LIKE ?
        ORDER BY p.product_id ASC
    ";

    $stmt = mysqli_prepare($conn, $inventorySql);
    $searchText = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "ss", $searchText, $searchText);
    mysqli_stmt_execute($stmt);
    $inventoryItems = mysqli_stmt_get_result($stmt);
} else {
    $inventoryItems = mysqli_query($conn, "
        SELECT 
            p.product_id,
            p.product_name,
            p.sku,
            p.product_image,
            p.reorder_level,
            c.category_name,
            l.location_name,
            s.in_stock,
            s.reserved,
            (s.in_stock - s.reserved) AS available,
            p.unit_cost
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        JOIN inventory_stock s ON p.product_id = s.product_id
        JOIN inventory_locations l ON s.location_id = l.location_id
        ORDER BY p.product_id ASC
    ");
}

/* Stock by category data */
$categoryData = mysqli_query($conn, "
    SELECT 
        c.category_name,
        SUM(s.in_stock) AS total_stock
    FROM categories c
    JOIN products p ON c.category_id = p.category_id
    JOIN inventory_stock s ON p.product_id = s.product_id
    GROUP BY c.category_id, c.category_name
    ORDER BY total_stock DESC
");

$totalStockForCategory = getSingleValue($conn, "
    SELECT COALESCE(SUM(in_stock), 0) FROM inventory_stock
");

/* Stock by location data */
$locationData = mysqli_query($conn, "
    SELECT 
        l.location_name,
        SUM(s.in_stock) AS total_stock
    FROM inventory_locations l
    JOIN inventory_stock s ON l.location_id = s.location_id
    GROUP BY l.location_id, l.location_name
    ORDER BY total_stock DESC
");

$totalStockForLocation = getSingleValue($conn, "
    SELECT COALESCE(SUM(in_stock), 0) FROM inventory_stock
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StockSmart Inventory</title>
    <link rel="stylesheet" href="inventory_style.css">
</head>
<body>

<aside class="sidebar">
    <div class="logo-area">
        <div class="logo-icon">⌂</div>
        <h2>Stock<span>Smart</span></h2>
    </div>

    <p class="menu-heading">MAIN</p>
    <ul class="menu">
        <li>Dashboard</li>
        <li>Products</li>
        <li class="active">Inventory</li>
        <li>Suppliers</li>
        <li>Sales</li>
    </ul>

    <p class="menu-heading">ALERTS</p>
    <ul class="menu">
        <li>Restocking Alerts <span class="badge orange"><?php echo $restockingAlerts; ?></span></li>
        <li>Expiry Alerts <span class="badge red"><?php echo $expiryAlerts; ?></span></li>
    </ul>

    <p class="menu-heading">ADMIN</p>
    <ul class="menu">
        <li>Reports</li>
        <li>Users</li>
        <li>Settings</li>
    </ul>

    <div class="sidebar-admin">
        <div class="admin-circle">AD</div>
        <div>
            <b>Admin User</b>
            <p>Super Admin</p>
        </div>
    </div>
</aside>

<main class="main-content">

    <header class="top-header">
        <div>
            <h1>Inventory</h1>
            <p>Dashboard <span>›</span> Inventory</p>
        </div>

        <form class="search-form" method="GET">
            <input 
                type="text" 
                name="search" 
                placeholder="Search products, SKU..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </form>

        <div class="header-actions">
            <div class="notification">🔔</div>
            <div class="profile">
                <div class="profile-circle">AD</div>
                <div>
                    <b>Admin User</b>
                    <p>Super Admin</p>
                </div>
            </div>
        </div>
    </header>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon cyan">□</div>
            <h2><?php echo number_format($totalItems); ?></h2>
            <p>Total Items</p>
            <div class="mini-line cyan-line"></div>
            <span class="growth up">↑ 8.4%</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">◎</div>
            <h2><?php echo number_format($inStock); ?></h2>
            <p>In Stock</p>
            <div class="mini-line green-line"></div>
            <span class="growth up">↑ 6.2%</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">⚠</div>
            <h2><?php echo number_format($lowStock); ?></h2>
            <p>Low Stock</p>
            <div class="mini-line yellow-line"></div>
            <span class="growth warn">↑ 2.1%</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon pink">⊖</div>
            <h2><?php echo number_format($outOfStock); ?></h2>
            <p>Out of Stock</p>
            <div class="mini-line red-line"></div>
            <span class="growth down">↓ 3.4%</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">रु</div>
            <h2>Rs. <?php echo number_format($totalInventoryValue); ?></h2>
            <p>Total Inventory Value</p>
            <div class="mini-line purple-line"></div>
            <span class="growth up">↑ 9.7%</span>
        </div>
    </section>

    <section class="content-layout">

        <div class="inventory-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="small-icon">□</span>
                    <h3>Inventory Overview</h3>
                </div>

                <div class="filters">
                    <button>All Categories</button>
                    <button>All Locations</button>
                    <button>This Month</button>
                </div>
            </div>

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>In Stock</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Location</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($item = mysqli_fetch_assoc($inventoryItems)) { 
                        $available = (int)$item['available'];
                        $reorderLevel = (int)$item['reorder_level'];

                        if ($available <= 0) {
                            $statusText = "Out of Stock";
                            $statusClass = "out";
                        } elseif ($available <= $reorderLevel) {
                            $statusText = "Low Stock";
                            $statusClass = "low";
                        } else {
                            $statusText = "In Stock";
                            $statusClass = "in";
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <div class="product-img"><?php echo $item['product_image']; ?></div>
                                <div>
                                    <b><?php echo htmlspecialchars($item['product_name']); ?></b>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td><?php echo number_format($item['in_stock']); ?></td>
                        <td><?php echo number_format($item['reserved']); ?></td>
                        <td><b><?php echo number_format($available); ?></b></td>
                        <td><span class="status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                        <td><?php echo htmlspecialchars($item['location_name']); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="table-footer">
                <p>Showing inventory items from database</p>
                <div class="pagination">
                    <button>‹</button>
                    <button class="active-page">1</button>
                    <button>2</button>
                    <button>3</button>
                    <button>...</button>
                    <button>›</button>
                </div>
            </div>
        </div>

        <div class="side-panels">

            <div class="side-card">
                <div class="panel-title">
                    <span class="small-icon purple-bg">◉</span>
                    <h3>Stock by Category</h3>
                </div>

                <div class="donut-and-list">
                    <div class="donut-chart"></div>

                    <div class="category-list">
                        <?php 
                        $colors = ["cyan-dot", "green-dot", "yellow-dot", "orange-dot", "purple-dot", "gray-dot"];
                        $i = 0;
                        while ($cat = mysqli_fetch_assoc($categoryData)) { 
                            $percent = $totalStockForCategory > 0 
                                ? round(($cat['total_stock'] / $totalStockForCategory) * 100) 
                                : 0;
                            $colorClass = $colors[$i % count($colors)];
                            $i++;
                        ?>
                        <div class="list-row">
                            <span><i class="<?php echo $colorClass; ?>"></i><?php echo htmlspecialchars($cat['category_name']); ?></span>
                            <b><?php echo $percent; ?>% (<?php echo number_format($cat['total_stock']); ?>)</b>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="side-card">
                <div class="panel-title">
                    <span class="small-icon yellow-bg">⌖</span>
                    <h3>Stock by Location</h3>
                </div>

                <div class="location-list">
                    <?php while ($loc = mysqli_fetch_assoc($locationData)) { 
                        $percent = $totalStockForLocation > 0 
                            ? round(($loc['total_stock'] / $totalStockForLocation) * 100) 
                            : 0;
                    ?>
                    <div class="location-row">
                        <div class="location-text">
                            <span><?php echo htmlspecialchars($loc['location_name']); ?></span>
                            <b><?php echo number_format($loc['total_stock']); ?> (<?php echo $percent; ?>%)</b>
                        </div>
                        <div class="progress">
                            <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

        </div>

    </section>
</main>

</body>
</html>
