<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Get and validate input
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$phone = trim($data['phone'] ?? '');
$department = trim($data['department'] ?? '');

// Validation
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate VNIT email domain
if (!preg_match('/@vnit\.ac\.in$/i', $email)) {
    echo json_encode(['success' => false, 'message' => 'Only VNIT email addresses allowed (e.g., bt21cse045@vnit.ac.in)']);
    exit;
}

// Validate password strength
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Validate phone number (optional but must be valid if provided)
if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number (must be 10 digits)']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered. Please login instead.']);
        exit;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Generate roll number from email (BT23CSE080@vnit.ac.in -> BT23CSE080)
    $roll_number = strtoupper(explode('@', $email)[0]);
    
    // Check if roll number already exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ?");
    $stmt->execute([$roll_number]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Roll number already registered']);
        exit;
    }
    
    // Insert new student
    $stmt = $pdo->prepare("
        INSERT INTO students (name, email, password, phone, roll_number, department, wallet_balance, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, 100.00, 0)
    ");
    
    $result = $stmt->execute([
        $name,
        $email,
        $hashed_password,
        $phone ?: '0000000000',  // Default phone if empty
        $roll_number,
        $department ?: 'Not Specified'  // Default department
    ]);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        exit;
    }
    
    $student_id = $pdo->lastInsertId();
    
    // Try to add wallet transaction
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transactions (student_id, amount, transaction_type, description, created_at)
            VALUES (?, 100.00, 'credit', 'Welcome bonus', NOW())
        ");
        $stmt->execute([$student_id]);
    } catch (PDOException $e) {
        error_log("Wallet transaction failed: " . $e->getMessage());
    }
    
    // Try to send welcome notification
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, created_at)
            VALUES (?, 'student', 'Welcome to VNIT E-Vehicle!', 'Your account has been created successfully. You received ₹100 welcome bonus in your wallet.', NOW())
        ");
        $stmt->execute([$student_id]);
    } catch (PDOException $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
    
    // Get the newly created student
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, roll_number, department, wallet_balance, is_verified, created_at 
        FROM students WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Welcome bonus of ₹100 added to your wallet.',
        'student' => $student
    ]);
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}
?>