<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit();
}

$vehicle_number = trim($_POST['vehicle_number'] ?? '');
$vehicle_type = trim($_POST['vehicle_type'] ?? 'e-rickshaw');
$capacity = intval($_POST['capacity'] ?? 4);

if (empty($vehicle_number)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle number required']);
    exit();
}

$insert = "INSERT INTO vehicles (vehicle_number, vehicle_type, capacity) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insert);
$stmt->bind_param("ssi", $vehicle_number, $vehicle_type, $capacity);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Vehicle added', 'vehicle_id' => $conn->insert_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add vehicle']);
}
$conn->close();
?>