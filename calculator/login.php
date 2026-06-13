<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "cal_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = ? AND password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $password);
$stmt->execute(); 

$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $_SESSION['username'] = $username;
    header("Location: calculator.html");
    exit();
} else {
    echo "Invalid username or password.";
}

$stmt->close();
$conn->close();
?>