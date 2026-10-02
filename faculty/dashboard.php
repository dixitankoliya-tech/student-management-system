<?php
/**
 * SMS 2.0: Faculty Dashboard
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../config/academic_rules.php';

requireAuth();

$user = getCurrentUser();
$faculty_id = $_SESSION['faculty_db_id']; 

$academicEngine = new AcademicEngine($pdo);
$period = $academicEngine->getCurrentAcademicPeriod();
$academicYear = $period ? $period['academic_year'] : 'N/A';
$semesterType = $period ? $period['semester_type'] : 'N/A';

// Fetch real stats
$my_subjects_count = $academicEngine->getFacultySubjectCount($faculty_id, $academicYear, $semesterType);
$my_students_count = $academicEngine->getFacultyUniqueStudentCount($faculty_id, $academicYear, $semesterType);

// My Classes count (Unique course+semester+division mapped to faculty)
$mod = ($semesterType === 'Odd') ? 1 : 0;
$classStmt = $pdo->prepare("SELECT COUNT(DISTINCT course, semester, division) FROM faculty_subjects WHERE faculty_id = ? AND academic_year = ? AND (semester % 2) = ?");
$classStmt->execute([$faculty_id, $academicYear, $mod]);
$my_classes_count = $classStmt->fetchColumn();

// 2. Study Materials
$materialsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.id) FROM study_materials m
    JOIN faculty_subjects fs ON m.faculty_id = fs.faculty_id AND m.subject_id = fs.subject_id AND m.course = fs.course AND m.semester = fs.semester AND m.division = fs.division
    WHERE m.faculty_id = ? AND fs.academic_year = ? AND m.semester % 2 = ?
");
$materialsStmt->execute([$faculty_id, $academicYear, $mod]);
$materials_count = $materialsStmt->fetchColumn();

// 3. Pending Submissions
$assignmentsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sub.id) FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.id
    JOIN faculty_subjects fs ON a.faculty_id = fs.faculty_id AND a.subject_id = fs.subject_id AND a.course = fs.course AND a.semester = fs.semester AND a.division = fs.division
    WHERE a.faculty_id = ? AND sub.status != 'Graded' AND fs.academic_year = ? AND (a.semester % 2) = ?
");
$assignmentsStmt->execute([$faculty_id, $academicYear, $mod]);
$pending_submissions_count = $assignmentsStmt->fetchColumn();

// 4. Notifications
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifStmt->execute([$_SESSION['user_id']]);
$notifications_count = $notifStmt->fetchColumn();

// Today's classes
$today = date('l'); 
$classesStmt = $pdo->prepare("
    SELECT t.*, s.subject_name 
    FROM timetables t 
    JOIN subjects s ON t.subject_id = s.id 
    WHERE t.faculty_id = ? AND t.day_of_week = ? AND t.academic_year = ? AND t.semester % 2 = ?
    ORDER BY t.start_time ASC
");
$classesStmt->execute([$faculty_id, $today, $academicYear, $mod]);
$todays_classes = $classesStmt->fetchAll();

// My Teaching Load
$teachingLoadStmt = $pdo->prepare("
    SELECT fs.*, s.subject_name, s.subject_code,
    (SELECT COUNT(*) FROM student_academic_enrollments sae 
     WHERE sae.course = fs.course AND sae.semester = fs.semester 
       AND sae.division = fs.division AND sae.academic_year = fs.academic_year
       AND sae.status = 'Active') as enrolled_students
    FROM faculty_subjects fs
    JOIN subjects s ON fs.subject_id = s.id
    WHERE fs.faculty_id = ? AND fs.academic_year = ? AND (fs.semester % 2) = ?
    ORDER BY fs.course ASC, fs.semester ASC
");
$teachingLoadStmt->execute([$faculty_id, $academicYear, $mod]);
$teaching_load = $teachingLoadStmt->fetchAll();

// Recent Announcements
$annStmt = $pdo->query("SELECT * FROM announcements WHERE audience IN ('All', 'Faculty') AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC LIMIT 3");
$recent_announcements = $annStmt->fetchAll();

?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<!-- MAIN DASHBOARD CONTENT -->
<header class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Dashboard</h2>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($user['full_name']) ?></p>
    </div>
    <div class="text-end">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill border border-primary-subtle shadow-sm me-2">
            <i class="fas fa-calendar-alt me-1"></i> <?= htmlspecialchars($academicYear) ?>
        </span>
        <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill border border-info-subtle shadow-sm me-2">
            <i class="fas fa-clock me-1"></i> <?= htmlspecialchars($semesterType) ?> Semester
        </span>
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill border border-success-subtle shadow-sm">
            <i class="fas fa-chalkboard-teacher me-1"></i> Faculty Workspace
        </span>
    </div>
</header>

    <!-- KPI GRID -->
    <div class="row g-4 mb-5">
        <!-- Assigned Subjects -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">My Subjects</h6>
                        <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-book" ></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-3" style="font-size: 36px; line-height: 1;"><?= number_format($my_subjects_count) ?></h2>
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill" style="font-size: 11px; padding: 4px 9px; font-weight: 700;">Active Semester</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Students -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Unique Students</h6>
                        <div class="kpi-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-users" ></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-3" style="font-size: 36px; line-height: 1;"><?= number_format($my_students_count) ?></h2>
                    <div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size: 11px; padding: 4px 9px; font-weight: 700;">Across <?= number_format($my_classes_count) ?> Classes</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Study Materials -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Study Materials</h6>
                        <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-file-pdf" ></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-3" style="font-size: 36px; line-height: 1;"><?= number_format($materials_count) ?></h2>
                    <div>
                        <span class="badge <?php echo $materials_count > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-secondary bg-opacity-10 text-secondary'; ?> rounded-pill" style="font-size: 11px; padding: 4px 9px; font-weight: 700;">
                            <?php echo $materials_count > 0 ? 'Shared Files' : 'No materials yet'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unread Notifications -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 shadow-sm" style="border-radius: 14px; border: 1px solid #e5e7eb;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 12px; letter-spacing: 0.04em;">Unread Notifications</h6>
                        <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-bell" ></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-3" style="font-size: 36px; line-height: 1;"><?= number_format($notifications_count) ?></h2>
                    <div>
                        <span class="badge <?php echo $notifications_count > 0 ? 'bg-danger text-white' : 'bg-secondary bg-opacity-10 text-secondary'; ?> rounded-pill" style="font-size: 11px; padding: 4px 9px; font-weight: 700;">
                            <?php echo $notifications_count > 0 ? 'Action Required' : 'All Caught Up'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- My Teaching Load Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0">My Teaching Load</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Program / Course</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Division</th>
                                    <th>Students</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teaching_load as $load): ?>
                                    <?php 
                                        $capacity = 100; 
                                        $isFull = $load['enrolled_students'] >= $capacity;
                                        $available = $capacity - $load['enrolled_students'];
                                    ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($load['subject_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($load['subject_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($load['course']) ?></td>
                                    <td><?= htmlspecialchars($load['academic_year']) ?></td>
                                    <td>Sem <?= htmlspecialchars($load['semester']) ?></td>
                                    <td><span class="badge bg-info text-dark">Div <?= htmlspecialchars($load['division']) ?></span></td>
                                    <td><span class="badge bg-primary"><?= $load['enrolled_students'] ?></span></td>
                                    <td>100</td>
                                    <td>
                                        <?php if ($isFull): ?>
                                            <span class="badge bg-danger">FULL</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $available ?> SEATS</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($teaching_load)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="text-center py-4 text-muted">
                                                <i class="fas fa-folder-open  mb-2 opacity-50"></i>
                                                <h6>No active teaching assignments for this semester.</h6>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <div class="row g-4">
        <!-- Today's Schedule -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Today's Schedule (<?= $today ?>)</h5>
                        <a href="timetable.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View Full</a>
                    </div>
                    <div class="card-body p-4">
                        <?php if (count($todays_classes) > 0): ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($todays_classes as $class): ?>
                                    <div class="card schedule-card border-0 shadow-sm">
                                        <div class="card-body d-flex justify-content-between align-items-center p-3">
                                            <div>
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($class['subject_name']) ?></h6>
                                                <div class="text-muted small">
                                                    <i class="fas fa-building me-1"></i> <?= htmlspecialchars($class['course']) ?> - Sem <?= htmlspecialchars($class['semester']) ?> (Div <?= htmlspecialchars($class['division']) ?>)
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-door-open me-1"></i> Room: <?= htmlspecialchars($class['room_no'] ?: 'TBA') ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-primary">
                                                    <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-calendar-check  mb-2 opacity-50"></i>
                                <h6>No classes scheduled today</h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <a href="attendance.php" class="btn btn-light text-start p-3 border rounded-3 d-flex align-items-center">
                                <div class="bg-primary text-white rounded p-2 me-3">
                                    <i class="fas fa-clipboard-user"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Take Attendance</h6>
                                    <small class="text-muted">Record today's attendance</small>
                                </div>
                            </a>
                            <a href="assignments.php" class="btn btn-light text-start p-3 border rounded-3 d-flex align-items-center">
                                <div class="bg-success text-white rounded p-2 me-3">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Assignments</h6>
                                    <small class="text-muted">Create or grade submissions</small>
                                </div>
                            </a>
                            <a href="marks.php" class="btn btn-light text-start p-3 border rounded-3 d-flex align-items-center">
                                <div class="bg-danger text-white rounded p-2 me-3">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Enter Marks</h6>
                                    <small class="text-muted">Update student evaluations</small>
                                </div>
                            </a>
                            <a href="study_materials.php" class="btn btn-light text-start p-3 border rounded-3 d-flex align-items-center">
                                <div class="bg-warning text-white rounded p-2 me-3">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Upload Material</h6>
                                    <small class="text-muted">Share notes & resources</small>
                                </div>
                            </a>
                            <a href="my_students.php" class="btn btn-light text-start p-3 border rounded-3 d-flex align-items-center">
                                <div class="bg-info text-white rounded p-2 me-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">View Students</h6>
                                    <small class="text-muted">Access class roster</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mt-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Recent Announcements</h5>
                        <a href="announcements.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                    </div>
                    <div class="card-body p-0 mt-3">
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




