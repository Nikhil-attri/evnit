<?php
// api/auth/logout.php
// Handles user logout and session cleanup

session_start();

// Store user type before destroying session
$user_type = $_SESSION['user_type'] ?? 'student';

// If driver, update status to inactive
if ($user_type === 'driver' && isset($_SESSION['user_id'])) {
    require_once '../../config/db.php';
    
    $driver_id = $_SESSION['user_id'];
$update_query = "UPDATE drivers SET status = 'inactive' WHERE driver_id = ?";

    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $conn->close();
}

// Destroy session
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header('Location: ../../frontend/login.html');
exit();
?>