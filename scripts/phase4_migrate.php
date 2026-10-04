<?php
/**
 * Phase 4: University Organizational Hierarchy — Database Migration
 * 
 * Creates:
 *   1. department_heads  — tracks Dean/HOD assignments (one active per department)
 *   2. course_coordinators — tracks Course Coordinator assignments (one active per course)
 *   3. faculty_roles — additional role metadata for faculty
 * 
 * Alters:
 *   - users.role enum expanded to include 'dean' and 'coordinator'
 *   - faculty table gets a 'role' column
 */

require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = [];

// ========================================================================
// 1. Add 'role' column to faculty table
// ========================================================================
try {
    $pdo->exec("ALTER TABLE faculty ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'faculty'");
    $success[] = "Added 'role' column to faculty table";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "faculty.role column already exists";
    } else {
        $errors[] = "faculty.role: " . $e->getMessage();
    }
}

// ========================================================================
// 2. Create department_heads table
// ========================================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_heads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            faculty_id INT NOT NULL,
            assigned_date DATE NOT NULL DEFAULT (CURRENT_DATE),
            end_date DATE NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            assigned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_dept_status (department_id, status),
            INDEX idx_faculty (faculty_id)
        )
    ");
    $success[] = "Created department_heads table";
} catch (PDOException $e) {
    $errors[] = "department_heads: " . $e->getMessage();
}

// ========================================================================
// 3. Create course_coordinators table
// ========================================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_coordinators (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            faculty_id INT NOT NULL,
            assigned_date DATE NOT NULL DEFAULT (CURRENT_DATE),
            end_date DATE NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            assigned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course_status (course_id, status),
            INDEX idx_faculty (faculty_id)
        )
    ");
    $success[] = "Created course_coordinators table";
} catch (PDOException $e) {
    $errors[] = "course_coordinators: " . $e->getMessage();
}

// ========================================================================
// 4. Update the existing HOD faculty member (ID=1) to have role='dean'
// ========================================================================
try {
    $stmt = $pdo->prepare("UPDATE faculty SET role = 'dean' WHERE id = 1 AND designation = 'HOD'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $success[] = "Updated faculty ID=1 (HOD) to role='dean'";
    } else {
        $success[] = "Faculty ID=1 already updated or not HOD";
    }
} catch (PDOException $e) {
    $errors[] = "Update HOD role: " . $e->getMessage();
}

// Insert into department_heads
try {
    $exists = $pdo->query("SELECT COUNT(*) FROM department_heads WHERE department_id = 1 AND faculty_id = 1 AND status = 'Active'")->fetchColumn();
    if ($exists == 0) {
        $pdo->exec("INSERT INTO department_heads (department_id, faculty_id, status) VALUES (1, 1, 'Active')");
        $success[] = "Inserted Dean assignment: Faculty 1 -> Department 1";
    } else {
        $success[] = "Dean assignment already exists";
    }
} catch (PDOException $e) {
    $errors[] = "Insert dean: " . $e->getMessage();
}

// ========================================================================
// 5. Assign Course Coordinators (Faculty IDs 2-6, one per course)
// ========================================================================
$coordinator_map = [
    1 => 2,  // Course 1 (B.Sc. IT Dual) -> Faculty 2
    2 => 3,  // Course 2 (BCA Dual) -> Faculty 3
    3 => 4,  // Course 3 (BCA) -> Faculty 4
    4 => 5,  // Course 4 (I.M.Sc. IT) -> Faculty 5
    5 => 6,  // Course 5 (M.Sc. IT) -> Faculty 6
];

foreach ($coordinator_map as $course_id => $faculty_id) {
    try {
        // Update faculty role
        $stmt = $pdo->prepare("UPDATE faculty SET role = 'coordinator' WHERE id = ?");
        $stmt->execute([$faculty_id]);
        
        // Insert coordinator assignment
        $exists = $pdo->query("SELECT COUNT(*) FROM course_coordinators WHERE course_id = $course_id AND faculty_id = $faculty_id AND status = 'Active'")->fetchColumn();
        if ($exists == 0) {
            $stmt2 = $pdo->prepare("INSERT INTO course_coordinators (course_id, faculty_id, status) VALUES (?, ?, 'Active')");
            $stmt2->execute([$course_id, $faculty_id]);
            $success[] = "Assigned Faculty $faculty_id as Coordinator for Course $course_id";
        } else {
            $success[] = "Coordinator for Course $course_id already assigned";
        }
    } catch (PDOException $e) {
        $errors[] = "Coordinator $course_id: " . $e->getMessage();
    }
}

// ========================================================================
// 6. Expand users.role enum to include 'dean' and 'coordinator'
// ========================================================================
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','faculty','student','dean','coordinator') NOT NULL DEFAULT 'student'");
    $success[] = "Expanded users.role enum to include dean/coordinator";
} catch (PDOException $e) {
    $errors[] = "Expand users.role: " . $e->getMessage();
}

// Update user records for the dean and coordinators
try {
    // Dean (users record linked to faculty_id=1)
    $pdo->exec("UPDATE users SET role = 'faculty' WHERE faculty_id = 1");
    $success[] = "Dean user record stays as 'faculty' role (uses faculty portal with elevated permissions)";
    
    // Coordinators also stay as faculty role - they use the faculty portal
    foreach ([2,3,4,5,6] as $fid) {
        $pdo->exec("UPDATE users SET role = 'faculty' WHERE faculty_id = $fid");
    }
    $success[] = "Coordinator user records stay as 'faculty' role (faculty portal with elevated permissions)";
} catch (PDOException $e) {
    $errors[] = "Update user roles: " . $e->getMessage();
}

// ========================================================================
// REPORT
// ========================================================================
echo "=== PHASE 4 DATABASE MIGRATION ===\n\n";
echo "SUCCESSES:\n";
foreach ($success as $s) echo "  ✓ $s\n";
if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $e) echo "  ✗ $e\n";
}

// Verify final state
echo "\n=== VERIFICATION ===\n";
echo "Faculty roles:\n";
$roles = $pdo->query("SELECT role, COUNT(*) as cnt FROM faculty GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $r) echo "  {$r['role']}: {$r['cnt']}\n";

echo "\nDepartment Heads:\n";
$heads = $pdo->query("SELECT dh.*, f.first_name, f.last_name, d.department_name FROM department_heads dh JOIN faculty f ON dh.faculty_id = f.id JOIN departments d ON dh.department_id = d.id WHERE dh.status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($heads as $h) echo "  {$h['department_name']}: {$h['first_name']} {$h['last_name']} (Faculty ID {$h['faculty_id']})\n";

echo "\nCourse Coordinators:\n";
$coords = $pdo->query("SELECT cc.*, f.first_name, f.last_name, c.course_name FROM course_coordinators cc JOIN faculty f ON cc.faculty_id = f.id JOIN courses c ON cc.course_id = c.id WHERE cc.status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($coords as $c) echo "  {$c['course_name']}: {$c['first_name']} {$c['last_name']} (Faculty ID {$c['faculty_id']})\n";

echo "\nUser role distribution:\n";
$uroles = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
foreach ($uroles as $r) echo "  {$r['role']}: {$r['cnt']}\n";

echo "\nTotal Faculty: " . $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn() . "\n";
echo "Total Students: " . $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() . "\n";
