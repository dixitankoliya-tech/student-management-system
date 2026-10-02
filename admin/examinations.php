<?php
/**
 * SMS 2.0: Admin Examinations Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_exam'])) {
        $exam_name = trim($_POST['exam_name'] ?? '');
        $course = $_POST['course'] ?? '';
        $academic_year = $_POST['academic_year'] ?? '';
        $semester = (int)($_POST['semester'] ?? 1);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        
        if (empty($exam_name) || empty($course) || empty($academic_year)) {
            $error = 'Exam Name, Course, and Academic Year are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO examinations (exam_name, course, academic_year, semester, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$exam_name, $course, $academic_year, $semester, $start_date, $end_date]);
                logActivity($pdo, 'ADD_EXAM', null, "Added exam: $exam_name");
                
                // Notify students
                $notif_title = "New Examination Scheduled";
                $notif_msg = "$exam_name has been scheduled to start on " . date('M j, Y', strtotime($start_date)) . ".";
                $q = $pdo->prepare("SELECT id FROM students WHERE course = ? AND semester = ?");
                $q->execute([$course, $semester]);
                $students = $q->fetchAll();
                foreach($students as $s) {
                    send_notification($pdo, 'student', $s['id'], $notif_title, $notif_msg, 'academic', 'examinations.php');
                }
                $success = 'Examination added successfully.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_status'])) {
        $exam_id = (int)$_POST['exam_id'];
        $new_status = $_POST['status'];
        try {
            $stmt = $pdo->prepare("UPDATE examinations SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $exam_id]);
            logActivity($pdo, 'UPDATE_EXAM_STATUS', null, "Updated exam $exam_id status to $new_status");
            $success = 'Status updated.';
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    }
}

// Fetch lookups
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query("SELECT year_name FROM academic_years WHERE status='Active' ORDER BY starting_year DESC")->fetchAll(PDO::FETCH_COLUMN);

try {
    $stmt = $pdo->query("SELECT * FROM examinations ORDER BY start_date DESC, id DESC");
    $exams = $stmt->fetchAll();
} catch (PDOException $e) {
    $exams = [];
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
        <h1 class="h3 text-gray-800 fw-bold mb-1">Examinations</h1>
        <p class="text-muted small mb-0">Manage Examinations in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Create Exam
            </button>
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Exam Name</th>
                            <th>Course / Sem</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $ex): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ex['exam_name']) ?></strong></td>
                            <td><?= htmlspecialchars($ex['course']) ?> / Sem <?= $ex['semester'] ?> (<?= htmlspecialchars($ex['academic_year']) ?>)</td>
                            <td>
                                <?= $ex['start_date'] ? date('d M Y', strtotime($ex['start_date'])) : 'TBD' ?> 
                                - 
                                <?= $ex['end_date'] ? date('d M Y', strtotime($ex['end_date'])) : 'TBD' ?>
                            </td>
                            <td>
                                <?php
                                $badgeColor = [
                                    'Upcoming' => 'secondary',
                                    'Ongoing' => 'primary',
                                    'Completed' => 'warning',
                                    'Results Published' => 'success'
                                ][$ex['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badgeColor ?>"><?= $ex['status'] ?></span>
                            </td>
                            <td>
                                <form method="POST" class="d-flex align-items-center">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                    <select name="status" class="form-select form-select-sm me-2" style="width:140px;">
                                        <option value="Upcoming" <?= $ex['status'] == 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                        <option value="Ongoing" <?= $ex['status'] == 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                        <option value="Completed" <?= $ex['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Results Published" <?= $ex['status'] == 'Results Published' ? 'selected' : '' ?>>Results Published</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-outline-primary"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Create Examination</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Exam Name (e.g. Mid-Term, Final)</label>
                        <input type="text" name="exam_name" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold">Course</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold">Academic Year</label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>"><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Semester</label>
                        <input type="number" name="semester" class="form-control" required min="1" max="10">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_exam" class="btn btn-primary px-4">Save Exam</button>
                </div>
            </form>
        </div>
    <?php include '../includes/footer.php'; ?>


