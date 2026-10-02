<?php
/**
 * SMS 2.0: Student Timetable
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if ($student) {
    $enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
    $enrStmt->execute([$student_id]);
    $student['academic_year'] = $enrStmt->fetchColumn() ?: 'Academic period unavailable';
}
if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch timetable
$timeStmt = $pdo->prepare("
    SELECT 
        t.*,
        s.subject_name,
        s.subject_code,
        s.subject_type,
        f.first_name as fac_first_name,
        f.last_name as fac_last_name
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.id
    JOIN faculty f ON t.faculty_id = f.id
    WHERE t.course = ? AND t.academic_year = ? AND t.semester = ? AND t.division = ?
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time
");
$timeStmt->execute([$student['course'], $student['academic_year'], $student['semester'], $student['division']]);
$raw_timetable = $timeStmt->fetchAll();

$schedule = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [],
    'Thursday' => [], 'Friday' => [], 'Saturday' => []
];

foreach ($raw_timetable as $t) {
    $schedule[$t['day_of_week']][] = $t;
}

// Today's classes
$today = date('l');
$todays_classes = isset($schedule[$today]) ? $schedule[$today] : [];

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Timetable</h2>
        <p class="text-muted mb-0">Class schedule for Semester <?php echo htmlspecialchars($student['semester']); ?> &bull; Division <?php echo htmlspecialchars($student['division']); ?></p>
    </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" id="timetableTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-4" id="today-tab" data-bs-toggle="pill" data-bs-target="#today" type="button" role="tab">Today's Classes</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-4" id="weekly-tab" data-bs-toggle="pill" data-bs-target="#weekly" type="button" role="tab">Weekly View</button>
    </li>
</ul>

<div class="tab-content" id="timetableTabsContent">
    <!-- Today's Classes -->
    <div class="tab-pane fade show active" id="today" role="tabpanel">
        <div class="row">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="m-0 fw-bold text-dark"><i class="fas fa-calendar-day text-primary me-2"></i> <?php echo $today; ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($todays_classes) || in_array($today, ['Sunday'])): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="fas fa-mug-hot fs-3 mb-3 text-light"></i>
                                <h5>No classes today</h5>
                                <p class="mb-0">Enjoy your free time or use it for self-study.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($todays_classes as $class): 
                                    $is_practical = $class['subject_type'] === 'Practical';
                                ?>
                                    <div class="list-group-item px-4 py-3 border-bottom-0">
                                        <div class="row align-items-center">
                                            <div class="col-md-3 border-end">
                                                <div class="fw-bold text-primary"><?php echo date('h:i A', strtotime($class['start_time'])); ?></div>
                                                <div class="text-muted small">to <?php echo date('h:i A', strtotime($class['end_time'])); ?></div>
                                            </div>
                                            <div class="col-md-9 ps-md-4">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($class['subject_name']); ?></h6>
                                                    <?php if ($is_practical): ?>
                                                        <span class="badge bg-info-subtle text-info">Lab</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex text-muted small gap-4 mt-2">
                                                    <div><i class="fas fa-user-tie me-1 text-secondary"></i> Prof. <?php echo htmlspecialchars($class['fac_last_name']); ?></div>
                                                    <?php if ($class['room_no']): ?>
                                                        <div><i class="fas fa-map-marker-alt me-1 text-secondary"></i> Room <?php echo htmlspecialchars($class['room_no']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Weekly View -->
    <div class="tab-pane fade" id="weekly" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 text-center" style="min-width: 800px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3" style="width: 120px;">Day</th>
                                <th class="py-3">Classes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedule as $day => $classes): ?>
                                <tr>
                                    <td class="bg-light fw-bold text-dark"><?php echo $day; ?></td>
                                    <td class="p-3 text-start">
                                        <?php if (empty($classes)): ?>
                                            <span class="text-muted fst-italic">No Classes</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($classes as $class): ?>
                                                    <div class="border rounded p-2 bg-white" style="min-width: 200px;">
                                                        <div class="fw-bold text-primary small mb-1">
                                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                                        </div>
                                                        <div class="fw-bold text-dark text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($class['subject_name']); ?>">
                                                            <?php echo htmlspecialchars($class['subject_code']); ?>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($class['room_no'] ?: 'TBA'); ?> &bull; <?php echo htmlspecialchars($class['fac_last_name']); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
