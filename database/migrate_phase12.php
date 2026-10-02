<?php
/**
 * Phase 12: Database Migration & Backfill
 */
require_once __DIR__ . '/../config/database.php';

echo "Starting Phase 12 Database Migration...\n";

try {
    // 1. Semester Master Table
    echo "Creating semesters_master table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS semesters_master (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semester_number INT NOT NULL UNIQUE,
        description VARCHAR(50) NOT NULL,
        status ENUM('Active','Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed semesters 1 to 8
    $checkSem = $pdo->query("SELECT COUNT(*) FROM semesters_master")->fetchColumn();
    if ($checkSem == 0) {
        $stmt = $pdo->prepare("INSERT INTO semesters_master (semester_number, description) VALUES (?, ?)");
        for ($i=1; $i<=8; $i++) {
            $stmt->execute([$i, "Semester $i"]);
        }
        echo "Seeded 8 semesters.\n";
    }

    // 2. Department Link in Courses (Programs)
    echo "Adding department_id to courses...\n";
    $colExists = $pdo->query("SHOW COLUMNS FROM courses LIKE 'department_id'")->fetch();
    if (!$colExists) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN department_id INT NULL AFTER description");
        $pdo->exec("ALTER TABLE courses ADD CONSTRAINT fk_courses_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
    }

    // 3. Student Academic Enrollments (Lifecycle)
    echo "Creating student_academic_enrollments...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_academic_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course VARCHAR(50) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        semester INT NOT NULL,
        division VARCHAR(10) NOT NULL,
        status ENUM('Active','Promoted','Completed','On Hold','Withdrawn','Alumni') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_student_term (student_id, academic_year, semester)
    )");

    // 4. Student Subject Enrollments (Mapping to offerings)
    // We treat 'faculty_subjects' as the subject_offering.
    echo "Creating student_subject_enrollments...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_subject_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        faculty_subject_id INT NOT NULL,
        status ENUM('Enrolled','Completed','Backlog','Dropped') DEFAULT 'Enrolled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_student_offering (student_id, faculty_subject_id)
    )");

    // 5. Backfill Data!
    echo "Backfilling existing active students into academic enrollments...\n";
    $backfillQuery = "
        INSERT IGNORE INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status)
        SELECT id, course, '2024-25', semester, division, 'Active' FROM students WHERE 1
    ";
    // We assume '2024-25' as current active academic year for the backfill since admission_year is mostly 2024.
    $pdo->exec($backfillQuery);

    echo "Backfilling existing student subject enrollments...\n";
    // For each student, find all faculty_subjects matching their course, semester, and division for the current year.
    // Given the 10,500 scale, doing this in pure SQL is fastest.
    $backfillSubjects = "
        INSERT IGNORE INTO student_subject_enrollments (student_id, faculty_subject_id, status)
        SELECT s.id, fs.id, 'Enrolled'
        FROM students s
        JOIN faculty_subjects fs ON s.course = fs.course AND s.semester = fs.semester AND s.division = fs.division
    ";
    $pdo->exec($backfillSubjects);

    // 6. Update student marks to enforce subject enrollment link
    // To prevent over-engineering, we will just leave marks pointing to subject_id and student_id, which is completely valid as long as student_subject_enrollments proves they took it.

    echo "Migration Complete! Database architecture is now Phase 12 ready.\n";

} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}

