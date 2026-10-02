<?php
/**
 * SMS 2.0: Comprehensive Student Profile & Academic Intelligence Dossier
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
require_once '../includes/academic_helper.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$id = (int)$_GET['id'];

try {
    // 1. Fetch student demographic record
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: students.php?error=" . urlencode("Student not found."));
        exit();
    }

    $semester = (int)$student['semester'];

    // 2. Fetch academic performance aggregate
    $stmt = $pdo->prepare("SELECT * FROM academic_performance WHERE student_id = ? AND semester = ?");
    $stmt->execute([$id, $semester]);
    $perf = $stmt->fetch();

    // 3. Fetch subject marks
    $stmt = $pdo->prepare("
        SELECT sm.*, s.subject_code, s.subject_name, s.credits, s.max_internal, s.max_external 
        FROM student_marks sm 
        JOIN subjects s ON sm.subject_id = s.id 
        WHERE sm.student_id = ? AND sm.semester = ? 
        ORDER BY sm.percentage DESC
    ");
    $stmt->execute([$id, $semester]);
    $subjects = $stmt->fetchAll();

    // 4. Strongest & Weakest Subjects
    $strongestSubject = !empty($subjects) ? $subjects[0] : null;
    $weakestSubject = !empty($subjects) ? end($subjects) : null;

    // 5. Fetch achievements
    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE student_id = ? ORDER BY id DESC");
    $stmt->execute([$id]);
    $achievements = $stmt->fetchAll();

    // 6. Generate deterministic smart insights
    $insights = generateSmartInsights($pdo, $id, $semester);

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    header("Location: students.php?error=" . urlencode("Error fetching student details."));
    exit();
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="students.php" class="text-decoration-none">Students</a></li>
                <li class="breadcrumb-item active" aria-current="page">Student Profile</li>
            </ol>
        </nav>
        <h1 class="h3 text-gray-800 fw-bold mb-0">Student Profile & Academic Intelligence</h1>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="report_card.php?student_id=<?php echo $student['id']; ?>&semester=<?php echo $semester; ?>" class="btn btn-outline-primary shadow-sm rounded-pill px-3" target="_blank">
            <i class="fas fa-file-invoice me-1"></i> Report Card
        </a>
        <a href="certificate.php?student_id=<?php echo $student['id']; ?>" class="btn btn-outline-warning shadow-sm rounded-pill px-3 text-dark fw-bold" target="_blank">
            <i class="fas fa-certificate me-1"></i> Certificate
        </a>
        <a href="manage_marks.php?student_id=<?php echo $student['id']; ?>&semester=<?php echo $semester; ?>" class="btn btn-primary shadow-sm rounded-pill px-3">
            <i class="fas fa-edit me-1"></i> Update Marks
        </a>
        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-secondary shadow-sm rounded-pill px-3">
            <i class="fas fa-user-edit me-1"></i> Edit Bio
        </a>
    </div>
</div>

<div class="row">
    <!-- Left Column: Identity & Contact Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 mb-4 h-100">
            <div class="card-body text-center pt-5 pb-4">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow" style="width: 100px; height: 100px; font-size: 2.5rem; font-weight: 600;">
                    <?php echo substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1); ?>
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="text-muted mb-2">
                    <span class="badge bg-light text-dark border px-3 py-2 fs-6"><?php echo htmlspecialchars($student['enrollment_no']); ?></span>
                </p>

                <!-- Academic Tags -->
                <div class="mb-3">
                    <?php if (isset($perf['performance_category'])): ?>
                        <div class="mb-2"><?php echo getCategoryBadge($perf['performance_category']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($perf['alert_level'])): ?>
                        <div><?php echo getAlertBadge($perf['alert_level']); ?></div>
                    <?php endif; ?>
                </div>

                <hr class="my-4">
                
                <div class="text-start px-3">
                    <p class="mb-2 d-flex align-items-center">
                        <i class="fas fa-graduation-cap text-primary me-3" style="width: 20px;"></i> 
                        <span class="text-dark fw-semibold"><?php echo htmlspecialchars($student['course'] . ' (' . $student['specialization'] . ')'); ?></span>
                    </p>
                    <p class="mb-2 d-flex align-items-center">
                        <i class="fas fa-layer-group text-primary me-3" style="width: 20px;"></i> 
                        <span>Semester <?php echo htmlspecialchars($student['semester']); ?> &bull; Division <?php echo htmlspecialchars($student['division']); ?></span>
                    </p>
                    <p class="mb-2 d-flex align-items-center">
                        <i class="fas fa-envelope text-primary me-3" style="width: 20px;"></i> 
                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="text-decoration-none text-dark text-break"><?php echo htmlspecialchars($student['email']); ?></a>
                    </p>
                    <p class="mb-2 d-flex align-items-center">
                        <i class="fas fa-phone text-primary me-3" style="width: 20px;"></i> 
                        <span><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : '<span class="text-muted">Not provided</span>'; ?></span>
                    </p>
                    <p class="mb-0 d-flex align-items-start">
                        <i class="fas fa-map-marker-alt text-primary me-3 mt-1" style="width: 20px;"></i> 
                        <span>
                            <?php echo !empty($student['city']) ? htmlspecialchars($student['city']) : ''; ?>
                            <?php if(!empty($student['address'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($student['address']); ?></small>
                            <?php endif; ?>
                            <?php if(empty($student['city']) && empty($student['address'])) echo '<span class="text-muted">Not provided</span>'; ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="card-footer bg-light py-3 text-muted small text-center border-top-0 rounded-bottom">
                <i class="far fa-clock me-1"></i> Enrolled: <?php echo date('d M Y', strtotime($student['created_at'])); ?> 
            </div>
        </div>
    </div>

    <!-- Right Column: Tabs (Academic Intelligence, Curriculum Marks, Demographics) -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-primary" id="ai-tab" data-bs-toggle="tab" data-bs-target="#ai" type="button" role="tab" aria-controls="ai" aria-selected="true">
                            <i class="fas fa-brain me-2"></i>Academic Intelligence
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-secondary" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab" aria-controls="academic" aria-selected="false">
                            <i class="fas fa-graduation-cap me-2"></i>Curriculum Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-secondary" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="false">
                            <i class="fas fa-user me-2"></i>Personal Info
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content" id="profileTabsContent">
                    
                    <!-- ========================================================== -->
                    <!-- TAB 1: ACADEMIC PERFORMANCE INTELLIGENCE                   -->
                    <!-- ========================================================== -->
                    <div class="tab-pane fade show active" id="ai" role="tabpanel" aria-labelledby="ai-tab">
                        
                        <!-- Top Highlights Row -->
                        <div class="row g-3 mb-4 text-center">
                            <!-- Overall Examination % -->
                            <div class="col-sm-3 col-6">
                                <div class="p-3 bg-light rounded border">
                                    <span class="text-muted small fw-bold text-uppercase">Exam Score</span>
                                    <div class="display-6 fw-bold text-primary"><?php echo $perf['overall_percentage'] ?? '0'; ?>%</div>
                                    <small class="text-muted">Grade: <?php echo $perf['grade'] ?? '—'; ?></small>
                                </div>
                            </div>

                            <!-- Performance Intelligence Score -->
                            <div class="col-sm-3 col-6">
                                <div class="p-3 bg-light rounded border">
                                    <span class="text-muted small fw-bold text-uppercase">AI Score</span>
                                    <div class="display-6 fw-bold text-dark"><?php echo $perf['performance_score'] ?? '0'; ?></div>
                                    <small class="text-muted">Scale of 100</small>
                                </div>
                            </div>

                            <!-- Class Rank -->
                            <div class="col-sm-3 col-6">
                                <div class="p-3 bg-light rounded border">
                                    <span class="text-muted small fw-bold text-uppercase">Class Rank</span>
                                    <div class="display-6 fw-bold text-warning-emphasis">#<?php echo $perf['class_rank'] ?? '—'; ?></div>
                                    <small class="text-muted">Semester <?php echo $semester; ?></small>
                                </div>
                            </div>

                            <!-- Semester Improvement Delta -->
                            <div class="col-sm-3 col-6">
                                <div class="p-3 bg-light rounded border">
                                    <span class="text-muted small fw-bold text-uppercase">Growth Leap</span>
                                    <?php 
                                        $imp = (float)($perf['improvement_percentage'] ?? 0);
                                        $color = ($imp > 0) ? 'text-success' : (($imp < 0) ? 'text-danger' : 'text-muted');
                                        $sign = ($imp > 0) ? '+' : '';
                                    ?>
                                    <div class="display-6 fw-bold <?php echo $color; ?>"><?php echo $sign . $imp; ?>%</div>
                                    <small class="text-muted">vs Prev Sem (<?php echo $perf['previous_semester_percentage'] ?? 'N/A'; ?>%)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Continuous Performance Progress Bars -->
                        <div class="card bg-light border-0 p-3 mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Continuous Academic Indicators</h6>
                            
                            <!-- Attendance -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold">Institutional Attendance</span>
                                    <span class="fw-bold text-success"><?php echo $perf['attendance_percentage'] ?? 0; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $perf['attendance_percentage'] ?? 0; ?>%;"></div>
                                </div>
                            </div>

                            <!-- Assignment Performance -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold">Assignments & Continuous Assessment</span>
                                    <span class="fw-bold text-primary"><?php echo $perf['assignment_score'] ?? 0; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $perf['assignment_score'] ?? 0; ?>%;"></div>
                                </div>
                            </div>

                            <!-- Practical Score -->
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold">Practical Lab & Viva Examination</span>
                                    <span class="fw-bold text-info"><?php echo $perf['practical_score'] ?? 0; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $perf['practical_score'] ?? 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Strongest & Weakest Subjects Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="p-3 bg-white border rounded h-100 border-start border-success border-4">
                                    <div class="small text-muted text-uppercase fw-bold"><i class="fas fa-arrow-up text-success me-1"></i> Strongest Academic Subject</div>
                                    <?php if ($strongestSubject): ?>
                                        <h6 class="fw-bold text-dark mt-1 mb-0"><?php echo htmlspecialchars($strongestSubject['subject_name']); ?></h6>
                                        <div class="text-success fw-bold fs-5 mt-1"><?php echo $strongestSubject['percentage']; ?>% <span class="badge bg-success ms-1">Grade <?php echo $strongestSubject['grade']; ?></span></div>
                                    <?php else: ?>
                                        <div class="text-muted small mt-1">No marks recorded</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-white border rounded h-100 border-start border-danger border-4">
                                    <div class="small text-muted text-uppercase fw-bold"><i class="fas fa-arrow-down text-danger me-1"></i> Subject Requiring Remediation</div>
                                    <?php if ($weakestSubject): ?>
                                        <h6 class="fw-bold text-dark mt-1 mb-0"><?php echo htmlspecialchars($weakestSubject['subject_name']); ?></h6>
                                        <div class="text-danger fw-bold fs-5 mt-1"><?php echo $weakestSubject['percentage']; ?>% <span class="badge bg-danger ms-1">Grade <?php echo $weakestSubject['grade']; ?></span></div>
                                    <?php else: ?>
                                        <div class="text-muted small mt-1">No marks recorded</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Smart Insights Box -->
                        <?php if (!empty($insights)): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Deterministic Diagnostic Insights</h6>
                                <div class="list-group">
                                    <?php foreach ($insights as $ins): ?>
                                        <div class="list-group-item list-group-item-action p-3 border-light bg-light rounded mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="fas <?php echo $ins['icon']; ?> fa-lg me-3"></i>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo $ins['title']; ?></div>
                                                    <div class="small text-muted"><?php echo $ins['text']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Honors & Achievements -->
                        <?php if (!empty($achievements)): ?>
                            <div>
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-award text-warning me-2"></i>Awarded Citations & Achievements</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($achievements as $ach): ?>
                                        <span class="badge bg-warning text-dark border px-3 py-2 shadow-sm fs-6">
                                            <i class="fas <?php echo htmlspecialchars($ach['badge_icon']); ?> me-1"></i>
                                            <?php echo htmlspecialchars($ach['title']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ========================================================== -->
                    <!-- TAB 2: CURRICULUM DETAILS & SUBJECT MARKS                  -->
                    <!-- ========================================================== -->
                    <div class="tab-pane fade" id="academic" role="tabpanel" aria-labelledby="academic-tab">
                        <div class="row g-4 mt-1 mb-4">
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Course</p>
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($student['course']); ?></p>
                            </div>
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Specialization</p>
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($student['specialization']); ?></p>
                            </div>
                            <div class="col-sm-4">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Semester</p>
                                <p class="fs-5 text-dark"><span class="badge bg-info text-dark px-3 py-2">Semester <?php echo htmlspecialchars($student['semester']); ?></span></p>
                            </div>
                            <div class="col-sm-4">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Division</p>
                                <p class="fs-5 text-dark"><span class="badge bg-secondary px-3 py-2">Div <?php echo htmlspecialchars($student['division']); ?></span></p>
                            </div>
                            <div class="col-sm-4">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Admission Year</p>
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($student['admission_year']); ?></p>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Subject Examination Results</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Subject Name</th>
                                        <th class="text-center">Internal (30)</th>
                                        <th class="text-center">External (70)</th>
                                        <th class="text-center">Total (100)</th>
                                        <th class="text-center">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($subjects)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No marks recorded. <a href="manage_marks.php?student_id=<?php echo $student['id']; ?>">Enter marks now</a>.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($subjects as $sb): ?>
                                            <tr>
                                                <td class="font-monospace fw-bold"><?php echo htmlspecialchars($sb['subject_code']); ?></td>
                                                <td><?php echo htmlspecialchars($sb['subject_name']); ?></td>
                                                <td class="text-center"><?php echo $sb['internal_marks']; ?></td>
                                                <td class="text-center"><?php echo $sb['external_marks']; ?></td>
                                                <td class="text-center fw-bold text-dark"><?php echo $sb['total_marks']; ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo ($sb['grade'] === 'F') ? 'bg-danger' : 'bg-primary'; ?>"><?php echo $sb['grade']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- ========================================================== -->
                    <!-- TAB 3: PERSONAL INFORMATION                                -->
                    <!-- ========================================================== -->
                    <div class="tab-pane fade" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                        <div class="row g-4 mt-1">
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Full Name</p>
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                            </div>
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Gender</p>
                                <p class="fs-5 text-dark">
                                    <?php if($student['gender'] == 'Male'): ?>
                                        <i class="fas fa-mars text-primary me-2"></i> Male
                                    <?php elseif($student['gender'] == 'Female'): ?>
                                        <i class="fas fa-venus text-danger me-2"></i> Female
                                    <?php else: ?>
                                        <i class="fas fa-genderless text-secondary me-2"></i> Other
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Date of Birth</p>
                                <p class="fs-5 text-dark"><?php echo date('d F Y', strtotime($student['dob'])); ?></p>
                            </div>
                            <div class="col-sm-6">
                                <p class="text-muted small fw-bold mb-1 text-uppercase">Residential City</p>
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($student['city'] ?? 'Not specified'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light py-3 text-muted small text-center border-top-0 rounded-bottom">
                <i class="far fa-clock me-1"></i> Record created: <?php echo date('d M Y H:i', strtotime($student['created_at'])); ?> 
                <span class="mx-2">|</span> 
                <i class="fas fa-history me-1"></i> Last updated: <?php echo date('d M Y H:i', strtotime($student['updated_at'])); ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
