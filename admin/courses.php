<?php
/**
 * SMS 2.0: Admin Course Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_course'])) {
        $course_code = trim($_POST['course_code'] ?? '');
        $course_name = trim($_POST['course_name'] ?? '');
        
        if (empty($course_code) || empty($course_name)) {
            $error = 'Course code and name are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (?, ?)");
                $stmt->execute([$course_code, $course_name]);
                logActivity($pdo, 'ADD_COURSE', null, "Added course: $course_name");
                $success = 'Course added successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'Course code or name already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $course_id = (int)$_POST['course_id'];
        $new_status = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
        try {
            $stmt = $pdo->prepare("UPDATE courses SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $course_id]);
            logActivity($pdo, 'UPDATE_COURSE', null, "Toggled course $course_id status to $new_status");
            $success = 'Course status updated.';
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    }
}

// Fetch courses and calculate stats
try {
    $stmt = $pdo->query("
        SELECT c.*, 
        (SELECT COUNT(*) FROM students s WHERE s.course = c.course_name) as student_count,
        (SELECT COUNT(*) FROM subjects sub WHERE sub.course = c.course_name) as subject_count,
        (SELECT COUNT(*) FROM faculty_subjects fs WHERE fs.course = c.course_name) as faculty_assignments
        FROM courses c 
        ORDER BY c.course_name ASC
    ");
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching courses.';
    $courses = [];
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
        <h1 class="h3 text-gray-800 fw-bold mb-1">Course Management</h1>
        <p class="text-muted small mb-0">Manage Course Management in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal" shadow-sm rounded-pill px-3">
                <i class="fas fa-plus me-2"></i> Add Course
            </button>
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Subjects</th>
                                <th class="text-center">Faculty Assigned</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No courses found</h5><p>There are currently no records available in this section.</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($course['course_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                                        <td class="text-center"><span class="badge bg-info text-dark rounded-pill"><?= $course['student_count'] ?></span></td>
                                        <td class="text-center"><span class="badge bg-secondary rounded-pill"><?= $course['subject_count'] ?></span></td>
                                        <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $course['faculty_assignments'] ?></span></td>
                                        <td>
                                            <?php if ($course['status'] === 'Active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $course['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= $course['status'] === 'Active' ? 'warning' : 'success' ?>" title="Toggle Status">
                                                    <i class="fas fa-<?= $course['status'] === 'Active' ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-primary" title="Edit Course"><i class="fas fa-edit"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Course Code</label>
                        <input type="text" name="course_code" class="form-control" required placeholder="e.g. 0161901">
                        <small class="text-muted">Used for generating student enrollment numbers.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Course Name</label>
                        <input type="text" name="course_name" class="form-control" required placeholder="e.g. BSc IT">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_course" class="btn btn-primary px-4">Save Course</button>
                </div>
            </form>
        </div>
    <?php include '../includes/footer.php'; ?>



