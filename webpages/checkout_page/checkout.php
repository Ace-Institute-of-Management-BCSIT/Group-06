<?php
include "db.php";

$order_id = 1;

if (isset($_POST['confirm_payment'])) {
    $payment_method = $_POST['payment_method'];

    $update = "UPDATE orders SET payment_method = ?, order_status = 'Paid' WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "si", $payment_method, $order_id);
    mysqli_stmt_execute($stmt);

    echo "<script>alert('Payment saved successfully!'); window.location.href='checkout.php';</script>";
}

$order_query = "SELECT * FROM orders WHERE order_id = $order_id";
$order_result = mysqli_query($conn, $order_query);
$order = mysqli_fetch_assoc($order_result);

$item_query = "
    SELECT oi.quantity, oi.item_price, p.product_name, p.sku, p.discount, p.image_path, p.is_combo
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = $order_id
";
$items = mysqli_query($conn, $item_query);

$payment_query = "SELECT * FROM payment_methods";
$payments = mysqli_query($conn, $payment_query);

$total_items = 0;
$subtotal = 0;
$normal_items = [];
$combo_item = null;

while ($row = mysqli_fetch_assoc($items)) {
    $line_total = $row['quantity'] * $row['item_price'];
    $subtotal += $line_total;
    $total_items += $row['quantity'];

    if ($row['is_combo'] == 1) {
        $combo_item = $row;
    } else {
        $normal_items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="checkout-wrapper">

    <div class="items-table">
        <div class="table-header">
            <div>QTY</div>
            <div>ARTICLE / SKU</div>
            <div></div>
            <div>DISC.</div>
            <div>PRICE</div>
        </div>

        <?php foreach ($normal_items as $item): ?>
            <div class="table-row">
                <div class="qty">
                    <?php echo number_format($item['quantity'], 2); ?>
                </div>

                <div class="article">
                    <strong><?php echo $item['product_name']; ?></strong>
                    <span><?php echo $item['sku']; ?></span>
                </div>

                <div class="product-image">
                    <img src="<?php echo $item['image_path']; ?>" alt="">
                </div>

                <div class="discount">
                    <?php echo number_format($item['discount'], 0); ?>
                </div>

                <div class="price">
                    <?php echo number_format($item['quantity'] * $item['item_price'], 0); ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($combo_item): ?>
            <div class="combo-row">
                <div class="combo-qty">
                    <strong><?php echo number_format($combo_item['quantity'], 2); ?></strong>
                    <span>1.00</span>
                    <span>1.00</span>
                    <span>1.00</span>
                    <span>1.00</span>
                </div>

                <div class="combo-details">
                    <h3><?php echo $combo_item['product_name']; ?></h3>
                    <p><?php echo $combo_item['sku']; ?></p>
                    <p>0.3l Homemade Lemonade</p>
                    <p>Melty Burger</p>
                    <p>Classic Fries</p>
                </div>

                <div class="combo-img-box">
                    <img src="<?php echo $combo_item['image_path']; ?>" alt="">
                </div>

                <div class="combo-discount">
                    <strong>0</strong>
                    <span>112</span>
                    <span>644</span>
                    <span>308</span>
                </div>

                <div class="combo-price">
                    <strong><?php echo number_format($combo_item['quantity'] * $combo_item['item_price'], 0); ?></strong>
                    <span>266</span>
                    <span>490</span>
                    <span>168</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST">
        <div class="payment-section">
            <h4>SELECT PAYMENT METHOD</h4>

            <div class="payment-cards">
                <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
                    <label class="payment-card">
                        <input type="radio" name="payment_method" value="<?php echo $payment['method_name']; ?>" required>

                        <img src="<?php echo $payment['image_path']; ?>" alt="">
                        <h3><?php echo $payment['method_name']; ?></h3>
                    </label>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="summary-box">
            <div class="summary-left">
                <p>
                    <strong><?php echo $total_items; ?> Items Total</strong>
                    <span>ID : <?php echo $order['receipt_code']; ?></span>
                </p>

                <p>
                    <strong>Applied Discounts</strong>
                </p>

                <p>
                    <strong>Loyalty Member Benefits</strong>
                </p>
            </div>

            <div class="summary-right">
                <p>
                    <span><?php echo number_format($order['subtotal'], 2); ?></span>
                    <strong><?php echo number_format($order['subtotal'], 0); ?> NPR</strong>
                </p>

                <p>
                    <span>(0.00%)</span>
                    <strong><?php echo number_format($order['discount_total'], 0); ?> NPR</strong>
                </p>

                <p>
                    <span>(0.00 pts)</span>
                    <strong>0 NPR</strong>
                </p>
            </div>
        </div>

        <div class="total-section">
            <div class="total-payment">
                <h2>TOTAL PAYMENT</h2>
                <h1><?php echo number_format($order['total_amount'], 0); ?> <span>NPR</span></h1>
            </div>

            <div class="open-amount">
                <h5>UNPAID BALANCE</h5>
                <h3><?php echo $order['order_status']; ?></h3>
                <h1><?php echo number_format($order['total_amount'], 0); ?> <span>NPR</span></h1>
            </div>
        </div>

        <button type="submit" name="confirm_payment" class="pay-btn">
            CONFIRM PAYMENT
        </button>
    </form>

</div>

</body>
</html>
