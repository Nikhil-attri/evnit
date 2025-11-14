<?php
// backend/api/drivers/get_driver_stats.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check driver authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$driver_id = $_SESSION['user_id'];

try {
    // Get today's date range
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // Today's stats
    $today_sql = "
        SELECT
            COUNT(CASE WHEN DATE(r.request_time) = CURDATE() THEN r.ride_id END) as today_rides,
            COALESCE(SUM(CASE WHEN DATE(r.request_time) = CURDATE() AND r.status = 'completed' THEN r.fare END), 0) as today_earnings,
            COUNT(CASE WHEN DATE(r.request_time) = CURDATE() AND r.status IN ('accepted', 'started') THEN r.ride_id END) as active_rides,
            ROUND(AVG(CASE WHEN DATE(r.request_time) = CURDATE() AND rr.rating IS NOT NULL THEN rr.rating END), 2) as avg_rating
        FROM rides r
        LEFT JOIN ride_reviews rr ON r.ride_id = rr.ride_id
        WHERE r.driver_id = ?
        GROUP BY r.driver_id
    ";

    $stmt = $conn->prepare($today_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $today_stats = $stmt->get_result()->fetch_assoc();

    // Overall stats
    $total_sql = "
        SELECT
            COUNT(r.ride_id) as total_rides,
            COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.fare END), 0) as total_earnings,
            ROUND(AVG(CASE WHEN rr.rating IS NOT NULL THEN rr.rating END), 2) as avg_rating,
            COUNT(CASE WHEN r.status = 'completed' THEN 1 END) / COUNT(r.ride_id) * 100 as completion_rate
        FROM rides r
        LEFT JOIN ride_reviews rr ON r.ride_id = rr.ride_id
        WHERE r.driver_id = ?
    ";

    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $total_stats = $stmt->get_result()->fetch_assoc();

    // Calculate efficiency score based on multiple factors
    $efficiency_score = 85; // Base score

    // Factor: Acceptance rate (higher is better)
    $acceptance_sql = "
        SELECT COUNT(*) / (SELECT COUNT(*) FROM rides WHERE driver_id = ? AND DATE(request_time) = CURDATE()) as acceptance_rate
        FROM rides WHERE driver_id = ? AND DATE(request_time) = CURDATE()
    ";
    $stmt = $conn->prepare($acceptance_sql);
    $stmt->bind_param("ii", $driver_id, $driver_id);
    $stmt->execute();
    $acceptance_result = $stmt->get_result()->fetch_row();

    if ($acceptance_result && $acceptance_result[0] > 0) {
        $acceptance_rate = $acceptance_result[1] / $acceptance_result[0];
        $efficiency_score += min(15, $acceptance_rate * 100); // Max 15 points
    }

    // Factor: Average response time (lower is better)
    $response_time_sql = "
        SELECT AVG(
            TIMESTAMPDIFF(MINUTE, r.accept_time, r.request_time)
        ) as avg_response_time
        FROM rides
        WHERE driver_id = ?
        AND DATE(request_time) = CURDATE()
        AND accept_time IS NOT NULL
        AND request_time IS NOT NULL
    ";
    $stmt = $conn->prepare($response_time_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $response_time_result = $stmt->get_result()->fetch_row();

    if ($response_time_result && $response_time_result[0]) {
        $avg_response_minutes = $response_time_result[0];
        if ($avg_response_minutes < 2) {
            $efficiency_score += 10; // Excellent response time
        } elseif ($avg_response_minutes < 5) {
            $efficiency_score += 5; // Good response time
        }
    }

    // Cap efficiency score at 100
    $efficiency_score = min(100, $efficiency_score);

    echo json_encode([
        'status' => 'success',
        'today_rides' => intval($today_stats['today_rides'] ?? 0),
        'today_earnings' => floatval($today_stats['today_earnings'] ?? 0),
        'active_rides' => intval($today_stats['active_rides'] ?? 0),
        'total_rides' => intval($total_stats['total_rides'] ?? 0),
        'total_earnings' => floatval($total_stats['total_earnings'] ?? 0),
        'avg_rating' => floatval($total_stats['avg_rating'] ?? 0),
        'completion_rate' => floatval($total_stats['completion_rate'] ?? 0),
        'efficiency_score' => intval($efficiency_score),
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Driver stats error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve driver statistics',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>