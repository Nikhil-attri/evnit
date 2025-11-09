<?php
// api/admin/dashboard_stats.php
// Get dashboard statistics for admin

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Total vehicles
$vehicles_query = "SELECT COUNT(*) as total FROM vehicles";
$vehicles_result = $conn->query($vehicles_query);
$total_vehicles = $vehicles_result->fetch_assoc()['total'];

// Active drivers (online now)
$active_drivers_query = "SELECT COUNT(*) as total FROM drivers WHERE status = 'active'";
$active_drivers_result = $conn->query($active_drivers_query);
$active_drivers = $active_drivers_result->fetch_assoc()['total'];

// Rides today
$rides_today_query = "
    SELECT COUNT(*) as total, SUM(fare) as revenue
    FROM rides 
    WHERE DATE(request_time) = CURDATE()
";
$rides_today_result = $conn->query($rides_today_query);
$rides_today_data = $rides_today_result->fetch_assoc();
$rides_today = $rides_today_data['total'];
$revenue_today = floatval($rides_today_data['revenue']);

// Average battery level
$battery_query = "SELECT AVG(battery_level) as avg_battery FROM vehicles";
$battery_result = $conn->query($battery_query);
$avg_battery = round($battery_result->fetch_assoc()['avg_battery']);

// Average rating
$rating_query = "SELECT AVG(rating) as avg_rating FROM rides WHERE rating IS NOT NULL";
$rating_result = $conn->query($rating_query);
$avg_rating = round($rating_result->fetch_assoc()['avg_rating'], 1);

// Active rides right now
$active_rides_query = "
    SELECT 
        r.id, r.ride_code, r.status, r.pickup_name, r.drop_name,
        r.estimated_duration_min,
        s.name as student_name, s.roll_number,
        d.name as driver_name, d.driver_id
    FROM rides r
    JOIN students s ON r.student_id = s.id
    JOIN drivers d ON r.driver_id = d.id
    WHERE r.status IN ('accepted', 'in_progress')
    ORDER BY r.request_time DESC
    LIMIT 10
";
$active_rides_result = $conn->query($active_rides_query);

$active_rides = [];
while ($row = $active_rides_result->fetch_assoc()) {
    $active_rides[] = [
        'ride_id' => $row['id'],
        'ride_code' => $row['ride_code'],
        'student' => $row['student_name'] . ' (' . $row['roll_number'] . ')',
        'driver' => $row['driver_name'] . ' (' . $row['driver_id'] . ')',
        'route' => $row['pickup_name'] . ' → ' . $row['drop_name'],
        'status' => $row['status'],
        'eta' => $row['estimated_duration_min'] . ' min'
    ];
}

// Peak hour analysis (last 7 days)
$peak_hour_query = "
    SELECT HOUR(request_time) as hour, COUNT(*) as ride_count
    FROM rides
    WHERE request_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY HOUR(request_time)
    ORDER BY ride_count DESC
    LIMIT 1
";
$peak_hour_result = $conn->query($peak_hour_query);
$peak_hour_data = $peak_hour_result->fetch_assoc();
$peak_hour = $peak_hour_data ? $peak_hour_data['hour'] . ':00' : 'N/A';

// Daily trend (last 7 days)
$trend_query = "
    SELECT DATE(request_time) as date, COUNT(*) as rides, SUM(fare) as revenue
    FROM rides
    WHERE request_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(request_time)
    ORDER BY date ASC
";
$trend_result = $conn->query($trend_query);

$daily_trend = [];
while ($row = $trend_result->fetch_assoc()) {
    $daily_trend[] = [
        'date' => $row['date'],
        'rides' => intval($row['rides']),
        'revenue' => floatval($row['revenue'])
    ];
}

echo json_encode([
    'status' => 'success',
    'stats' => [
        'total_vehicles' => intval($total_vehicles),
        'active_drivers' => intval($active_drivers),
        'rides_today' => intval($rides_today),
        'revenue_today' => $revenue_today,
        'avg_battery_level' => $avg_battery,
        'avg_rating' => $avg_rating,
        'peak_hour' => $peak_hour
    ],
    'active_rides' => $active_rides,
    'daily_trend' => $daily_trend
]);

$conn->close();
?>