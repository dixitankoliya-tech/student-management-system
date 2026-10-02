<?php
/**
 * SMS 2.0: Student Report Card
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
if ($student) {
    $enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
    $enrStmt->execute([$student_id]);
    $student['academic_year'] = $enrStmt->fetchColumn() ?: 'Academic period unavailable';
}
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
    ORDER BY s.semester ASC, s.subject_name ASC
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

foreach ($semester_marks as $sem => $marks) {
    $res = calculateSemesterResult($marks);
    $semester_results[$sem] = $res;
    
    $total_credits_all += $res['total_credits'];
    $total_points_all += ($res['sgpa'] * $res['total_credits']);
}

$cgpa = $total_credits_all > 0 ? round($total_points_all / $total_credits_all, 2) : 0;

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom d-print-none">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Official Report Card</h2>
        <p class="text-muted mb-0">Consolidated academic history</p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4"><i class="fas fa-print me-2"></i> Print Report Card</button>
    </div>
</div>

<style>
    @media print {
        body { background: #fff !important; }
        .sidebar, .navbar, .d-print-none { display: none !important; }
        #page-content-wrapper { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .container-fluid { padding: 0 !important; }
        @page { size: auto; margin: 20mm; }
    }
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 8rem;
        color: rgba(0, 0, 0, 0.05);
        z-index: 0;
        pointer-events: none;
        white-space: nowrap;
    }
</style>

<div class="card border border-2 shadow-none rounded-0 position-relative bg-white" style="min-height: 800px;">
    <div class="watermark fw-bold">GLS UNIVERSITY</div>
    
    <div class="card-body p-5 position-relative z-1">
        <!-- University Header -->
        <div class="text-center mb-5 border-bottom border-dark border-2 pb-4">
            <h1 class="fw-bold text-dark text-uppercase mb-1" style="letter-spacing: 2px;">GLS University</h1>
            <h5 class="text-muted text-uppercase mb-3" style="letter-spacing: 1px;">Office of the Registrar</h5>
            <h3 class="fw-bold text-dark text-uppercase" style="letter-spacing: 3px;">Consolidated Academic Record</h3>
        </div>
        
        <!-- Student Info -->
        <div class="row mb-5">
            <div class="col-sm-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <th class="text-uppercase text-muted" style="width: 150px;">Student Name</th>
                        <td class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                    </tr>
                    <tr>
                        <th class="text-uppercase text-muted">Enrollment No.</th>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['enrollment_no']); ?></td>
                    </tr>
                    <tr>
                        <th class="text-uppercase text-muted">Date of Birth</th>
                        <td class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($student['dob'])); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <th class="text-uppercase text-muted" style="width: 150px;">Program</th>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['course']); ?></td>
                    </tr>
                    <tr>
                        <th class="text-uppercase text-muted">Admission Year</th>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['academic_year']); ?></td>
                    </tr>
                    <tr>
                        <th class="text-uppercase text-muted">Current Sem</th>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['semester']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if (empty($semester_results)): ?>
            <div class="text-center py-5">
                <h5 class="text-muted fst-italic">No academic records found for this student.</h5>
            </div>
        <?php else: ?>
            <!-- Results Tables -->
            <?php foreach ($semester_results as $sem => $res): ?>
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom border-dark pb-2 mb-3">Semester <?php echo $sem; ?></h5>
                    <table class="table table-bordered border-dark align-middle text-center mb-2">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">Subject Code & Name</th>
                                <th>Credits</th>
                                <th>Max Marks</th>
                                <th>Marks Obt.</th>
                                <th>Grade</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($res['subjects'] as $sub): ?>
                                <tr>
                                    <td class="text-start">
                                        <span class="fw-bold"><?php echo htmlspecialchars($sub['subject_code']); ?></span> - 
                                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    </td>
                                    <td><?php echo $sub['credits']; ?></td>
                                    <td><?php echo $sub['max_marks']; ?></td>
                                    <td class="fw-bold"><?php echo $sub['marks_obtained']; ?></td>
                                    <td class="fw-bold"><?php echo $sub['grade']; ?></td>
                                    <td class="fw-bold"><?php echo $sub['grade_point']; ?>.0</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td class="text-end">SEMESTER TOTALS</td>
                                <td><?php echo $res['total_credits']; ?></td>
                                <td><?php echo $res['total_max']; ?></td>
                                <td><?php echo $res['total_obtained']; ?></td>
                                <td colspan="2" class="text-end">SGPA: <?php echo $res['sgpa']; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <!-- Summary -->
            <div class="mt-5 border border-2 border-dark p-4">
                <div class="row text-center">
                    <div class="col-4 border-end border-dark">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Total Credits Earned</h6>
                        <h2 class="fw-bold text-dark mb-0"><?php echo $total_credits_all; ?></h2>
                    </div>
                    <div class="col-4 border-end border-dark">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Cumulative GPA (CGPA)</h6>
                        <h2 class="fw-bold text-dark mb-0"><?php echo $cgpa; ?></h2>
                    </div>
                    <div class="col-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Overall Status</h6>
                        <h2 class="fw-bold text-dark mb-0">PASS</h2>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="row mt-5 pt-5 text-center">
            <div class="col-6">
                <div class="border-top border-dark d-inline-block pt-2 px-4 fw-bold text-uppercase" style="min-width: 200px;">
                    Controller of Examinations
                </div>
            </div>
            <div class="col-6">
                <div class="border-top border-dark d-inline-block pt-2 px-4 fw-bold text-uppercase" style="min-width: 200px;">
                    Registrar
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5 text-muted small">
            <em>This is a system-generated document. Printed on <?php echo date('M d, Y h:i A'); ?></em>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
