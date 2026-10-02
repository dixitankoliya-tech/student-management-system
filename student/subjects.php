<?php
/**
 * SMS 2.0: Student Subjects
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student data to know their academic context
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if ($student) {
    require_once '../config/academic_rules.php';
    $engine = new AcademicEngine($pdo);
    $active_cal = $engine->getCurrentAcademicPeriod();
    $student['academic_year'] = $active_cal ? $active_cal['academic_year'] : '2026-2027';
}

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch subjects mapped to the student's context, including assigned faculty
$subjectsStmt = $pdo->prepare("
    SELECT s.subject_code, s.subject_name, s.credits, s.subject_type, s.semester,
           f.first_name as fac_first_name, f.last_name as fac_last_name
    FROM subjects s
    LEFT JOIN faculty_subjects fs ON s.id = fs.subject_id 
          AND fs.course = ? 
          AND fs.academic_year = ? 
          AND fs.semester = ? 
          AND fs.division = ?
    LEFT JOIN faculty f ON fs.faculty_id = f.id
    WHERE s.course = ?
      AND s.semester = ?
    ORDER BY s.subject_name
");
$subjectsStmt->execute([
    $student['course'], $student['academic_year'], $student['semester'], $student['division'],
    $student['course'], $student['semester']
]);
$subjects = $subjectsStmt->fetchAll();

// If course mapping isn't perfect, let's also just fallback to subjects that match the course and semester directly.
// The above uses subquery `WHERE s.course_id = ...`. If that fails, I'll provide a more lenient query.

// Fallback logic for subjects if no course_id matches
if (empty($subjects)) {
    // Maybe course_id is not linked via `courses` table name correctly.
    // In Phase 2, `subjects` has `department_id`, `academic_year`, `subject_type`.
    // Wait, let's just use `faculty_subjects` to get the subjects they are enrolled in.
    $subjectsStmt2 = $pdo->prepare("
        SELECT s.subject_code, s.subject_name, s.credits, s.subject_type, fs.semester,
               f.first_name as fac_first_name, f.last_name as fac_last_name
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.id
        LEFT JOIN faculty f ON fs.faculty_id = f.id
        WHERE fs.course = ? 
          AND fs.academic_year = ? 
          AND fs.semester = ? 
          AND fs.division = ?
        ORDER BY s.subject_name
    ");
    $subjectsStmt2->execute([
        $student['course'], $student['academic_year'], $student['semester'], $student['division']
    ]);
    $subjects = $subjectsStmt2->fetchAll();
}

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Subjects</h2>
        <p class="text-muted mb-0">Curriculum for Semester <?php echo htmlspecialchars($student['semester']); ?></p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Type</th>
                        <th>Credits</th>
                        <th>Faculty Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="fas fa-book-open fa-2x mb-3 text-light"></i>
                                <p class="mb-0">No subjects are mapped for your current semester yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary fw-bold"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($sub['subject_name']); ?></strong></td>
                                <td>
                                    <?php if(isset($sub['subject_type']) && $sub['subject_type'] === 'Practical'): ?>
                                        <span class="badge bg-info-subtle text-info"><i class="fas fa-laptop-code me-1"></i>Practical</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary"><i class="fas fa-book me-1"></i>Theory</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($sub['credits']) ? htmlspecialchars($sub['credits']) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($sub['fac_first_name']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                <?php echo substr($sub['fac_first_name'], 0, 1) . substr($sub['fac_last_name'], 0, 1); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($sub['fac_first_name'] . ' ' . $sub['fac_last_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
