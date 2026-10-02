<?php
/**
 * SMS 2.0: Global Search API
 * Handles fast, scalable searching across students, faculty, subjects, and programs.
 */
require_once '../../config/database.php';
require_once '../../includes/admin_auth.php';

// Must be JSON response
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !hasRole('admin')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$term = "%$query%";
$results = [];
$max_per_category = 5;

try {
    // 1. Search Students
    $stmt = $pdo->prepare("SELECT id, enrollment_no, first_name, last_name, course, semester FROM students WHERE enrollment_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT ?");
    $stmt->bindValue(1, $term);
    $stmt->bindValue(2, $term);
    $stmt->bindValue(3, $term);
    $stmt->bindValue(4, $max_per_category, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $results[] = [
            'type' => 'Student',
            'title' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars($row['enrollment_no'] . ' | ' . $row['course'] . ' Sem ' . $row['semester'], ENT_QUOTES, 'UTF-8'),
            'url' => 'view_student.php?id=' . (int)$row['id'],
            'icon' => 'fa-user-graduate'
        ];
    }

    // 2. Search Faculty
    $stmt = $pdo->prepare("SELECT id, faculty_id, first_name, last_name, department FROM faculty WHERE faculty_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT ?");
    $stmt->bindValue(1, $term);
    $stmt->bindValue(2, $term);
    $stmt->bindValue(3, $term);
    $stmt->bindValue(4, $max_per_category, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $results[] = [
            'type' => 'Faculty',
            'title' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars($row['faculty_id'] . ' | Dept: ' . $row['department'], ENT_QUOTES, 'UTF-8'),
            'url' => 'faculty.php?search=' . urlencode($row['faculty_id']),
            'icon' => 'fa-chalkboard-teacher'
        ];
    }

    // 3. Search Subjects
    $stmt = $pdo->prepare("SELECT id, subject_code, subject_name, course FROM subjects WHERE subject_code LIKE ? OR subject_name LIKE ? LIMIT ?");
    $stmt->bindValue(1, $term);
    $stmt->bindValue(2, $term);
    $stmt->bindValue(3, $max_per_category, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $results[] = [
            'type' => 'Subject',
            'title' => htmlspecialchars($row['subject_name'], ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars($row['subject_code'] . ' | ' . $row['course'], ENT_QUOTES, 'UTF-8'),
            'url' => 'subjects.php?search=' . urlencode($row['subject_code']),
            'icon' => 'fa-book'
        ];
    }
    
    // 4. Search Programs / Departments
    $stmt = $pdo->prepare("SELECT id, course_name, degree_type FROM courses WHERE course_code LIKE ? OR course_name LIKE ? LIMIT ?");
    $stmt->bindValue(1, $term);
    $stmt->bindValue(2, $term);
    $stmt->bindValue(3, $max_per_category, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $results[] = [
            'type' => 'Program',
            'title' => htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars('Degree: ' . $row['degree_type'], ENT_QUOTES, 'UTF-8'),
            'url' => 'courses.php',
            'icon' => 'fa-graduation-cap'
        ];
    }

    echo json_encode(['results' => $results]);

} catch (PDOException $e) {
    // Log the error securely on the server
    error_log("Global Search PDOException: " . $e->getMessage());
    // Return a safe generic response
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => 'An internal server error occurred while searching.']);
}
