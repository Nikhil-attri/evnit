<?php
// ============================================
// FILE: backend/api/payments/process_payment.php
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$ride_id = $input['ride_id'] ?? null;
$payment_method = $input['payment_method'] ?? null;
$barcode_data = $input['barcode_data'] ?? null;

if (!$ride_id || !$payment_method) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get ride details
    $stmt = $pdo->prepare("SELECT * FROM rides WHERE ride_id = ?");
    $stmt->execute([$ride_id]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ride) {
        throw new Exception('Ride not found');
    }

    $student_id = $ride['student_id'];
    $fare = $ride['fare'];

    // Process based on payment method
    if ($payment_method === 'wallet') {
        // Check wallet balance
        $stmt = $pdo->prepare("SELECT wallet_balance FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $balance = $stmt->fetchColumn();

        if ($balance < $fare) {
            throw new Exception('Insufficient wallet balance');
        }

        // Deduct from wallet
        $stmt = $pdo->prepare("UPDATE students SET wallet_balance = wallet_balance - ? WHERE student_id = ?");
        $stmt->execute([$fare, $student_id]);

    } elseif ($payment_method === 'id_card') {
        // Verify barcode matches student ID
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE barcode_id = ?");
        $stmt->execute([$barcode_data]);
        $verified_student = $stmt->fetchColumn();

        if ($verified_student != $student_id) {
            throw new Exception('ID card verification failed');
        }
    }

    // Record payment
    $stmt = $pdo->prepare("
        INSERT INTO payments (ride_id, student_id, amount, payment_method, status, transaction_type, created_at) 
        VALUES (?, ?, ?, ?, 'completed', 'ride_payment', NOW())
    ");
    $stmt->execute([$ride_id, $student_id, $fare, $payment_method]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>