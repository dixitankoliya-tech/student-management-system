ALTER TABLE subjects 
ADD COLUMN academic_year VARCHAR(20) DEFAULT NULL, 
ADD COLUMN subject_type ENUM('Theory', 'Practical') DEFAULT 'Theory', 
ADD COLUMN department VARCHAR(100) DEFAULT NULL, 
ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active';
