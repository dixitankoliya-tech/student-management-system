<?php
/**
 * SMS 2.0: Manage Daily Attendance (Admin)
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : '';
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';
$filter_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    requireValidCsrf();
    $attendance_date = $_POST['attendance_date'];
    $subject_id = (int)$_POST['subject_id'];
    $division = $_POST['division'] ?? '';
    $course = $_POST['course'] ?? '';
    $semester = $_POST['semester'] ?? '';
    
    try {
        if (empty($subject_id) || empty($division)) {
            throw new Exception("Subject and Division are required to mark attendance.");
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, subject_id, division, attendance_date, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        
        if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
            foreach ($_POST['attendance'] as $student_id => $status) {
                $stmt->execute([$student_id, $subject_id, $division, $attendance_date, $status]);
            }
        }
        
        $pdo->commit();
        logActivity($pdo, 'MARK_ATTENDANCE', null, "Admin marked attendance for Subject $subject_id, Div $division on $attendance_date");
        header("Location: attendance.php?date=$attendance_date&semester=$semester&course=" . urlencode($course) . "&subject_id=$subject_id&division=" . urlencode($division) . "&msg=saved");
        exit();
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error saving attendance: " . $e->getMessage();
    }
}

$filter_division = isset($_GET['division']) ? $_GET['division'] : '';

// Get unique courses for filter
$courses = $pdo->query("SELECT DISTINCT course FROM students ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

// Get subjects if course and semester selected
$subjects = [];
if ($filter_course && $filter_semester) {
    $stmt = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE course = ? AND semester = ? ORDER BY subject_name");
    $stmt->execute([$filter_course, $filter_semester]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build query
$students = [];
if ($filter_course && $filter_semester && $filter_subject_id && $filter_division) {
    $sql = "SELECT s.id, s.enrollment_no, s.first_name, s.last_name, a.status 
            FROM students s 
            JOIN student_academic_enrollments sae ON s.id = sae.student_id AND sae.status = 'Active'
            LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ? AND a.subject_id = ? AND a.division = ?
            WHERE sae.course = ? AND sae.semester = ? AND sae.division = ?
            ORDER BY s.enrollment_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter_date, $filter_subject_id, $filter_division, $filter_course, $filter_semester, $filter_division]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<div id="page-content-wrapper" class="w-100 bg-light">
<?php include '../includes/admin_navbar.php'; ?>

<div class="container-fluid px-4 py-4">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Manage Attendance</h1>
        <p class="text-muted small mb-0">Record and update student attendance across all courses.</p>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="fas fa-check-circle me-2"></i> Attendance saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 bg-white rounded-4">
        <form method="GET" action="attendance.php" class="row g-3" id="filterForm">
            <div class="col-md-2">
                <label class="form-label text-muted small text-uppercase fw-bold">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="document.getElementById('filterForm').submit();" required>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small text-uppercase fw-bold">Course</label>
                <select name="course" class="form-select" onchange="document.getElementById('filterForm').submit();" required>
                    <option value="">Select...</option>
                    <?php foreach($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $filter_course ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small text-uppercase fw-bold">Semester</label>
                <select name="semester" class="form-select" onchange="document.getElementById('filterForm').submit();" required>
                    <option value="">Select...</option>
                    <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i === $filter_semester ? 'selected' : ''; ?>>Sem <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small text-uppercase fw-bold">Division</label>
                <select name="division" class="form-select" onchange="document.getElementById('filterForm').submit();" required>
                    <option value="">Select...</option>
                    <?php foreach(['A','B','C','D'] as $div): ?>
                        <option value="<?php echo $div; ?>" <?php echo $div === $filter_division ? 'selected' : ''; ?>><?php echo $div; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small text-uppercase fw-bold">Subject</label>
                <select name="subject_id" class="form-select" onchange="document.getElementById('filterForm').submit();" required <?php echo empty($subjects) ? 'disabled' : ''; ?>>
                    <option value="">Select Subject...</option>
                    <?php foreach($subjects as $sub): ?>
                        <option value="<?php echo $sub['id']; ?>" <?php echo $filter_subject_id == $sub['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub['subject_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter Students</button>
                </div>
            </noscript>
        </form>
    </div>
</div>

<?php if ($filter_course && $filter_semester && $filter_subject_id): ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($filter_subject_id); ?>">
            <input type="hidden" name="division" value="<?php echo htmlspecialchars($filter_division); ?>">
            <input type="hidden" name="course" value="<?php echo htmlspecialchars($filter_course); ?>">
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($filter_semester); ?>">
            
            <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-users me-2"></i> Students List</h5>
                <span class="badge bg-secondary"><?php echo count($students); ?> Students</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Enrollment No</th>
                            <th>Name</th>
                            <th class="text-end px-4">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($students) > 0): ?>
                            <?php foreach($students as $s): ?>
                                <tr>
                                    <td class="px-4 fw-medium text-muted"><?php echo htmlspecialchars($s['enrollment_no']); ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                    <td class="text-end px-4">
                                        <div class="btn-group" role="group">
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $s['id']; ?>]" id="present_<?php echo $s['id']; ?>" value="Present" <?php echo ($s['status'] == 'Present' || !$s['status']) ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success btn-sm px-3" for="present_<?php echo $s['id']; ?>">Present</label>
                                            
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $s['id']; ?>]" id="absent_<?php echo $s['id']; ?>" value="Absent" <?php echo ($s['status'] == 'Absent') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger btn-sm px-3" for="absent_<?php echo $s['id']; ?>">Absent</label>
                                            
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $s['id']; ?>]" id="leave_<?php echo $s['id']; ?>" value="Leave" <?php echo ($s['status'] == 'Leave') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning btn-sm px-3" for="leave_<?php echo $s['id']; ?>">Leave</label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-graduate fs-3 mb-3 opacity-25"></i>
                                    <h5>No students found</h5>
                                    <p class="small">No students are currently enrolled in this course and semester combination.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if(count($students) > 0): ?>
            <div class="p-4 bg-light border-top text-end rounded-bottom-4">
                <button type="submit" name="save_attendance" class="btn btn-primary px-5 fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Attendance
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php else: ?>
<div class="text-center py-5 mt-4 text-muted">
    <i class="fas fa-calendar-check fs-1 mb-3 opacity-25"></i>
    <h4 class="fw-bold">Select Filters</h4>
    <p>Please select a Course, Semester, and Subject to mark attendance.</p>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
