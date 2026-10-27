-- ====================================================================
-- Database: hostel_complaints
-- Project: Hostel Maintenance and Complaint Tracker
-- Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
-- Technology: MariaDB / MySQL
-- ====================================================================

-- 1. Create the database if it doesn't already exist
CREATE DATABASE IF NOT EXISTS `hostel_complaints` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `hostel_complaints`;

-- 2. Drop existing tables if re-importing (complaints first due to foreign key)
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `students`;

-- 3. Create the `students` table
-- Stores minimal student identification: Name, Admission Number, and Email
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `admission_no` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create the `complaints` table
-- Stores hostel maintenance requests linked to a student
CREATE TABLE `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `category` ENUM('Electrical', 'Plumbing', 'Network', 'Housekeeping') NOT NULL,
  `status` ENUM('Submitted', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Submitted',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_complaint_student`
    FOREIGN KEY (`student_id`) 
    REFERENCES `students` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


