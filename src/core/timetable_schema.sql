-- Create tables for timetable system

-- Year levels table
CREATE TABLE IF NOT EXISTS years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Groups table
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(10) NOT NULL,
    year_id INT NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (year_id) REFERENCES years(id) ON DELETE CASCADE,
    UNIQUE KEY year_group (year_id, name)
);

-- Users table already exists, but we'll add the student-specific fields
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS group_id INT,
ADD CONSTRAINT fk_user_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL;

-- Subjects table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    color VARCHAR(10) DEFAULT '#3b82f6',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Professor-Subject assignments (which professors can teach which subjects)
CREATE TABLE IF NOT EXISTS professor_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY professor_subject (professor_id, subject_id)
);

-- Timetable entries
CREATE TABLE IF NOT EXISTS timetable_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    professor_id INT NOT NULL,
    group_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY time_slot (group_id, day_of_week, start_time)
);

-- Insert default years
INSERT IGNORE INTO years (name, description) VALUES 
('Y1', 'First Year'),
('Y2', 'Second Year'),
('Y3', 'Third Year');

-- Insert default groups
INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G1', id, 'Group 1' FROM years WHERE name = 'Y1';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G2', id, 'Group 2' FROM years WHERE name = 'Y1';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G3', id, 'Group 3' FROM years WHERE name = 'Y1';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G4', id, 'Group 4' FROM years WHERE name = 'Y1';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G1', id, 'Group 1' FROM years WHERE name = 'Y2';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G2', id, 'Group 2' FROM years WHERE name = 'Y2';

INSERT IGNORE INTO groups (name, year_id, description) 
SELECT 'G1', id, 'Group 1' FROM years WHERE name = 'Y3';

-- Insert default subjects
INSERT IGNORE INTO subjects (name, code, color, description) VALUES
('Mathematics', 'MATH101', '#3b82f6', 'Fundamental mathematics course'),
('Physics', 'PHYS101', '#8b5cf6', 'Introduction to physics'),
('Chemistry', 'CHEM101', '#ec4899', 'Basic principles of chemistry'),
('Biology', 'BIOL101', '#10b981', 'Introduction to biology'),
('Computer Science', 'CS101', '#f59e0b', 'Introduction to computer science'),
('Literature', 'LIT101', '#6366f1', 'World literature studies'),
('History', 'HIST101', '#ef4444', 'World history'),
('Economics', 'ECON101', '#0ea5e9', 'Principles of economics'); 