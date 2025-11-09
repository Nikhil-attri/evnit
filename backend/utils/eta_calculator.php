<?php
// api/utils/eta_calculator.php
// Calculate estimated time of arrival based on distance and traffic conditions

/**
 * Calculate ETA in minutes
 * @param float $distance_km Distance in kilometers
 * @param string $time_of_day Optional time (for peak hour calculation)
 * @return int ETA in minutes
 */
function calculateETA($distance_km, $time_of_day = null) {
    // Base speed for e-vehicles on campus (km/h)
    $base_speed = 20; 
    
    // Adjust for peak hours (8-10 AM, 1-2 PM, 5-7 PM)
    $current_hour = $time_of_day ? intval($time_of_day) : intval(date('H'));
    
    $is_peak_hour = (
        ($current_hour >= 8 && $current_hour < 10) ||
        ($current_hour >= 13 && $current_hour < 14) ||
        ($current_hour >= 17 && $current_hour < 19)
    );
    
    if ($is_peak_hour) {
        $base_speed = 15; // Slower during peak hours
    }
    
    // Calculate base ETA
    $eta_hours = $distance_km / $base_speed;
    $eta_minutes = ceil($eta_hours * 60);
    
    // Add buffer time for stops and traffic (1-2 minutes)
    $buffer_minutes = ($distance_km > 0.5) ? 2 : 1;
    $eta_minutes += $buffer_minutes;
    
    // Minimum ETA is 2 minutes
    return max(2, $eta_minutes);
}

/**
 * Calculate waiting time for vehicle to reach pickup
 * @param float $vehicle_lat Current vehicle latitude
 * @param float $vehicle_lng Current vehicle longitude
 * @param float $pickup_lat Pickup latitude
 * @param float $pickup_lng Pickup longitude
 * @return int Waiting time in minutes
 */
function calculateWaitingTime($vehicle_lat, $vehicle_lng, $pickup_lat, $pickup_lng) {
    require_once 'distance_calculator.php';
    
    $distance = calculateDistance($vehicle_lat, $vehicle_lng, $pickup_lat, $pickup_lng);
    return calculateETA($distance);
}

/**
 * Get peak hour multiplier for pricing (future feature)
 * @return float Multiplier (1.0 = normal, 1.5 = peak)
 */
function getPeakHourMultiplier() {
    $current_hour = intval(date('H'));
    
    // Peak hours: 8-10 AM, 1-2 PM, 5-7 PM
    $is_peak_hour = (
        ($current_hour >= 8 && $current_hour < 10) ||
        ($current_hour >= 13 && $current_hour < 14) ||
        ($current_hour >= 17 && $current_hour < 19)
    );
    
    return $is_peak_hour ? 1.0 : 1.0; // Currently flat rate, can adjust later
}

/**
 * Estimate total trip duration including wait + ride time
 * @param array $vehicle Vehicle data with current location
 * @param float $pickup_lat Pickup latitude
 * @param float $pickup_lng Pickup longitude
 * @param float $drop_lat Drop latitude
 * @param float $drop_lng Drop longitude
 * @return array ['wait_time' => int, 'ride_time' => int, 'total_time' => int]
 */
function estimateTotalTripDuration($vehicle, $pickup_lat, $pickup_lng, $drop_lat, $drop_lng) {
    require_once 'distance_calculator.php';
    
    // Vehicle to pickup
    $wait_distance = calculateDistance(
        $vehicle['current_lat'], 
        $vehicle['current_lng'], 
        $pickup_lat, 
        $pickup_lng
    );
    $wait_time = calculateETA($wait_distance);
    
    // Pickup to drop
    $ride_distance = calculateDistance($pickup_lat, $pickup_lng, $drop_lat, $drop_lng);
    $ride_time = calculateETA($ride_distance);
    
    return [
        'wait_time' => $wait_time,
        'ride_time' => $ride_time,
        'total_time' => $wait_time + $ride_time
    ];
}
?>