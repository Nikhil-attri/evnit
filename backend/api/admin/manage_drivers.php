<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT 
            d.*,
            v.vehicle_number,
            v.model,
            COUNT(r.ride_id) as total_rides,
            COALESCE(SUM(r.fare), 0) as total_earnings
        FROM drivers d
        LEFT JOIN vehicles v ON d.driver_id = v.driver_id
        LEFT JOIN rides r ON d.driver_id = r.driver_id AND r.status = 'completed'
        GROUP BY d.driver_id
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'drivers' => $drivers]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

// ===== FILE: backend/api/admin/analytics.php =====
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/db.php';

try {
    // Rides by day (last 7 days)
    $stmt = $pdo->query("
        SELECT 
            DATE(ride_date) as date,
            COUNT(*) as rides,
            SUM(fare) as revenue
        FROM rides
        WHERE ride_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(ride_date)
        ORDER BY date
    ");
    $rides_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Popular routes
    $stmt = $pdo->query("
        SELECT 
            l1.name as pickup,
            l2.name as drop,
            COUNT(*) as count
        FROM rides r
        JOIN locations l1 ON r.pickup_location = l1.location_id
        JOIN locations l2 ON r.drop_location = l2.location_id
        WHERE r.status = 'completed'
        GROUP BY r.pickup_location, r.drop_location
        ORDER BY count DESC
        LIMIT 5
    ");
    $popular_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment method distribution
    $stmt = $pdo->query("
        SELECT 
            payment_method,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM rides WHERE status = 'completed'), 1) as percentage
        FROM rides
        WHERE status = 'completed'
        GROUP BY payment_method
    ");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'rides_by_day' => $rides_by_day,
        'popular_routes' => $popular_routes,
        'payment_methods' => $payment_methods
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>