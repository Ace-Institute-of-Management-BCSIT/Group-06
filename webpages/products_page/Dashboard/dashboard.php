<?php
include 'db.php';

function getValue($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return $row ? array_values($row)[0] : 0;
}

$totalProducts = getValue($conn, "SELECT COUNT(*) AS total FROM products");

$lowStock = getValue($conn, "
    SELECT COUNT(*) AS total 
    FROM products 
    WHERE stock_quantity <= reorder_level
");

$expiringSoon = getValue($conn, "
    SELECT COUNT(*) AS total 
    FROM products 
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
");

$totalSuppliers = getValue($conn, "SELECT COUNT(*) AS total FROM suppliers");

$todaysSales = getValue($conn, "
    SELECT COALESCE(SUM(total_amount), 0) AS total 
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");

$restockAlerts = getValue($conn, "
    SELECT COUNT(*) AS total 
    FROM alerts
    WHERE alert_type = 'RESTOCK' OR alert_type = 'LOW_STOCK'
");

$lowStockProducts = mysqli_query($conn, "
    SELECT product_name, stock_quantity, reorder_level
    FROM products
    WHERE stock_quantity <= reorder_level
    ORDER BY stock_quantity ASC
    LIMIT 6
");

$expiryAlerts = mysqli_query($conn, "
    SELECT product_name, batch_no, stock_quantity, expiry_date,
    DATEDIFF(expiry_date, CURDATE()) AS days_left
    FROM products
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
    ORDER BY expiry_date ASC
    LIMIT 5
");

$recentSales = mysqli_query($conn, "
    SELECT c.category_name, si.quantity, si.subtotal, s.sale_date
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY s.sale_date DESC
    LIMIT 7
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StockSmart Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">⌂</div>
        <h2>Stock<span>Smart</span></h2>
    </div>

    <p class="menu-title">MAIN</p>
    <ul class="menu">
        <li class="active">Dashboard</li>
        <li>Products</li>
        <li>Inventory</li>
        <li>Suppliers</li>
        <li>Sales</li>
    </ul>

    <p class="menu-title">ALERTS</p>
    <ul class="menu">
        <li>Restocking Alerts <span class="badge yellow"><?php echo $restockAlerts; ?></span></li>
        <li>Expiry Alerts <span class="badge red"><?php echo $expiringSoon; ?></span></li>
    </ul>

    <p class="menu-title">ADMIN</p>
    <ul class="menu">
        <li>Reports</li>
        <li>Users</li>
        <li>Settings</li>
    </ul>

    <div class="sidebar-user">
        <div class="avatar">AD</div>
        <div>
            <b>Admin User</b>
            <p>Super Admin</p>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <h1>Dashboard</h1>
            <p><?php echo date("l, d F Y"); ?></p>
        </div>

        <div class="search-box">
            <input type="text" placeholder="Search products, suppliers, orders...">
        </div>

        <div class="top-user">
            <div class="avatar small">AD</div>
            <div>
                <b>Admin User</b>
                <p>Super Admin</p>
            </div>
        </div>
    </header>

    <section class="cards">
        <div class="card">
            <div class="icon blue">□</div>
            <h2><?php echo number_format($totalProducts); ?></h2>
            <p>Total Products</p>
        </div>

        <div class="card">
            <div class="icon orange">⚠</div>
            <h2><?php echo $lowStock; ?></h2>
            <p>Low Stock Items</p>
        </div>

        <div class="card">
            <div class="icon red-icon">▣</div>
            <h2><?php echo $expiringSoon; ?></h2>
            <p>Expiring Soon</p>
        </div>

        <div class="card">
            <div class="icon green">▱</div>
            <h2><?php echo $totalSuppliers; ?></h2>
            <p>Total Suppliers</p>
        </div>

        <div class="card">
            <div class="icon purple">₹</div>
            <h2>Rs. <?php echo number_format($todaysSales); ?></h2>
            <p>Today's Sales</p>
        </div>

        <div class="card">
            <div class="icon orange">↗</div>
            <h2><?php echo $restockAlerts; ?></h2>
            <p>Restocking Alerts</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="left-column">
            <div class="panel">
                <div class="panel-header">
                    <h3>Sales Overview — This Week</h3>
                    <a href="#">Full report →</a>
                </div>

                <div class="chart">
                    <div class="bar" style="height:80px"><span>38k</span></div>
                    <div class="bar" style="height:110px"><span>52k</span></div>
                    <div class="bar" style="height:65px"><span>31k</span></div>
                    <div class="bar" style="height:100px"><span>47k</span></div>
                    <div class="bar" style="height:125px"><span>58k</span></div>
                    <div class="bar light" style="height:95px"><span>45k</span></div>
                </div>

                <div class="days">
                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                </div>

                <p class="weekly-total">Weekly total: <b>Rs. 2,71,000</b></p>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Sales</h3>
                    <a href="#">View all →</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($recentSales)) { ?>
                        <tr>
                            <td><?php echo $row['category_name']; ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><b>Rs. <?php echo number_format($row['subtotal']); ?></b></td>
                            <td><?php echo date("d M Y", strtotime($row['sale_date'])); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="right-column">
            <div class="panel">
                <div class="panel-header">
                    <h3>Low Stock Products</h3>
                    <a href="#">Restock all →</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($lowStockProducts)) { ?>
                        <tr>
                            <td><?php echo $row['product_name']; ?></td>
                            <td><?php echo $row['stock_quantity']; ?> units</td>
                            <td>
                                <?php if ($row['stock_quantity'] <= 5) { ?>
                                    <span class="status critical">Critical</span>
                                <?php } else { ?>
                                    <span class="status low">Low</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Expiry Alerts</h3>
                    <a href="#">Manage →</a>
                </div>

                <?php while($row = mysqli_fetch_assoc($expiryAlerts)) { ?>
                <div class="expiry-item">
                    <div class="expiry-left">
                        <div class="warning-icon">!</div>
                        <div>
                            <b><?php echo $row['product_name']; ?></b>
                            <p>Batch #<?php echo $row['batch_no']; ?> · <?php echo $row['stock_quantity']; ?> units in stock</p>
                        </div>
                    </div>
                    <div class="expiry-date">
                        <b><?php echo date("M d", strtotime($row['expiry_date'])); ?></b>
                        <p>
                            <?php 
                            if ($row['days_left'] == 0) {
                                echo "Today";
                            } else {
                                echo $row['days_left'] . " days left";
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </section>
</main>

</body>
</html>
