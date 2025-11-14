<?php
// api/rides/get_estimate.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../utils/distance_calculator.php';
require_once '../utils/eta_calculator.php';

// Check user authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$student_id = $_SESSION['user_id'];
$pickup_lat = floatval($_POST['pickup_lat'] ?? 0);
$pickup_lng = floatval($_POST['pickup_lng'] ?? 0);
$pickup_name = trim($_POST['pickup_name'] ?? '');
$drop_lat = floatval($_POST['drop_lat'] ?? 0);
$drop_lng = floatval($_POST['drop_lng'] ?? 0);
$drop_name = trim($_POST['drop_name'] ?? '');
$shared_ride = isset($_POST['shared_ride']) && $_POST['shared_ride'] === '1';

// Validate locations
if (!$pickup_lat || !$pickup_lng || !$drop_lat || !$drop_lng || !$pickup_name || !$drop_name) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid location data']);
    exit();
}

// VNIT campus boundaries
$CAMPUS_BOUNDS = [
    'north' => 21.1303, 'south' => 21.1172,
    'west' => 79.0416, 'east' => 79.0596
];

function isWithinCampus($lat, $lng, $bounds) {
    return ($lat >= $bounds['south'] && $lat <= $bounds['north'] &&
            $lng >= $bounds['west'] && $lng <= $bounds['east']);
}

if (!isWithinCampus($pickup_lat, $pickup_lng, $CAMPUS_BOUNDS) ||
    !isWithinCampus($drop_lat, $drop_lng, $CAMPUS_BOUNDS)) {
    echo json_encode(['status' => 'error', 'message' => 'Locations must be within VNIT campus']);
    exit();
}

// Calculate distance & ETA
$distance_km = calculateDistance($pickup_lat, $pickup_lng, $drop_lat, $drop_lng);
$eta_minutes = calculateETA($distance_km);

// Get nearest available vehicle
$vehicle_sql = "
    SELECT v.id, v.vehicle_number, v.vehicle_type, v.capacity, v.current_occupancy,
           v.current_lat, v.current_lng, d.id AS driver_id, d.name AS driver_name
    FROM vehicles v
    JOIN drivers d ON d.vehicle_id = v.id
    WHERE v.status='available' AND (v.capacity - v.current_occupancy) >= 1 AND d.status='active'
    ORDER BY (6371 * acos(
        cos(radians(?)) * cos(radians(v.current_lat)) *
        cos(radians(v.current_lng) - radians(?)) +
        sin(radians(?)) * sin(radians(v.current_lat))
    )) ASC
    LIMIT 1
";

$stmt = $conn->prepare($vehicle_sql);
$stmt->bind_param("ddd", $pickup_lat, $pickup_lng, $pickup_lat);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status'=>'error', 'message'=>'No vehicles available now']);
    exit();
}

$vehicle = $result->fetch_assoc();
$vehicle_distance = calculateDistance($vehicle['current_lat'], $vehicle['current_lng'], $pickup_lat, $pickup_lng);
$vehicle_eta_minutes = calculateETA($vehicle_distance);

// Dynamic fare calculation
$base_fare = 10.0;
$fare = $shared_ride ? 7.0 : $base_fare; // 30% discount for shared rides

// Check for shared ride availability
$can_share = false;
$shared_passengers = [];

if ($shared_ride) {
    // Look for existing compatible rides
    $share_sql = "
        SELECT r.ride_id, r.pickup_lat, r.pickup_lng, r.drop_lat, r.drop_lng,
               r.pickup_name, r.drop_name, r.status, r.student_id,
               s.name as student_name, v.vehicle_id, v.vehicle_number
        FROM rides r
        JOIN students s ON r.student_id = s.student_id
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        WHERE r.status IN ('pending', 'accepted')
        AND r.pickup_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND r.vehicle_id IN (
            SELECT vehicle_id FROM vehicles
            WHERE (capacity - current_occupancy) > 1
        )
        HAVING (
            (6371 * acos(cos(radians(?)) * cos(radians(r.pickup_lat)) *
             cos(radians(r.pickup_lng) - radians(?)) +
             sin(radians(?)) * sin(radians(r.pickup_lat)))) <= 0.5
        ) AND (
            (6371 * acos(cos(radians(?)) * cos(radians(r.drop_lat)) *
             cos(radians(r.drop_lng) - radians(?)) +
             sin(radians(?)) * sin(radians(r.drop_lat)))) <= 0.5
        )
        ORDER BY r.request_time ASC
        LIMIT 3
    ";

    $stmt = $conn->prepare($share_sql);
    $stmt->bind_param("dddddd", $pickup_lat, $pickup_lng, $pickup_lat, $drop_lat, $drop_lng, $drop_lat);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $can_share = true;
        $shared_passengers = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Check student wallet
$wallet_stmt = $conn->prepare("SELECT wallet_balance FROM students WHERE id=?");
$wallet_stmt->bind_param("i", $student_id);
$wallet_stmt->execute();
$wallet_balance = $wallet_stmt->get_result()->fetch_assoc()['wallet_balance'];
$sufficient_balance = $wallet_balance >= $fare;

// Return JSON
echo json_encode([
    'status'=>'success',
    'estimate'=>[
        'distance_km'=>round($distance_km,2),
        'eta_minutes'=>$eta_minutes,
        'fare'=>$fare,
        'vehicle_info'=>[
            'vehicle_id'=>$vehicle['id'],
            'vehicle_number'=>$vehicle['vehicle_number'],
            'vehicle_type'=>$vehicle['vehicle_type'],
            'driver_name'=>$vehicle['driver_name'],
            'driver_id'=>$vehicle['driver_id'],
            'available_seats'=>$vehicle['capacity'] - $vehicle['current_occupancy']
        ],
        'vehicle_eta_minutes'=>$vehicle_eta_minutes,
        'wallet_balance'=>$wallet_balance,
        'sufficient_balance'=>$sufficient_balance
    ],
    'pickup'=>['name'=>$pickup_name,'lat'=>$pickup_lat,'lng'=>$pickup_lng],
    'drop'=>['name'=>$drop_name,'lat'=>$drop_lat,'lng'=>$drop_lng]
]);

$conn->close();
?>
