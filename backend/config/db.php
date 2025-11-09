<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "evnit_system";

// MySQLi connection
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Kolkata');

// PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode([
        'status' => 'error',
        'message' => 'PDO connection failed: ' . $e->getMessage()
    ]));
}
?>
