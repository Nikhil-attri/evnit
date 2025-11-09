<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? 0;
$pickup_location = $data['pickup_location'] ?? 0;
$drop_location = $data['drop_location'] ?? 0;

if (!$student_id || !$pickup_location || !$drop_location) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($pickup_location === $drop_location) {
    echo json_encode(['success' => false, 'message' => 'Pickup and drop locations must be different']);
    exit;
}

try {
    // Check if locations exist and are within VNIT
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE location_id IN (?, ?)");
    $stmt->execute([$pickup_location, $drop_location]);
    
    if ($stmt->fetchColumn() != 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid locations']);
        exit;
    }
    
    // Forward to get_estimate.php logic
    require_once '../utils/distance_calculator.php';
    require_once '../utils/eta_calculator.php';
    
    // Get location coordinates
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE location_id = ?");
    $stmt->execute([$pickup_location]);
    $pickup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt->execute([$drop_location]);
    $drop = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $distance = calculateDistance(
        $pickup['latitude'], $pickup['longitude'],
        $drop['latitude'], $drop['longitude']
    );
    
    $eta = calculateETA($distance);
    
    echo json_encode([
        'success' => true,
        'distance' => round($distance, 2),
        'eta' => $eta,
        'fare' => 10, // Flat fare
        'pickup' => $pickup,
        'drop' => $drop
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>