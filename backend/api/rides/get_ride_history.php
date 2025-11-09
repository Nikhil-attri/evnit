<?php
// api/rides/get_ride_history.php
// Returns ride history for a logged-in student

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

$student_id = $_SESSION['user_id'];

try {
    // Prepare SQL to fetch all rides for this student
    $stmt = $conn->prepare("
        SELECT 
            r.id as ride_id,
            r.ride_code,
            r.pickup_name,
            r.drop_name,
            r.pickup_lat,
            r.pickup_lng,
            r.drop_lat,
            r.drop_lng,
            r.distance_km,
            r.estimated_duration_min as eta,
            r.fare,
            r.status,
            r.payment_status,
            d.id as driver_id,
            d.name as driver_name,
            v.id as vehicle_id,
            v.vehicle_number,
            v.vehicle_type,
            r.ride_date
        FROM rides r
        LEFT JOIN drivers d ON r.driver_id = d.id
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.student_id = ?
        ORDER BY r.id DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rides = [];
    while ($row = $result->fetch_assoc()) {
        // Optional: Format datetime nicely
        $row['ride_date'] = date("Y-m-d H:i:s", strtotime($row['ride_date']));
        $rides[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'rides' => $rides
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch ride history',
        'debug' => $e->getMessage()
    ]);
}

// Close DB connection
$conn->close();
?>
