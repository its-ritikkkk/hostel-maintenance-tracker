-- ===============================================================
-- IIT (ISM) Dhanbad - Multi-Hostel Sample Data Setup
-- ===============================================================

USE hostel_tracker;

-- Clear previous sample data in order of foreign key dependencies
DELETE FROM complaint_updates;
DELETE FROM complaints;
DELETE FROM users;
DELETE FROM hostels;

ALTER TABLE hostels AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE complaints AUTO_INCREMENT = 1;
ALTER TABLE complaint_updates AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------
-- 1. Insert Hostels
-- ---------------------------------------------------------------
INSERT INTO hostels (id, name, code, description) VALUES
(1, 'Aquamarine Hostel', 'AQUA', 'IIT (ISM) Dhanbad - Aquamarine Hostel'),
(2, 'Amber Hostel', 'AMB', 'IIT (ISM) Dhanbad - Amber Hostel'),
(3, 'Jasper Hostel', 'JASP', 'IIT (ISM) Dhanbad - Jasper Hostel'),
(4, 'Diamond Hostel', 'DIAM', 'IIT (ISM) Dhanbad - Diamond Hostel'),
(5, 'Rosaline Hostel', 'ROS', 'IIT (ISM) Dhanbad - Rosaline Girls Hostel'),
(6, 'Opal Hostel', 'OPAL', 'IIT (ISM) Dhanbad - Opal Hostel'),
(7, 'Topaz Hostel', 'TOP', 'IIT (ISM) Dhanbad - Topaz Hostel'),
(8, 'Ruby Hostel', 'RUBY', 'IIT (ISM) Dhanbad - Ruby Girls Hostel'),
(9, 'Sapphire Hostel', 'SAPH', 'IIT (ISM) Dhanbad - Sapphire Hostel');

-- ---------------------------------------------------------------
-- 2. Insert Users:
-- Separate Warden Accounts for each key hostel:
--   - Aquamarine Warden: warden.aquamarine@iitism.ac.in (Password: Warden@123)
--   - Amber Warden: warden.amber@iitism.ac.in (Password: Warden@123)
--   - Jasper Warden: warden.jasper@iitism.ac.in (Password: Warden@123)
-- Students:
--   - Aarav Sharma (Aquamarine): aarav.21je0123@iitism.ac.in (Password: Student@123)
--   - Rohit Verma (Amber): rohit.20je0789@iitism.ac.in (Password: Student@123)
--   - Sneha Mukherjee (Jasper): sneha.23je0234@iitism.ac.in (Password: Student@123)
--   - Priya Patel (Rosaline): priya.22je0456@iitism.ac.in (Password: Student@123)
-- ---------------------------------------------------------------

INSERT INTO users (id, name, email, password_hash, role, hostel_id, hostel, room_number, phone) VALUES
-- Wardens
(1, 'Aquamarine Hostel Warden', 'warden.aquamarine@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 1, 'Aquamarine Hostel', 'Warden Office, Aquamarine', '0326-2235101'),
(2, 'Amber Hostel Warden', 'warden.amber@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 2, 'Amber Hostel', 'Warden Office, Amber', '0326-2235102'),
(3, 'Jasper Hostel Warden', 'warden.jasper@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 3, 'Jasper Hostel', 'Warden Office, Jasper', '0326-2235103'),
(4, 'Chief Warden / Super Admin', 'admin@iitism.ac.in', '$2y$10$pU1fTM.PFQlfzJFzu2unMeJ7Ch/ZiFebs5L.h9yDzC6ow44fvib3K', 'admin', NULL, 'Chief Warden Office', 'Admin Desk', '0326-2235001'),

-- Students
(5, 'Aarav Sharma', 'aarav.21je0123@iitism.ac.in', '$2y$10$olmqS4FI9OUih827nL4JleiNzYGAM/KJdwBdAl5RXHwawA/b3ec.2', 'student', 1, 'Aquamarine Hostel', 'A-304', '9876543210'),
(6, 'Rohit Verma', 'rohit.20je0789@iitism.ac.in', '$2y$10$olmqS4FI9OUih827nL4JleiNzYGAM/KJdwBdAl5RXHwawA/b3ec.2', 'student', 2, 'Amber Hostel', 'C-118', '9876543212'),
(7, 'Sneha Mukherjee', 'sneha.23je0234@iitism.ac.in', '$2y$10$olmqS4FI9OUih827nL4JleiNzYGAM/KJdwBdAl5RXHwawA/b3ec.2', 'student', 3, 'Jasper Hostel', 'D-405', '9876543213'),
(8, 'Priya Patel', 'priya.22je0456@iitism.ac.in', '$2y$10$olmqS4FI9OUih827nL4JleiNzYGAM/KJdwBdAl5RXHwawA/b3ec.2', 'student', 5, 'Rosaline Hostel', 'B-212', '9876543211');

-- ---------------------------------------------------------------
-- 3. Insert Sample Complaints Associated with Specific Hostels
-- ---------------------------------------------------------------
INSERT INTO complaints (id, student_id, title, description, category, hostel_id, hostel, room_number, status, admin_remarks, created_at, updated_at) VALUES
(1, 5, 'Fluorescent tube light flickering in room A-304', 'The main tube light on the study desk is flickering constantly and making a buzzing noise. Need electrician to replace choke or starter.', 'Electrical', 1, 'Aquamarine Hostel', 'A-304', 'Submitted', NULL, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR),
(2, 6, 'Washroom tap leaking continuously in 1st floor wing C', 'Tap number 2 in the shared washroom near room C-118 is leaking heavily, wasting clean water. Needs washer replacement.', 'Plumbing', 2, 'Amber Hostel', 'C-118', 'In Progress', 'Plumber assigned by Amber warden. Inspection scheduled for 2:00 PM.', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 3 HOUR),
(3, 7, 'Hostel LAN port no internet connectivity in D-405', 'Ethernet wall port is not giving DHCP IP address. Connected directly with CAT6 cable to laptop. Wi-Fi signal is also very weak in this corner.', 'Network / Internet', 3, 'Jasper Hostel', 'D-405', 'Resolved', 'Network switch port in Jasper rack reset by CC team. Connection verified working.', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 1 DAY),
(4, 5, 'Corridor dustbins overflowing and floor sweeping needed', 'Dustbin placed in wing A 3rd floor corridor has not been cleared for 2 days. Bad odor in the wing.', 'Housekeeping', 1, 'Aquamarine Hostel', 'A-304', 'Submitted', NULL, NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 5 HOUR),
(5, 8, 'Window latch broken and rattling heavily in wind', 'The right window glass latch is broken and cannot be locked shut. Rainwater splashes inside during storms.', 'Other', 5, 'Rosaline Hostel', 'B-212', 'In Progress', 'Carpenter notified to repair latch mechanism.', NOW() - INTERVAL 18 HOUR, NOW() - INTERVAL 4 HOUR);

-- ---------------------------------------------------------------
-- 4. Insert Complaint Status History
-- ---------------------------------------------------------------
INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, remarks, created_at) VALUES
(2, 2, 'Submitted', 'In Progress', 'Plumber assigned by Amber warden. Inspection scheduled for 2:00 PM.', NOW() - INTERVAL 3 HOUR),
(3, 3, 'Submitted', 'In Progress', 'Ticket forwarded to Computer Centre network engineer.', NOW() - INTERVAL 2 DAY),
(3, 3, 'In Progress', 'Resolved', 'Network switch port in Jasper rack reset by CC team. Connection verified working.', NOW() - INTERVAL 1 DAY);
