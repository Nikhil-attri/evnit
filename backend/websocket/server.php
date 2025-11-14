<?php
// backend/websocket/server.php
require_once '../config/db.php';

class VNITWebSocketServer {
    private $server;
    private $clients = [];
    private $users = []; // user_id => connection_id mapping
    private $conn;

    public function __construct($host = '0.0.0.0', $port = 8080) {
        $this->conn = getDbConnection();
        $this->server = new Swoole\WebSocket\Server($host, $port);

        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
        $this->server->on('start', [$this, 'onStart']);

        echo "WebSocket server starting on $host:$port...\n";
    }

    public function onOpen($server, $request) {
        $connection_id = $request->fd;

        // Extract user info from query params or headers
        $user_type = $request->get['user_type'] ?? 'unknown';
        $user_id = $request->get['user_id'] ?? null;
        $auth_token = $request->get['auth'] ?? null;

        if (!$user_id || !$auth_token) {
            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'Authentication required'
            ]));
            $server->close($request->fd);
            return;
        }

        // Validate auth token against database
        if (!$this->validateAuthToken($user_id, $user_type, $auth_token)) {
            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'Invalid authentication'
            ]));
            $server->close($request->fd);
            return;
        }

        $this->clients[$connection_id] = [
            'connection_id' => $connection_id,
            'user_id' => $user_id,
            'user_type' => $user_type,
            'connected_at' => time(),
            'last_ping' => time()
        ];

        $this->users[$user_id] = $connection_id;

        echo "New connection: $connection_id (User: $user_id, Type: $user_type)\n";

        // Send connection confirmation
        $server->push($request->fd, json_encode([
            'type' => 'connected',
            'user_id' => $user_id,
            'server_time' => time()
        ]));

        // Update user status in database
        $this->updateUserOnlineStatus($user_id, $user_type, true);

        // Broadcast user online status to relevant users
        $this->broadcastUserStatus($user_id, $user_type, 'online');
    }

    public function onMessage($server, $frame) {
        $connection_id = $frame->fd;

        if (!isset($this->clients[$connection_id])) {
            return;
        }

        $client = $this->clients[$connection_id];
        $data = json_decode($frame->data, true);

        if (!$data || !isset($data['type'])) {
            return;
        }

        // Update last ping
        $this->clients[$connection_id]['last_ping'] = time();

        try {
            switch ($data['type']) {
                case 'ping':
                    $server->push($connection_id, json_encode([
                        'type' => 'pong',
                        'timestamp' => time()
                    ]));
                    break;

                case 'location_update':
                    $this->handleLocationUpdate($server, $client, $data);
                    break;

                case 'ride_request':
                    $this->handleRideRequest($server, $client, $data);
                    break;

                case 'ride_status_update':
                    $this->handleRideStatusUpdate($server, $client, $data);
                    break;

                case 'sos_alert':
                    $this->handleSOSAlert($server, $client, $data);
                    break;

                case 'chat_message':
                    $this->handleChatMessage($server, $client, $data);
                    break;

                default:
                    $server->push($connection_id, json_encode([
                        'type' => 'error',
                        'message' => 'Unknown message type: ' . $data['type']
                    ]));
            }
        } catch (Exception $e) {
            error_log("WebSocket message handling error: " . $e->getMessage());
            $server->push($connection_id, json_encode([
                'type' => 'error',
                'message' => 'Server error while processing message'
            ]));
        }
    }

    public function onClose($server, $fd) {
        if (isset($this->clients[$fd])) {
            $client = $this->clients[$fd];
            $user_id = $client['user_id'];
            $user_type = $client['user_type'];

            // Remove from tracking
            unset($this->users[$user_id]);
            unset($this->clients[$fd]);

            echo "Connection closed: $fd (User: $user_id)\n";

            // Update database status
            $this->updateUserOnlineStatus($user_id, $user_type, false);

            // Broadcast user offline status
            $this->broadcastUserStatus($user_id, $user_type, 'offline');
        }
    }

    public function onStart($server) {
        echo "WebSocket server started successfully\n";

        // Start periodic tasks
        $server->tick(30000, function() use ($server) {
            $this->cleanupStaleConnections($server);
        });

        $server->tick(10000, function() use ($server) {
            $this->broadcastDriverLocations($server);
        });
    }

    private function validateAuthToken($user_id, $user_type, $token) {
        try {
            $table = $user_type === 'student' ? 'students' :
                    ($user_type === 'driver' ? 'drivers' : 'admins');
            $id_field = $user_type === 'student' ? 'student_id' :
                       ($user_type === 'driver' ? 'driver_id' : 'admin_id');

            $stmt = $this->conn->prepare("
                SELECT $id_field, status
                FROM $table
                WHERE $id_field = ? AND SHA2(CONCAT($id_field, created_at, 'vnit_websocket_secret'), 256) = ?
            ");
            $stmt->bind_param("is", $user_id, $token);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Auth validation error: " . $e->getMessage());
            return false;
        }
    }

    private function updateUserOnlineStatus($user_id, $user_type, $is_online) {
        try {
            $table = $user_type === 'student' ? 'students' : 'drivers';
            $id_field = $user_type === 'student' ? 'student_id' : 'driver_id';

            $stmt = $this->conn->prepare("
                UPDATE $table
                SET last_login = NOW(),
                    status = CASE
                        WHEN ? THEN 'active'
                        ELSE CASE
                            WHEN user_type = 'driver' THEN 'offline'
                            ELSE 'active'
                        END
                    END
                WHERE $id_field = ?
            ");
            $stmt->bind_param("ii", $is_online, $user_id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
        }
    }

    private function handleLocationUpdate($server, $client, $data) {
        if ($client['user_type'] !== 'driver') {
            return;
        }

        $driver_id = $client['user_id'];
        $lat = floatval($data['latitude'] ?? 0);
        $lng = floatval($data['longitude'] ?? 0);
        $speed = floatval($data['speed'] ?? 0);
        $battery = intval($data['battery'] ?? 100);
        $ride_id = intval($data['ride_id'] ?? null);

        if (!$lat || !$lng) {
            return;
        }

        // Update driver location in database
        try {
            $this->conn->beginTransaction();

            // Update drivers table
            $stmt = $this->conn->prepare("
                UPDATE drivers d
                JOIN vehicles v ON d.driver_id = v.driver_id
                SET d.current_lat = ?, d.current_lng = ?, v.last_latitude = ?,
                    v.last_longitude = ?, v.battery_level = ?, v.last_location_update = NOW()
                WHERE d.driver_id = ?
            ");
            $stmt->bind_param("ddddii", $lat, $lng, $lat, $lng, $battery, $driver_id);
            $stmt->execute();

            // Log location history
            if ($ride_id) {
                $stmt = $this->conn->prepare("
                    INSERT INTO location_logs (vehicle_id, ride_id, latitude, longitude, speed, logged_at)
                    SELECT v.vehicle_id, ?, ?, ?, ?, NOW()
                    FROM vehicles v WHERE v.driver_id = ?
                ");
                $stmt->bind_param("idddi", $ride_id, $lat, $lng, $speed, $driver_id);
                $stmt->execute();
            }

            $this->conn->commit();

            // Broadcast to students tracking this ride
            $this->broadcastToRideParticipants($server, $ride_id, [
                'type' => 'location_update',
                'driver_id' => $driver_id,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'battery' => $battery,
                'timestamp' => time()
            ], $driver_id);

        } catch (Exception $e) {
            error_log("Location update error: " . $e->getMessage());
            $this->conn->rollback();
        }
    }

    private function handleRideRequest($server, $client, $data) {
        if ($client['user_type'] !== 'student') {
            return;
        }

        $student_id = $client['user_id'];
        $pickup_lat = floatval($data['pickup_lat'] ?? 0);
        $pickup_lng = floatval($data['pickup_lng'] ?? 0);
        $drop_lat = floatval($data['drop_lat'] ?? 0);
        $drop_lng = floatval($data['drop_lng'] ?? 0);
        $shared_ride = boolval($data['shared_ride'] ?? false);

        if (!$pickup_lat || !$pickup_lng || !$drop_lat || !$drop_lng) {
            return;
        }

        try {
            // Find nearby drivers
            $stmt = $this->conn->prepare("
                SELECT d.driver_id, d.name, v.vehicle_id, v.vehicle_number, v.vehicle_type,
                       (6371 * acos(cos(radians(?)) * cos(radians(d.current_lat)) *
                        cos(radians(d.current_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(d.current_lat)))) AS distance_km
                FROM drivers d
                JOIN vehicles v ON d.driver_id = v.driver_id
                WHERE d.status = 'active' AND v.status = 'available'
                HAVING distance_km <= 2.0
                ORDER BY distance_km ASC
                LIMIT 5
            ");
            $stmt->bind_param("ddd", $pickup_lat, $pickup_lng, $pickup_lat);
            $stmt->execute();
            $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Send ride request to nearby drivers
            foreach ($drivers as $driver) {
                $driver_connection_id = $this->users[$driver['driver_id']] ?? null;
                if ($driver_connection_id) {
                    $server->push($driver_connection_id, json_encode([
                        'type' => 'ride_request',
                        'student_id' => $student_id,
                        'pickup_lat' => $pickup_lat,
                        'pickup_lng' => $pickup_lng,
                        'drop_lat' => $drop_lat,
                        'drop_lng' => $drop_lng,
                        'shared_ride' => $shared_ride,
                        'distance_km' => round($driver['distance_km'], 2),
                        'request_id' => uniqid('ride_req_', true),
                        'timestamp' => time()
                    ]));
                }
            }

            // Confirm to student
            $server->push($client['connection_id'], json_encode([
                'type' => 'ride_request_sent',
                'drivers_notified' => count($drivers),
                'timestamp' => time()
            ]));

        } catch (Exception $e) {
            error_log("Ride request error: " . $e->getMessage());
        }
    }

    private function handleRideStatusUpdate($server, $client, $data) {
        if ($client['user_type'] !== 'driver') {
            return;
        }

        $driver_id = $client['user_id'];
        $ride_id = intval($data['ride_id'] ?? 0);
        $status = $data['status'] ?? '';
        $eta_minutes = intval($data['eta_minutes'] ?? null);

        if (!$ride_id || !$status) {
            return;
        }

        try {
            // Update ride status in database
            $stmt = $this->conn->prepare("
                UPDATE rides
                SET status = ?,
                    accept_time = CASE WHEN ? = 'accepted' THEN NOW() ELSE accept_time END,
                    started_at = CASE WHEN ? = 'started' THEN NOW() ELSE started_at END,
                    completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
                WHERE ride_id = ? AND driver_id = ?
            ");
            $stmt->bind_param("ssssii", $status, $status, $status, $status, $ride_id, $driver_id);
            $stmt->execute();

            // Get student ID for this ride
            $stmt = $this->conn->prepare("
                SELECT student_id FROM rides WHERE ride_id = ?
            ");
            $stmt->bind_param("i", $ride_id);
            $stmt->execute();
            $student_id = $stmt->get_result()->fetch_assoc()['student_id'];

            // Notify student
            $student_connection_id = $this->users[$student_id] ?? null;
            if ($student_connection_id) {
                $server->push($student_connection_id, json_encode([
                    'type' => 'ride_status_update',
                    'ride_id' => $ride_id,
                    'status' => $status,
                    'eta_minutes' => $eta_minutes,
                    'timestamp' => time()
                ]));
            }

        } catch (Exception $e) {
            error_log("Ride status update error: " . $e->getMessage());
        }
    }

    private function handleSOSAlert($server, $client, $data) {
        $user_id = $client['user_id'];
        $user_type = $client['user_type'];
        $latitude = floatval($data['latitude'] ?? 0);
        $longitude = floatval($data['longitude'] ?? 0);
        $message = $data['message'] ?? 'SOS Alert';
        $ride_id = intval($data['ride_id'] ?? null);

        if (!$latitude || !$longitude) {
            return;
        }

        try {
            // Log SOS alert
            $stmt = $this->conn->prepare("
                INSERT INTO emergency_alerts
                (ride_id, student_id, driver_id, alert_lat, alert_lng, alert_message, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $driver_id = $user_type === 'driver' ? $user_id : null;
            $student_id = $user_type === 'student' ? $user_id : null;
            $stmt->bind_param("iiidds", $ride_id, $student_id, $driver_id, $latitude, $longitude, $message);
            $stmt->execute();

            $alert_id = $this->conn->insert_id;

            // Broadcast to all admins
            foreach ($this->clients as $admin_client) {
                if ($admin_client['user_type'] === 'admin') {
                    $server->push($admin_client['connection_id'], json_encode([
                        'type' => 'sos_alert',
                        'alert_id' => $alert_id,
                        'user_id' => $user_id,
                        'user_type' => $user_type,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'message' => $message,
                        'ride_id' => $ride_id,
                        'timestamp' => time()
                    ]));
                }
            }

            // Notify nearby drivers as well
            $this->broadcastToNearbyDrivers($server, $latitude, $longitude, [
                'type' => 'sos_alert_nearby',
                'alert_id' => $alert_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance_km' => 1.0,
                'timestamp' => time()
            ], $user_id);

        } catch (Exception $e) {
            error_log("SOS alert error: " . $e->getMessage());
        }
    }

    private function handleChatMessage($server, $client, $data) {
        $sender_id = $client['user_id'];
        $sender_type = $client['user_type'];
        $recipient_id = intval($data['recipient_id'] ?? 0);
        $recipient_type = $data['recipient_type'] ?? '';
        $message = trim($data['message'] ?? '');
        $ride_id = intval($data['ride_id'] ?? null);

        if (!$recipient_id || !$message) {
            return;
        }

        $recipient_connection_id = $this->users[$recipient_id] ?? null;
        if (!$recipient_connection_id) {
            return;
        }

        $server->push($recipient_connection_id, json_encode([
            'type' => 'chat_message',
            'sender_id' => $sender_id,
            'sender_type' => $sender_type,
            'message' => $message,
            'ride_id' => $ride_id,
            'timestamp' => time()
        ]));
    }

    private function broadcastToRideParticipants($server, $ride_id, $message, $exclude_user_id = null) {
        if (!$ride_id) {
            return;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT student_id, driver_id FROM rides WHERE ride_id = ?
            ");
            $stmt->bind_param("i", $ride_id);
            $stmt->execute();
            $ride = $stmt->get_result()->fetch_assoc();

            if ($ride) {
                $participants = [
                    ['id' => $ride['student_id'], 'type' => 'student'],
                    ['id' => $ride['driver_id'], 'type' => 'driver']
                ];

                foreach ($participants as $participant) {
                    if ($participant['id'] && $participant['id'] != $exclude_user_id) {
                        $connection_id = $this->users[$participant['id']] ?? null;
                        if ($connection_id) {
                            $server->push($connection_id, json_encode($message));
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Broadcast error: " . $e->getMessage());
        }
    }

    private function broadcastToNearbyDrivers($server, $lat, $lng, $message, $exclude_user_id = null) {
        try {
            $stmt = $this->conn->prepare("
                SELECT driver_id
                FROM drivers
                WHERE status = 'active'
                HAVING (6371 * acos(cos(radians(?)) * cos(radians(current_lat)) *
                     cos(radians(current_lng) - radians(?)) +
                     sin(radians(?)) * sin(radians(current_lat)))) <= 1.0
            ");
            $stmt->bind_param("ddd", $lat, $lng, $lat);
            $stmt->execute();
            $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($drivers as $driver) {
                if ($driver['driver_id'] != $exclude_user_id) {
                    $connection_id = $this->users[$driver['driver_id']] ?? null;
                    if ($connection_id) {
                        $server->push($connection_id, json_encode($message));
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Nearby drivers broadcast error: " . $e->getMessage());
        }
    }

    private function broadcastUserStatus($user_id, $user_type, $status) {
        // Could be implemented to broadcast user online/offline status to relevant users
        // For now, we'll keep it simple
    }

    private function broadcastDriverLocations($server) {
        try {
            $stmt = $this->conn->prepare("
                SELECT d.driver_id, d.current_lat, d.current_lng, v.vehicle_number,
                       v.vehicle_type, v.battery_level, v.status as vehicle_status
                FROM drivers d
                JOIN vehicles v ON d.driver_id = v.driver_id
                WHERE d.status = 'active'
            ");
            $stmt->execute();
            $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $location_data = [
                'type' => 'driver_locations_batch',
                'drivers' => $drivers,
                'timestamp' => time()
            ];

            // Send to all students
            foreach ($this->clients as $client) {
                if ($client['user_type'] === 'student') {
                    $server->push($client['connection_id'], json_encode($location_data));
                }
            }

        } catch (Exception $e) {
            error_log("Location broadcast error: " . $e->getMessage());
        }
    }

    private function cleanupStaleConnections($server) {
        $current_time = time();
        $timeout = 300; // 5 minutes

        foreach ($this->clients as $connection_id => $client) {
            if ($current_time - $client['last_ping'] > $timeout) {
                echo "Closing stale connection: $connection_id\n";
                $server->close($connection_id);
            }
        }
    }

    public function start() {
        $this->server->start();
    }
}

// Start the WebSocket server
if (php_sapi_name() === 'cli') {
    $server = new VNITWebSocketServer();
    $server->start();
}