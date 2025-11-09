<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config/db.php';

echo "<h2>Testing Registration</h2>";

// Test 1: Check students table structure
echo "<h3>Students Table Structure:</h3>";
$stmt = $pdo->query("DESCRIBE students");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($columns);
echo "</pre>";

// Test 2: Try a simple insert
echo "<h3>Test Insert:</h3>";
try {
    $testEmail = 'test' . time() . '@vnit.ac.in';
    $testPassword = password_hash('password123', PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO students (name, email, password) VALUES (?, ?, ?)");
    $result = $stmt->execute(['Test User', $testEmail, $testPassword]);
    
    if ($result) {
        echo "✅ Insert successful!<br>";
        echo "Inserted ID: " . $pdo->lastInsertId() . "<br>";
    }
} catch (PDOException $e) {
    echo "❌ Insert failed: " . $e->getMessage() . "<br>";
}

// Test 3: Check all students
echo "<h3>All Students:</h3>";
$stmt = $pdo->query("SELECT * FROM students");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($students);
echo "</pre>";
?>