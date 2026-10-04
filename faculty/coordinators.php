<?php
/**
 * SMS 2.0: Dean - Coordinators
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

$coordinators = getAllCoordinators($pdo);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Course Coordinators</h2>
        <p class="text-muted mb-0">List of all active course coordinators in the department</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Course</th><th>Coordinator</th><th>Email</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($coordinators as $coord): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($coord['course_name']) ?></td>
                        <td><?= htmlspecialchars($coord['first_name'] . ' ' . $coord['last_name']) ?></td>
                        <td><?= htmlspecialchars($coord['email']) ?></td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($coord['email']) ?>" class="btn btn-sm btn-outline-primary">Contact</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
