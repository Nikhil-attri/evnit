#!/bin/bash

# VNIT E-Vehicle Smart Ride Sharing System - Deployment Script
# This script helps deploy the complete smart mobility platform

echo "🚗 VNIT E-Vehicle Deployment Script"
echo "=========================================="

# Configuration checks
if [ ! -f "backend/config/db.php" ]; then
    echo "❌ Error: Database configuration not found"
    echo "Please copy backend/config/db.example.php to backend/config/db.php and configure"
    exit 1
fi

if [ ! -f "backend/websocket/server.php" ]; then
    echo "❌ Error: WebSocket server not found"
    echo "Please ensure all files are present before deployment"
    exit 1
fi

# Database setup
echo "📊 Setting up database..."
mysql -u root -p << 'EOF'

# Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS vnit_evnit_db;

# Use the database
USE vnit_evnit_db;

-- Check if required tables exist
SELECT COUNT(*) INTO @tables_exist FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN (
    'users', 'students', 'drivers', 'admins',
    'vehicles', 'rides', 'locations', 'payments',
    'wallet_transactions', 'ride_reviews', 'notifications',
    'activity_logs', 'emergency_alerts', 'location_logs',
    'analytics_cache'
);

SET @result = IF(@tables_exist = 25, 'Database tables already exist', 'Creating database tables...');

-- Create tables if they don't exist
SELECT @result AS message;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'driver', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    roll_number VARCHAR(50) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    total_rides INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Drivers table
CREATE TABLE IF NOT EXISTS drivers (
    driver_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    license_number VARCHAR(50) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    total_rides INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.0,
    status ENUM('active', 'inactive', 'offine', 'busy', 'on_trip') DEFAULT 'offline',
    current_lat DECIMAL(10,8) DEFAULT 21.1458,
    current_lng DECIMAL(10,8) DEFAULT 79.0882,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_driver_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_location (current_lat, current_lng)
);

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'operator') DEFAULT 'admin',
    permissions JSON,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Vehicles table
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type ENUM('electric_cart', 'electric_scooter', 'electric_bike', 'electric_car') DEFAULT 'electric_cart',
    capacity INT DEFAULT 4,
    current_occupancy INT DEFAULT 0,
    battery_level INT DEFAULT 100,
    status ENUM('available', 'in_use', 'maintenance', 'offline') DEFAULT 'offline',
    last_latitude DECIMAL(10,8),
    last_longitude DECIMAL(10,8),
    last_location_update TIMESTAMP NULL,
    driver_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    INDEX idx_vehicle_number (vehicle_number),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_location (last_latitude, last_longitude),
    INDEX idx_location_update (last_location_update)
);

-- Rides table
CREATE TABLE IF NOT EXISTS rides (
    ride_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_code VARCHAR(10) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    driver_id INT,
    vehicle_id INT,
    pickup_location INT,
    drop_location INT,
    pickup_lat DECIMAL(10,8),
    pickup_lng DECIMAL(10,8),
    drop_lat DECIMAL(10,8),
    drop_lng DECIMAL(10,8),
    pickup_name VARCHAR(255),
    drop_name VARCHAR(255),
    fare DECIMAL(10,2) DEFAULT 10.00,
    status ENUM('pending', 'accepted', 'started', 'completed', 'cancelled') DEFAULT 'pending',
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accept_time TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    estimated_duration_minutes INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    INDEX idx_ride_code (ride_code),
    INDEX idx_student_id (student_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_status (status),
    INDEX idx_request_time (request_time),
    INDEX idx_pickup_drop (pickup_location, drop_location)
);

-- Locations table
CREATE TABLE IF NOT EXISTS locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(10,8) NOT NULL,
    type ENUM('pickup', 'drop', 'academic', 'hostel', 'canteen', 'library', 'sports', 'admin') DEFAULT 'pickup',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_coordinates (latitude, longitude)
);

-- Sample data insertion
INSERT INTO locations (name, latitude, longitude, type, description) VALUES
('Main Gate', 21.1458, 79.0882, 'pickup', 'Campus main entrance'),
('Library', 21.1470, 79.0878, 'academic', 'Central library building'),
('Hostel Block A', 21.1480, 79.0885, 'hostel', 'Boys hostel block A'),
('Canteen', 21.1490, 79.0880, 'canteen', 'Main canteen area'),
('CRC', 21.1460, 79.0890, 'academic', 'Central Research Center'),
('Parking Area', 21.1440, 79.0875, 'pickup', 'Main vehicle parking area');

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT UNIQUE NOT NULL,
    payment_method ENUM('wallet', 'id_card', 'upi', 'razorpay') DEFAULT 'wallet',
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(255) NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    INDEX idx_ride_id (ride_id),
    INDEX idx_status (status),
    INDEX idx_payment_time (payment_time)
);

