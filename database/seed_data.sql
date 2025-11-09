-- VNIT E-Vehicle System - Simple Login Test Data
-- Run this AFTER creating the schema
-- This creates ONE account for each user type for easy testing

USE evnit_system;

-- =====================================================
-- CLEAR EXISTING DATA (Optional)
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE drivers;
TRUNCATE TABLE students;
TRUNCATE TABLE admins;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 1. ONE ADMIN ACCOUNT
-- =====================================================
INSERT INTO admins (name, username, email, password, role, status) VALUES 
('Admin User', 'admin', 'admin@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active');

-- =====================================================
-- 2. ONE STUDENT ACCOUNT
-- =====================================================
INSERT INTO students (name, email, password, phone, roll_number, department, year, wallet_balance, is_verified, status) VALUES
('Raj Kumar', 'student@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'BT21CSE045', 'Computer Science', '3rd Year', 500.00, TRUE, 'active');

-- =====================================================
-- 3. ONE DRIVER ACCOUNT
-- =====================================================
INSERT INTO drivers (name, email, password, phone, license_number, status) VALUES
('Ramesh Driver', 'driver@vnit.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9123456781', 'MH31DL12345', 'active');

-- =====================================================
-- LOGIN CREDENTIALS FOR TESTING
-- =====================================================
-- 
-- ADMIN LOGIN:
-- Username/Email: admin@vnit.ac.in
-- Password: password123
--
-- STUDENT LOGIN:
-- Email: student@vnit.ac.in
-- Password: password123
--
-- DRIVER LOGIN:
-- Email: driver@vnit.ac.in
-- Password: password123
--
-- =====================================================

SELECT 'Test accounts created successfully!' AS Status;
SELECT '=====================================' AS '';
SELECT 'ADMIN LOGIN' AS 'Account Type', 'admin@vnit.ac.in' AS 'Email/Username', 'password123' AS 'Password'
UNION ALL
SELECT 'STUDENT LOGIN', 'student@vnit.ac.in', 'password123'
UNION ALL
SELECT 'DRIVER LOGIN', 'driver@vnit.ac.in', 'password123';