-- seed.sql
-- Database schema for Lyceum Alumni Tracking System (LATS)

CREATE DATABASE IF NOT EXISTS lats_db;
USE lats_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'alumni', 'officer') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    first_login BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alumni table
CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_id VARCHAR(20) UNIQUE,
    program VARCHAR(100),
    batch_year INT,
    address TEXT,
    contact_no VARCHAR(20),
    advanced_degree VARCHAR(255),
    employment_status VARCHAR(100),
    company VARCHAR(100),
    job_title VARCHAR(100),
    position_level VARCHAR(100),
    discipline_match BOOLEAN,
    years_experience INT,
    date_started DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Batch Officers table
CREATE TABLE IF NOT EXISTS batch_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_year INT NOT NULL,
    user_id INT,
    officer_role ENUM('president', 'vp', 'secretary', 'treasurer', 'pro') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Polls table
CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    poll_type ENUM('single', 'multiple', 'yesno'),
    visibility ENUM('all', 'batch', 'program'),
    target_batch INT,
    target_program VARCHAR(100),
    is_anonymous BOOLEAN DEFAULT FALSE,
    open_date DATETIME,
    close_date DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Poll Options table
CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT,
    option_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

-- Poll Votes table
CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT,
    option_id INT,
    user_id INT,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Spotlights table
CREATE TABLE IF NOT EXISTS spotlights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id INT,
    quote TEXT,
    achievement TEXT,
    status ENUM('pending', 'active', 'archived') DEFAULT 'pending',
    rotation_start DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Spotlight Reactions table
CREATE TABLE IF NOT EXISTS spotlight_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spotlight_id INT,
    user_id INT,
    reaction_type ENUM('like', 'inspire', 'congratulate'),
    FOREIGN KEY (spotlight_id) REFERENCES spotlights(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    type VARCHAR(50),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    scope ENUM('all', 'batch', 'program'),
    target_batch INT,
    target_program VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Import Logs table
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255),
    records_added INT,
    duplicates_skipped INT,
    imported_by INT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imported_by) REFERENCES users(id)
);

-- Filter Presets table
CREATE TABLE IF NOT EXISTS filter_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    preset_name VARCHAR(100),
    filter_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed Admin
INSERT INTO users (name, email, password_hash, role, is_active, first_login) VALUES 
('System Admin', 'admin@lyceum.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, FALSE);

-- Seed Alumni
-- Note: All passwords are 'password' hashed with default cost ($2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO users (name, email, password_hash, role) VALUES 
('Juan Dela Cruz', 'juan@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Maria Santos', 'maria@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Antonio Luna', 'antonio@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Elena Reyes', 'elena@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni');

-- Seed Spotlight
INSERT INTO spotlights (alumni_id, quote, achievement, status, rotation_start, created_by) VALUES 
(1, 'LATS helped me reconnect with Batch 2024 and find my path in fintech. Stay proud, Lyceans!', 'Lead Software Engineer at GCash, pioneering mobile payments in the PH.', 'active', CURDATE(), 1);

-- Seed Notifications
INSERT INTO notifications (user_id, type, message) VALUES 
(2, 'welcome', 'Welcome to the LATS Portal! Please complete your profile to access all features.');

INSERT INTO users (name, email, password_hash, role) VALUES 
('Ricardo Ramos', 'ricardo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Sonia Garcia', 'sonia@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Jose Rizal', 'jose@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Luzviminda Cruz', 'luz@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Fernando Amorsolo', 'fernando@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Corazon Aquino', 'cory@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Gabriel Silang', 'gabriel@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Melchora Aquino', 'melchora@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Andres Bonifacio', 'andres@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Leonor Rivera', 'leonor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Emilio Aguinaldo', 'emilio@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Teresa Magbanua', 'teresa@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Marcelo del Pilar', 'marcelo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Gregoria de Jesus', 'gregoria@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Francisco Balagtas', 'francisco@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni'),
('Lapu Lapu', 'lapu@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni');

-- Seed Alumni Details
-- Using user IDs 2-21
INSERT INTO alumni (user_id, student_id, program, batch_year, address, contact_no, employment_status, company, job_title, discipline_match, years_experience) VALUES 
(2, '2024-0001', 'BSIT', 2024, 'Muntinlupa City', '09171234567', 'Employed', 'GCash', 'Junior Dev', TRUE, 0),
(3, '2023-0001', 'BSCS', 2023, 'Alabang, Muntinlupa', '09181234567', 'Employed', 'Accenture', 'Software Engineer', TRUE, 1),
(4, '2022-0001', 'BSIT', 2022, 'Las Pinas City', '09191234567', 'Employed', 'BDO', 'IT Support', TRUE, 2),
(5, '2021-0001', 'BSBA', 2021, 'Taguig City', '09201234567', 'Employed', 'Globe Telecom', 'Account Executive', TRUE, 3),
(6, '2020-0001', 'BSED', 2020, 'Paranaque City', '09211234567', 'Employed', 'DepEd', 'Teacher', TRUE, 4),
(7, '2019-0001', 'BSN', 2019, 'Cavite', '09221234567', 'Employed', 'St. Lukes', 'Registered Nurse', TRUE, 5),
(8, '2018-0001', 'BSIT', 2018, 'Laguna', '09231234567', 'Employed', 'Canva', 'UX Designer', TRUE, 6),
(9, '2017-0001', 'BSCS', 2017, 'Batangas', '09241234567', 'Employed', 'Google PH', 'Site Reliability Engineer', TRUE, 7),
(10, '2016-0001', 'BSBA', 2016, 'Quezon City', '09251234567', 'Employed', 'Ayala Corp', 'Marketing Manager', TRUE, 8),
(11, '2015-0001', 'BSIT', 2015, 'Manila', '09261234567', 'Self-employed', 'Freelance', 'Full Stack Developer', TRUE, 9),
(12, '2014-0001', 'BSCS', 2014, 'Pasig City', '09271234567', 'Employed', 'Oracle', 'DBA', TRUE, 10),
(13, '2013-0001', 'BSIT', 2013, 'Muntinlupa', '09281234567', 'Employed', 'SAP PH', 'Consultant', TRUE, 11),
(14, '2012-0001', 'BSBA', 2012, 'Taguig', '09291234567', 'Employed', 'Jollibee Foods', 'Store Manager', TRUE, 12),
(15, '2011-0001', 'BSED', 2011, 'Paranaque', '09301234567', 'Employed', 'De La Salle', 'Professor', TRUE, 13),
(16, '2010-0001', 'BSN', 2010, 'Las Pinas', '09311234567', 'Employed', 'Medical City', 'Head Nurse', TRUE, 14),
(17, '2009-0001', 'BSIT', 2009, 'Cavite', '09321234567', 'Employed', 'Intel PH', 'Hardware Engineer', TRUE, 15),
(18, '2008-0001', 'BSCS', 2008, 'Laguna', '09331234567', 'Employed', 'IBM PH', 'Lead Analyst', TRUE, 16),
(19, '2007-0001', 'BSBA', 2007, 'Batangas', '09341234567', 'Employed', 'Shell PH', 'Operations Head', TRUE, 17),
(20, '2006-0001', 'BSED', 2006, 'Muntinlupa', '09351234567', 'Employed', 'Lyceum', 'Principal', TRUE, 18),
(21, '2005-0001', 'BSIT', 2005, 'Taguig', '09361234567', 'Employed', 'Maya PH', 'VP of Tech', TRUE, 19);
