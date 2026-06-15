<?php
include 'db.php';

$result = mysqli_query(
    $conn,
    "SELECT * FROM products"
);

$totalProducts = mysqli_num_rows($result);

$activeProducts = mysqli_num_rows(
mysqli_query(
$conn,
"SELECT * FROM products WHERE stock > 0"
));

$lowStock = mysqli_num_rows(
mysqli_query(
$conn,
"SELECT * FROM products WHERE stock < 10"
));

$outStock = mysqli_num_rows(
mysqli_query(
$conn,
"SELECT * FROM products WHERE stock = 0"
));

$totalValueQuery = mysqli_query(
$conn,
"SELECT SUM(stock * price) AS totalValue FROM products"
);

$totalValue = mysqli_fetch_assoc($totalValueQuery);
?>
