<?php
function sendNotification($user_id, $user_type, $title, $message) {
    // Placeholder for push notification implementation
    // In production, integrate with FCM or similar service
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_type, $title, $message]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function sendRideNotification($ride_id, $event) {
    global $pdo;
    
    // Get ride details
    $stmt = $pdo->prepare("
        SELECT r.*, s.name as student_name, d.name as driver_name
        FROM rides r
        JOIN students s ON r.student_id = s.student_id
        LEFT JOIN drivers d ON r.driver_id = d.driver_id
        WHERE r.ride_id = ?
    ");
    $stmt->execute([$ride_id]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ride) return false;
    
    switch ($event) {
        case 'assigned':
            sendNotification($ride['student_id'], 'student', 
                'Driver Assigned', 
                'Your ride has been assigned to ' . $ride['driver_name']);
            break;
        case 'started':
            sendNotification($ride['student_id'], 'student', 
                'Ride Started', 
                'Your driver has started the trip');
            break;
        case 'completed':
            sendNotification($ride['student_id'], 'student', 
                'Ride Completed', 
                'Your ride has been completed. Fare: ₹' . $ride['fare']);
            break;
    }
    
    return true;
}
?>