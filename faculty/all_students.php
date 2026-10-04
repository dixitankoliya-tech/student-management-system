<?php
/**
 * SMS 2.0: Dean - All Students
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

if (!isDean($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;"><h1>403 Access Denied</h1></div>');
}

$students = $pdo->query("SELECT * FROM students ORDER BY course, semester, enrollment_no")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Department Students</h2>
        <p class="text-muted mb-0">All students across all department courses</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Enrollment No</th><th>Name</th><th>Course</th><th>Semester</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $stu): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($stu['enrollment_no']) ?></td>
                        <td><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
                        <td><?= htmlspecialchars($stu['course']) ?></td>
                        <td><?= htmlspecialchars($stu['semester']) ?></td>
                        <td>
                            <a href="student_detail.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
