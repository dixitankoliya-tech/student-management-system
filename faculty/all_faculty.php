<?php
/**
 * SMS 2.0: Dean - All Faculty
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

$faculties = $pdo->query("SELECT * FROM faculty WHERE status = 'Active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Department Faculty</h2>
        <p class="text-muted mb-0">All faculty members in the department</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Designation</th><th>Role</th><th>Email</th></tr></thead>
                <tbody>
                    <?php foreach ($faculties as $fac): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($fac['faculty_id']) ?></td>
                        <td><?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?></td>
                        <td><?= htmlspecialchars($fac['designation'] ?? 'Faculty') ?></td>
                        <td>
                            <?php if ($fac['role'] === 'dean'): ?>
                                <span class="badge bg-primary">Dean/HOD</span>
                            <?php elseif ($fac['role'] === 'coordinator'): ?>
                                <span class="badge bg-warning text-dark">Coordinator</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Faculty</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($fac['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
