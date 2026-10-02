<?php
/**
 * SMS 2.0: Student Marks
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
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

// Fetch published marks
$marksStmt = $pdo->prepare("
    SELECT 
        s.subject_name,
        s.subject_code,
        e.exam_name,
        e.start_date,
        m.max_marks,
        m.marks_obtained,
        m.status
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    JOIN examinations e ON m.exam_id = e.id
    WHERE m.student_id = ? AND m.status = 'Locked'
    ORDER BY e.start_date DESC, s.subject_name ASC
");
$marksStmt->execute([$student_id]);
$marks = $marksStmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Marks</h2>
        <p class="text-muted mb-0">View your published examination results</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Examination</th>
                        <th>Subject</th>
                        <th class="text-center">Maximum Marks</th>
                        <th class="text-center">Marks Obtained</th>
                        <th class="text-center">Percentage</th>
                        <th class="text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($marks)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-list fs-3 mb-3 text-light"></i>
                                <p class="mb-0">No published marks available yet.</p>
                                <small>Marks will appear here once they are reviewed and published by the administration.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($marks as $m): 
                            $pct = $m['max_marks'] > 0 ? round(($m['marks_obtained'] / $m['max_marks']) * 100, 2) : 0;
                            $pctClass = 'success';
                            if ($pct < 40) $pctClass = 'danger';
                            elseif ($pct < 60) $pctClass = 'warning';
                        ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?php echo htmlspecialchars($m['exam_name']); ?></strong>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($m['start_date'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($m['subject_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['subject_code']); ?></small>
                                </td>
                                <td class="text-center fw-semibold text-secondary"><?php echo $m['max_marks']; ?></td>
                                <td class="text-center fw-bold text-dark"><?php echo $m['marks_obtained']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $pctClass; ?>-subtle text-<?php echo $pctClass; ?> fs-6 px-3 py-2 rounded-pill">
                                        <?php echo $pct; ?>%
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Published</span>
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
