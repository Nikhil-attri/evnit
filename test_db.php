<?php
$host = "localhost";
$user = "root";
$pass = ""; // adjust if needed
$dbname = "evnit_system";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Database connected successfully!";
}
?>
