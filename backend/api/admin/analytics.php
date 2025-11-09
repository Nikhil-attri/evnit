<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Last 30 days analytics
$query = "
    SELECT 
        DATE(request_time) as date,
        COUNT(*) as total_rides,
        SUM(fare) as revenue,
        AVG(estimated_duration_min) as avg_duration
    FROM rides
    WHERE request_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(request_time)
    ORDER BY date ASC
";

$result = $conn->query($query);
$analytics = [];
while ($row = $result->fetch_assoc()) {
    $analytics[] = [
        'date' => $row['date'],
        'total_rides' => intval($row['total_rides']),
        'revenue' => floatval($row['revenue']),
        'avg_duration' => round(floatval($row['avg_duration']), 1)
    ];
}

echo json_encode(['status' => 'success', 'analytics' => $analytics]);
$conn->close();
?>