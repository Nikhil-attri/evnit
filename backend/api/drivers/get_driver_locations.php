<?php
header('Content-Type: application/json');

// Allow requests from frontend
header("Access-Control-Allow-Origin: *");

// Database connection
$host = "localhost";
$db_name = "evnit_db";   // Change to your database name
$username = "root";      // Change if needed
$password = "";          // Change if needed

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit;
}

// Fetch active drivers with vehicle info
$sql = "SELECT 
            d.id AS driver_id,
            d.name AS driver_name,
            d.latitude,
            d.longitude,
            v.vehicle_number,
            v.vehicle_type
        FROM drivers d
        INNER JOIN vehicles v ON d.vehicle_id = v.id
        WHERE d.active_status = 1"; // Only active drivers

$result = $conn->query($sql);

$drivers = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            "driver_id" => (int)$row['driver_id'],
            "driver_name" => $row['driver_name'],
            "latitude" => (float)$row['latitude'],
            "longitude" => (float)$row['longitude'],
            "vehicle_number" => $row['vehicle_number'],
            "vehicle_type" => $row['vehicle_type']
        ];
    }
    echo json_encode([
        "status" => "success",
        "drivers" => $drivers
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to fetch driver locations"
    ]);
}

$conn->close();
?>
