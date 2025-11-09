<?php
// api/rides/update_ride_status.php
// Driver updates ride status (reached pickup → started ride)

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
$new_status = trim($_POST['status'] ?? '');
$current_lat = floatval($_POST['current_lat'] ?? 0);
$current_lng = floatval($_POST['current_lng'] ?? 0);

// Validate inputs
if ($ride_id == 0 || empty($new_status)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ride ID or status']);
    exit();
}

// Valid status transitions
$valid_statuses = ['in_progress', 'completed'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit();
}

// Verify ride belongs to this driver
$verify_query = "SELECT id, status, student_id, vehicle_id FROM rides WHERE id = ? AND driver_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $ride_id, $driver_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ride not found or unauthorized']);
    exit();
}

$ride = $verify_result->fetch_assoc();
$current_status = $ride['status'];

// Begin transaction
$conn->begin_transaction();

try {
    if ($new_status === 'in_progress' && $current_status === 'accepted') {
        // Driver reached pickup, starting ride
        $update_query = "UPDATE rides SET status = 'in_progress', pickup_time = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $ride_id);
        $update_stmt->execute();
        
        // Update vehicle status
        $update_vehicle = "UPDATE vehicles SET status = 'on_ride' WHERE id = ?";
        $vehicle_stmt = $conn->prepare($update_vehicle);
        $vehicle_stmt->bind_param("i", $ride['vehicle_id']);
        $vehicle_stmt->execute();
        
        // Notify student
        $notif_query = "INSERT INTO notifications (user_type, user_id, title, message, type) VALUES ('student', ?, 'Ride Started', 'Your ride has started. Enjoy your trip!', 'ride_update')";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("i", $ride['student_id']);
        $notif_stmt->execute();
        
        $message = 'Ride started successfully';
        
    } elseif ($new_status === 'completed' && $current_status === 'in_progress') {
        // Ride completed - handled by complete_ride.php
        echo json_encode([
            'status' => 'error',
            'message' => 'Use complete_ride.php endpoint to complete rides'
        ]);
        $conn->rollback();
        exit();
        
    } else {
        // Invalid status transition
        echo json_encode([
            'status' => 'error',
            'message' => "Cannot change status from $current_status to $new_status"
        ]);
        $conn->rollback();
        exit();
    }
    
    // Log location if provided
    if ($current_lat != 0 && $current_lng != 0) {
        $location_log = "INSERT INTO ride_locations (ride_id, vehicle_lat, vehicle_lng) VALUES (?, ?, ?)";
        $location_stmt = $conn->prepare($location_log);
        $location_stmt->bind_param("idd", $ride_id, $current_lat, $current_lng);
        $location_stmt->execute();
        
        // Update driver's current location
        $update_driver_loc = "UPDATE drivers SET current_lat = ?, current_lng = ? WHERE id = ?";
        $driver_loc_stmt = $conn->prepare($update_driver_loc);
        $driver_loc_stmt->bind_param("ddi", $current_lat, $current_lng, $driver_id);
        $driver_loc_stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'ride_id' => $ride_id,
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update ride status',
        'debug' => $e->getMessage()
    ]);
}

$conn->close();
?>