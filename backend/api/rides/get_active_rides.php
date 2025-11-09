<?php
// api/rides/get_active_rides.php
// Driver fetches assigned rides (shows only NEXT location to reach)

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check if user is logged in as driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$driver_id = $_SESSION['user_id'];

// Get driver's active rides
$rides_query = "
    SELECT 
        r.id, r.ride_code, r.status,
        r.pickup_lat, r.pickup_lng, r.pickup_name,
        r.drop_lat, r.drop_lng, r.drop_name,
        r.distance_km, r.estimated_duration_min, r.fare,
        r.request_time, r.accept_time,
        s.name as student_name, s.phone as student_phone, s.roll_number,
        v.vehicle_number, v.vehicle_type,
        CASE 
            WHEN r.status = 'accepted' THEN 'pickup'
            WHEN r.status = 'in_progress' THEN 'drop'
            ELSE 'none'
        END as next_destination_type
    FROM rides r
    JOIN students s ON r.student_id = s.id
    JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.driver_id = ?
      AND r.status IN ('accepted', 'in_progress')
    ORDER BY 
        CASE r.status
            WHEN 'in_progress' THEN 1
            WHEN 'accepted' THEN 2
        END,
        r.request_time ASC
";

$stmt = $conn->prepare($rides_query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

$rides = [];
while ($row = $result->fetch_assoc()) {
    // Determine next location to show driver
    if ($row['next_destination_type'] === 'pickup') {
        $next_location = [
            'type' => 'pickup',
            'name' => $row['pickup_name'],
            'lat' => floatval($row['pickup_lat']),
            'lng' => floatval($row['pickup_lng']),
            'instruction' => "Pick up " . $row['student_name']
        ];
    } elseif ($row['next_destination_type'] === 'drop') {
        $next_location = [
            'type' => 'drop',
            'name' => $row['drop_name'],
            'lat' => floatval($row['drop_lat']),
            'lng' => floatval($row['drop_lng']),
            'instruction' => "Drop " . $row['student_name']
        ];
    } else {
        $next_location = null;
    }
    
    $rides[] = [
        'ride_id' => $row['id'],
        'ride_code' => $row['ride_code'],
        'status' => $row['status'],
        'student' => [
            'name' => $row['student_name'],
            'phone' => $row['student_phone'],
            'roll_number' => $row['roll_number']
        ],
        'vehicle' => [
            'number' => $row['vehicle_number'],
            'type' => $row['vehicle_type']
        ],
        'fare' => floatval($row['fare']),
        'distance_km' => floatval($row['distance_km']),
        'eta_minutes' => intval($row['estimated_duration_min']),
        'request_time' => $row['request_time'],
        'next_location' => $next_location,
        // Only show full route info if ride is in progress
        'full_route' => ($row['status'] === 'in_progress') ? [
            'pickup' => [
                'name' => $row['pickup_name'],
                'lat' => floatval($row['pickup_lat']),
                'lng' => floatval($row['pickup_lng'])
            ],
            'drop' => [
                'name' => $row['drop_name'],
                'lat' => floatval($row['drop_lat']),
                'lng' => floatval($row['drop_lng'])
            ]
        ] : null
    ];
}

echo json_encode([
    'status' => 'success',
    'active_rides' => $rides,
    'total_active' => count($rides)
]);

$conn->close();
?>