-- Wallet transactions table
CREATE TABLE IF NOT EXISTS wallet_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    transaction_type ENUM('recharge', 'deduct', 'refund') DEFAULT 'recharge',
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    reference_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at)
);

-- Ride reviews table
CREATE TABLE IF NOT EXISTS ride_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    student_id INT NOT NULL,
    driver_id INT,
    rating TINYINT DEFAULT 5,
    review_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    INDEX idx_ride_id (ride_id),
    INDEX idx_student_id (student_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_created_at (created_at)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('student', 'driver', 'admin') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'emergency') DEFAULT 'info',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_type),
    INDEX idx_priority (priority),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('student', 'driver', 'admin') NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_type),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
);

-- Emergency alerts table
CREATE TABLE IF NOT EXISTS emergency_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NULL,
    driver_id INT NULL,
    alert_type ENUM('sos', 'medical', 'accident', 'theft', 'other') DEFAULT 'sos',
    alert_lat DECIMAL(10,8) NOT NULL,
    alert_lng DECIMAL(10,8) NOT NULL,
    alert_message TEXT NOT NULL,
    status ENUM('active', 'resolved', 'escalated', 'cancelled') DEFAULT 'active',
    response_time TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    additional_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Location logs table
CREATE TABLE IF NOT EXISTS location_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    driver_id INT NULL,
    ride_id INT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(10,8) NOT NULL,
    speed DECIMAL(8,5) NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_ride_id (ride_id),
    INDEX idx_logged_at (logged_at)
);

-- Analytics cache table
CREATE TABLE IF NOT EXISTS analytics_cache (
    cache_id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) UNIQUE NOT NULL,
    cache_value JSON,
    expires_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cache_key (cache_key),
    INDEX idx_expires_at (expires_at)
);

-- Insert admin user (change password in production!)
INSERT INTO users (email, password_hash, role, status) VALUES
('admin@vnit.ac.in', '$2y$10$zWk6eV@8M3l3aT7oNc3t', 'admin', 'active')
ON DUPLICATE KEY UPDATE status = VALUES(VALUES(status);

EOF

echo "✅ Database setup completed!"

# File permissions
echo "🔒 Setting file permissions..."
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -name "*.sh" -exec chmod 755 {} \;

# Create necessary directories
mkdir -p logs
mkdir -p uploads
mkdir -p cache
mkdir -p temp

echo "✅ File permissions set!"

# Composer dependencies
echo "📦 Installing PHP dependencies..."
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader
else
    echo "⚠ No composer.json found, skipping dependencies"
fi

# Node.js dependencies for WebSocket server
echo "🔧 Installing Node.js dependencies..."
if [ -f "backend/websocket/package.json" ]; then
    cd backend/websocket && npm ci
else
    echo "⚠ No WebSocket package.json found"
fi

echo "✅ Dependencies installed!"

# Configuration
echo "⚙ Setting up configuration..."

# Copy environment file
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ Environment file copied. Please configure it with your settings."
fi

# Create necessary directories
mkdir -p config
mkdir -p backup

# Set up log rotation
cat > logs/logrotate.conf << 'EOF'
logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
    postrotate
    endscript
    lastaction rotate
    mail-error
}
EOF

echo "✅ Configuration completed!"

echo ""
echo "🎯 VNIT E-Vehicle Smart Ride Sharing System Ready!"
echo "=========================================="
echo "📚 Next Steps:"
echo "1. Configure .env file with your database settings"
echo "2. Start web server (Apache/Nginx) for backend"
echo "3. Start WebSocket server: cd backend/websocket && php server.php"
echo "4. Access frontend: http://your-domain/frontend/"
echo "5. Admin Dashboard: http://your-domain/frontend/admin-dashboard.html"
echo "6. Student Dashboard: http://your-domain/frontend/student-dashboard.html"
echo "7. Driver Dashboard: http://your-domain/frontend/driver-dashboard.html"
echo ""
echo "📞 Key URLs:"
echo "Student Dashboard: http://your-domain/frontend/student-dashboard.html"
echo "Driver Dashboard: http://your-domain/frontend/driver-dashboard.html"
echo "Admin Dashboard: http://your-domain/frontend/admin-dashboard.html"
echo "WebSocket Server: ws://your-domain:8080"
echo ""
echo "🔒 Security Notes:"
echo "1. Ensure HTTPS is configured in production"
echo "2. Change all default passwords"
echo "3. Set up proper database user permissions"
echo "4. Configure firewall rules for port 8080 (WebSocket)"
echo "5. Regularly update dependencies and monitor security patches"
echo ""