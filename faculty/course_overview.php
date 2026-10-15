<?php
/**
 * SMS 2.0: Coordinator - Course Overview
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

if (!isCoordinator($pdo, $faculty_id) && !isDean($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;"><h1>403 Access Denied</h1></div>');
}

$role_info = getFacultyRoleInfo($pdo, $faculty_id);
if (isDean($pdo, $faculty_id)) {
    $firstCourse = $pdo->query("SELECT id, course_name FROM courses WHERE status = 'Active' ORDER BY course_name LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $course_id = $firstCourse['id'];
    $course_name = $firstCourse['course_name'];
} else {
    if (empty($role_info['coordinated_courses'])) {
        die("No course assigned.");
    }
    $course_id = $role_info['coordinated_courses'][0];
    $course_name = $role_info['coordinated_course_names'][0];
}

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Course Overview: <?= htmlspecialchars($course_name) ?></h2>
        <p class="text-muted mb-0">Manage course parameters and academic structure</p>
    </div>
</header>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Course Details</h5>
                <p><strong>Code:</strong> <?= htmlspecialchars($course['course_code']) ?></p>
                <p><strong>Name:</strong> <?= htmlspecialchars($course['course_name']) ?></p>
                <p><strong>Total Semesters:</strong> <?= htmlspecialchars($course['total_semesters']) ?></p>
                <p><strong>Department:</strong> FCAIT</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
