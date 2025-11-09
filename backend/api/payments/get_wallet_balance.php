<?php
// api/payments/get_wallet_balance.php
// Get student wallet balance

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$student_id = $_SESSION['user_id'];

$query = "SELECT wallet_balance, name, roll_number FROM students WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Student not found']);
    exit();
}

$student = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'wallet_balance' => floatval($student['wallet_balance']),
    'student_id' => $student_id,
    'student_name' => $student['name'],
    'roll_number' => $student['roll_number']
]);

$conn->close();
?>