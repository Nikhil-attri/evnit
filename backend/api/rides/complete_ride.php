<?php
// api/rides/complete_ride.php
// Complete ride and process payment (wallet or ID card)

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$driver_id = $_SESSION['user_id'];
$ride_id = intval($_POST['ride_id'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? 'wallet');
$barcode_scanned = trim($_POST['barcode_scanned'] ?? '');

// Validate
if ($ride_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ride ID']);
    exit();
}

// Verify ride
$ride_query = "
    SELECT r.*, s.wallet_balance, s.id_card_barcode, s.name as student_name
    FROM rides r
    JOIN students s ON r.student_id = s.id
    WHERE r.id = ? AND r.driver_id = ? AND r.status = 'in_progress'
";
$ride_stmt = $conn->prepare($ride_query);
$ride_stmt->bind_param("ii", $ride_id, $driver_id);
$ride_stmt->execute();
$ride_result = $ride_stmt->get_result();

if ($ride_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ride not found or already completed']);
    exit();
}

$ride = $ride_result->fetch_assoc();
$student_id = $ride['student_id'];
$fare = floatval($ride['fare']);
$vehicle_id = $ride['vehicle_id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Validate payment method
    if ($payment_method === 'id_card') {
        // Verify barcode matches student
        if (empty($barcode_scanned)) {
            throw new Exception('Barcode not scanned');
        }
        if ($barcode_scanned !== $ride['id_card_barcode']) {
            throw new Exception('Invalid ID card - does not match student');
        }
        
        // Deduct from wallet (ID card links to wallet)
        if ($ride['wallet_balance'] < $fare) {
            throw new Exception('Insufficient wallet balance');
        }
        
        $new_balance = $ride['wallet_balance'] - $fare;
        $update_wallet = "UPDATE students SET wallet_balance = ? WHERE id = ?";
        $wallet_stmt = $conn->prepare($update_wallet);
        $wallet_stmt->bind_param("di", $new_balance, $student_id);
        $wallet_stmt->execute();
        
        // Log wallet transaction
        $wallet_log = "INSERT INTO wallet_transactions (student_id, transaction_type, amount, balance_after, description, reference_id) VALUES (?, 'debit', ?, ?, ?, ?)";
        $wallet_log_stmt = $conn->prepare($wallet_log);
        $description = "Ride payment - " . $ride['ride_code'];
        $wallet_log_stmt->bind_param("iddss", $student_id, $fare, $new_balance, $description, $ride['ride_code']);
        $wallet_log_stmt->execute();
        
    } elseif ($payment_method === 'wallet') {
        // Direct wallet deduction
        if ($ride['wallet_balance'] < $fare) {
            throw new Exception('Insufficient wallet balance');
        }
        
        $new_balance = $ride['wallet_balance'] - $fare;
        $update_wallet = "UPDATE students SET wallet_balance = ? WHERE id = ?";
        $wallet_stmt = $conn->prepare($update_wallet);
        $wallet_stmt->bind_param("di", $new_balance, $student_id);
        $wallet_stmt->execute();
        
        // Log wallet transaction
        $wallet_log = "INSERT INTO wallet_transactions (student_id, transaction_type, amount, balance_after, description, reference_id) VALUES (?, 'debit', ?, ?, ?, ?)";
        $wallet_log_stmt = $conn->prepare($wallet_log);
        $description = "Ride payment - " . $ride['ride_code'];
        $wallet_log_stmt->bind_param("iddss", $student_id, $fare, $new_balance, $description, $ride['ride_code']);
        $wallet_log_stmt->execute();
    } else {
        throw new Exception('Invalid payment method');
    }
    
    // Create payment record
    $transaction_id = 'TXN-' . date('YmdHis') . rand(100, 999);
    $payment_insert = "
        INSERT INTO payments (transaction_id, student_id, ride_id, amount, payment_type, payment_method, status, barcode_scanned)
        VALUES (?, ?, ?, ?, 'ride_payment', ?, 'success', ?)
    ";
    $payment_stmt = $conn->prepare($payment_insert);
    $payment_stmt->bind_param("siidss", $transaction_id, $student_id, $ride_id, $fare, $payment_method, $barcode_scanned);
    $payment_stmt->execute();
    
    // Update ride status
    $update_ride = "UPDATE rides SET status = 'completed', drop_time = NOW(), payment_status = 'paid', payment_method = ? WHERE id = ?";
    $update_ride_stmt = $conn->prepare($update_ride);
    $update_ride_stmt->bind_param("si", $payment_method, $ride_id);
    $update_ride_stmt->execute();
    
    // Update vehicle (reduce occupancy, change status if empty)
    $update_vehicle = "
        UPDATE vehicles 
        SET current_occupancy = GREATEST(current_occupancy - 1, 0),
            status = CASE WHEN current_occupancy - 1 <= 0 THEN 'available' ELSE status END
        WHERE id = ?
    ";
    $vehicle_stmt = $conn->prepare($update_vehicle);
    $vehicle_stmt->bind_param("i", $vehicle_id);
    $vehicle_stmt->execute();
    
    // Update driver (earnings, total rides, status)
    $update_driver = "
        UPDATE drivers 
        SET total_earnings = total_earnings + ?,
            total_rides = total_rides + 1,
            status = 'active'
        WHERE id = ?
    ";
    $driver_stmt = $conn->prepare($update_driver);
    $driver_stmt->bind_param("di", $fare, $driver_id);
    $driver_stmt->execute();
    
    // Notify student
    $notif_query = "INSERT INTO notifications (user_type, user_id, title, message, type) VALUES ('student', ?, 'Ride Completed', ?, 'ride_update')";
    $notif_message = "Your ride has been completed. ₹$fare has been deducted from your wallet.";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("is", $student_id, $notif_message);
    $notif_stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Ride completed successfully',
        'payment' => [
            'transaction_id' => $transaction_id,
            'amount' => $fare,
            'method' => $payment_method,
            'student_new_balance' => $new_balance
        ],
        'ride' => [
            'ride_id' => $ride_id,
            'ride_code' => $ride['ride_code'],
            'student_name' => $ride['student_name']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>