<?php
// api/rides/accept_ride.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
    exit();
}

$student_id = $_SESSION['user_id'];
$vehicle_id = intval($_POST['vehicle_id'] ?? 0);
$driver_id = intval($_POST['driver_id'] ?? 0);
$pickup_lat = floatval($_POST['pickup_lat'] ?? 0);
$pickup_lng = floatval($_POST['pickup_lng'] ?? 0);
$pickup_name = trim($_POST['pickup_name'] ?? '');
$drop_lat = floatval($_POST['drop_lat'] ?? 0);
$drop_lng = floatval($_POST['drop_lng'] ?? 0);
$drop_name = trim($_POST['drop_name'] ?? '');
$distance_km = floatval($_POST['distance_km'] ?? 0);
$eta_minutes = intval($_POST['estimated_duration_min'] ?? 0);
$fare = 10.0;

if (!$vehicle_id || !$driver_id || !$pickup_name || !$drop_name) {
    echo json_encode(['status'=>'error','message'=>'Invalid ride details']);
    exit();
}

// Check wallet
$stmt = $conn->prepare("SELECT wallet_balance FROM students WHERE id=?");
$stmt->bind_param("i",$student_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();

if ($wallet['wallet_balance'] < $fare) {
    echo json_encode(['status'=>'error','message'=>'Insufficient wallet balance']);
    exit();
}

// Check vehicle availability
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE id=? AND status='available'");
$stmt->bind_param("i",$vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle || $vehicle['current_occupancy'] >= $vehicle['capacity']) {
    echo json_encode(['status'=>'error','message'=>'Vehicle unavailable or full']);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert ride
    $ride_code = 'RIDE-'.date('YmdHis').rand(100,999);
    $stmt = $conn->prepare("INSERT INTO rides (ride_code, student_id, driver_id, vehicle_id, pickup_lat, pickup_lng, pickup_name, drop_lat, drop_lng, drop_name, distance_km, estimated_duration_min, fare, status, payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'accepted','pending')");
    $stmt->bind_param("siiiddsdddisd",$ride_code,$student_id,$driver_id,$vehicle_id,$pickup_lat,$pickup_lng,$pickup_name,$drop_lat,$drop_lng,$drop_name,$distance_km,$eta_minutes,$fare);
    $stmt->execute();
    $ride_id = $conn->insert_id;

    // Deduct fare
    $stmt = $conn->prepare("UPDATE students SET wallet_balance = wallet_balance - ? WHERE id=?");
    $stmt->bind_param("di",$fare,$student_id);
    $stmt->execute();

    // Update vehicle occupancy
    $stmt = $conn->prepare("UPDATE vehicles SET current_occupancy = current_occupancy + 1 WHERE id=?");
    $stmt->bind_param("i",$vehicle_id);
    $stmt->execute();

    // Update driver status
    $stmt = $conn->prepare("UPDATE drivers SET status='on_ride' WHERE id=?");
    $stmt->bind_param("i",$driver_id);
    $stmt->execute();

    // Notification to driver
    $message = "New ride assigned: $pickup_name → $drop_name | Fare: ₹$fare";
    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message, type) VALUES ('driver', ?, 'New Ride Assigned', ?, 'ride_update')");
    $stmt->bind_param("is",$driver_id,$message);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['status'=>'success','message'=>'Ride confirmed!','ride'=>[
        'ride_id'=>$ride_id,
        'ride_code'=>$ride_code,
        'status'=>'accepted',
        'pickup'=>$pickup_name,
        'drop'=>$drop_name,
        'fare'=>$fare,
        'eta'=>$eta_minutes
    ]]);

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>'Failed to book ride','debug'=>$e->getMessage()]);
}

$conn->close();
?>
