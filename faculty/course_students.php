<?php
/**
 * SMS 2.0: Coordinator - Course Students
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

// Filters
$search = $_GET['search'] ?? '';
$semester = $_GET['semester'] ?? '';
$status = $_GET['status'] ?? 'Active';

$query = "SELECT * FROM students WHERE course = ?";
$params = [$course_name];

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($semester) {
    $query .= " AND semester = ?";
    $params[] = $semester;
}

if ($search) {
    $query .= " AND (enrollment_no LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY semester ASC, enrollment_no ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Course Students: <?= htmlspecialchars($course_name) ?></h2>
        <p class="text-muted mb-0">Manage and view students enrolled in this course</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or Enrollment No" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Semester</label>
                <select name="semester" class="form-select">
                    <option value="">All Semesters</option>
                    <?php for($i=1; $i<=10; $i++): ?>
                        <option value="<?= $i ?>" <?= $semester == $i ? 'selected' : '' ?>>Sem <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Active" <?= $status == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Alumni" <?= $status == 'Alumni' ? 'selected' : '' ?>>Alumni</option>
                    <option value="Suspended" <?= $status == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="course_students.php" class="btn btn-light w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Enrollment No</th>
                        <th>Name</th>
                        <th>Semester</th>
                        <th>CGPA</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No students found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $stu): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($stu['enrollment_no']) ?></td>
                            <td><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
                            <td><span class="badge bg-secondary rounded-pill">Sem <?= htmlspecialchars($stu['semester']) ?></span></td>
                            <td>
                                <?php if ($stu['cgpa'] > 0): ?>
                                    <span class="badge bg-<?= $stu['cgpa'] >= 8 ? 'success' : ($stu['cgpa'] >= 6 ? 'info' : 'warning') ?> text-<?= $stu['cgpa'] >= 6 ? 'white' : 'dark' ?>">
                                        <?= number_format((float)$stu['cgpa'], 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $stu['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($stu['status']) ?></span>
                            </td>
                            <td>
                                <a href="student_detail.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>
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
