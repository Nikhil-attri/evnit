<?php
// ============================================
// FILE: backend/api/payments/payment_history.php
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/db.php';

$student_id = $_GET['student_id'] ?? null;
$limit = $_GET['limit'] ?? 20;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, r.pickup_location, r.drop_location 
        FROM payments p
        LEFT JOIN rides r ON p.ride_id = r.ride_id
        WHERE p.student_id = ?
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$student_id, (int)$limit]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>