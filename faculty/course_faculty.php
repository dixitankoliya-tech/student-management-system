<?php
/**
 * SMS 2.0: Coordinator - Course Faculty
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

if (!isCoordinator($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;"><h1>403 Access Denied</h1></div>');
}

$role_info = getFacultyRoleInfo($pdo, $faculty_id);
if (empty($role_info['coordinated_course_names'])) {
    die("No course assigned.");
}

$course_name = $role_info['coordinated_course_names'][0];

// Fetch all faculty assigned to this course through faculty_subjects
$stmt = $pdo->prepare("
    SELECT f.id, f.faculty_id as fac_code, f.first_name, f.last_name, f.email, f.designation,
           COUNT(DISTINCT fs.subject_id) as assigned_subjects,
           GROUP_CONCAT(DISTINCT s.subject_name SEPARATOR ', ') as subject_names
    FROM faculty f
    JOIN faculty_subjects fs ON f.id = fs.faculty_id
    JOIN subjects s ON fs.subject_id = s.id
    WHERE fs.course = ? AND f.status = 'Active'
    GROUP BY f.id
    ORDER BY f.first_name, f.last_name
");
$stmt->execute([$course_name]);
$faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Course Faculty: <?= htmlspecialchars($course_name) ?></h2>
        <p class="text-muted mb-0">Faculty members assigned to teach this course</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Workload (Subjects)</th>
                        <th>Subjects</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faculties)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No faculty currently assigned to this course.</td></tr>
                    <?php else: ?>
                        <?php foreach ($faculties as $fac): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($fac['fac_code']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($fac['designation'] ?? 'Faculty') ?></td>
                            <td><span class="badge bg-primary rounded-pill"><?= $fac['assigned_subjects'] ?></span></td>
                            <td class="text-muted small" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($fac['subject_names']) ?>">
                                <?= htmlspecialchars($fac['subject_names']) ?>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($fac['email']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-envelope"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
