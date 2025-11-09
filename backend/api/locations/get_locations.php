<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../../config/db.php';

try {
    // Create table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS locations (
            location_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Insert default locations if empty
    $countResult = $conn->query("SELECT COUNT(*) as total FROM locations");
    $count = $countResult->fetch_assoc()['total'];

    if ($count == 0) {
        $defaultLocations = [
            ['Main Gate', 21.1350, 79.0520, 'Main entrance to VNIT campus'],
            ['Admin Building', 21.1365, 79.0535, 'Administrative office complex'],
            ['CSE Department', 21.1380, 79.0545, 'Computer Science & Engineering'],
            ['ECE Department', 21.1375, 79.0540, 'Electronics & Communication Engineering'],
            ['Hostel 1 (Boys)', 21.1420, 79.0580, 'Boys hostel block 1'],
            ['Hostel 2 (Girls)', 21.1415, 79.0575, 'Girls hostel block'],
            ['Library', 21.1370, 79.0550, 'Central Library'],
            ['CRC', 21.1390, 79.0560, 'Central Research Complex'],
            ['Canteen 1', 21.1385, 79.0555, 'Main canteen near admin'],
            ['Canteen 2', 21.1425, 79.0585, 'Hostel area canteen'],
            ['Sports Complex', 21.1400, 79.0590, 'Indoor and outdoor sports facilities'],
            ['Auditorium', 21.1360, 79.0530, 'Main auditorium'],
            ['Medical Center', 21.1355, 79.0525, 'Campus health center'],
            ['Guest House', 21.1410, 79.0570, 'Guest house for visitors'],
            ['Faculty Quarters', 21.1440, 79.0600, 'Faculty residential area'],
            ['Workshop', 21.1345, 79.0515, 'Engineering workshop'],
            ['Parking Area', 21.1340, 79.0510, 'Main parking lot']
        ];

        $stmt = $conn->prepare("INSERT INTO locations (name, latitude, longitude, description) VALUES (?, ?, ?, ?)");
        foreach ($defaultLocations as $loc) {
            $stmt->bind_param("sdds", $loc[0], $loc[1], $loc[2], $loc[3]);
            $stmt->execute();
        }
        $stmt->close();
    }

    $result = $conn->query("SELECT location_id, name, latitude, longitude, description, is_active FROM locations WHERE is_active = TRUE ORDER BY name ASC");
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = [
            'location_id' => (int)$row['location_id'],
            'name' => $row['name'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'description' => $row['description'],
            'is_active' => (bool)$row['is_active']
        ];
    }

    echo json_encode(['status'=>'success','locations'=>$locations,'total_count'=>count($locations)]);
} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}

$conn->close();
?>
