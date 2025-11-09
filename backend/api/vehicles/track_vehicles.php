// ===== FILE: backend/api/vehicles/track_vehicles.php =====
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update vehicle location
    $data = json_decode(file_get_contents('php://input'), true);
    
    $vehicle_id = $data['vehicle_id'] ?? 0;
    $latitude = $data['latitude'] ?? 0;
    $longitude = $data['longitude'] ?? 0;
    $ride_id = $data['ride_id'] ?? null;
    
    if (!$vehicle_id || !$latitude || !$longitude) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID and coordinates required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO location_logs (vehicle_id, ride_id, latitude, longitude)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$vehicle_id, $ride_id, $latitude, $longitude]);
        
        // Update vehicle current location (store in vehicles table for quick access)
        $stmt = $pdo->prepare("UPDATE vehicles SET last_latitude = ?, last_longitude = ? WHERE vehicle_id = ?");
        $stmt->execute([$latitude, $longitude, $vehicle_id]);
        
        echo json_encode(['success' => true, 'message' => 'Location updated']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // Get all vehicle locations
    try {
        $stmt = $pdo->query("
            SELECT 
                v.*,
                d.name as driver_name,
                d.phone as driver_phone
            FROM vehicles v
            LEFT JOIN drivers d ON v.driver_id = d.driver_id
            WHERE v.status = 'active'
        ");
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>

// ===== FILE: backend/api/vehicles/get_vehicle_info.php =====
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/db.php';

$driver_id = $_GET['driver_id'] ?? 0;

if (!$driver_id) {
    echo json_encode(['success' => false, 'message' => 'Driver ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM vehicles WHERE driver_id = ? AND status = 'active' LIMIT 1
    ");
    $stmt->execute([$driver_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vehicle) {
        echo json_encode(['success' => true, 'vehicle' => $vehicle]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No vehicle assigned']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>