<?php
// backend/api/drivers/get_ride_requests.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../utils/route_optimizer.php';

// Check driver authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$driver_id = $_SESSION['user_id'];

try {
    // Get driver's current location
    $driver_sql = "
        SELECT current_lat, current_lng
        FROM drivers
        WHERE driver_id = ?
    ";
    $stmt = $conn->prepare($driver_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $driver_result = $stmt->get_result()->fetch_assoc();

    if (!$driver_result || !$driver_result['current_lat'] || !$driver_result['current_lng']) {
        echo json_encode(['status' => 'error', 'message' => 'Driver location not available']);
        exit();
    }

    $driver_lat = $driver_result['current_lat'];
    $driver_lng = $driver_result['current_lng'];

    // Find nearby ride requests using route optimizer
    $routeOptimizer = new RouteOptimizer($conn);
    $requests = $routeOptimizer->findSharedRideOpportunities($driver_lat, $driver_lng, $driver_lat, $driver_lng, 2.0);

    // Format requests for frontend
    $formatted_requests = [];
    foreach ($requests as $request) {
        // Calculate distance from driver
        $distance = calculateDistance(
            $driver_lat, $driver_lng,
            $request['pickup_lat'], $request['pickup_lng']
        );

        // Check if request is urgent (within 5 minutes)
        $request_time = new DateTime($request['request_time']);
        $now = new DateTime();
        $time_diff = $now->getTimestamp() - $request_time->getTimestamp();
        $is_urgent = $time_diff < 300; // 5 minutes

        // Calculate ETA
        $eta_minutes = ceil($distance / 0.3); // Assuming 300m/min average speed

        // Get shared passengers info
        $shared_passengers = [];
        if (isset($request['shared_passengers']) && is_array($request['shared_passengers'])) {
            foreach ($request['shared_passengers'] as $passenger) {
                $shared_passengers[] = [
                    'student_name' => $passenger['student_name'],
                    'roll_number' => $passenger['roll_number'] ?? ''
                ];
            }
        }

        $formatted_requests[] = [
            'ride_request_id' => uniqid('req_' . time()),
            'pickup_name' => $request['pickup_name'],
            'drop_name' => $request['drop_name'],
            'pickup_lat' => $request['pickup_lat'],
            'pickup_lng' => $request['pickup_lng'],
            'drop_lat' => $request['drop_lat'],
            'drop_lng' => $request['drop_lng'],
            'distance_km' => round($distance, 2),
            'eta_minutes' => $eta_minutes,
            'fare' => floatval($request['fare'] ?? 10.0),
            'student_id' => $request['student_id'],
            'student_name' => $request['student_name'],
            'student_email' => $request['student_email'] ?? '',
            'request_time' => $request['request_time'],
            'status' => $request['status'],
            'urgent' => $is_urgent,
            'shared_ride' => !empty($shared_passengers),
            'shared_passengers' => $shared_passengers,
            'expires_in' => max(0, 600 - $time_diff) // 10 minutes expiry
        ];
    }

    // Sort by priority (urgent first, then by distance)
    usort($formatted_requests, function($a, $b) {
        if ($a['urgent'] && !$b['urgent']) return -1;
        if (!$a['urgent'] && $b['urgent']) return 1;
        return $a['distance_km'] <=> $b['distance_km'];
    });

    echo json_encode([
        'status' => 'success',
        'requests' => $formatted_requests,
        'driver_location' => [
            'lat' => $driver_lat,
            'lng' => $driver_lng
        ],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Get ride requests error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve ride requests',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>