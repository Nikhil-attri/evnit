<?php
// backend/api/admin/fleet_status.php
session_start();
header('Content-Type: application/json');
require_once '../../../config/db.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get all vehicles with their current status
    $fleet_sql = "
        SELECT
            v.vehicle_id, v.vehicle_number, v.vehicle_type, v.capacity, v.battery_level,
            v.status as vehicle_status, v.last_latitude, v.last_longitude,
            v.last_location_update,
            d.driver_id, d.name as driver_name, d.phone as driver_phone,
            d.current_lat as driver_lat, d.current_lng as driver_lng,
            d.status as driver_status,
            COUNT(CASE WHEN r.status = 'started' THEN 1 END) as active_rides_today,
            COUNT(CASE WHEN r.status = 'completed' AND DATE(r.request_time) = CURDATE() THEN 1 END) as completed_rides_today,
            COALESCE(AVG(CASE WHEN rr.rating IS NOT NULL THEN rr.rating END), 0) as avg_rating,
            COALESCE(SUM(CASE WHEN r.status = 'completed' AND DATE(r.request_time) = CURDATE() THEN r.fare END), 0) as today_earnings,
            COUNT(CASE WHEN r.status = 'completed' THEN 1 END) / COUNT(r.ride_id) * 100 as completion_rate
        FROM vehicles v
        LEFT JOIN drivers d ON v.driver_id = d.driver_id
        LEFT JOIN rides r ON v.vehicle_id = r.vehicle_id
        LEFT JOIN ride_reviews rr ON r.ride_id = rr.ride_id
        GROUP BY v.vehicle_id, v.vehicle_number, v.vehicle_type, v.capacity, v.battery_level,
                 v.status, v.last_latitude, v.last_longitude, v.last_location_update,
                 d.driver_id, d.name, d.phone, d.current_lat, d.current_lng, d.status
    ";

    $stmt = $conn->prepare($fleet_sql);
    $stmt->execute();
    $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate performance metrics for each vehicle
    foreach ($vehicles as &$vehicle) {
        // Calculate efficiency rate
        $efficiency_rate = 85; // Base rate

        if ($vehicle['completed_rides_today'] > 0) {
            $completion_rate = ($vehicle['completed_rides_today'] / ($vehicle['active_rides_today'] + $vehicle['completed_rides_today'])) * 100;
            if ($completion_rate >= 95) {
                $efficiency_rate += 15;
            } elseif ($completion_rate >= 85) {
                $efficiency_rate += 10;
            } elseif ($completion_rate >= 75) {
                $efficiency_rate += 5;
            }
        }

        // Determine status
        if ($vehicle['vehicle_status'] === 'available') {
            $vehicle['availability'] = 'Available';
        } elseif ($vehicle['vehicle_status'] === 'in_use') {
            $vehicle['availability'] = 'In Use';
        } elseif ($vehicle['vehicle_status'] === 'maintenance') {
            $vehicle['availability'] = 'Maintenance';
        } else {
            $vehicle['availability'] = 'Offline';
        }

        $vehicle['efficiency_rate'] = intval($efficiency_rate);

        // Format location update time
        if ($vehicle['last_location_update']) {
            $vehicle['last_update'] = date('M j, H:i', strtotime($vehicle['last_location_update']));
        } else {
            $vehicle['last_update'] = 'Never';
        }
    }

    // Sort vehicles by efficiency and status
    usort($vehicles, function($a, $b) {
        // First sort by status priority
        $statusPriority = ['in_use' => 1, 'available' => 2, 'maintenance' => 3, 'offline' => 4];
        $aPriority = $statusPriority[$a['vehicle_status']] ?? 5;
        $bPriority = $statusPriority[$b['vehicle_status']] ?? 5;

        if ($aPriority != $bPriority) {
            return $aPriority - $bPriority;
        }

        // If same status, sort by efficiency rate (higher first)
        if ($aPriority === $bPriority) {
            return $b['efficiency_rate'] - $a['efficiency_rate'];
        }

        return 0;
    });

    echo json_encode([
        'status' => 'success',
        'vehicles' => $vehicles,
        'summary' => [
            'total_vehicles' => count($vehicles),
            'available_vehicles' => count(array_filter($vehicles, fn($v) => $v['vehicle_status'] === 'available')),
            'vehicles_in_use' => count(array_filter($vehicles, fn($v) => $v['vehicle_status'] === 'in_use')),
            'vehicles_maintenance' => count(array_filter($vehicles, fn($v) => $v['vehicle_status'] === 'maintenance')),
            'avg_efficiency' => array_reduce($vehicles, fn($sum, $v) => $sum + $v['efficiency_rate'], 0) / count($vehicles),
            'active_drivers' => count(array_filter($vehicles, fn($v) => $v['driver_status'] === 'active')),
            'timestamp' => time()
        ]
    ]);

} catch (Exception $e) {
    error_log("Fleet status error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve fleet status',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>