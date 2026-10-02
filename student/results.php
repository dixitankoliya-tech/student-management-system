<?php
/**
 * SMS 2.0: Student Results
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
require_once '../includes/academic_calculator.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch all Locked/Published marks
$marksStmt = $pdo->prepare("
    SELECT 
        m.subject_id,
        s.subject_name,
        s.subject_code,
        s.credits,
        s.semester,
        m.max_marks,
        m.marks_obtained
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    WHERE m.student_id = ? AND m.status = 'Locked'
    ORDER BY s.semester DESC, s.subject_name ASC
");
$marksStmt->execute([$student_id]);
$all_marks = $marksStmt->fetchAll();

// Group marks by semester
$semester_marks = [];
foreach ($all_marks as $m) {
    $sem = $m['semester'];
    if (!isset($semester_marks[$sem])) {
        $semester_marks[$sem] = [];
    }
    $semester_marks[$sem][] = $m;
}

// Calculate results per semester and CGPA
$semester_results = [];
$total_credits_all = 0;
$total_points_all = 0;

for ($sem = $student['semester']; $sem >= 1; $sem--) {
    if (isset($semester_marks[$sem])) {
        $res = calculateSemesterResult($semester_marks[$sem]);
        $semester_results[$sem] = $res;
        
        $total_credits_all += $res['total_credits'];
        $total_points_all += ($res['sgpa'] * $res['total_credits']);
    } else {
        // Empty state for this semester
        $semester_results[$sem] = null;
    }
}

$cgpa = $total_credits_all > 0 ? round($total_points_all / $total_credits_all, 2) : 0;

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Results</h2>
        <p class="text-muted mb-0">Semester-wise Grade point averages and CGPA</p>
    </div>
    <div class="text-end">
        <span class="badge bg-primary fs-5 px-4 py-2 rounded-pill shadow-sm">CGPA: <?php echo $cgpa > 0 ? $cgpa : 'N/A'; ?></span>
    </div>
</div>

<?php if (empty($semester_results)): ?>
    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
        <div class="card-body">
            <i class="fas fa-chart-bar fs-1 text-light mb-3"></i>
            <h5 class="fw-bold text-muted">No Results Available</h5>
            <p class="text-muted mb-0">Results will be generated once your marks are published.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($semester_results as $sem => $res): ?>
        <?php if ($res === null): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-5">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i> Semester <?php echo $sem; ?></h5>
                    <span class="badge bg-secondary fs-6">Not Available</span>
                </div>
                <div class="card-body p-5 text-center">
                    <p class="text-muted mb-0">Results for this semester are not yet published.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 mb-5">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i> Semester <?php echo $sem; ?></h5>
                    <span class="badge bg-<?php echo $res['result_status'] === 'Pass' ? 'success' : 'danger'; ?> fs-6">
                        <?php echo $res['result_status']; ?>
                    </span>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Subject</th>
                                    <th class="text-center">Credits</th>
                                    <th class="text-center">Total Marks</th>
                                    <th class="text-center">Percentage</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-end pe-4">Grade Point</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($res['subjects'] as $sub): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?php echo htmlspecialchars($sub['subject_name']); ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($sub['subject_code']); ?></small>
                                        </td>
                                        <td class="text-center fw-semibold text-secondary"><?php echo $sub['credits']; ?></td>
                                        <td class="text-center"><?php echo $sub['marks_obtained']; ?> / <?php echo $sub['max_marks']; ?></td>
                                        <td class="text-center"><?php echo $sub['percentage']; ?>%</td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $sub['pass_fail'] === 'Pass' ? 'success' : 'danger'; ?>-subtle text-<?php echo $sub['pass_fail'] === 'Pass' ? 'success' : 'danger'; ?> fs-6 rounded-pill px-3 py-1">
                                                <?php echo $sub['grade']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-dark"><?php echo $sub['grade_point']; ?>.0</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-3 border-top-0 rounded-bottom-4">
                    <div class="row text-center">
                        <div class="col-sm-4 mb-2 mb-sm-0 border-end border-white">
                            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Total Credits</div>
                            <div class="fw-bold text-dark fs-5"><?php echo $res['total_credits']; ?></div>
                        </div>
                        <div class="col-sm-4 mb-2 mb-sm-0 border-end border-white">
                            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Overall Percentage</div>
                            <div class="fw-bold text-dark fs-5"><?php echo $res['overall_percentage']; ?>%</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">SGPA</div>
                            <div class="fw-bold text-primary fs-5"><?php echo $res['sgpa']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
