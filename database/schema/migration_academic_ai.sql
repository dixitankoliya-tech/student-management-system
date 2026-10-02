-- ==========================================================
-- SMS 2.0: AI/ML ACADEMIC INTELLIGENCE + TOPPER EDITION
-- Migration & Seed Data Script
-- Target Database: student_management
-- ==========================================================

USE student_management;

-- 1. Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(100) NOT NULL,
    semester INT NOT NULL,
    credits INT DEFAULT 4,
    max_internal DECIMAL(5,2) DEFAULT 30.00,
    max_external DECIMAL(5,2) DEFAULT 70.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Student Marks Table (Subject-Wise)
CREATE TABLE IF NOT EXISTS student_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    internal_marks DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    external_marks DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    total_marks DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    grade VARCHAR(5) NOT NULL DEFAULT 'F',
    semester INT NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_subject_sem (student_id, subject_id, semester),
    CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_marks_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Academic Performance Aggregate Table (Per Semester Intelligence Metrics)
CREATE TABLE IF NOT EXISTS academic_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester INT NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    total_marks_obtained DECIMAL(6,2) DEFAULT 0.00,
    total_max_marks DECIMAL(6,2) DEFAULT 500.00,
    overall_percentage DECIMAL(5,2) DEFAULT 0.00,
    grade VARCHAR(5) DEFAULT 'F',
    attendance_percentage DECIMAL(5,2) DEFAULT 80.00,
    assignment_score DECIMAL(5,2) DEFAULT 80.00,
    practical_score DECIMAL(5,2) DEFAULT 80.00,
    previous_semester_percentage DECIMAL(5,2) NULL,
    improvement_percentage DECIMAL(5,2) DEFAULT 0.00,
    performance_score DECIMAL(5,2) DEFAULT 0.00,
    performance_category VARCHAR(50) DEFAULT 'On Track',
    alert_level VARCHAR(20) DEFAULT 'GREEN',
    class_rank INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_perf_sem (student_id, semester),
    CONSTRAINT fk_perf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Achievements Table
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    badge_icon VARCHAR(50) DEFAULT 'fa-trophy',
    badge_color VARCHAR(20) DEFAULT 'warning',
    description TEXT NOT NULL,
    awarded_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_achieve_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. System Settings Table (Configurable Weights & Thresholds)
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- SEED DATA: Default System Settings (Weights & Rules)
-- ==========================================================
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('weight_academic', '0.60', 'Weight for Academic Marks Percentage (60%)'),
('weight_attendance', '0.15', 'Weight for Attendance Percentage (15%)'),
('weight_assignment', '0.10', 'Weight for Assignment Scores (10%)'),
('weight_practical', '0.15', 'Weight for Practical Exam Scores (15%)'),
('alert_red_attendance', '65.0', 'Attendance below this triggers RED Alert'),
('alert_red_percentage', '50.0', 'Marks percentage below this triggers RED Alert'),
('alert_yellow_attendance', '75.0', 'Attendance below this triggers YELLOW Alert'),
('alert_yellow_percentage', '60.0', 'Marks percentage below this triggers YELLOW Alert')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ==========================================================
-- SEED DATA: Core B.Sc. IT (AI/ML) Subjects (Semesters 1 - 6)
-- ==========================================================
INSERT INTO subjects (id, subject_code, subject_name, semester, credits, max_internal, max_external) VALUES
-- Semester 1
(101, 'BIT101', 'Programming in C & Logic Development', 1, 4, 30.00, 70.00),
(102, 'BIT102', 'Digital Electronics & Computer Architecture', 1, 4, 30.00, 70.00),
(103, 'BIT103', 'Mathematical Foundations for Computing', 1, 4, 30.00, 70.00),
(104, 'BIT104', 'Technical Communication & Soft Skills', 1, 3, 30.00, 70.00),
(105, 'BIT105', 'Web Technologies (HTML5, CSS3, JS)', 1, 4, 30.00, 70.00),

-- Semester 2
(201, 'BIT201', 'Data Structures & Algorithms in C', 2, 4, 30.00, 70.00),
(202, 'BIT202', 'Object-Oriented Programming with C++', 2, 4, 30.00, 70.00),
(203, 'BIT203', 'Discrete Mathematics & Graph Theory', 2, 4, 30.00, 70.00),
(204, 'BIT204', 'Database Management Systems & SQL', 2, 4, 30.00, 70.00),
(205, 'BIT205', 'Environmental Studies & Digital Ethics', 2, 3, 30.00, 70.00),

