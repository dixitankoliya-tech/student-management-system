<?php
/**
 * SMS 2.0: Faculty Marks & Academic Score Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
require_once '../includes/academic_helper.php';

$message = '';
$error = '';
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 5;

// Fetch all students for the dropdown
$studentsList = [];
try {
    $stmt = $pdo->query("SELECT id, enrollment_no, first_name, last_name, course, semester, division FROM students ORDER BY enrollment_no ASC");
    $studentsList = $stmt->fetchAll();
    if (!$selected_student_id && !empty($studentsList)) {
        $selected_student_id = $studentsList[0]['id'];
    }
} catch (PDOException $e) {
    error_log("Manage Marks Error: " . $e->getMessage());
    $error = "Failed to load student registry.";
}

// Determine selected student's course for subject filtering
$selectedStudentCourse = '';
if ($selected_student_id) {
    foreach ($studentsList as $s) {
        if ((int)$s['id'] === $selected_student_id) {
            $selectedStudentCourse = $s['course'] ?? '';
            break;
        }
    }
}

// Fetch subjects for this semester AND student's course
$subjects = [];
try {
    if (!empty($selectedStudentCourse)) {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE semester = ? AND (course = ? OR course IS NULL) ORDER BY subject_code ASC");
        $stmt->execute([$selected_semester, $selectedStudentCourse]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE semester = ? ORDER BY subject_code ASC");
        $stmt->execute([$selected_semester]);
    }
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Subjects Error: " . $e->getMessage());
}

// Handle Form Submission (Save/Update Marks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    requireValidCsrf();
    $post_student_id = (int)$_POST['student_id'];
    $post_semester = (int)$_POST['semester'];
    $attendance = max(0, min(100, (float)($_POST['attendance_percentage'] ?? 80)));
    $assignment = max(0, min(100, (float)($_POST['assignment_score'] ?? 80)));
    $practical = max(0, min(100, (float)($_POST['practical_score'] ?? 80)));
    $prev_pct = isset($_POST['previous_semester_percentage']) && $_POST['previous_semester_percentage'] !== '' 
                ? max(0, min(100, (float)$_POST['previous_semester_percentage'])) 
                : null;

    try {
        $pdo->beginTransaction();

        $acad_year = function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : '2024-2025';

        // 1. Save or Update Subject Marks
        if (!empty($_POST['marks'])) {
            $insertMarkStmt = $pdo->prepare("
                INSERT INTO student_marks 
                (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    internal_marks = VALUES(internal_marks),
                    external_marks = VALUES(external_marks),
                    total_marks = VALUES(total_marks),
                    percentage = VALUES(percentage),
                    grade = VALUES(grade),
                    academic_year = VALUES(academic_year)
            ");

            // Build a lookup map for subject max values
            $subjectMaxLookup = [];
            foreach ($subjects as $sub) {
                $subjectMaxLookup[$sub['id']] = [
                    'max_internal' => (float)$sub['max_internal'],
                    'max_external' => (float)$sub['max_external']
                ];
            }

            foreach ($_POST['marks'] as $sub_id => $markData) {
                $sub_id = (int)$sub_id;
                $maxInt = $subjectMaxLookup[$sub_id]['max_internal'] ?? 30;
                $maxExt = $subjectMaxLookup[$sub_id]['max_external'] ?? 70;
                $maxTotal = $maxInt + $maxExt;

                $internal = max(0, min($maxInt, (float)($markData['internal'] ?? 0)));
                $external = max(0, min($maxExt, (float)($markData['external'] ?? 0)));
                $total = $internal + $external;
                $pct = ($maxTotal > 0) ? round(($total / $maxTotal) * 100, 2) : 0;
                $grade = calculateGrade($pct);

                $insertMarkStmt->execute([
                    $post_student_id, $sub_id, $internal, $external, $total, $pct, $grade, $post_semester, $acad_year
                ]);
            }
        }

        // 2. Update Attendance, Assignment, Practical, Prev % in academic_performance
        $updatePerfStmt = $pdo->prepare("
            INSERT INTO academic_performance 
            (student_id, semester, academic_year, attendance_percentage, assignment_score, practical_score, previous_semester_percentage)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                academic_year = VALUES(academic_year),
                attendance_percentage = VALUES(attendance_percentage),
                assignment_score = VALUES(assignment_score),
                practical_score = VALUES(practical_score),
                previous_semester_percentage = VALUES(previous_semester_percentage)
        ");
        $updatePerfStmt->execute([$post_student_id, $post_semester, $acad_year, $attendance, $assignment, $practical, $prev_pct]);

        $pdo->commit();

        // 3. Recalculate Student Aggregate Profile and Class Ranking
        recalculateStudentAcademicProfile($pdo, $post_student_id, $post_semester);
        recalculateClassRanksAndAchievements($pdo, $post_semester);

        logActivity($pdo, 'MARKS_ENTRY', $post_student_id, "Recorded/Updated academic scores and attendance for Semester $post_semester");

        // Send Notification
        $notif_title = "Academic Results Updated";
        $notif_msg = "Your marks and academic profile for Semester $post_semester have been updated.";
        send_notification($pdo, 'student', $post_student_id, $notif_title, $notif_msg, 'academic', 'results.php');

        $message = "Academic records successfully calculated and stored! Profile and rankings updated.";
        $selected_student_id = $post_student_id;
        $selected_semester = $post_semester;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Save Marks Error: " . $e->getMessage());
        $error = "Error saving marks: " . $e->getMessage();
    }
}

// Fetch current details of selected student
$selectedStudent = null;
$existingMarks = [];
$existingPerf = null;

if ($selected_student_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$selected_student_id]);
        $selectedStudent = $stmt->fetch();

        // Fetch marks
        $stmt = $pdo->prepare("SELECT * FROM student_marks WHERE student_id = ? AND semester = ?");
        $stmt->execute([$selected_student_id, $selected_semester]);
        $marksRows = $stmt->fetchAll();
        foreach ($marksRows as $m) {
            $existingMarks[$m['subject_id']] = $m;
        }

        // Fetch performance summary
        $stmt = $pdo->prepare("SELECT * FROM academic_performance WHERE student_id = ? AND semester = ?");
        $stmt->execute([$selected_student_id, $selected_semester]);
        $existingPerf = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Fetch Student Marks Details Error: " . $e->getMessage());
    }
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div>
        <h1 class="h3 text-gray-800 fw-bold mb-1"><i class="fas fa-edit text-primary me-2"></i>Academic Marks & Score Management</h1>
        <p class="text-muted small mb-0">Record internal & external examination scores, attendance metrics, and trigger automated intelligence calculations.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="topper_board.php" class="btn btn-outline-warning rounded-pill shadow-sm"><i class="fas fa-trophy me-1"></i> Topper Board</a>
        <a href="performance_ai.php" class="btn btn-outline-primary rounded-pill shadow-sm"><i class="fas fa-brain me-1"></i> Performance AI</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Student Selector Bar -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded p-4">
        <form method="GET" action="manage_marks.php" class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label fw-bold">Select Student</label>
                <select name="student_id" class="form-select shadow-sm" onchange="this.form.submit()">
                    <?php foreach ($studentsList as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo ($st['id'] == $selected_student_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st['enrollment_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (Sem ' . $st['semester'] . ', Div ' . $st['division'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Semester</label>
                <select name="semester" class="form-select shadow-sm" onchange="this.form.submit()">
                    <?php for ($sem = 1; $sem <= 6; $sem++): ?>
                        <option value="<?php echo $sem; ?>" <?php echo ($selected_semester == $sem) ? 'selected' : ''; ?>>
                            Semester <?php echo $sem; ?> (BSc IT AI/ML)
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="fas fa-sync-alt me-1"></i> Load Data</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedStudent): ?>
<form method="POST" action="manage_marks.php?student_id=<?php echo $selected_student_id; ?>&semester=<?php echo $selected_semester; ?>">
    <?php csrfField(); ?>
    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
    <input type="hidden" name="semester" value="<?php echo $selected_semester; ?>">

    <div class="row g-4 mb-4">
        <!-- Student Identity Summary Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-id-badge me-2"></i>Student Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-initials bg-primary text-white mx-auto mb-2" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?php echo substr($selectedStudent['first_name'], 0, 1) . substr($selectedStudent['last_name'], 0, 1); ?>
                        </div>
                        <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']); ?></h5>
                        <span class="badge bg-light text-dark border mt-1"><?php echo htmlspecialchars($selectedStudent['enrollment_no']); ?></span>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Degree / Stream</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($selectedStudent['course'] . ' (' . $selectedStudent['specialization'] . ')'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Semester & Division</span>
                            <span class="fw-semibold">Sem <?php echo htmlspecialchars($selectedStudent['semester']); ?> | Div <?php echo htmlspecialchars($selectedStudent['division']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Current Performance Category</span>
                            <span><?php echo isset($existingPerf['performance_category']) ? getCategoryBadge($existingPerf['performance_category']) : '<span class="text-muted">Uncalculated</span>'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Alert Level</span>
                            <span><?php echo isset($existingPerf['alert_level']) ? getAlertBadge($existingPerf['alert_level']) : '<span class="text-muted">Uncalculated</span>'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Class Rank</span>
                            <span class="fw-bold text-primary"><?php echo !empty($existingPerf['class_rank']) ? '#' . $existingPerf['class_rank'] : 'N/A'; ?></span>
                        </li>
                    </ul>

                    <div class="mt-4 pt-3 border-top text-center">
                        <a href="view_student.php?id=<?php echo $selected_student_id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                            <i class="fas fa-eye me-1"></i> Full Profile
                        </a>
                        <a href="report_card.php?student_id=<?php echo $selected_student_id; ?>&semester=<?php echo $selected_semester; ?>" class="btn btn-sm btn-outline-primary rounded-pill" target="_blank">
                            <i class="fas fa-file-invoice me-1"></i> Report Card
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Behavioral & Non-Exam Metrics -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i>Attendance & Continuous Evaluation Inputs</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">These continuous indicators are weighted dynamically by the <strong>Performance Intelligence Formula</strong> (Attendance: 15%, Assignments: 10%, Practicals: 15%).</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Overall Attendance Percentage (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-calendar-check text-success"></i></span>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="attendance_percentage" 
                                       value="<?php echo htmlspecialchars($existingPerf['attendance_percentage'] ?? 85.0); ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Below 65% triggers critical RED Alert status.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Continuous Assignment Score (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-tasks text-primary"></i></span>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="assignment_score" 
                                       value="<?php echo htmlspecialchars($existingPerf['assignment_score'] ?? 80.0); ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Aggregated homework & case study assignments.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Practical Lab Exam Score (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-laptop-code text-info"></i></span>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="practical_score" 
                                       value="<?php echo htmlspecialchars($existingPerf['practical_score'] ?? 85.0); ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Lab exercises and coding viva assessments.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Previous Semester Aggregate (% Baseline)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-history text-secondary"></i></span>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" name="previous_semester_percentage" 
                                       value="<?php echo htmlspecialchars($existingPerf['previous_semester_percentage'] ?? ''); ?>" placeholder="Optional (e.g. 78.5)">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Used to calculate <strong>Most Improved Student</strong> rate.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Examination Marks Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-graduation-cap me-2"></i>Curriculum Subject Examination Scores (Semester <?php echo $selected_semester; ?>)</h6>
            <span class="badge bg-light text-dark border">Passing: 40% (Total: 100 per subject)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%;">Code</th>
                        <th style="width: 38%;">Subject Title</th>
                        <th style="width: 18%;">Internal Marks (Max 30)</th>
                        <th style="width: 18%;">External Marks (Max 70)</th>
                        <th style="width: 14%;" class="text-center">Current Total & Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No subjects registered for Semester <?php echo $selected_semester; ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <?php 
                                $subId = $sub['id'];
                                $m = $existingMarks[$subId] ?? null;
                                $intVal = $m ? $m['internal_marks'] : '';
                                $extVal = $m ? $m['external_marks'] : '';
                                $totVal = $m ? $m['total_marks'] : '—';
                                $grdVal = $m ? $m['grade'] : '—';
                            ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border fw-bold"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                    <small class="text-muted"><?php echo $sub['credits']; ?> Credits | Max: Internal 30, External 70</small>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.5" min="0" max="30" class="form-control" 
                                               name="marks[<?php echo $subId; ?>][internal]" 
                                               value="<?php echo htmlspecialchars($intVal); ?>" placeholder="0–30" required>
                                        <span class="input-group-text">/ 30</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.5" min="0" max="70" class="form-control" 
                                               name="marks[<?php echo $subId; ?>][external]" 
                                               value="<?php echo htmlspecialchars($extVal); ?>" placeholder="0–70" required>
                                        <span class="input-group-text">/ 70</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold fs-6 text-dark"><?php echo $totVal; ?></span> / 100
                                    <?php if ($grdVal !== '—'): ?>
                                        <span class="badge <?php echo ($grdVal === 'F') ? 'bg-danger' : 'bg-primary'; ?> ms-2"><?php echo $grdVal; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-3 text-end">
            <button type="submit" name="save_marks" class="btn btn-primary px-4 shadow-sm rounded-pill">
                <i class="fas fa-save me-1"></i> Save Marks & Recalculate Intelligence
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
