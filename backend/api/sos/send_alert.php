<?php
// backend/api/sos/send_alert.php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check user authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit();
}

$latitude = floatval($data['latitude'] ?? 0);
$longitude = floatval($data['longitude'] ?? 0);
$message = trim($data['message'] ?? 'Emergency alert from eVNIT user');
$timestamp = time();

// Validate coordinates
if (!$latitude || !$longitude) {
    echo json_encode(['status' => 'error', 'message' => 'Valid location coordinates required']);
    exit();
}

try {
    $conn->beginTransaction();

    // Log emergency alert
    $stmt = $conn->prepare("
        INSERT INTO emergency_alerts
        (student_id, driver_id, alert_lat, alert_lng, alert_message, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");

    if ($user_type === 'student') {
        $stmt->bind_param("iddss", $user_id, null, $latitude, $longitude, $message);
    } elseif ($user_type === 'driver') {
        $stmt->bind_param("iddss", null, $user_id, $latitude, $longitude, $message);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user type for SOS']);
        $conn->rollback();
        exit();
    }

    $stmt->execute();
    $alert_id = $conn->insert_id;

    // Get ride information if user is student
    $ride_info = null;
    if ($user_type === 'student') {
        $ride_stmt = $conn->prepare("
            SELECT r.ride_id, r.ride_code, d.name as driver_name, d.phone as driver_phone,
                   v.vehicle_number, d.driver_id
            FROM rides r
            LEFT JOIN drivers d ON r.driver_id = d.driver_id
            LEFT JOIN vehicles v ON r.vehicle_id = v.vehicle_id
            WHERE r.student_id = ? AND r.status IN ('accepted', 'started')
            ORDER BY r.request_time DESC
            LIMIT 1
        ");
        $ride_stmt->bind_param("i", $user_id);
        $ride_stmt->execute();
        $ride_info = $ride_stmt->get_result()->fetch_assoc();
    }

    // Get user details for notification
    $user_table = $user_type === 'student' ? 'students' : 'drivers';
    $id_field = $user_type === 'student' ? 'student_id' : 'driver_id';

    $user_stmt = $conn->prepare("
        SELECT name, email, phone FROM $user_table WHERE $id_field = ?
    ");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_details = $user_stmt->get_result()->fetch_assoc();

    // Log activity
    $activity_stmt = $conn->prepare("
        INSERT INTO activity_logs
        (user_id, user_type, action, description, ip_address)
        VALUES (?, ?, 'SOS_ALERT', ?, ?)
    ");
    $description = "Emergency alert triggered at coordinates ($latitude, $longitude)";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $activity_stmt->bind_param("isss", $user_id, $user_type, $description, $ip_address);
    $activity_stmt->execute();

    $conn->commit();

    // ====== NOTIFICATION SYSTEM ======

    // 1. Notify nearby drivers
    notifyNearbyDrivers($conn, $latitude, $longitude, $alert_id, $user_id, $user_type, $user_details);

    // 2. Notify all admin users
    notifyAdmins($conn, $alert_id, $user_id, $user_type, $user_details, $latitude, $longitude, $message, $ride_info);

    // 3. Send SMS to VNIT Security (if configured)
    sendSecurityAlert($user_details, $latitude, $longitude, $message, $ride_info);

    // 4. Send email notification (if configured)
    sendEmergencyEmail($user_details, $latitude, $longitude, $message, $ride_info);

    $response = [
        'status' => 'success',
        'message' => 'SOS alert sent successfully',
        'alert_id' => $alert_id,
        'timestamp' => $timestamp,
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }

    error_log("SOS Alert Error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to process SOS alert',
        'error' => $e->getMessage()
    ]);
}

// ====== NOTIFICATION FUNCTIONS ======

function notifyNearbyDrivers($conn, $lat, $lng, $alert_id, $user_id, $user_type, $user_details) {
    try {
        // Find drivers within 1km radius
        $stmt = $conn->prepare("
            SELECT d.driver_id, d.name, d.phone, d.current_lat, d.current_lng,
                   v.vehicle_number, v.vehicle_type,
                   (6371 * acos(cos(radians(?)) * cos(radians(d.current_lat)) *
                    cos(radians(d.current_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(d.current_lat)))) AS distance_km
            FROM drivers d
            JOIN vehicles v ON d.driver_id = v.driver_id
            WHERE d.status = 'active'
            HAVING distance_km <= 1.0
            ORDER BY distance_km ASC
        ");

        $stmt->bind_param("ddd", $lat, $lng, $lat);
        $stmt->execute();
        $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($drivers as $driver) {
            // Create notification for driver
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications
                (user_id, user_type, title, message, type, priority)
                VALUES (?, 'driver', ?, ?, 'emergency', 'urgent')
            ");

            $title = "🚨 Nearby Emergency Alert";
            $message = "Emergency near you. User: {$user_details['name']}. Distance: " . round($driver['distance_km'], 2) . "km";

            $notif_stmt->bind_param("iss", $driver['driver_id'], $title, $message);
            $notif_stmt->execute();

            // Log that driver was notified
            error_log("SOS: Notified driver {$driver['driver_id']} ({$driver['name']}) about emergency alert {$alert_id}");
        }

    } catch (Exception $e) {
        error_log("Error notifying nearby drivers: " . $e->getMessage());
    }
}

function notifyAdmins($conn, $alert_id, $user_id, $user_type, $user_details, $lat, $lng, $message, $ride_info) {
    try {
        // Get all admin users
        $stmt = $conn->prepare("
            SELECT admin_id, name, email, phone FROM admins WHERE status = 'active'
        ");
        $stmt->execute();
        $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($admins as $admin) {
            // Create high-priority notification
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications
                (user_id, user_type, title, message, type, priority)
                VALUES (?, 'admin', ?, ?, 'emergency', 'urgent')
            ");

            $title = "🚨 URGENT: Emergency Alert - {$alert_id}";
            $admin_message = "Emergency Alert Details:\n" .
                           "User: {$user_details['name']} ({$user_type})\n" .
                           "Phone: {$user_details['phone']}\n" .
                           "Location: {$lat}, {$lng}\n" .
                           "Message: {$message}";

            if ($ride_info) {
                $admin_message .= "\n\nActive Ride:\n" .
                               "Ride Code: {$ride_info['ride_code']}\n" .
                               "Driver: {$ride_info['driver_name']}\n" .
                               "Vehicle: {$ride_info['vehicle_number']}\n" .
                               "Driver Phone: {$ride_info['driver_phone']}";
            }

            $notif_stmt->bind_param("iss", $admin['admin_id'], $title, $admin_message);
            $notif_stmt->execute();

            error_log("SOS: Notified admin {$admin['admin_id']} ({$admin['name']}) about emergency alert {$alert_id}");
        }

    } catch (Exception $e) {
        error_log("Error notifying admins: " . $e->getMessage());
    }
}

function sendSecurityAlert($user_details, $lat, $lng, $message, $ride_info) {
    // This would integrate with VNIT's security communication system
    // For now, we'll just log the alert that would be sent
    $security_message = "EMERGENCY ALERT - VNIT CAMPUS\n" .
                      "User: {$user_details['name']} ({$user_details['phone']})\n" .
                      "Location: https://maps.google.com/?q={$lat},{$lng}\n" .
                      "Message: {$message}";

    if ($ride_info) {
        $security_message .= "\n\nACTIVE RIDE INFO:\n" .
                         "Ride Code: {$ride_info['ride_code']}\n" .
                         "Driver: {$ride_info['driver_name']}\n" .
                         "Vehicle: {$ride_info['vehicle_number']}\n" .
                         "Driver Phone: {$ride_info['driver_phone']}";
    }

    // Log the security alert
    error_log("SECURITY ALERT: " . $security_message);

    // In production, this would send SMS to security team phones
    // Example: sendSMS("+919876543210", $security_message);
}

function sendEmergencyEmail($user_details, $lat, $lng, $message, $ride_info) {
    // In production, this would send email to VNIT security and admin team
    $to = "security@vnit.ac.in, admin@vnit.ac.in";
    $subject = "🚨 URGENT: eVNIT Emergency Alert";

    $email_body = "Emergency Alert Details:\n\n" .
                 "User Name: {$user_details['name']}\n" .
                 "User Phone: {$user_details['phone']}\n" .
                 "User Email: {$user_details['email']}\n" .
                 "Location: {$lat}, {$lng}\n" .
                 "Map Link: https://maps.google.com/?q={$lat},{$lng}\n" .
                 "Message: {$message}\n" .
                 "Time: " . date('Y-m-d H:i:s') . "\n";

    if ($ride_info) {
        $email_body .= "\nActive Ride Information:\n" .
                      "Ride Code: {$ride_info['ride_code']}\n" .
                      "Driver: {$ride_info['driver_name']}\n" .
                      "Vehicle: {$ride_info['vehicle_number']}\n" .
                      "Driver Phone: {$ride_info['driver_phone']}";
    }

    // Log email that would be sent
    error_log("EMAIL TO: {$to}\nSUBJECT: {$subject}\nBODY: {$email_body}");

    // In production:
    // mail($to, $subject, $email_body, "From: emergency@evnit.vnit.ac.in");
}

$conn->close();
?>