<?php
// test_driver.php - Place this in backend/api/auth/ folder
// Run this once: http://localhost/evnit/backend/api/auth/test_driver.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';

echo "<h2>Driver Database Test</h2>";

// Check existing drivers
echo "<h3>Existing Drivers:</h3>";
try {
    $stmt = $pdo->query("SELECT driver_id, name, email, phone, license_number FROM drivers");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($drivers) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Driver ID</th><th>Name</th><th>Email</th><th>Phone</th><th>License</th></tr>";
        foreach ($drivers as $d) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($d['driver_id']) . "</td>";
            echo "<td>" . htmlspecialchars($d['name']) . "</td>";
            echo "<td>" . htmlspecialchars($d['email']) . "</td>";
            echo "<td>" . htmlspecialchars($d['phone']) . "</td>";
            echo "<td>" . htmlspecialchars($d['license_number']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No drivers found in database.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Create/Update test driver
echo "<h3>Creating/Updating Test Driver:</h3>";

$test_driver_id = "3";
$test_password = "driver123"; // Use this password to login
$hashed_password = password_hash($test_password, PASSWORD_BCRYPT);

try {
    // Check if driver exists
    $stmt = $pdo->prepare("SELECT driver_id FROM drivers WHERE driver_id = ?");
    $stmt->execute([$test_driver_id]);
    
    if ($stmt->fetch()) {
        // Update existing driver
        $stmt = $pdo->prepare("UPDATE drivers SET password = ?, name = ?, email = ?, phone = ?, license_number = ? WHERE driver_id = ?");
        $result = $stmt->execute([
            $hashed_password,
            "Test Driver",
            "driver3@vnit.ac.in",
            "9876543210",
            "DL123456",
            $test_driver_id
        ]);
        
        if ($result) {
            echo "<p style='color:green'>✅ Driver updated successfully!</p>";
        }
    } else {
        // Insert new driver
        $stmt = $pdo->prepare("INSERT INTO drivers (driver_id, name, email, phone, password, license_number, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $result = $stmt->execute([
            $test_driver_id,
            "Test Driver",
            "driver3@vnit.ac.in",
            "9876543210",
            $hashed_password,
            "DL123456"
        ]);
        
        if ($result) {
            echo "<p style='color:green'>✅ New driver created successfully!</p>";
        }
    }
    
    echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-radius:5px;'>";
    echo "<h4>Test Login Credentials:</h4>";
    echo "<p><strong>Driver ID:</strong> 3</p>";
    echo "<p><strong>Password:</strong> driver123</p>";
    echo "</div>";
    
    // Verify the password hash
    $stmt = $pdo->prepare("SELECT password FROM drivers WHERE driver_id = ?");
    $stmt->execute([$test_driver_id]);
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $verify = password_verify($test_password, $stored['password']);
    echo "<p>Password verification test: " . ($verify ? "<span style='color:green'>✅ PASS</span>" : "<span style='color:red'>❌ FAIL</span>") . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='../../../frontend/login.html'>Go to Login Page</a></p>";
?>