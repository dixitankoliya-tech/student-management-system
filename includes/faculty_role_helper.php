<?php
/**
 * Faculty Role Helper
 * 
 * Provides functions to determine the authenticated faculty member's
 * hierarchical role (Dean, Course Coordinator, or Faculty) and enforce
 * scope-based access control.
 * 
 * Roles are stored in the faculty.role column AND backed by
 * department_heads / course_coordinators tables for relational integrity.
 * 
 * Usage:
 *   require_once '../includes/faculty_role_helper.php';
 *   $role_info = getFacultyRoleInfo($pdo, $faculty_db_id);
 *   requireCourseAccess($pdo, $faculty_db_id, $course_id);
 */

/**
 * Get the full role info for a faculty member.
 * Returns: ['role' => 'dean'|'coordinator'|'faculty',
 *           'faculty' => [...row...],
 *           'department_head' => [...row...] or null,
 *           'coordinated_courses' => [...ids...],
 *           'coordinated_course_names' => [...names...] ]
 */
function getFacultyRoleInfo($pdo, $faculty_id) {
    $info = [
        'role' => 'faculty',
        'faculty' => null,
        'department_head' => null,
        'coordinated_courses' => [],
        'coordinated_course_names' => [],
    ];
    
    // Get faculty record
    $stmt = $pdo->prepare("SELECT * FROM faculty WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$faculty) return $info;
    
    $info['faculty'] = $faculty;
    $info['role'] = $faculty['role'] ?? 'faculty';
    
    // Check if Dean/HOD
    if ($info['role'] === 'dean') {
        $dh = $pdo->prepare("SELECT dh.*, d.department_name, d.department_code FROM department_heads dh JOIN departments d ON dh.department_id = d.id WHERE dh.faculty_id = ? AND dh.status = 'Active'");
        $dh->execute([$faculty_id]);
        $info['department_head'] = $dh->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check if Coordinator
    if ($info['role'] === 'coordinator') {
        $cc = $pdo->prepare("SELECT cc.course_id, c.course_name FROM course_coordinators cc JOIN courses c ON cc.course_id = c.id WHERE cc.faculty_id = ? AND cc.status = 'Active'");
        $cc->execute([$faculty_id]);
        $rows = $cc->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $info['coordinated_courses'][] = $r['course_id'];
            $info['coordinated_course_names'][] = $r['course_name'];
        }
    }
    
    return $info;
}

/**
 * Check if the faculty member is the Dean/HOD.
 */
function isDean($pdo, $faculty_id) {
    $stmt = $pdo->prepare("SELECT role FROM faculty WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $role = $stmt->fetchColumn();
    return $role === 'dean';
}

/**
 * Check if the faculty member is a Course Coordinator.
 */
function isCoordinator($pdo, $faculty_id) {
    $stmt = $pdo->prepare("SELECT role FROM faculty WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $role = $stmt->fetchColumn();
    return $role === 'coordinator';
}

/**
 * Check if faculty has access to a specific course.
 * Dean: access to ALL courses in their department.
 * Coordinator: access to their assigned course only.
 * Faculty: access to courses they are assigned to via faculty_subjects.
 */
function hasCourseAccess($pdo, $faculty_id, $course_id) {
    $role_info = getFacultyRoleInfo($pdo, $faculty_id);
    
    // Dean has access to all courses
    if ($role_info['role'] === 'dean') {
        return true;
    }
    
    // Coordinator has access to their assigned courses
    if ($role_info['role'] === 'coordinator') {
        if (in_array($course_id, $role_info['coordinated_courses'])) {
            return true;
        }
    }
    
    // Faculty has access to courses they teach in
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ? AND course = (SELECT course_name FROM courses WHERE id = ?)");
    $stmt->execute([$faculty_id, $course_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Require course access. Denies with 403 if unauthorized.
 */
function requireCourseAccess($pdo, $faculty_id, $course_id) {
    if (!hasCourseAccess($pdo, $faculty_id, $course_id)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:60px;font-family:sans-serif;"><h1>403 — Access Denied</h1><p>You do not have permission to access this course.</p><a href="dashboard.php">Return to Dashboard</a></div>');
    }
}

/**
 * Get the list of course IDs that the faculty member can access.
 * Dean: all courses. Coordinator: assigned course(s). Faculty: taught courses.
 */
function getAccessibleCourseIds($pdo, $faculty_id) {
    $role_info = getFacultyRoleInfo($pdo, $faculty_id);
    
    if ($role_info['role'] === 'dean') {
        return $pdo->query("SELECT id FROM courses WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if ($role_info['role'] === 'coordinator') {
        return $role_info['coordinated_courses'];
    }
    
    // Regular faculty: get courses from their subject assignments
    $stmt = $pdo->prepare("SELECT DISTINCT c.id FROM faculty_subjects fs JOIN courses c ON fs.course = c.course_name WHERE fs.faculty_id = ?");
    $stmt->execute([$faculty_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get the list of student IDs that the faculty member can access.
 * Dean: all students. Coordinator: students in their course. Faculty: students in their subjects.
 */
function getAccessibleStudentIds($pdo, $faculty_id) {
    $role_info = getFacultyRoleInfo($pdo, $faculty_id);
    
    if ($role_info['role'] === 'dean') {
        return $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    $course_ids = getAccessibleCourseIds($pdo, $faculty_id);
    if (empty($course_ids)) return [];
    
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $course_names_stmt = $pdo->prepare("SELECT course_name FROM courses WHERE id IN ($placeholders)");
    $course_names_stmt->execute($course_ids);
    $course_names = $course_names_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($course_names)) return [];
    $placeholders2 = implode(',', array_fill(0, count($course_names), '?'));
    $stmt = $pdo->prepare("SELECT id FROM students WHERE course IN ($placeholders2)");
    $stmt->execute($course_names);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get all coordinators with their course info.
 */
function getAllCoordinators($pdo) {
    return $pdo->query("
        SELECT cc.*, f.first_name, f.last_name, f.faculty_id AS fac_code, f.email, 
               c.course_name, c.course_code, c.id AS crs_id
        FROM course_coordinators cc 
        JOIN faculty f ON cc.faculty_id = f.id 
        JOIN courses c ON cc.course_id = c.id 
        WHERE cc.status = 'Active'
        ORDER BY c.course_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get the current Dean/HOD.
 */
function getCurrentDean($pdo) {
    $stmt = $pdo->query("
        SELECT dh.*, f.first_name, f.last_name, f.faculty_id AS fac_code, f.email, f.designation,
               d.department_name, d.department_code
        FROM department_heads dh 
        JOIN faculty f ON dh.faculty_id = f.id 
        JOIN departments d ON dh.department_id = d.id 
        WHERE dh.status = 'Active'
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
