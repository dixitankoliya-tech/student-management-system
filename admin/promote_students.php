<?php
/**
 * SMS 2.0: Admin Student Promotion Workflow
 * Promotes students from one semester to the next, creating proper enrollment records.
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../config/academic_rules.php';

requireAuth();
requireRole('admin');

$error = '';
$success = '';
$promoted_count = 0;

// Fetch lookups
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
$divisions = $pdo->query("SELECT division_name FROM divisions WHERE status='Active' ORDER BY division_name")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_students'])) {
    requireValidCsrf();
    
    $from_course = $_POST['from_course'] ?? '';
    $from_semester = (int)($_POST['from_semester'] ?? 0);
    $to_semester = (int)($_POST['to_semester'] ?? 0);
    $to_division = $_POST['to_division'] ?? '';
    $academic_year = trim($_POST['academic_year'] ?? '');
    $student_ids = $_POST['student_ids'] ?? [];
    
    if (empty($from_course) || $from_semester < 1 || $to_semester < 1 || $to_semester <= $from_semester) {
        $error = 'Invalid promotion parameters. Target semester must be greater than source.';
    } elseif (empty($student_ids)) {
        $error = 'No students selected for promotion.';
    } elseif (empty($academic_year)) {
        $error = 'Academic year is required.';
    } else {
        $academicEngine = new AcademicEngine($pdo);
        if ($academicEngine->checkAcademicCutoff()) {
            $error = 'The academic cutoff date (30 November) has passed. Promotions for the current academic year are no longer allowed.';
        } else {
            try {
                $pdo->beginTransaction();
            
            // Prepare statements outside loop
            $stmtGetEnrollment = $pdo->prepare("SELECT id FROM student_academic_enrollments WHERE student_id = ? AND course = ? AND semester = ? AND status = 'Active' LIMIT 1");
            $stmtUpdateOldEnrollment = $pdo->prepare("UPDATE student_academic_enrollments SET status = 'Promoted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtGetDiv = $pdo->prepare("SELECT division FROM students WHERE id = ?");
            $stmtInsertNewEnrollment = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) VALUES (?, ?, ?, ?, ?, 'Active') ON DUPLICATE KEY UPDATE status = 'Active', updated_at = CURRENT_TIMESTAMP");
            $stmtUpdateStudentState = $pdo->prepare("UPDATE students SET semester = ?, division = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtInsertHistory = $pdo->prepare("INSERT INTO promotion_history (student_id, from_enrollment_id, to_enrollment_id, from_semester, to_semester, from_division, to_division, academic_year, promoted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $offerStmt = $pdo->prepare("SELECT id FROM faculty_subjects WHERE course = ? AND semester = ? AND academic_year = ?");
            $stmtInsertSubject = $pdo->prepare("INSERT INTO student_subject_enrollments (student_id, faculty_subject_id, status) VALUES (?, ?, 'Enrolled') ON DUPLICATE KEY UPDATE status = 'Enrolled'");

            foreach ($student_ids as $sid) {
                $sid = (int)$sid;
                
                // Get current enrollment
                $stmtGetEnrollment->execute([$sid, $from_course, $from_semester]);
                $from_enrollment = $stmtGetEnrollment->fetch();
                
                if (!$from_enrollment) continue;
                
                // Mark old enrollment as Promoted
                $stmtUpdateOldEnrollment->execute([$from_enrollment['id']]);
                
                // Determine division — keep existing if not specified
                $div = !empty($to_division) ? $to_division : null;
                if (!$div) {
                    $stmtGetDiv->execute([$sid]);
                    $div = $stmtGetDiv->fetchColumn() ?: 'A';
                }
                
                // Create new enrollment
                $stmtInsertNewEnrollment->execute([$sid, $from_course, $academic_year, $to_semester, $div]);
                $to_enrollment_id = $pdo->lastInsertId();
                
                // Update students table with new current state
                $stmtUpdateStudentState->execute([$to_semester, $div, $sid]);
                
                // Record promotion history
                if ($to_enrollment_id && $from_enrollment['id']) {
                    try {
                        $stmtInsertHistory->execute([$sid, $from_enrollment['id'], $to_enrollment_id, $from_semester, $to_semester, $div, $div, $academic_year, $_SESSION['user_id']]);
                    } catch (PDOException $e) {
                        // Non-critical if promotion_history insert fails
                        error_log("Promotion history insert: " . $e->getMessage());
                    }
                }
                
                // Auto-enroll in new semester subject offerings
                $offerStmt->execute([$from_course, $to_semester, $academic_year]);
                $offerings = $offerStmt->fetchAll();
                foreach ($offerings as $off) {
                    try {
                        $stmtInsertSubject->execute([$sid, $off['id']]);
                    } catch (PDOException $e) {
                        // Skip duplicates silently
                    }
                }
                
                $promoted_count++;
            }
            
            $pdo->commit();
            
            // Audit log
            logActivity($pdo, 'PROMOTE', null, "Promoted $promoted_count students from $from_course Sem $from_semester to Sem $to_semester");
            
            $success = "Successfully promoted $promoted_count student(s) from Semester $from_semester to Semester $to_semester.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Promotion failed: ' . $e->getMessage();
        }
        } // close the else block for academic engine check
    }
}

// Fetch eligible students for promotion (if filters selected)
$eligible_students = [];
$filter_course = $_GET['course'] ?? $_POST['from_course'] ?? '';
$filter_semester = (int)($_GET['semester'] ?? $_POST['from_semester'] ?? 0);

if (!empty($filter_course) && $filter_semester > 0) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.enrollment_no, s.first_name, s.last_name, s.course, s.semester, s.division,
        (SELECT COUNT(*) FROM marks m JOIN examinations e ON m.exam_id = e.id WHERE m.student_id = s.id AND e.semester = s.semester) as marks_count,
        sae.status as enrollment_status
        FROM students s
        LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.course = s.course AND sae.semester = s.semester AND sae.status = 'Active'
        WHERE s.course = ? AND s.semester = ?
        ORDER BY s.enrollment_no ASC
        LIMIT 500
    ");
    $stmt->execute([$filter_course, $filter_semester]);
    $eligible_students = $stmt->fetchAll();
}
?>
<?php
include '../includes/admin_header.php';
?>
    <div class="d-flex" id="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <?php include '../includes/admin_navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item active">Promotion</li>
                    </ol>
                </nav>

                <h2 class="fw-bold mb-4"><i class="fas fa-arrow-up me-2 text-success"></i>Student Promotion</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Step 1: Select source -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-light border-0 p-3">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Step 1: Select Students to Promote</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Program</label>
                                <select name="course" class="form-select" required>
                                    <option value="">Select Program...</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Current Semester</label>
                                <input type="number" name="semester" class="form-control" min="1" max="12" value="<?= $filter_semester ?: '' ?>" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Find Students
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($eligible_students)): ?>
                <!-- Step 2: Promote -->
                <form method="POST">
                    <?php csrfField(); ?>
                    <input type="hidden" name="from_course" value="<?= htmlspecialchars($filter_course) ?>">
                    <input type="hidden" name="from_semester" value="<?= $filter_semester ?>">
                    
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-light border-0 p-3">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Step 2: Promotion Settings</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Target Semester</label>
                                    <input type="number" name="to_semester" class="form-control" min="<?= $filter_semester + 1 ?>" max="12" value="<?= $filter_semester + 1 ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Academic Year</label>
                                    <input type="text" name="academic_year" class="form-control" value="2026-2027" required placeholder="e.g., 2026-2027">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Division (optional)</label>
                                    <select name="to_division" class="form-select">
                                        <option value="">Keep Current</option>
                                        <?php foreach ($divisions as $d): ?>
                                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-light border-0 p-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Step 3: Select Students (<?= count($eligible_students) ?> eligible)</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAll(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Deselect All</button>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllBox" onchange="toggleAll(this.checked)"></th>
                                            <th>Enrollment No</th>
                                            <th>Student Name</th>
                                            <th>Division</th>
                                            <th>Marks Records</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eligible_students as $st): ?>
                                        <tr>
                                            <td><input type="checkbox" name="student_ids[]" value="<?= $st['id'] ?>" class="student-checkbox" checked></td>
                                            <td><code><?= htmlspecialchars($st['enrollment_no']) ?></code></td>
                                            <td><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></td>
                                            <td><span class="badge bg-info text-dark">Div <?= htmlspecialchars($st['division']) ?></span></td>
                                            <td><?= $st['marks_count'] ?> records</td>
                                            <td>
                                                <?php if ($st['enrollment_status'] === 'Active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($st['enrollment_status'] ?? 'No Enrollment') ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 text-end">
                                <div class="alert alert-warning d-inline-block text-start me-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong>Warning:</strong> This action will update student records and create new enrollment entries. Historical data is preserved.
                                </div>
                                <button type="submit" name="promote_students" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to promote the selected students? This action preserves all historical data.');">
                                    <i class="fas fa-arrow-up me-2"></i>Promote Selected Students
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php elseif (!empty($filter_course) && $filter_semester > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No students found in <?= htmlspecialchars($filter_course) ?> Semester <?= $filter_semester ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleAll(checked) {
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checked);
        document.getElementById('selectAllBox').checked = checked;
    }
    </script>
</body>
</html>
