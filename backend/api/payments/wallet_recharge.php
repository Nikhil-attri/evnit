<?php
// ============================================
// FILE: backend/api/payments/wallet_recharge.php
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$amount = $input['amount'] ?? null;
$payment_method = $input['payment_method'] ?? 'upi'; // upi, card, netbanking

if (!$student_id || !$amount || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update wallet balance
    $stmt = $pdo->prepare("UPDATE students SET wallet_balance = wallet_balance + ? WHERE student_id = ?");
    $stmt->execute([$amount, $student_id]);

    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO payments (ride_id, student_id, amount, payment_method, status, transaction_type, created_at) 
        VALUES (NULL, ?, ?, ?, 'completed', 'recharge', NOW())
    ");
    $stmt->execute([$student_id, $amount, $payment_method]);

    // Get new balance
    $stmt = $pdo->prepare("SELECT wallet_balance FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $balance = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Wallet recharged successfully',
        'new_balance' => (float)$balance
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Recharge failed: ' . $e->getMessage()]);
}
?>