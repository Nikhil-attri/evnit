<?php
function optimizeRoute($waypoints) {
    // Simple nearest neighbor algorithm for route optimization
    if (count($waypoints) <= 2) {
        return $waypoints;
    }
    
    $optimized = [$waypoints[0]];
    $remaining = array_slice($waypoints, 1);
    
    while (!empty($remaining)) {
        $last = end($optimized);
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;
        $nearestIndex = -1;
        
        foreach ($remaining as $index => $point) {
            $dist = calculateDistance(
                $last['latitude'], $last['longitude'],
                $point['latitude'], $point['longitude']
            );
            
            if ($dist < $minDistance) {
                $minDistance = $dist;
                $nearest = $point;
                $nearestIndex = $index;
            }
        }
        
        $optimized[] = $nearest;
        array_splice($remaining, $nearestIndex, 1);
    }
    
    return $optimized;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
?>