-- Semester 3
(301, 'BIT301', 'Core & Advanced Java Programming', 3, 4, 30.00, 70.00),
(302, 'BIT302', 'Operating Systems & System Programming', 3, 4, 30.00, 70.00),
(303, 'BIT303', 'Probability & Statistics for Data Science', 3, 4, 30.00, 70.00),
(304, 'BIT304', 'Computer Networks & Internet Protocols', 3, 4, 30.00, 70.00),
(305, 'BIT305', 'Foundations of Python for AI', 3, 4, 30.00, 70.00),

-- Semester 4
(401, 'BIT401', 'Advanced Python & Scientific Computing', 4, 4, 30.00, 70.00),
(402, 'BIT402', 'Software Engineering & Agile Methodologies', 4, 4, 30.00, 70.00),
(403, 'BIT403', 'Linear Algebra & Numerical Methods', 4, 4, 30.00, 70.00),
(404, 'BIT404', 'Web Application Development with PHP/MySQL', 4, 4, 30.00, 70.00),
(405, 'BIT405', 'Introduction to Artificial Intelligence', 4, 4, 30.00, 70.00),

-- Semester 5
(1, 'AIML501', 'Machine Learning & Pattern Recognition', 5, 4, 30.00, 70.00),
(2, 'AIML502', 'Deep Learning & Neural Networks', 5, 4, 30.00, 70.00),
(3, 'AIML503', 'Natural Language Processing', 5, 4, 30.00, 70.00),
(4, 'AIML504', 'Big Data Analytics & Mining', 5, 4, 30.00, 70.00),
(5, 'AIML505', 'Cloud Computing & DevOps', 5, 4, 30.00, 70.00),

-- Semester 6
(601, 'AIML601', 'Computer Vision & Image Understanding', 6, 4, 30.00, 70.00),
(602, 'AIML602', 'Reinforcement Learning & Autonomous Agents', 6, 4, 30.00, 70.00),
(603, 'AIML603', 'Generative AI & Large Language Models', 6, 4, 30.00, 70.00),
(604, 'AIML604', 'AI Ethics, Governance & Cyber Security', 6, 3, 30.00, 70.00),
(605, 'AIML605', 'Capstone Research Project & Viva Voce', 6, 5, 30.00, 70.00)
ON DUPLICATE KEY UPDATE subject_name=VALUES(subject_name), credits=VALUES(credits), semester=VALUES(semester);

-- ==========================================================
-- SEED DATA: Student Marks, Attendance, and Performance
-- (Ensuring diverse scenarios: Topper, High Performer,
--  Most Improved, Consistent, and At-Risk students)
-- ==========================================================

-- Student 1: Rahul Sharma (id=1) - Strong Performer, High Attendance
INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES
(1, 1, 26.0, 60.0, 86.0, 86.0, 'A', 5, '2024-2025'),
(1, 2, 25.0, 58.0, 83.0, 83.0, 'A', 5, '2024-2025'),
(1, 3, 27.0, 62.0, 89.0, 89.0, 'A', 5, '2024-2025'),
(1, 4, 24.0, 56.0, 80.0, 80.0, 'A', 5, '2024-2025'),
(1, 5, 26.0, 61.0, 87.0, 87.0, 'A', 5, '2024-2025')
ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks);

INSERT INTO academic_performance 
(student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level, class_rank)
VALUES
(1, 5, '2024-2025', 425.00, 500.00, 85.00, 'A', 94.50, 90.00, 92.00, 81.50, 3.50, 87.98, 'High Performer', 'GREEN', 2)
ON DUPLICATE KEY UPDATE overall_percentage=VALUES(overall_percentage);

-- Student 2: Priya Patel (id=2) - Overall Topper (Class Rank 1, 90%+)
INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES
(2, 1, 29.0, 66.0, 95.0, 95.0, 'A+', 5, '2024-2025'),
(2, 2, 28.0, 65.0, 93.0, 93.0, 'A+', 5, '2024-2025'),
(2, 3, 29.0, 64.0, 93.0, 93.0, 'A+', 5, '2024-2025'),
(2, 4, 28.0, 63.0, 91.0, 91.0, 'A+', 5, '2024-2025'),
(2, 5, 29.0, 67.0, 96.0, 96.0, 'A+', 5, '2024-2025')
ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks);

INSERT INTO academic_performance 
(student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level, class_rank)
VALUES
(2, 5, '2024-2025', 468.00, 500.00, 93.60, 'A+', 96.00, 95.00, 97.00, 91.00, 2.60, 94.61, 'Exceptional', 'GREEN', 1)
ON DUPLICATE KEY UPDATE overall_percentage=VALUES(overall_percentage);

