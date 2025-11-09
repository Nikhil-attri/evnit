<?php
// api/vehicles/get_vehicles.php
// Get all vehicles (for admin)

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$status_filter = $_GET['status'] ?? 'all';

$query = "
    SELECT 
        v.id, v.vehicle_number, v.vehicle_type, v.capacity, v.current_occupancy,
        v.battery_level, v.status, v.current_lat, v.current_lng,
        v.last_service_date, v.total_distance_km, v.created_at,
        d.name as driver_name, d.driver_id, d.phone as driver_phone
    FROM vehicles v
    LEFT JOIN drivers d ON d.vehicle_id = v.id
";

if ($status_filter !== 'all') {
    $query .= " WHERE v.status = ?";
}

$query .= " ORDER BY v.id ASC";

if ($status_filter !== 'all') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = [
        'id' => $row['id'],
        'vehicle_number' => $row['vehicle_number'],
        'vehicle_type' => $row['vehicle_type'],
        'capacity' => intval($row['capacity']),
        'current_occupancy' => intval($row['current_occupancy']),
        'battery_level' => intval($row['battery_level']),
        'status' => $row['status'],
        'location' => [
            'lat' => floatval($row['current_lat']),
            'lng' => floatval($row['current_lng'])
        ],
        'last_service_date' => $row['last_service_date'],
        'total_distance_km' => floatval($row['total_distance_km']),
        'driver' => $row['driver_name'] ? [
            'name' => $row['driver_name'],
            'driver_id' => $row['driver_id'],
            'phone' => $row['driver_phone']
        ] : null,
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'status' => 'success',
    'vehicles' => $vehicles,
    'total_count' => count($vehicles)
]);

$conn->close();
?>