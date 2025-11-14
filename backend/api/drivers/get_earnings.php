<?php
// backend/api/drivers/get_earnings.php
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
    // Overall earnings
    $total_sql = "
        SELECT
            COUNT(r.ride_id) as total_rides,
            COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.fare END), 0) as total_earnings,
            COALESCE(AVG(r.fare), 0) as avg_fare,
            MIN(r.fare) as min_fare,
            MAX(r.fare) as max_fare
        FROM rides r
        WHERE r.driver_id = ?
    ";

    $stmt = $conn->prepare($total_sql);
    $stmt->break_param("i", $driver_id);
    $stmt->execute();
    $total_stats = $stmt->get_result()->fetch_assoc();

    // Weekly earnings (last 7 days)
    $weekly_sql = "
        SELECT
            DATE(r.request_time) as ride_date,
            COUNT(r.ride_id) as daily_rides,
            COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.fare END), 0) as daily_earnings
        FROM rides r
        WHERE r.driver_id = ?
        AND r.request_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(r.request_time)
        ORDER BY ride_date DESC
        LIMIT 7
    ";

    $stmt = $conn->prepare($weekly_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $weekly_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Monthly earnings (last 6 months)
    $monthly_sql = "
        SELECT
            DATE_FORMAT(r.request_time, '%Y-%m') as month,
            COUNT(r.ride_id) as monthly_rides,
            COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.fare END), 0) as monthly_earnings
        FROM rides r
        WHERE r.driver_id = ?
        AND r.request_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(r.request_time, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ";

    $stmt = $conn->prepare($monthly_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $monthly_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Performance trends
    $performance_sql = "
        SELECT
            AVG(
                TIMESTAMPDIFF(MINUTE, r.accept_time, r.request_time)
            ) as avg_response_time,
            COUNT(CASE WHEN r.status = 'completed' THEN 1 END) / COUNT(*) * 100 as completion_rate,
            AVG(
                CASE WHEN r.status = 'completed' THEN rr.rating END
            ) as avg_rating,
            COUNT(CASE WHEN DATE(r.request_time) = CURDATE() THEN 1 END) as today_rides,
            COUNT(CASE WHEN r.status = 'completed' AND DATE(r.request_time) = CURDATE() THEN 1 END) as today_completed
        FROM rides r
        LEFT JOIN ride_reviews rr ON r.ride_id = rr.ride_id
        WHERE r.driver_id = ?
        AND r.request_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY r.driver_id
    ";

    $stmt = $conn->prepare($performance_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $performance_data = $stmt->get_result()->fetch_assoc();

    // Determine peak hours
    $peak_hours_sql = "
        SELECT
            HOUR(r.request_time) as hour_of_day,
            COUNT(*) as ride_count
        FROM rides r
        WHERE r.driver_id = ?
        AND r.request_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY HOUR(r.request_time)
        ORDER BY ride_count DESC
        LIMIT 3
    ";

    $stmt = $conn->prepare($peak_hours_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $peak_hours = [];
    while ($row = $stmt->fetch_assoc()) {
        $peak_hours[] = sprintf("%02d:00 - %02d:00", $row['hour_of_day'], $row['hour_of_day']);
    }

    // Areas for improvement
    $improvement_areas = [];
    if ($performance_data['completion_rate'] < 95) {
        $improvement_areas[] = 'Increase ride completion rate';
    }
    if ($performance_data['avg_response_time'] > 5) {
        $improvement_areas[] = 'Reduce response time';
    }
    if ($performance_data['avg_rating'] < 4.5) {
        $improvement_areas[] = 'Improve customer ratings';
    }
    if (empty($improvement_areas)) {
        $improvement_areas[] = 'Maintain current performance';
    }

    echo json_encode([
        'status' => 'success',
        'total_rides' => intval($total_stats['total_rides'] ?? 0),
        'total_earnings' => floatval($total_stats['total_earnings'] ?? 0),
        'avg_rating' => floatval($total_stats['avg_rating'] ?? 0),
        'completion_rate' => floatval($performance_data['completion_rate'] ?? 0),
        'avg_fare' => floatval($total_stats['avg_fare'] ?? 0),
        'min_fare' => floatval($total_stats['min_fare'] ?? 0),
        'max_fare' => floatval($total_stats['max_fare'] ?? 0),
        'weekly_earnings' => $weekly_data,
        'monthly_earnings' => $monthly_data,
        'performance' => [
            'avg_response_time' => round($performance_data['avg_response_time'] ?? 0),
            'acceptance_rate' => 85, // Would need separate calculation
            'peak_hours' => $peak_hours,
            'best_day' => $weekly_data[0]['ride_date'] ?? date('Y-m-d'),
            'improvement_areas' => $improvement_areas
        ],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("Earnings data error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve earnings data',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>