<?php
/**
 * SMS 2.0: Student Dashboard
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

// Timetable
// Fetch active enrollment to determine academic year
$enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
$enrStmt->execute([$student_id]);
$student['academic_year'] = $enrStmt->fetchColumn();

if (!$student['academic_year']) {
    $student['academic_year'] = date('Y') . '-' . (date('Y') + 1);
}

// Timetable
$today = date('l');
$ttStmt = $pdo->prepare("SELECT t.*, s.subject_name FROM timetables t JOIN subjects s ON t.subject_id = s.id WHERE t.course = ? AND t.semester = ? AND t.division = ? AND t.academic_year = ? AND t.day_of_week = ? ORDER BY t.start_time ASC");
$ttStmt->execute([$student['course'], $student['semester'], $student['division'], $student['academic_year'], $today]);
$today_classes = $ttStmt->fetchAll();


// 1. Overall Attendance
$attStmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN a.status='Present' THEN 1 END) as present_count,
        COUNT(a.id) as total_days
    FROM attendance a
    WHERE a.student_id = ?
");
$attStmt->execute([$student_id]);
$attendance = $attStmt->fetch();
$overall_attendance_pct = $attendance['total_days'] > 0 ? round(($attendance['present_count'] / $attendance['total_days']) * 100, 2) : 'N/A';


// 2. Current Percentage / Performance Score
$perfStmt = $pdo->prepare("SELECT * FROM academic_performance WHERE student_id = ? ORDER BY semester DESC LIMIT 1");
$perfStmt->execute([$student_id]);
$perf = $perfStmt->fetch();
$current_sgpa = $perf['performance_score'] ?? 'N/A';
$current_cgpa = $perf['grade'] ?? 'N/A';
$current_percentage = $perf['overall_percentage'] ?? 'N/A';


// 3. Upcoming Exams
$examStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM examinations 
    WHERE course = ? AND semester = ? AND academic_year = ? AND start_date >= CURDATE() AND status = 'Upcoming'
");
$examStmt->execute([$student['course'], $student['semester'], $student['academic_year']]);
$upcoming_exams = $examStmt->fetchColumn();

// 4. Pending Assignments
$pendingAssgStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) 
    FROM assignments a
    JOIN faculty_subjects fs ON a.faculty_id = fs.faculty_id AND a.subject_id = fs.subject_id AND a.course = fs.course AND a.semester = fs.semester AND a.division = fs.division
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE a.course = ? AND a.semester = ? AND a.division = ? AND fs.academic_year = ?
      AND a.due_date > NOW() 
      AND s.id IS NULL
");
$pendingAssgStmt->execute([$student_id, $student['course'], $student['semester'], $student['division'], $student['academic_year']]);
$pending_assignments = $pendingAssgStmt->fetchColumn();

// 5. Recent Announcements
$annStmt = $pdo->query("SELECT * FROM announcements WHERE audience IN ('All', 'Student') AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC LIMIT 3");
$recent_announcements = $annStmt->fetchAll();

// 6. Recent Marks
$marksStmt = $pdo->prepare("
    SELECT m.marks_obtained, m.max_marks, s.subject_name, e.exam_name
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    JOIN examinations e ON m.exam_id = e.id
    WHERE m.student_id = ? AND m.status = 'Locked'
    ORDER BY m.updated_at DESC LIMIT 3
");
$marksStmt->execute([$student_id]);
$recent_marks = $marksStmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
        <p class="text-muted mb-0">
            <?php echo htmlspecialchars($student['course']); ?> &bull; 
            <?php echo htmlspecialchars($student['academic_year']); ?> &bull; 
            Division <?php echo htmlspecialchars($student['division']); ?>
        </p>
    </div>
    <div class="text-end">
        <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill shadow-sm">Semester <?php echo htmlspecialchars($student['semester']); ?></span>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Attendance -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Attendance</h6>
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-calendar-check" ></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-0" style="font-size: 36px; line-height: 1;"><?php echo $overall_attendance_pct; ?><?php echo $overall_attendance_pct !== 'N/A' ? '%' : ''; ?></h2>
            </div>
        </div>
    </div>

    <!-- SGPA -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Current SGPA</h6>
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-chart-line" ></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-0" style="font-size: 36px; line-height: 1;"><?php echo $current_sgpa; ?></h2>
            </div>
        </div>
    </div>
    
    <!-- Pending Assignments -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Pending Tasks</h6>
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-tasks" ></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-0" style="font-size: 36px; line-height: 1;"><?php echo $pending_assignments; ?></h2>
            </div>
        </div>
    </div>

    <!-- Upcoming Exams -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Upcoming Exams</h6>
                    <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-clock" ></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-0" style="font-size: 36px; line-height: 1;"><?php echo $upcoming_exams; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Today's Timetable placeholder -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-calendar-day me-2 text-primary"></i> Today's Timetable</h6>
            </div>
            <div class="card-body">
                                <?php if (count($today_classes) > 0): ?>
                    <div class="list-group list-group-flush mb-3">
                        <?php foreach ($today_classes as $class): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($class['subject_name']) ?></h6>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i> <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?> | Room: <?= htmlspecialchars($class['room_no']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-3"><i class="fas fa-bed text-secondary me-2"></i> No classes scheduled for today.</p>
                <?php endif; ?>
                <a href="timetable.php" class="btn btn-sm btn-outline-primary mt-2">View Full Timetable</a>
            </div>
        </div>
    </div>

    <!-- Academic Alerts -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-exclamation-triangle me-2 text-danger"></i> Academic Alerts</h6>
            </div>
            <div class="card-body">
                <?php if ($overall_attendance_pct !== 'N/A' && $overall_attendance_pct < 75): ?>
                    <div class="alert alert-danger py-2 mb-2">
                        <i class="fas fa-exclamation-circle me-2"></i> Attendance is below the required 75% threshold!
                    </div>
                <?php else: ?>
                    <p class="text-muted">No active alerts. Keep up the good work!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Marks -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-success"></i> Recent Marks</h6>
                <a href="marks.php" class="btn btn-sm btn-light text-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_marks)): ?>
                    <div class="p-4 text-center text-muted">No published marks available yet.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($recent_marks as $m): ?>
                            <div class="list-group-item px-4 py-3 border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($m['subject_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($m['exam_name']); ?></small>
                                    </div>
                                    <span class="badge bg-success-subtle text-success fs-6">
                                        <?php echo $m['marks_obtained']; ?> / <?php echo $m['max_marks']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bullhorn me-2 text-info"></i> Recent Announcements</h6>
                <a href="announcements.php" class="btn btn-sm btn-light text-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_announcements)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">No new announcements at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($recent_announcements as $ann): ?>
                        <div class="list-group-item p-3 border-bottom-0 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($ann['title']); ?></h6>
                                <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('M d', strtotime($ann['publish_date'])); ?></small>
                            </div>
                            <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars(substr($ann['message'], 0, 80)) . '...'; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


