<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php'; // Your PDO connection file

// Get POST input
$phone = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$phone || !$password) {
    echo json_encode(['status'=>'error','message'=>'Phone and password required']);
    exit;
}

try {
    // Query driver by phone
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver && password_verify($password, $driver['password'])) {
        // Login success
        $_SESSION['driver_id'] = $driver['driver_id'];
        $_SESSION['driver_name'] = $driver['name'];

        // Update last login
        $update = $pdo->prepare("UPDATE drivers SET last_login = NOW() WHERE driver_id = ?");
        $update->execute([$driver['driver_id']]);

        echo json_encode(['status'=>'success','message'=>'Login successful','driver'=>$driver]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Invalid phone or password']);
    }

} catch (PDOException $e) {
    echo json_encode(['status'=>'error','message'=>'Database error: '.$e->getMessage()]);
}
