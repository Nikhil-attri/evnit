<?php
// utils/route_optimizer.php (Enhanced)
require_once __DIR__ . '/../config/db.php';

/**
 * Calculate Haversine distance between two points
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // km

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng/2) * sin($dLng/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c;
}

function deg2rad($deg) {
    return $deg * (M_PI/180);
}

/**
 * Enhanced Route Optimizer for Smart Ride Sharing
 */

class RouteOptimizer {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Find compatible ride requests for sharing
     */
    public function findSharedRideOpportunities($pickup_lat, $pickup_lng, $drop_lat, $drop_lng, $radius_km = 0.5) {
        try {
            // Find rides with similar pickup/drop points within radius
            $sql = "
                SELECT r.ride_id, r.student_id, r.pickup_lat, r.pickup_lng, r.drop_lat, r.drop_lng,
                       r.pickup_name, r.drop_name, r.status, r.request_time,
                       s.name as student_name, s.roll_number,
                       v.vehicle_id, v.capacity, v.current_occupancy,
                       d.driver_id, d.name as driver_name,
                       (6371 * acos(cos(radians(?)) * cos(radians(r.pickup_lat)) *
                        cos(radians(r.pickup_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(r.pickup_lat)))) AS pickup_distance,
                       (6371 * acos(cos(radians(?)) * cos(radians(r.drop_lat)) *
                        cos(radians(r.drop_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(r.drop_lat)))) AS drop_distance
                FROM rides r
                JOIN students s ON r.student_id = s.student_id
                JOIN vehicles v ON r.vehicle_id = v.vehicle_id
                JOIN drivers d ON v.driver_id = d.driver_id
                WHERE r.status IN ('pending', 'accepted')
                AND r.request_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                AND (v.capacity - v.current_occupancy) >= 1
                HAVING pickup_distance <= ? AND drop_distance <= ?
                ORDER BY r.request_time ASC
                LIMIT 5
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ddddddddd",
                $pickup_lat, $pickup_lng, $pickup_lat,
                $drop_lat, $drop_lng, $drop_lat,
                $radius_km, $radius_km
            );
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Route optimization error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Group ride requests by proximity and direction
     */
    public function groupRideRequests($requests, $max_group_size = 4) {
        $groups = [];
        $used = [];

        foreach ($requests as $index => $request) {
            if (in_array($index, $used)) {
                continue;
            }

            $group = [$request];
            $used[] = $index;

            // Find compatible requests for this group
            foreach ($requests as $other_index => $other_request) {
                if (in_array($other_index, $used) || count($group) >= $max_group_size) {
                    continue;
                }

                if ($this->areRequestsCompatible($request, $other_request)) {
                    $group[] = $other_request;
                    $used[] = $other_index;
                }
            }

            if (count($group) > 1) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * Check if two ride requests are compatible for sharing
     */
    private function areRequestsCompatible($req1, $req2) {
        $pickup_distance = calculateDistance(
            $req1['pickup_lat'], $req1['pickup_lng'],
            $req2['pickup_lat'], $req2['pickup_lng']
        );

        $drop_distance = calculateDistance(
            $req1['drop_lat'], $req1['drop_lng'],
            $req2['drop_lat'], $req2['drop_lng']
        );

        // Compatible if both pickup and drop are within 500m
        return $pickup_distance <= 0.5 && $drop_distance <= 0.5;
    }

    /**
     * Optimize multi-stop route for shared rides
     */
    public function optimizeMultiStopRoute($stops) {
        if (count($stops) <= 2) {
            return $stops;
        }

        // Use nearest neighbor with time window optimization
        return $this->nearestNeighborOptimization($stops);
    }

    /**
     * Nearest neighbor algorithm with time optimization
     */
    private function nearestNeighborOptimization($stops) {
        if (empty($stops)) {
            return [];
        }

        $optimized = [];
        $remaining = $stops;

        // Start with the pickup point
        $current = null;

        // Find the best starting point (typically first pickup)
        foreach ($stops as $index => $stop) {
            if (strpos(strtolower($stop['name']), 'pickup') !== false) {
                $current = $stop;
                unset($remaining[$index]);
                $optimized[] = $current;
                break;
            }
        }

        // If no pickup found, start with first point
        if ($current === null && !empty($remaining)) {
            $current = array_shift($remaining);
            $optimized[] = $current;
        }

        // Visit remaining points in optimal order
        while (!empty($remaining)) {
            $nearest = null;
            $nearestScore = PHP_FLOAT_MAX;
            $nearestIndex = -1;

            foreach ($remaining as $index => $stop) {
                $score = $this->calculateStopScore($current, $stop);

                if ($score < $nearestScore) {
                    $nearestScore = $score;
                    $nearest = $stop;
                    $nearestIndex = $index;
                }
            }

            if ($nearest !== null) {
                $current = $nearest;
                $optimized[] = $current;
                unset($remaining[$nearestIndex]);
            } else {
                break;
            }
        }

        return $optimized;
    }

    /**
     * Calculate score for next stop (distance + time + priority)
     */
    private function calculateStopScore($from, $to) {
        $distance = calculateDistance(
            $from['lat'], $from['lng'],
            $to['lat'], $to['lng']
        );

        // Base score is distance
        $score = $distance;

        // Factor in priority (drop-offs usually higher priority)
        if (strpos(strtolower($to['name']), 'drop') !== false) {
            $score *= 0.8; // Reduce score for drop-offs
        }

        // Factor in time urgency
        $current_hour = (int)date('H');
        if ($current_hour >= 8 && $current_hour <= 10) { // Peak morning hours
            if (strpos(strtolower($to['name']), 'academic') !== false) {
                $score *= 0.7; // Prioritize academic buildings
            }
        } elseif ($current_hour >= 17 && $current_hour <= 19) { // Peak evening hours
            if (strpos(strtolower($to['name']), 'hostel') !== false) {
                $score *= 0.7; // Prioritize hostels
            }
        }

        return $score;
    }

    /**
     * Calculate optimal driver assignment for multiple ride requests
     */
    public function findOptimalDriver($ride_requests) {
        if (empty($ride_requests)) {
            return null;
        }

        try {
            // Get all available drivers
            $sql = "
                SELECT d.driver_id, d.name, d.current_lat, d.current_lng,
                       v.vehicle_id, v.vehicle_number, v.vehicle_type, v.capacity,
                       v.current_occupancy, v.battery_level
                FROM drivers d
                JOIN vehicles v ON d.driver_id = v.driver_id
                WHERE d.status = 'active' AND v.status = 'available'
                AND (v.capacity - v.current_occupancy) >= ?
                ORDER BY d.rating DESC, v.battery_level DESC
            ";

            $min_capacity = count($ride_requests);
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $min_capacity);
            $stmt->execute();
            $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $best_driver = null;
            $best_score = PHP_FLOAT_MAX;

            foreach ($drivers as $driver) {
                $score = $this->calculateDriverScore($driver, $ride_requests);

                if ($score < $best_score) {
                    $best_score = $score;
                    $best_driver = $driver;
                }
            }

            return $best_driver;
        } catch (Exception $e) {
            error_log("Driver optimization error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate driver suitability score for ride requests
     */
    private function calculateDriverScore($driver, $requests) {
        $score = 0;

        // Distance to first pickup
        $first_pickup = $requests[0];
        $distance = calculateDistance(
            $driver['current_lat'], $driver['current_lng'],
            $first_pickup['pickup_lat'], $first_pickup['pickup_lng']
        );
        $score += $distance * 2; // Weight distance heavily

        // Battery level penalty
        if ($driver['battery_level'] < 20) {
            $score += 100; // Heavy penalty for low battery
        } elseif ($driver['battery_level'] < 50) {
            $score += 50; // Moderate penalty
        }

        // Capacity utilization bonus
        $available_seats = $driver['capacity'] - $driver['current_occupancy'];
        if ($available_seats >= count($requests)) {
            $score -= 10; // Bonus for perfect capacity match
        }

        // Rating bonus
        $rating_bonus = (5.0 - ($driver['rating'] ?? 5.0)) * 20;
        $score += $rating_bonus;

        return $score;
    }

    /**
     * Estimate travel time with traffic considerations
     */
    public function estimateTravelTime($distance_km, $traffic_factor = 1.0) {
        $base_speed = 20; // km/h average campus speed
        $adjusted_speed = $base_speed / $traffic_factor;

        $time_hours = $distance_km / $adjusted_speed;
        $time_minutes = $time_hours * 60;

        // Add buffer for campus conditions
        $buffer_minutes = min(5, $time_minutes * 0.2);

        return ceil($time_minutes + $buffer_minutes);
    }

    /**
     * Get current traffic factor based on time and location
     */
    public function getTrafficFactor($location_type = 'general') {
        $current_hour = (int)date('H');
        $day_of_week = (int)date('w');

        // Weekend - lighter traffic
        if ($day_of_week >= 6) {
            return 1.1;
        }

        // Peak hours
        if (($current_hour >= 8 && $current_hour <= 10) ||
            ($current_hour >= 17 && $current_hour <= 19)) {
            return 1.5; // Higher traffic factor
        }

        // Academic buildings during class change times
        if ($location_type === 'academic' &&
            ($current_hour === 9 || $current_hour === 13 || $current_hour === 17)) {
            return 1.8; // Peak academic traffic
        }

        return 1.2; // Normal campus traffic
    }
}

/**
 * Legacy functions for backward compatibility
 */
function optimizeRoute($stops) {
    if (count($stops) <= 2) {
        return $stops;
    }

    // Convert legacy format to new format
    $formatted_stops = [];
    foreach ($stops as $stop) {
        $formatted_stops[] = [
            'lat' => $stop['latitude'] ?? $stop['lat'],
            'lng' => $stop['longitude'] ?? $stop['lng'],
            'name' => $stop['name'] ?? 'Location'
        ];
    }

    $optimizer = new RouteOptimizer(getDbConnection());
    return $optimizer->optimizeMultiStopRoute($formatted_stops);
}
?>