-- Student 3: Rohan Desai (id=3) - Most Improved Student (+14% improvement!)
INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES
(3, 1, 25.0, 56.0, 81.0, 81.0, 'A', 5, '2024-2025'),
(3, 2, 24.0, 55.0, 79.0, 79.0, 'B+', 5, '2024-2025'),
(3, 3, 25.0, 57.0, 82.0, 82.0, 'A', 5, '2024-2025'),
(3, 4, 23.0, 54.0, 77.0, 77.0, 'B+', 5, '2024-2025'),
(3, 5, 26.0, 60.0, 86.0, 86.0, 'A', 5, '2024-2025')
ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks);

INSERT INTO academic_performance 
(student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level, class_rank)
VALUES
(3, 5, '2024-2025', 405.00, 500.00, 81.00, 'A', 88.00, 85.00, 89.00, 67.00, 14.00, 83.65, 'High Performer', 'GREEN', 3)
ON DUPLICATE KEY UPDATE overall_percentage=VALUES(overall_percentage);

-- Student 4: Neha Shah (id=4) - Subject Topper in NLP (98%)
INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES
(4, 1, 23.0, 52.0, 75.0, 75.0, 'B+', 5, '2024-2025'),
(4, 2, 22.0, 50.0, 72.0, 72.0, 'B+', 5, '2024-2025'),
(4, 3, 30.0, 68.0, 98.0, 98.0, 'A+', 5, '2024-2025'),
(4, 4, 21.0, 48.0, 69.0, 69.0, 'B', 5, '2024-2025'),
(4, 5, 24.0, 53.0, 77.0, 77.0, 'B+', 5, '2024-2025')
ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks);

INSERT INTO academic_performance 
(student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level, class_rank)
VALUES
(4, 5, '2024-2025', 391.00, 500.00, 78.20, 'B+', 85.00, 82.00, 88.00, 76.50, 1.70, 81.07, 'Strong Performer', 'GREEN', 4)
ON DUPLICATE KEY UPDATE overall_percentage=VALUES(overall_percentage);

-- Student 5: Amit Trivedi (id=5) - At Risk / Needs Attention (Low attendance & marks)
INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES
(5, 1, 14.0, 32.0, 46.0, 46.0, 'D', 5, '2024-2025'),
(5, 2, 13.0, 31.0, 44.0, 44.0, 'D', 5, '2024-2025'),
(5, 3, 16.0, 35.0, 51.0, 51.0, 'C', 5, '2024-2025'),
(5, 4, 12.0, 30.0, 42.0, 42.0, 'D', 5, '2024-2025'),
(5, 5, 15.0, 34.0, 49.0, 49.0, 'D', 5, '2024-2025')
ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks);

INSERT INTO academic_performance 
(student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level, class_rank)
VALUES
(5, 5, '2024-2025', 232.00, 500.00, 46.40, 'D', 58.00, 55.00, 60.00, 54.00, -7.60, 51.04, 'At Risk', 'RED', 5)
ON DUPLICATE KEY UPDATE overall_percentage=VALUES(overall_percentage);

-- ==========================================================
-- SEED DATA: Pre-Awarded Achievements
-- ==========================================================
DELETE FROM achievements WHERE student_id IN (1,2,3,4);

INSERT INTO achievements (student_id, title, badge_icon, badge_color, description, awarded_date) VALUES
(2, 'Class Topper', 'fa-trophy', 'warning', 'Rank #1 in TY B.Sc. IT (AI/ML) with 93.60% aggregate', '2024-11-20'),
(2, 'Academic Excellence', 'fa-star', 'warning', 'Maintained 90%+ aggregate across all curriculum units', '2024-11-20'),
(2, 'Subject Topper (Deep Learning)', 'fa-medal', 'primary', 'Highest score of 93% in AIML502 Deep Learning', '2024-11-20'),
(1, 'Best Attendance', 'fa-calendar-check', 'success', 'Demonstrated exceptional institutional attendance rate of 94.5%', '2024-11-20'),
(1, 'Consistent Performer', 'fa-award', 'info', 'Maintained uniform Grade A performance across all modules', '2024-11-20'),
(3, 'Most Improved Student', 'fa-chart-line', 'success', 'Highest academic jump of +14.0 percentage points compared to Semester 4', '2024-11-20'),
(4, 'Subject Topper (NLP)', 'fa-medal', 'primary', 'Highest university score of 98% in AIML503 Natural Language Processing', '2024-11-20');
