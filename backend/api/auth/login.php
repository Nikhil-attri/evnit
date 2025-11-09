<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();

// Allow CORS for testing (adjust in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Read JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$user_type = strtolower($data['user_type'] ?? '');
$password = $data['password'] ?? '';

if (!$user_type || !$password) {
    echo json_encode(['success' => false, 'message' => 'User type and password required']);
    exit;
}

// Helper function
function loginUser($pdo, $table, $emailField, $idField, $nameField, $emailOrId, $passwordInput, $userType) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $emailField = ? OR $idField = ?");
    $stmt->execute([$emailOrId, $emailOrId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($passwordInput, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user[$idField];
        $_SESSION['user_type'] = $userType;
        $_SESSION['user_name'] = $user[$nameField];
        $_SESSION['email'] = $user[$emailField];

        unset($user['password']); // hide sensitive info
        return ['success' => true, 'user_type' => $userType, 'user' => $user, 'message' => 'Login successful'];
    }
    return ['success' => false, 'message' => 'Invalid credentials'];
}

try {
    switch ($user_type) {

        case 'student':
            $login = trim($data['email'] ?? $data['roll_number'] ?? '');
            if (!$login) {
                echo json_encode(['success' => false, 'message' => 'Email or Roll Number required']);
                exit;
            }
            $response = loginUser($pdo, 'students', 'email', 'roll_number', 'name', $login, $password, 'student');
            break;

        case 'driver':
            $login = trim($data['driver_id'] ?? $data['email'] ?? $data['login'] ?? '');
            if (!$login) {
                echo json_encode(['success' => false, 'message' => 'Driver ID or Email required']);
                exit;
            }
            $response = loginUser($pdo, 'drivers', 'email', 'driver_id', 'name', $login, $password, 'driver');
            break;

        case 'admin':
            $login = trim($data['login'] ?? $data['username'] ?? $data['email'] ?? '');
            if (!$login) {
                echo json_encode(['success' => false, 'message' => 'Admin username or email required']);
                exit;
            }
            $response = loginUser($pdo, 'admins', 'email', 'username', 'name', $login, $password, 'admin');
            break;

        default:
            $response = ['success' => false, 'message' => 'Invalid user type'];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
