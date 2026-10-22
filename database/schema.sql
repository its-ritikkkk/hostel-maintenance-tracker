-- ===============================================================
-- IIT (ISM) Dhanbad - Hostel Maintenance and Complaint Tracker
-- Complete Database Schema (Multi-Hostel Architecture)
-- ===============================================================

CREATE DATABASE IF NOT EXISTS hostel_tracker 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE hostel_tracker;

-- ---------------------------------------------------------------
-- 1. Hostels Table (Master Registry of IIT (ISM) Dhanbad Hostels)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 2. Users Table (Students & Hostel-Specific Wardens / Admins)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    hostel_id INT NULL,
    hostel VARCHAR(100) NULL,
    room_number VARCHAR(30) NULL,
    phone VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_hostel 
        FOREIGN KEY (hostel_id) REFERENCES hostels(id) 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 3. Complaints Table (Stores Maintenance Requests per Hostel)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('Electrical', 'Plumbing', 'Network / Internet', 'Housekeeping', 'Other') NOT NULL,
    hostel_id INT NULL,
    hostel VARCHAR(100) NOT NULL,
    room_number VARCHAR(30) NOT NULL,
    status ENUM('Submitted', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Submitted',
    admin_remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaints_student 
        FOREIGN KEY (student_id) REFERENCES users(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_complaints_hostel 
        FOREIGN KEY (hostel_id) REFERENCES hostels(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 4. Complaint Updates Log (Audit trail of status changes)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaint_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    updated_by INT NOT NULL,
    old_status ENUM('Submitted', 'In Progress', 'Resolved') NULL,
    new_status ENUM('Submitted', 'In Progress', 'Resolved') NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_updates_complaint 
        FOREIGN KEY (complaint_id) REFERENCES complaints(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_updates_user 
        FOREIGN KEY (updated_by) REFERENCES users(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 5. Performance Indexes
-- ---------------------------------------------------------------
CREATE INDEX idx_users_hostel_id ON users(hostel_id);
CREATE INDEX idx_complaints_student_id ON complaints(student_id);
CREATE INDEX idx_complaints_hostel_id ON complaints(hostel_id);
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_complaints_category ON complaints(category);
CREATE INDEX idx_complaints_created_at ON complaints(created_at);
