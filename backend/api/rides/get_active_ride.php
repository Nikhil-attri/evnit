<?php
// backend/api/rides/get_active_ride.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check user authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];

try {
    // Get the most recent active ride for this student
    $stmt = $conn->prepare("
        SELECT r.*, d.name as driver_name, d.phone as driver_phone, d.driver_id,
               v.vehicle_number, v.vehicle_type, v.battery_level,
               pickup_loc.name as pickup_location_name,
               drop_loc.name as drop_location_name
        FROM rides r
        LEFT JOIN drivers d ON r.driver_id = d.driver_id
        LEFT JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        LEFT JOIN locations pickup_loc ON r.pickup_location = pickup_loc.location_id
        LEFT JOIN locations drop_loc ON r.drop_location = drop_loc.location_id
        WHERE r.student_id = ?
        AND r.status IN ('pending', 'assigned', 'accepted', 'driver_assigned', 'started', 'in_progress')
        ORDER BY r.request_time DESC
        LIMIT 1
    ");

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'success',
            'ride' => null,
            'message' => 'No active ride found'
        ]);
        exit();
    }

    $ride = $result->fetch_assoc();

    // Calculate additional details
    $ride['estimated_eta'] = null;
    $ride['driver_location'] = null;

    if ($ride['driver_id'] && $ride['status'] === 'accepted') {
        // Get current driver location for ETA calculation
        $location_stmt = $conn->prepare("
            SELECT current_lat, current_lng
            FROM drivers
            WHERE driver_id = ?
        ");
        $location_stmt->bind_param("i", $ride['driver_id']);
        $location_stmt->execute();
        $driver_location = $location_stmt->get_result()->fetch_assoc();

        if ($driver_location && $driver_location['current_lat'] && $driver_location['current_lng']) {
            $ride['driver_location'] = $driver_location;

            // Calculate ETA using distance calculator
            require_once '../utils/distance_calculator.php';
            require_once '../utils/eta_calculator.php';

            $distance_km = calculateDistance(
                $driver_location['current_lat'],
                $driver_location['current_lng'],
                $ride['pickup_lat'],
                $ride['pickup_lng']
            );

            $ride['estimated_eta'] = calculateETA($distance_km);
        }
    }

    // Get vehicle current status
    if ($ride['vehicle_id']) {
        $vehicle_stmt = $conn->prepare("
            SELECT status, last_latitude, last_longitude, last_location_update
            FROM vehicles
            WHERE vehicle_id = ?
        ");
        $vehicle_stmt->bind_param("i", $ride['vehicle_id']);
        $vehicle_stmt->execute();
        $vehicle_info = $vehicle_stmt->get_result()->fetch_assoc();

        if ($vehicle_info) {
            $ride['vehicle_status'] = $vehicle_info['status'];
            $ride['vehicle_current_lat'] = $vehicle_info['last_latitude'];
            $ride['vehicle_current_lng'] = $vehicle_info['last_longitude'];
            $ride['vehicle_last_update'] = $vehicle_info['last_location_update'];
        }
    }

    // Format response for frontend
    echo json_encode([
        'status' => 'success',
        'ride' => $ride,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Get active ride error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve active ride',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>