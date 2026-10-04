<?php
/**
 * SMS 2.0: Academic Promotion Engine
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

$courses = $pdo->query("SELECT * FROM courses WHERE status = 'Active' ORDER BY course_code")->fetchAll();

$selected_course = $_GET['course'] ?? '';
$selected_semester = $_GET['semester'] ?? '';

$students = [];
if ($selected_course && $selected_semester) {
    $stmt = $pdo->prepare("
        SELECT st.*, 
        (SELECT COUNT(*) FROM student_marks sm WHERE sm.student_id = st.id AND sm.grade = 'F') as backlog_count
        FROM students st 
        WHERE st.course = ? AND st.semester = ? AND st.status = 'Active'
    ");
    $stmt->execute([$selected_course, $selected_semester]);
    $students = $stmt->fetchAll();
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 text-gray-800"><i class="fas fa-level-up-alt text-primary me-2"></i>Academic Promotion Engine</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Select Course</label>
                <select name="course" class="form-select" required>
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_name']); ?>" <?php echo $selected_course === $c['course_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Current Semester</label>
                <select name="semester" class="form-select" required>
                    <option value="">-- Semester --</option>
                    <?php for($i=1; $i<=10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $selected_semester == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Load Students</button>
            </div>
        </form>
    </div>
</div>

<?php 
$max_semesters = 6;
if ($selected_course) {
    $stmtC = $pdo->prepare("SELECT duration_years FROM courses WHERE course_name = ?");
    $stmtC->execute([$selected_course]);
    $courseObj = $stmtC->fetch();
    $max_semesters = $courseObj ? ($courseObj['duration_years'] * 2) : 6;
}
$is_graduating = ($selected_semester == $max_semesters);
?>
<?php if ($selected_course && $selected_semester): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary">Students Eligible for <?= $is_graduating ? "Graduation" : "Promotion (Sem $selected_semester &rarr; " . ((int)$selected_semester + 1) . ")" ?></h6>
    </div>
    <div class="card-body">
        <?php if (count($students) > 0): ?>
            <form action="process_promotion.php" method="POST" id="promotionForm">
                <input type="hidden" name="course" value="<?php echo htmlspecialchars($selected_course); ?>">
                <input type="hidden" name="from_semester" value="<?php echo htmlspecialchars($selected_semester); ?>">
                <input type="hidden" name="to_semester" value="<?php echo (int)$selected_semester + 1; ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">
                                    <input class="form-check-input" type="checkbox" id="selectAll" checked>
                                </th>
                                <th>Enrollment No</th>
                                <th>Student Name</th>
                                <th>Current Sem</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $stu): 
                                $is_eligible = $stu['backlog_count'] <= 3;
                            ?>
                            <tr>
                                <td>
                                    <?php if ($is_eligible): ?>
                                        <input class="form-check-input student-checkbox" type="checkbox" name="student_ids[]" value="<?php echo $stu['id']; ?>" checked>
                                    <?php else: ?>
                                        <input class="form-check-input" type="checkbox" disabled>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace fw-bold"><?php echo htmlspecialchars($stu['enrollment_no']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            <?php echo substr($stu['first_name'],0,1) . substr($stu['last_name'],0,1); ?>
                                        </div>
                                        <?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?>
                                    </div>
                                </td>
                                <td><span class="badge bg-secondary">Semester <?php echo htmlspecialchars($stu['semester']); ?></span></td>
                                <td>
                                    <?php if ($is_eligible): ?>
                                        <span class="badge bg-success">Eligible (<?= $stu['backlog_count'] ?> ATKT)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Blocked (<?= $stu['backlog_count'] ?> ATKT)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light rounded border">
                    <div>
                        <?php if ($is_graduating): ?>
                            <span class="fw-bold">Action:</span> <span class="badge bg-success fs-6 ms-2">Graduate Students (Mark Alumni)</span>
                        <?php else: ?>
                            <span class="fw-bold">Promote to:</span> <span class="badge bg-primary fs-6 ms-2">Semester <?= (int)$selected_semester + 1 ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-success px-4" onclick="return confirm('Are you sure you want to <?= $is_graduating ? "graduate" : "promote" ?> the selected students?');">
                        <i class="fas <?= $is_graduating ? "fa-graduation-cap" : "fa-forward" ?> me-2"></i> <?= $is_graduating ? "Graduate" : "Promote" ?> Selected Students
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info border-0 shadow-sm text-center py-4 mb-0">
                <i class="fas fa-info-circle fs-4 mb-2 d-block text-info"></i>
                No active students found in this course and semester.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
