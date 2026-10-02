<?php
/**
 * SMS 2.0: Official Report Cards Directory & Student Selector
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
require_once '../includes/academic_helper.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_semester = isset($_GET['semester']) && $_GET['semester'] !== '' ? (int)$_GET['semester'] : 0;

// Fetch students with academic performance summary
$query = "
    SELECT s.id, s.enrollment_no, s.first_name, s.last_name, s.course, s.specialization, s.semester, s.division,
           ap.overall_percentage, ap.performance_score, ap.performance_category, ap.alert_level, ap.class_rank
    FROM students s
    LEFT JOIN academic_performance ap ON (s.id = ap.student_id AND ap.semester = s.semester)
";

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(s.enrollment_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($filter_semester > 0) {
    $where[] = "s.semester = ?";
    $params[] = $filter_semester;
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY s.semester ASC, s.enrollment_no ASC";

$students = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Report Cards Directory Error: " . $e->getMessage());
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div>
        <h1 class="h3 text-gray-800 fw-bold mb-1">Academic Report Cards</h1>
        <p class="text-muted small mb-0">Select any student to generate and print their official university grade sheet and academic dossier.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="topper_board.php" class="btn btn-outline-warning rounded-pill shadow-sm"><i class="fas fa-trophy me-1"></i> Topper Board</a>
        <a href="students.php" class="btn btn-outline-primary rounded-pill shadow-sm"><i class="fas fa-users me-1"></i> Students Directory</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded p-3">
        <form method="GET" action="report_cards.php" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by student name or enrollment no..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="semester" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Semesters</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($filter_semester == $i) ? 'selected' : ''; ?>>Semester <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Students Report Cards Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice me-2"></i>Available Student Report Cards (<?php echo count($students); ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Enrollment No</th>
                        <th>Semester & Div</th>
                        <th>Overall %</th>
                        <th>Category</th>
                        <th>Alert Status</th>
                        <th>Class Rank</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initials bg-primary text-white me-3" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                            <?php echo substr($st['first_name'], 0, 1) . substr($st['last_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($st['course'] . ' (' . $st['specialization'] . ')'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($st['enrollment_no']); ?></span></td>
                                <td>
                                    <span class="badge bg-info text-dark">Sem <?php echo htmlspecialchars($st['semester']); ?></span>
                                    <span class="badge bg-secondary">Div <?php echo htmlspecialchars($st['division']); ?></span>
                                </td>
                                <td>
                                    <?php if ($st['overall_percentage'] !== null): ?>
                                        <span class="fw-bold fs-6 <?php echo $st['overall_percentage'] >= 75 ? 'text-success' : ($st['overall_percentage'] < 50 ? 'text-danger' : 'text-primary'); ?>">
                                            <?php echo number_format($st['overall_percentage'], 2); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not graded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($st['performance_category']) ? getCategoryBadge($st['performance_category']) : '<span class="badge bg-light text-muted border">Pending</span>'; ?>
                                </td>
                                <td>
                                    <?php echo !empty($st['alert_level']) ? getAlertBadge($st['alert_level']) : '<span class="badge bg-light text-muted border">None</span>'; ?>
                                </td>
                                <td>
                                    <?php echo !empty($st['class_rank']) ? '<span class="fw-bold text-primary">#' . $st['class_rank'] . '</span>' : '<span class="text-muted small">—</span>'; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="report_card.php?student_id=<?php echo $st['id']; ?>&semester=<?php echo $st['semester']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 me-1" target="_blank">
                                            <i class="fas fa-file-invoice me-1"></i> Report Card
                                        </a>
                                        <a href="certificate.php?student_id=<?php echo $st['id']; ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3" target="_blank">
                                            <i class="fas fa-certificate me-1"></i> Certificate
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-folder-open fs-3"></i></div>
                                <h6 class="fw-bold text-muted">No student records found matching criteria</h6>
                                <p class="text-muted small mb-0">Try clearing filters or adding student records.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
