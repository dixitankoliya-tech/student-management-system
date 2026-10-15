<?php
/**
 * SMS 2.0: Faculty - Marks Entry
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$error = '';
$success = '';

// 1. Fetch assigned subjects for this faculty
try {
    $stmt = $pdo->prepare("
        SELECT fs.*, s.subject_name, s.subject_code, s.max_internal, s.max_external 
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.id
        WHERE fs.faculty_id = ?
        ORDER BY fs.course, fs.semester, s.subject_name
    ");
    $stmt->execute([$faculty_id]);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $assignments = [];
}

// 2. Fetch active examinations scoped to this faculty's assigned courses
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.* FROM examinations e
        JOIN faculty_subjects fs ON e.course = fs.course AND e.semester = fs.semester
        WHERE fs.faculty_id = ? AND e.status NOT IN ('Completed', 'Results Published')
        ORDER BY e.start_date DESC
    ");
    $stmt->execute([$faculty_id]);
    $active_exams = $stmt->fetchAll();
} catch (PDOException $e) {
    $active_exams = [];
}

// 3. Handle form submission (Saving Marks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    requireValidCsrf();
    $subject_id = (int)$_POST['subject_id'];
    $division = $_POST['division'];
    $exam_id = (int)$_POST['exam_id'];
    $action_type = $_POST['action_type']; // 'Draft' or 'Submitted'
    $marks_data = $_POST['marks'] ?? []; // array of student_id => marks_obtained

    // Validate ownership
    $stmt = $pdo->prepare("SELECT fs.course, fs.semester, s.max_internal, s.max_external FROM faculty_subjects fs JOIN subjects s ON fs.subject_id=s.id WHERE fs.faculty_id = ? AND fs.subject_id = ? AND fs.division = ?");
    $stmt->execute([$faculty_id, $subject_id, $division]);
    $subjectInfo = $stmt->fetch();
    
    if ($subjectInfo) {
        $max_marks = $subjectInfo['max_external']; // Simplification for now

        $pdo->beginTransaction();
        try {
            // Prepare statement to verify student belongs to this class
            $verifyStudentStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND course = ? AND semester = ? AND division = ?");
            
            foreach ($marks_data as $student_id => $mark_val) {
                if ($mark_val === '') continue; // Skip empty
                $mark = (float)$mark_val;
                if ($mark < 0 || $mark > $max_marks) {
                    throw new Exception("Marks must be between 0 and $max_marks.");
                }
                
                // Verify student belongs to this faculty's class
                $verifyStudentStmt->execute([$student_id, $subjectInfo['course'], $subjectInfo['semester'], $division]);
                if (!$verifyStudentStmt->fetch()) {
                    throw new Exception("Unauthorized: Student ID $student_id does not belong to this class.");
                }
                
                // Check current status
                $checkStmt = $pdo->prepare("SELECT status FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?");
                $checkStmt->execute([$exam_id, $student_id, $subject_id]);
                $existing = $checkStmt->fetch();
                
                if ($existing && in_array($existing['status'], ['Submitted', 'Locked'])) {
                    throw new Exception("Marks for one or more students are already submitted or locked and cannot be edited.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO marks (exam_id, student_id, subject_id, faculty_id, marks_obtained, max_marks, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), status = VALUES(status)
                ");
                $stmt->execute([$exam_id, $student_id, $subject_id, $faculty_id, $mark, $max_marks, $action_type]);

                // Send notification if submitted
                if ($action_type === 'Submitted') {
                    $notif_title = "Exam Marks Published";
                    $notif_msg = "Your marks for Subject $subject_id in Exam $exam_id have been published.";
                    send_notification($pdo, 'student', $student_id, $notif_title, $notif_msg, 'academic', 'results.php');
                }
            }
            $pdo->commit();
            logActivity($pdo, 'ENTER_MARKS', null, "Entered marks for Exam $exam_id, Subject $subject_id ($action_type)");
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Save Marks DB Error: " . $e->getMessage());
            $error = APP_DEBUG ? "Database error: " . $e->getMessage() : "A database error occurred while saving marks. Please try again.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = "Unauthorized to enter marks for this subject.";
    }
}

// 4. Handle loading students
$selected_assignment = null;
$selected_exam = null;
$students = [];
$existing_marks = [];
$can_edit = true;

if (isset($_GET['assignment_id']) && isset($_GET['exam_id'])) {
    $assignment_id = (int)$_GET['assignment_id'];
    $exam_id = (int)$_GET['exam_id'];
    
    foreach ($assignments as $a) {
        if ($a['id'] == $assignment_id) {
            $selected_assignment = $a;
            break;
        }
    }
    
    foreach ($active_exams as $e) {
        if ($e['id'] == $exam_id) {
            $selected_exam = $e;
            break;
        }
    }
    
    if ($selected_assignment && $selected_exam) {
        // Fetch students
        $stmt = $pdo->prepare("
            SELECT st.id, st.enrollment_no, st.first_name, st.last_name 
            FROM students st
            JOIN student_academic_enrollments sae ON st.id = sae.student_id AND sae.status = 'Active'
            WHERE sae.course = ? AND sae.semester = ? AND sae.division = ? AND sae.academic_year = ?
            ORDER BY st.enrollment_no ASC
        ");
        $stmt->execute([$selected_assignment['course'], $selected_assignment['semester'], $selected_assignment['division'], $selected_assignment['academic_year']]);
        $students = $stmt->fetchAll();
        
        // Fetch existing marks
        $stmt = $pdo->prepare("
            SELECT student_id, marks_obtained, status 
            FROM marks 
            WHERE exam_id = ? AND subject_id = ?
        ");
        $stmt->execute([$exam_id, $selected_assignment['subject_id']]);
        $existing = $stmt->fetchAll();
        foreach ($existing as $row) {
            $existing_marks[$row['student_id']] = $row;
            if (in_array($row['status'], ['Submitted', 'Locked'])) {
                $can_edit = false; // If any student is submitted, lock the whole form for simplicity, or we can lock per student. Let's do per student in UI.
            }
        }
    }
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Marks Entry</h1>
        <p class="text-muted small mb-0">Manage Marks Entry in the system.</p>
    </div>
    <div class="d-flex gap-2">
        
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-muted fw-semibold">Select Class (Subject + Division)</label>
                        <select name="assignment_id" class="form-select" required>
                            <option value="">Select...</option>
                            <?php foreach ($assignments as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (isset($_GET['assignment_id']) && $_GET['assignment_id'] == $a['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['subject_name']) ?> - <?= htmlspecialchars($a['course']) ?> Sem <?= $a['semester'] ?> Div <?= htmlspecialchars($a['division']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label text-muted fw-semibold">Select Examination</label>
                        <select name="exam_id" class="form-select" required>
                            <option value="">Select Exam...</option>
                            <?php foreach ($active_exams as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= (isset($_GET['exam_id']) && $_GET['exam_id'] == $e['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['exam_name']) ?> (<?= htmlspecialchars($e['course']) ?> - <?= htmlspecialchars($e['academic_year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Load Students</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_assignment && $selected_exam): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Marks Entry Sheet</h5>
                        <small class="text-muted"><?= htmlspecialchars($selected_assignment['subject_name']) ?> • Max Marks: <?= $selected_assignment['max_external'] ?></small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form method="POST" class="p-4">
                        <?php csrfField(); ?>
                        <input type="hidden" name="subject_id" value="<?= $selected_assignment['subject_id'] ?>">
                        <input type="hidden" name="division" value="<?= $selected_assignment['division'] ?>">
                        <input type="hidden" name="exam_id" value="<?= $selected_exam['id'] ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Enrollment No</th>
                                        <th>Student Name</th>
                                        <th style="width: 200px;">Marks Obtained</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $all_locked = true;
                                    foreach ($students as $student): 
                                        $mark_data = $existing_marks[$student['id']] ?? null;
                                        $mark_val = $mark_data ? $mark_data['marks_obtained'] : '';
                                        $status = $mark_data ? $mark_data['status'] : 'Not Entered';
                                        $is_locked = in_array($status, ['Submitted', 'Locked']);
                                        if (!$is_locked) $all_locked = false;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['enrollment_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td>
                                            <input type="number" step="0.01" name="marks[<?= $student['id'] ?>]" class="form-control" value="<?= htmlspecialchars($mark_val) ?>" max="<?= $selected_assignment['max_external'] ?>" min="0" <?= $is_locked ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <?php if ($status === 'Not Entered' || $status === 'Draft'): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= $status ?></span>
                                            <?php elseif ($status === 'Submitted'): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-lock me-1"></i>Submitted</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-double me-1"></i>Locked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($students)): ?>
                                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No students found</h5><p>No students found in this class..</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($students) && !$all_locked): ?>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" name="action_type" value="Draft" class="btn btn-outline-secondary btn-lg px-4 me-3">
                                    <i class="fas fa-save me-2"></i> Save as Draft
                                </button>
                                <button type="submit" name="action_type" value="Submitted" class="btn btn-primary btn-lg px-4" onclick="return confirm('Once submitted, you cannot edit the marks. Are you sure?');">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Marks
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>

<?php include '../includes/footer.php'; ?>

