<?php
// api/utils/distance_calculator.php
// Haversine formula to calculate distance between two GPS coordinates

/**
 * Calculate distance between two coordinates using Haversine formula
 * @param float $lat1 Latitude of point 1
 * @param float $lng1 Longitude of point 1
 * @param float $lat2 Latitude of point 2
 * @param float $lng2 Longitude of point 2
 * @return float Distance in kilometers
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Earth radius in kilometers
    
    // Convert degrees to radians
    $lat1_rad = deg2rad($lat1);
    $lng1_rad = deg2rad($lng1);
    $lat2_rad = deg2rad($lat2);
    $lng2_rad = deg2rad($lng2);
    
    // Haversine formula
    $dlat = $lat2_rad - $lat1_rad;
    $dlng = $lng2_rad - $lng1_rad;
    
    $a = sin($dlat/2) * sin($dlat/2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($dlng/2) * sin($dlng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    $distance = $earth_radius * $c;
    
    return $distance;
}

/**
 * Calculate total route distance for multiple waypoints
 * @param array $waypoints Array of ['lat' => x, 'lng' => y]
 * @return float Total distance in kilometers
 */
function calculateRouteDistance($waypoints) {
    $total_distance = 0;
    
    for ($i = 0; $i < count($waypoints) - 1; $i++) {
        $total_distance += calculateDistance(
            $waypoints[$i]['lat'],
            $waypoints[$i]['lng'],
            $waypoints[$i + 1]['lat'],
            $waypoints[$i + 1]['lng']
        );
    }
    
    return $total_distance;
}

/**
 * Check if a point is within VNIT campus boundaries
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @return bool True if within campus, false otherwise
 */
function isWithinVNITCampus($lat, $lng) {
    $campus_bounds = [
        'north' => 21.1303,
        'south' => 21.1172,
        'west' => 79.0416,
        'east' => 79.0596
    ];
    
    return ($lat >= $campus_bounds['south'] && 
            $lat <= $campus_bounds['north'] &&
            $lng >= $campus_bounds['west'] && 
            $lng <= $campus_bounds['east']);
}

/**
 * Find nearest campus location from a given point
 * @param float $lat Current latitude
 * @param float $lng Current longitude
 * @param mysqli $conn Database connection
 * @return array|null Nearest location details
 */
function findNearestCampusLocation($lat, $lng, $conn) {
    $query = "
        SELECT id, location_name, category, latitude, longitude,
        (6371 * acos(
            cos(radians(?)) * cos(radians(latitude)) *
            cos(radians(longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(latitude))
        )) AS distance_km
        FROM campus_locations
        WHERE is_active = TRUE
        ORDER BY distance_km ASC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ddd", $lat, $lng, $lat);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}
?>