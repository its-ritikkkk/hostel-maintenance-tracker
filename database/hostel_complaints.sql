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
  `email` VARCHAR(100) NOT NULL
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

-- ====================================================================
-- Sample Data for Testing and Lab Evaluation
-- ====================================================================

-- Sample Students
INSERT INTO `students` (`id`, `name`, `admission_no`, `email`) VALUES
(1, 'Ritik Kumar', '23JE0501', 'ritik.23je0501@iitism.ac.in'),
(2, 'Aarav Sharma', '23JE0112', 'aarav.23je0112@iitism.ac.in'),
(3, 'Priya Singh', '22JE0840', 'priya.22je0840@iitism.ac.in'),
(4, 'Rohan Verma', '23JE0987', 'rohan.23je0987@iitism.ac.in');

-- Sample Complaints
INSERT INTO `complaints` (`id`, `student_id`, `title`, `description`, `category`, `status`, `created_at`) VALUES
(1, 1, 'Ceiling Fan Not Working', 'The ceiling fan in Room B-204 is making a loud rattling sound and regulator knob is stuck.', 'Electrical', 'In Progress', '2026-10-21 10:15:00'),
(2, 2, 'Bathroom Tap Leaking', 'Tap in the 2nd floor common washroom is dripping continuously and wasting water.', 'Plumbing', 'Submitted', '2026-10-22 09:30:00'),
(3, 1, 'LAN Port Defective', 'The internet ethernet port in Room B-204 has no connectivity / link light is off.', 'Network', 'Submitted', '2026-10-22 14:45:00'),
(4, 3, 'Dustbin Overflow in Corridor', 'Corridor dustbin near Block A staircase is full and needs daily waste collection.', 'Housekeeping', 'Resolved', '2026-10-20 08:20:00'),
(5, 4, 'Tube Light Flickering', 'Tube light in Room C-105 flickers constantly causing headache while studying.', 'Electrical', 'In Progress', '2026-10-23 11:00:00'),
(6, 2, 'Shower Head Clogged', 'Shower head in 2nd floor bathroom has very low water pressure due to calcium scaling.', 'Plumbing', 'Resolved', '2026-10-19 16:10:00');
