<?php
// db.php
// This file connects PHP to the MySQL database in phpMyAdmin.

$host = "localhost";
$user = "root";
$password = "";
$database = "stocksmart_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
