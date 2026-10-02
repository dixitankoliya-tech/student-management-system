<?php
/**
 * SMS 2.0: Admin Subjects Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_subject'])) {
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_name = trim($_POST['subject_name'] ?? '');
        $course = $_POST['course'] ?? '';
        $academic_year = $_POST['academic_year'] ?? '';
        $semester = (int)($_POST['semester'] ?? 1);
        $credits = (int)($_POST['credits'] ?? 4);
        $subject_type = $_POST['subject_type'] ?? 'Theory';
        $department = $_POST['department'] ?? '';
        
        if (empty($subject_code) || empty($subject_name) || empty($course) || empty($academic_year) || empty($department)) {
            $error = 'All fields are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, course, academic_year, semester, credits, subject_type, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subject_code, $subject_name, $course, $academic_year, $semester, $credits, $subject_type, $department]);
                logActivity($pdo, 'ADD_SUBJECT', null, "Added subject: $subject_name ($subject_code)");
                $success = 'Subject added successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'Subject code already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $sub_id = (int)$_POST['subject_id'];
        $new_status = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
        try {
            $stmt = $pdo->prepare("UPDATE subjects SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $sub_id]);
            logActivity($pdo, 'UPDATE_SUBJECT', null, "Toggled subject $sub_id status to $new_status");
            $success = 'Status updated.';
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    }
}

// Fetch lookups
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query("SELECT year_name FROM academic_years WHERE status='Active' ORDER BY starting_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$departments = $pdo->query("SELECT department_name FROM departments WHERE status='Active' ORDER BY department_name")->fetchAll(PDO::FETCH_COLUMN);

// Fetch subjects
try {
    $stmt = $pdo->query("
        SELECT s.*, 
        (SELECT GROUP_CONCAT(CONCAT(f.first_name, ' ', f.last_name) SEPARATOR ', ') 
         FROM faculty_subjects fs 
         JOIN faculty f ON fs.faculty_id = f.id 
         WHERE fs.subject_id = s.id) as faculty_names
        FROM subjects s 
        ORDER BY s.course, s.semester, s.subject_name ASC
    ");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
}
?>
<?php
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<div id="page-content-wrapper" class="w-100 bg-light">
<?php include '../includes/admin_navbar.php'; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Subjects</h1>
        <p class="text-muted small mb-0">Manage Subjects in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Add Subject
            </button>
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Course/Sem</th>
                                <th>Dept</th>
                                <th>Type/Cr</th>
                                <th>Faculty</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sub['subject_code']) ?></strong></td>
                                <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                                <td><?= htmlspecialchars($sub['course']) ?> / Sem <?= $sub['semester'] ?> (<?= htmlspecialchars($sub['academic_year'] ?? '') ?>)</td>
                                <td><?= htmlspecialchars($sub['department'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sub['subject_type'] ?? 'Theory') ?> / <?= $sub['credits'] ?> Cr</td>
                                <td>
                                    <?php if ($sub['faculty_names']): ?>
                                        <small class="text-primary"><?= htmlspecialchars($sub['faculty_names']) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($sub['status'] ?? 'Active') === 'Active'): ?>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= ($sub['status'] ?? 'Active') === 'Active' ? 'Inactive' : 'Active' ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= ($sub['status'] ?? 'Active') === 'Active' ? 'warning' : 'success' ?>">
                                            <i class="fas fa-<?= ($sub['status'] ?? 'Active') === 'Active' ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Subject Code</label>
                            <input type="text" name="subject_code" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-muted fw-semibold">Subject Name</label>
                            <input type="text" name="subject_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Course</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Academic Year</label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>"><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Semester</label>
                            <input type="number" name="semester" class="form-control" required min="1" max="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Department</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Type</label>
                            <select name="subject_type" class="form-select" required>
                                <option value="Theory">Theory</option>
                                <option value="Practical">Practical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-semibold">Credits</label>
                            <input type="number" name="credits" class="form-control" required min="1" max="10" value="4">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_subject" class="btn btn-primary px-4">Save Subject</button>
                </div>
            </form>
        </div>
    <?php include '../includes/footer.php'; ?>

