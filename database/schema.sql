-- VNIT E-Vehicle System - Complete Database Schema (FIXED)
-- Run this file to create all necessary tables

CREATE DATABASE IF NOT EXISTS evnit_system;
USE evnit_system;

-- Drop existing tables if they exist (in correct order to avoid foreign key conflicts)
DROP TABLE IF EXISTS emergency_alerts;
DROP TABLE IF EXISTS analytics_logs;
DROP TABLE IF EXISTS campus_locations;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS broadcasts;
DROP TABLE IF EXISTS ride_reviews;
DROP TABLE IF EXISTS location_logs;
DROP TABLE IF EXISTS ride_locations;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS rides;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS system_settings;

-- =====================================================
-- 1. STUDENTS TABLE
-- =====================================================
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    roll_number VARCHAR(20) UNIQUE,
    department VARCHAR(100),
    year VARCHAR(10) DEFAULT NULL,
    id_card_barcode VARCHAR(50) UNIQUE DEFAULT NULL,
    wallet_balance DECIMAL(10, 2) DEFAULT 100.00,
    is_verified BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_roll (roll_number),
    INDEX idx_barcode (id_card_barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 2. DRIVERS TABLE (FIXED - Added email column)
-- =====================================================
CREATE TABLE drivers (
    driver_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('active', 'offline', 'busy', 'suspended') DEFAULT 'offline',
    current_lat DECIMAL(10,8) DEFAULT NULL,
    current_lng DECIMAL(11,8) DEFAULT NULL,
    total_earnings DECIMAL(10,2) DEFAULT 0.00,
    rating DECIMAL(3,2) DEFAULT 5.00,
    total_rides INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_location (current_lat, current_lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3. ADMINS TABLE
-- =====================================================
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    role ENUM('super_admin', 'admin', 'moderator', 'transport_admin', 'support_admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4. VEHICLES TABLE
-- =====================================================
CREATE TABLE vehicles (
    vehicle_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type ENUM('e-rickshaw', 'e-scooter', 'e-cart') DEFAULT 'e-rickshaw',
    model VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 4,
    current_capacity INT DEFAULT 0,
    current_occupancy INT DEFAULT 0,
    battery_level INT DEFAULT 100,
    status ENUM('active', 'available', 'on_ride', 'charging', 'maintenance', 'inactive') DEFAULT 'active',
    driver_id INT,
    last_latitude DECIMAL(10, 8) DEFAULT 21.1236,
    last_longitude DECIMAL(11, 8) DEFAULT 79.0511,
    last_service_date DATE DEFAULT NULL,
    total_distance_km DECIMAL(10,2) DEFAULT 0.00,
    last_location_update TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_driver (driver_id),
    INDEX idx_location (last_latitude, last_longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 5. LOCATIONS TABLE
-- =====================================================
CREATE TABLE locations (
    location_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    description TEXT,
    category ENUM('hostel', 'academic', 'administrative', 'facility', 'gate', 'other') DEFAULT 'other',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6. RIDES TABLE
-- =====================================================
CREATE TABLE rides (
    ride_id INT PRIMARY KEY AUTO_INCREMENT,
    ride_code VARCHAR(20) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    driver_id INT,
    vehicle_id INT,
    pickup_location INT NOT NULL,
    drop_location INT NOT NULL,
    pickup_lat DECIMAL(10,8) NOT NULL,
    pickup_lng DECIMAL(11,8) NOT NULL,
    pickup_name VARCHAR(100) NOT NULL,
    drop_lat DECIMAL(10,8) NOT NULL,
    drop_lng DECIMAL(11,8) NOT NULL,
    drop_name VARCHAR(100) NOT NULL,
    status ENUM('pending', 'assigned', 'accepted', 'driver_assigned', 'started', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    fare DECIMAL(10, 2) DEFAULT 10.00,
    distance DECIMAL(10, 2),
    distance_km DECIMAL(5,2) DEFAULT NULL,
    estimated_time INT,
    estimated_duration_min INT DEFAULT NULL,
    actual_time INT,
    payment_method ENUM('wallet', 'id_card', 'upi') DEFAULT 'wallet',
    payment_status ENUM('pending', 'paid', 'completed', 'refunded', 'failed') DEFAULT 'pending',
    ride_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accept_time TIMESTAMP NULL DEFAULT NULL,
    started_at TIMESTAMP NULL,
    pickup_time TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL,
    drop_time TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    rating INT DEFAULT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
    FOREIGN KEY (pickup_location) REFERENCES locations(location_id),
    FOREIGN KEY (drop_location) REFERENCES locations(location_id),
    INDEX idx_student (student_id),
    INDEX idx_driver (driver_id),
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_status (status),
    INDEX idx_date (ride_date),
    INDEX idx_request_time (request_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 7. RIDE LOCATIONS (Real-time tracking)
-- =====================================================
CREATE TABLE ride_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    vehicle_lat DECIMAL(10,8) NOT NULL,
    vehicle_lng DECIMAL(11,8) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    INDEX idx_ride (ride_id),
    INDEX idx_time (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. LOCATION LOGS TABLE
-- =====================================================
CREATE TABLE location_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    ride_id INT,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    speed DECIMAL(5, 2),
    battery_level INT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE SET NULL,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_ride (ride_id),
    INDEX idx_time (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 9. PAYMENTS TABLE
-- =====================================================
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    ride_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('wallet', 'id_card', 'upi', 'online', 'admin') NOT NULL,
    payment_type ENUM('ride', 'ride_payment', 'recharge', 'wallet_recharge', 'refund') DEFAULT 'ride',
    transaction_type ENUM('ride', 'recharge', 'refund') DEFAULT 'ride',
    status ENUM('pending', 'completed', 'success', 'failed', 'refunded') DEFAULT 'pending',
    barcode_scanned VARCHAR(50) DEFAULT NULL,
    upi_reference VARCHAR(100) DEFAULT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_ride (ride_id),
    INDEX idx_date (payment_date),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 10. WALLET TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE wallet_transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_type ENUM('credit', 'debit') NOT NULL,
    description VARCHAR(255),
    balance_after DECIMAL(10, 2),
    reference_id VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 11. RIDE REVIEWS TABLE
-- =====================================================
CREATE TABLE ride_reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    ride_id INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    driver_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    INDEX idx_driver (driver_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 12. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type ENUM('student', 'driver', 'admin') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('ride_update', 'payment', 'system', 'emergency') DEFAULT 'system',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    broadcast_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 13. BROADCASTS TABLE
-- =====================================================
CREATE TABLE broadcasts (
    broadcast_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    recipient_type ENUM('all', 'students', 'drivers') NOT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    recipients_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 14. ACTIVITY LOGS TABLE
-- =====================================================
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type ENUM('student', 'driver', 'admin') NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 15. CAMPUS LOCATIONS (Predefined stops)
-- =====================================================
CREATE TABLE campus_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    category ENUM('hostel', 'academic', 'administrative', 'facility', 'gate') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 16. ANALYTICS LOGS TABLE
-- =====================================================
CREATE TABLE analytics_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_date DATE NOT NULL,
    total_rides INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    avg_wait_time_min INT DEFAULT 0,
    peak_hour_start TIME DEFAULT NULL,
    peak_hour_end TIME DEFAULT NULL,
    active_vehicles INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 17. EMERGENCY ALERTS TABLE
-- =====================================================
CREATE TABLE emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT DEFAULT NULL,
    student_id INT NOT NULL,
    driver_id INT DEFAULT NULL,
    alert_lat DECIMAL(10,8) NOT NULL,
    alert_lng DECIMAL(11,8) NOT NULL,
    alert_message TEXT DEFAULT NULL,
    status ENUM('active', 'responded', 'resolved') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 18. SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT INITIAL DATA
-- =====================================================

-- Insert admin account
INSERT INTO admins (name, username, email, password, role) VALUES 
('System Admin', 'admin', 'admin@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');
-- Password: admin123

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('base_fare', '10', 'Base fare for all rides in INR'),
('peak_hour_start', '8', 'Peak hour start (24-hour format)'),
('peak_hour_end', '10', 'Peak hour end (24-hour format)'),
('evening_peak_start', '17', 'Evening peak hour start'),
('evening_peak_end', '19', 'Evening peak hour end'),
('max_capacity_per_vehicle', '4', 'Maximum passengers per vehicle'),
('welcome_bonus', '100', 'Welcome bonus for new students'),
('min_wallet_balance', '10', 'Minimum wallet balance required'),
('campus_lat_min', '21.132', 'Campus minimum latitude'),
('campus_lat_max', '21.148', 'Campus maximum latitude'),
('campus_lon_min', '79.047', 'Campus minimum longitude'),
('campus_lon_max', '79.065', 'Campus maximum longitude');

-- Insert sample campus locations (17 locations)
INSERT INTO locations (name, latitude, longitude, description, category) VALUES
('Main Gate', 21.1350, 79.0520, 'Main entrance to VNIT campus', 'gate'),
('Admin Building', 21.1365, 79.0535, 'Administrative office complex', 'administrative'),
('CSE Department', 21.1380, 79.0545, 'Computer Science & Engineering', 'academic'),
('ECE Department', 21.1375, 79.0540, 'Electronics & Communication Engineering', 'academic'),
('Hostel 1 (Boys)', 21.1420, 79.0580, 'Boys hostel block 1', 'hostel'),
('Hostel 2 (Girls)', 21.1415, 79.0575, 'Girls hostel block', 'hostel'),
('Library', 21.1370, 79.0550, 'Central Library', 'academic'),
('CRC', 21.1390, 79.0560, 'Central Research Complex', 'academic'),
('Canteen 1', 21.1385, 79.0555, 'Main canteen near admin', 'facility'),
('Canteen 2', 21.1425, 79.0585, 'Hostel area canteen', 'facility'),
('Sports Complex', 21.1400, 79.0590, 'Indoor and outdoor sports facilities', 'facility'),
('Auditorium', 21.1360, 79.0530, 'Main auditorium', 'facility'),
('Medical Center', 21.1355, 79.0525, 'Campus health center', 'facility'),
('Guest House', 21.1410, 79.0570, 'Guest house for visitors', 'facility'),
('Faculty Quarters', 21.1440, 79.0600, 'Faculty residential area', 'other'),
('Workshop', 21.1345, 79.0515, 'Engineering workshop', 'academic'),
('Parking Area', 21.1340, 79.0510, 'Main parking lot', 'facility');

-- Sample students
INSERT INTO students (name, email, password, phone, roll_number, department, wallet_balance) VALUES
('Raj Kumar', 'bt21cse045@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'BT21CSE045', 'Computer Science', 500.00),
('Priya Sharma', 'bt21ece032@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543211', 'BT21ECE032', 'Electronics', 300.00),
('Amit Patel', 'bt21mech018@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543212', 'BT21MECH018', 'Mechanical', 250.00);
-- Password for all: password123

-- Sample drivers (WITH EMAIL COLUMN)
INSERT INTO drivers (name, email, password, phone, license_number, status) VALUES
('Ramesh Driver', 'driver1@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9123456781', 'MH31DL12345', 'active'),
('Suresh Driver', 'driver2@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9123456782', 'MH31DL12346', 'active'),
('Mahesh Driver', 'driver3@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9123456783', 'MH31DL12347', 'active');
-- Password for all: password123

-- Sample vehicles
INSERT INTO vehicles (vehicle_number, model, capacity, battery_level, status, driver_id, last_latitude, last_longitude) VALUES
('MH31EV1001', 'Tata Nexon EV', 4, 85, 'active', 1, 21.1350, 79.0520),
('MH31EV1002', 'Mahindra e2o', 4, 90, 'active', 2, 21.1365, 79.0535),
('MH31EV1003', 'MG ZS EV', 4, 95, 'active', 3, 21.1380, 79.0545);

-- Sample completed rides
INSERT INTO rides (ride_code, student_id, driver_id, vehicle_id, pickup_location, drop_location, pickup_lat, pickup_lng, pickup_name, drop_lat, drop_lng, drop_name, status, fare, distance, payment_method, ride_date, completed_at) VALUES
('RIDE001', 1, 1, 1, 1, 7, 21.1350, 79.0520, 'Main Gate', 21.1370, 79.0550, 'Library', 'completed', 10.00, 1.2, 'wallet', NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR),
('RIDE002', 2, 2, 2, 5, 8, 21.1420, 79.0580, 'Hostel 1 (Boys)', 21.1390, 79.0560, 'CRC', 'completed', 10.00, 0.8, 'wallet', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 2 HOUR);

-- Sample payments
INSERT INTO payments (transaction_id, student_id, ride_id, amount, payment_method, transaction_type, status) VALUES
('TXN001', 1, 1, 10.00, 'wallet', 'ride', 'completed'),
('TXN002', 2, 2, 10.00, 'wallet', 'ride', 'completed');

-- Sample ride reviews
INSERT INTO ride_reviews (ride_id, student_id, driver_id, rating, review) VALUES
(1, 1, 1, 5, 'Excellent service! Very smooth ride.'),
(2, 2, 2, 4, 'Good driver, on time arrival.');

-- Sample notifications
INSERT INTO notifications (user_id, user_type, title, message, priority) VALUES
(1, 'student', 'Welcome to VNIT E-Vehicle', 'Your account has been created successfully!', 'normal'),
(1, 'driver', 'Vehicle Assigned', 'Vehicle MH31EV1001 has been assigned to you.', 'normal');

-- Success message
SELECT 'Database schema created successfully with all tables!' AS Status;