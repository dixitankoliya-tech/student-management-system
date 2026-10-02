CREATE TABLE marks (
  id int(11) NOT NULL AUTO_INCREMENT,
  exam_id int(11) NOT NULL,
  student_id int(11) NOT NULL,
  subject_id int(11) NOT NULL,
  faculty_id int(11) NOT NULL,
  marks_obtained decimal(5,2) NOT NULL DEFAULT 0.00,
  max_marks decimal(5,2) NOT NULL DEFAULT 0.00,
  status enum('Draft','Submitted','Locked') DEFAULT 'Draft',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY unique_mark (exam_id, student_id, subject_id),
  FOREIGN KEY (exam_id) REFERENCES examinations(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
