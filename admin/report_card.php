<?php
/**
 * SMS 2.0: Official Student Academic Performance Report Card (Printable)
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
require_once '../includes/academic_helper.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (hasRole('student')) {
    $student_id = $_SESSION['student_id'];
} else if (!hasRole('admin')) {
    // Should not reach here if strictly admin/student, but just in case
    header("Location: login.php");
    exit();
}

$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 5;

if (!$student_id) {
    header("Location: students.php");
    exit();
}

// Fetch student bio
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: 404.php");
    exit();
}

// Fetch academic performance aggregate via Centralized Engine
$academic_data = calculateStudentCGPA($pdo, $student_id);
$semester_data = $academic_data['semester_results'][$semester] ?? null;

$attendance_data = calculateAttendancePercentage($pdo, $student_id, $semester);

// Assign dynamic values to legacy variables for backward compatibility in view
$perf = [];
if ($semester_data) {
    $perf['overall_percentage'] = $semester_data['overall_percentage'];
    $perf['grade'] = $semester_data['sgpa'];
    $perf['attendance_percentage'] = $attendance_data['percentage'];
    $perf['performance_score'] = min(100, round(($semester_data['overall_percentage'] + $attendance_data['percentage']) / 2));
    $perf['performance_category'] = $perf['performance_score'] > 80 ? 'Excellent' : ($perf['performance_score'] > 60 ? 'Good' : 'Needs Improvement');
    $perf['class_rank'] = 'N/A'; // Dynamic ranking can be added later
} else {
    $perf = ['overall_percentage' => '0', 'grade' => 'N/A', 'attendance_percentage' => '0', 'performance_score' => '0', 'performance_category' => 'None', 'class_rank' => '-'];
}

$subjectMarks = $semester_data ? $semester_data['subjects'] : [];

// Fetch student achievements
$stmt = $pdo->prepare("SELECT * FROM achievements WHERE student_id = ?");
$stmt->execute([$student_id]);
$achievements = $stmt->fetchAll();

// Fetch dynamic smart insights
$insights = generateSmartInsights($pdo, $student_id, $semester);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Report Card - <?php echo htmlspecialchars($student['enrollment_no']); ?> - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 2rem 0;
        }
        .report-card-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            padding: 2.5rem;
        }
        .inst-header {
            border-bottom: 2px solid #1e40af;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6rem;
            color: rgba(0, 0, 0, 0.03);
            font-weight: 800;
            pointer-events: none;
            text-transform: uppercase;
        }
        .table-marks th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .report-card-container {
                border: 1px solid #94a3b8 !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 1.5rem !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container mb-4 no-print text-center">
    <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm me-2">
        <i class="fas fa-print me-1"></i> Print / Save PDF
    </button>
    <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fas fa-arrow-left me-1"></i> Back to Profile
    </a>
</div>

<div class="report-card-container position-relative">
    <div class="watermark">GLS UNIVERSITY</div>

    <!-- Institutional Letterhead -->
    <div class="inst-header text-center">
        <div class="d-flex justify-content-center align-items-center mb-2">
            <i class="fas fa-university fs-3 text-primary me-3"></i>
            <div>
                <h2 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">GLS UNIVERSITY</h2>
                <h6 class="text-primary fw-bold text-uppercase mb-0">Faculty of Computer Applications & Information Technology</h6>
            </div>
        </div>
        <p class="text-muted small mb-1">Affiliated to UGC | GLS Campus, Ellisbridge, Ahmedabad - 380006</p>
        <span class="badge bg-primary px-3 py-1 text-uppercase fs-6">Statement of Academic Performance & Intelligence Evaluation</span>
    </div>

    <!-- Student Metadata Grid -->
    <div class="row g-3 mb-4 bg-light p-3 rounded border">
        <div class="col-sm-6">
            <table class="table table-sm table-borderless mb-0 small">
                <tr>
                    <td class="text-muted fw-bold" style="width: 35%;">Student Name:</td>
                    <td class="fw-bold fs-6 text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Enrollment No:</td>
                    <td class="fw-bold font-monospace text-primary"><?php echo htmlspecialchars($student['enrollment_no']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Course / Stream:</td>
                    <td><?php echo htmlspecialchars($student['course'] . ' (' . $student['specialization'] . ')'); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-sm-6">
            <table class="table table-sm table-borderless mb-0 small">
                <tr>
                    <td class="text-muted fw-bold" style="width: 35%;">Semester & Div:</td>
                    <td>Semester <?php echo htmlspecialchars($student['semester']); ?> (Div <?php echo htmlspecialchars($student['division']); ?>)</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Academic Session:</td>
                    <td>2024 – 2025</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Institutional Class Rank:</td>
                    <td><span class="badge bg-dark text-white">Rank #<?php echo $perf['class_rank'] ?? '—'; ?></span></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Examination Breakdown Table -->
    <div class="mb-4">
        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-list-alt text-primary me-2"></i>Curriculum Examination Evaluation</h6>
        <table class="table table-bordered table-marks align-middle text-center mb-0">
            <thead>
                <tr>
                    <th style="width: 15%;">Course Code</th>
                    <th style="width: 40%;" class="text-start">Course Title</th>
                    <th style="width: 15%;">Internal (30)</th>
                    <th style="width: 15%;">External (70)</th>
                    <th style="width: 15%;">Total (100)</th>
                    <th style="width: 10%;">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjectMarks)): ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">No marks records uploaded for this semester.</td></tr>
                <?php else: ?>
                    <?php 
                    $totObtained = 0;
                    $totMax = 0;
                    foreach ($subjectMarks as $sm): 
                        $totObtained += (float)$sm['marks_obtained'];
                        $totMax += (float)$sm['max_marks'];
                    ?>
                        <tr>
                            <td class="font-monospace fw-bold"><?php echo htmlspecialchars($sm['subject_code']); ?></td>
                            <td class="text-start"><?php echo htmlspecialchars($sm['subject_name']); ?></td>
                            <td><?php echo $sm['internal_marks']; ?></td>
                            <td><?php echo $sm['external_marks']; ?></td>
                            <td class="fw-bold text-dark"><?php echo $sm['marks_obtained']; ?> / <?php echo $sm['max_marks']; ?></td>
                            <td><span class="badge <?php echo ($sm['grade'] === 'F') ? 'bg-danger' : 'bg-light text-dark border'; ?>"><?php echo $sm['grade']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td colspan="4" class="text-end">Aggregate Marks:</td>
                        <td class="text-primary fs-6"><?php echo $totObtained; ?> / <?php echo $totMax; ?></td>
                        <td><?php echo $perf['grade'] ?? '—'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary Scorecard & Continuous Intelligence -->
    <div class="row g-3 mb-4 text-center">
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <span class="text-muted small text-uppercase fw-bold">Aggregate %</span>
                <div class="fs-4 fw-bold text-primary"><?php echo $perf['overall_percentage'] ?? '0'; ?>%</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <span class="text-muted small text-uppercase fw-bold">Attendance</span>
                <div class="fs-4 fw-bold text-success"><?php echo $perf['attendance_percentage'] ?? '0'; ?>%</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <span class="text-muted small text-uppercase fw-bold">Intelligence Score</span>
                <div class="fs-4 fw-bold text-dark"><?php echo $perf['performance_score'] ?? '0'; ?> / 100</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <span class="text-muted small text-uppercase fw-bold">Category</span>
                <div class="mt-1"><?php echo isset($perf['performance_category']) ? getCategoryBadge($perf['performance_category']) : '—'; ?></div>
            </div>
        </div>
    </div>

    <!-- Academic Insights & Behavioral Notes -->
    <?php if (!empty($insights)): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fas fa-brain text-primary me-2"></i>Academic Intelligence Diagnostic Insights</h6>
            <ul class="list-group list-group-flush small">
                <?php foreach ($insights as $ins): ?>
                    <li class="list-group-item px-0 py-2 border-0">
                        <i class="fas <?php echo $ins['icon']; ?> me-2"></i>
                        <strong><?php echo $ins['title']; ?>:</strong> <?php echo $ins['text']; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Honors & Citations -->
    <?php if (!empty($achievements)): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fas fa-award text-warning me-2"></i>Institutional Honors & Citations</h6>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($achievements as $ach): ?>
                    <span class="badge bg-warning text-dark border px-3 py-2">
                        <i class="fas <?php echo htmlspecialchars($ach['badge_icon']); ?> me-1"></i>
                        <?php echo htmlspecialchars($ach['title']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Authentication Signatures -->
    <div class="row pt-5 mt-4 text-center small text-muted">
        <div class="col-4">
            <div style="border-top: 1px solid #94a3b8; width: 80%; margin: 0 auto; padding-top: 0.5rem;">
                Faculty Mentor / Coordinator
            </div>
        </div>
        <div class="col-4">
            <div style="border-top: 1px solid #94a3b8; width: 80%; margin: 0 auto; padding-top: 0.5rem;">
                Head of Department (IT)
            </div>
        </div>
        <div class="col-4">
            <div style="border-top: 1px solid #94a3b8; width: 80%; margin: 0 auto; padding-top: 0.5rem;">
                Controller of Examinations
            </div>
        </div>
    </div>
</div>

</body>
</html>
