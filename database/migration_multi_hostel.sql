-- ===============================================================
-- IIT (ISM) Dhanbad - Multi-Hostel Warden Migration Script
-- File: database/migration_multi_hostel.sql
-- ===============================================================
-- This migration upgrades an existing single-admin hostel tracker
-- to a Multi-Hostel Warden Management architecture.
-- Safe to run on existing data: does NOT drop tables or erase complaints.

USE hostel_tracker;

-- 1. Create Hostels Table
CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Populate Standard IIT (ISM) Dhanbad Hostels
INSERT IGNORE INTO hostels (id, name, code, description) VALUES
(1, 'Aquamarine Hostel', 'AQUA', 'IIT (ISM) Dhanbad - Aquamarine Hostel'),
(2, 'Amber Hostel', 'AMB', 'IIT (ISM) Dhanbad - Amber Hostel'),
(3, 'Jasper Hostel', 'JASP', 'IIT (ISM) Dhanbad - Jasper Hostel'),
(4, 'Diamond Hostel', 'DIAM', 'IIT (ISM) Dhanbad - Diamond Hostel'),
(5, 'Rosaline Hostel', 'ROS', 'IIT (ISM) Dhanbad - Rosaline Girls Hostel'),
(6, 'Opal Hostel', 'OPAL', 'IIT (ISM) Dhanbad - Opal Hostel'),
(7, 'Topaz Hostel', 'TOP', 'IIT (ISM) Dhanbad - Topaz Hostel'),
(8, 'Ruby Hostel', 'RUBY', 'IIT (ISM) Dhanbad - Ruby Girls Hostel'),
(9, 'Sapphire Hostel', 'SAPH', 'IIT (ISM) Dhanbad - Sapphire Hostel');

-- 3. Add hostel_id column to users table if it does not already exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'hostel_id');
SET @alter_sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN hostel_id INT NULL AFTER role', 
    'SELECT 1');
PREPARE s_user FROM @alter_sql;
EXECUTE s_user;
DEALLOCATE PREPARE s_user;

-- 4. Add hostel_id column to complaints table if it does not already exist
SET @col_exists_c = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaints' AND COLUMN_NAME = 'hostel_id');
SET @alter_sql_c = IF(@col_exists_c = 0, 
    'ALTER TABLE complaints ADD COLUMN hostel_id INT NULL AFTER category', 
    'SELECT 1');
PREPARE s_comp FROM @alter_sql_c;
EXECUTE s_comp;
DEALLOCATE PREPARE s_comp;

-- 5. Backfill hostel_id in users by mapping existing hostel text names
UPDATE users u
JOIN hostels h ON TRIM(u.hostel) = TRIM(h.name)
SET u.hostel_id = h.id
WHERE u.hostel_id IS NULL;

-- 6. Backfill hostel_id in complaints by mapping existing hostel text names
UPDATE complaints c
JOIN hostels h ON TRIM(c.hostel) = TRIM(h.name)
SET c.hostel_id = h.id
WHERE c.hostel_id IS NULL;

-- 7. Add foreign key index on complaints(hostel_id) and users(hostel_id)
CREATE INDEX idx_users_hostel_id ON users(hostel_id);
CREATE INDEX idx_complaints_hostel_id ON complaints(hostel_id);

-- 8. Insert or Update Dedicated Warden Accounts for the 3 Key Hostels:
-- Password for all demo wardens is: Warden@123
INSERT INTO users (name, email, password_hash, role, hostel_id, hostel, room_number, phone)
VALUES 
('Aquamarine Hostel Warden', 'warden.aquamarine@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 1, 'Aquamarine Hostel', 'Warden Office, Aquamarine', '0326-2235101'),
('Amber Hostel Warden', 'warden.amber@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 2, 'Amber Hostel', 'Warden Office, Amber', '0326-2235102'),
('Jasper Hostel Warden', 'warden.jasper@iitism.ac.in', '$2y$10$c6PSNWxhh6T0IPsXki/oFeQ3idnNl9V6msK5j.P9RuYNM0IMyolLe', 'admin', 3, 'Jasper Hostel', 'Warden Office, Jasper', '0326-2235103')
ON DUPLICATE KEY UPDATE 
    hostel_id = VALUES(hostel_id),
    hostel = VALUES(hostel),
    name = VALUES(name),
    role = 'admin';
