CREATE DATABASE IF NOT EXISTS student_management;
USE student_management;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_no VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    course VARCHAR(50) DEFAULT 'BSc IT',
    specialization VARCHAR(50) DEFAULT 'AI/ML',
    semester INT NOT NULL,
    division VARCHAR(10) NOT NULL,
    admission_year YEAR NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (enrollment_no)
);

-- Insert realistic sample records for BSc IT (AI/ML) at GLS University
INSERT INTO students (enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year) VALUES
('GLS2023001', 'Rahul', 'Sharma', 'Male', '2004-05-15', 'rahul.sharma@example.com', '9876543210', '12, Sunrise Park, Vastrapur', 'Ahmedabad', 'BSc IT', 'AI/ML', 5, 'A', 2023),
('GLS2023002', 'Priya', 'Patel', 'Female', '2005-02-20', 'priya.patel@example.com', '9876543211', '45, Krishna Bungalows, Navrangpura', 'Ahmedabad', 'BSc IT', 'AI/ML', 5, 'A', 2023),
('GLS2023003', 'Rohan', 'Desai', 'Male', '2003-11-10', 'rohan.desai@example.com', '9876543212', '78, Green Avenue, SG Highway', 'Ahmedabad', 'BSc IT', 'AI/ML', 5, 'B', 2023),
('GLS2023004', 'Neha', 'Shah', 'Female', '2004-08-05', 'neha.shah@example.com', '9876543213', 'B-10, Satyam Apartments, Maninagar', 'Ahmedabad', 'BSc IT', 'AI/ML', 5, 'A', 2023),
('GLS2023005', 'Amit', 'Trivedi', 'Male', '2004-01-25', 'amit.trivedi@example.com', '9876543214', 'C-2, Royal Flats, Bodakdev', 'Ahmedabad', 'BSc IT', 'AI/ML', 5, 'B', 2023);
