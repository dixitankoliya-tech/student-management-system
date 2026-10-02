<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

// Same search and filter logic as students.php to export what they see
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$filter_gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(enrollment_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
}

if (!empty($filter_semester)) {
    $whereConditions[] = "semester = ?";
    $params[] = $filter_semester;
}

if (!empty($filter_gender)) {
    $whereConditions[] = "gender = ?";
    $params[] = $filter_gender;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

try {
    $sql = "SELECT enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year, created_at FROM students $whereClause ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log the export activity
    logActivity($pdo, 'EXPORT', null, "Exported student records to CSV");

    // Output Headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Student_Export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // CSV Column Headers
    fputcsv($output, array('Enrollment No', 'First Name', 'Last Name', 'Gender', 'Date of Birth', 'Email', 'Phone', 'Address', 'City', 'Course', 'Specialization', 'Semester', 'Division', 'Admission Year', 'Registered On'));

    foreach ($students as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();

} catch(PDOException $e) {
    error_log("Export Error: " . $e->getMessage());
    die("An error occurred during export.");
}
?>
