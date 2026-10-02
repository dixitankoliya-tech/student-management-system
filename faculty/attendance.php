<?php
/**
 * SMS 2.0: Faculty - Attendance Entry
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$error = '';
$success = '';

// 1. Fetch assigned subjects for this faculty to populate the dropdown
try {
    $stmt = $pdo->prepare("
        SELECT fs.*, s.subject_name, s.subject_code 
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

// 2. Handle form submission (Saving attendance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    requireValidCsrf();
    $subject_id = (int)$_POST['subject_id'];
    $division = $_POST['division'];
    $date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance'] ?? []; // array of student_id => status

    // Verify ownership again
    $stmt = $pdo->prepare("SELECT course, semester FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? AND division = ?");
    $stmt->execute([$faculty_id, $subject_id, $division]);
    $subjectInfo = $stmt->fetch();
    if ($subjectInfo) {
        $pdo->beginTransaction();
        try {
            // Prepare upsert statement once
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, faculty_id, subject_id, division, attendance_date, status)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), faculty_id = VALUES(faculty_id)
            ");
            
            // Get subject name for notification
            $subjStmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
            $subjStmt->execute([$subject_id]);
            $subject_name = $subjStmt->fetchColumn() ?: "Subject $subject_id";

            $existing_stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE faculty_id = ? AND subject_id = ? AND division = ? AND attendance_date = ?");
            $existing_stmt->execute([$faculty_id, $subject_id, $division, $date]);
            $existing_records = $existing_stmt->fetchAll(PDO::FETCH_KEY_PAIR);


            $verifyStudentStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND course = ? AND semester = ? AND division = ?");

            foreach ($attendance_data as $student_id => $status) {
                // Verify student belongs to this faculty's class
                $verifyStudentStmt->execute([$student_id, $subjectInfo['course'], $subjectInfo['semester'], $division]);
                if (!$verifyStudentStmt->fetch()) {
                    throw new PDOException("Unauthorized: Student ID $student_id does not belong to this class.");
                }
                
                $stmt->execute([$student_id, $faculty_id, $subject_id, $division, $date, $status]);
                
                                $old_status = $existing_records[$student_id] ?? null;
                $status_changed = ($old_status !== $status);
                
                // Notify if newly absent
                if ($status === 'Absent' && $status_changed) {
                    $notif_title = "Absence Alert: " . $subject_name;
                    $notif_msg = "You were marked Absent for $subject_name on " . date('M j, Y', strtotime($date)) . ".";
                    send_notification($pdo, 'student', $student_id, $notif_title, $notif_msg, 'alert', 'attendance.php');
                }
            }
            $pdo->commit();
            logActivity($pdo, 'MARK_ATTENDANCE', null, "Marked attendance for Subject $subject_id, Div $division on $date");
            $success = "Attendance saved successfully.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error saving attendance: " . $e->getMessage();
        }
    } else {
        $error = "Unauthorized to mark attendance for this subject and division.";
    }
}

// 3. Handle loading students for a selected subject, division, and date
$selected_assignment = null;
$students = [];
$existing_attendance = [];
$selected_date = date('Y-m-d');

if (isset($_GET['assignment_id']) && isset($_GET['date'])) {
    $assignment_id = (int)$_GET['assignment_id'];
    $selected_date = $_GET['date'];
    
    // Find the assignment
    foreach ($assignments as $a) {
        if ($a['id'] == $assignment_id) {
            $selected_assignment = $a;
            break;
        }
    }
    
    if ($selected_assignment) {
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
        
        // Fetch existing attendance if any
        $stmt = $pdo->prepare("
            SELECT student_id, status FROM attendance 
            WHERE subject_id = ? AND attendance_date = ? AND division = ?
        ");
        $stmt->execute([$selected_assignment['subject_id'], $selected_date, $selected_assignment['division']]);
        $existing = $stmt->fetchAll();
        foreach ($existing as $row) {
            $existing_attendance[$row['student_id']] = $row['status'];
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
        <h1 class="h3 text-gray-800 fw-bold mb-1">Record Attendance</h1>
        <p class="text-muted small mb-0">Manage Record Attendance in the system.</p>
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
                    <div class="col-md-6">
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
                    <div class="col-md-4">
                        <label class="form-label text-muted fw-semibold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selected_date) ?>" required max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Load Students</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_assignment): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Attendance Sheet</h5>
                        <small class="text-muted"><?= htmlspecialchars($selected_assignment['subject_name']) ?> • Date: <?= date('d M Y', strtotime($selected_date)) ?></small>
                    </div>
                    <?php if (!empty($existing_attendance)): ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-info-circle me-1"></i> Already Marked - You can update it</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <form method="POST" class="p-4">
                        <?php csrfField(); ?>
                        <input type="hidden" name="subject_id" value="<?= $selected_assignment['subject_id'] ?>">
                        <input type="hidden" name="division" value="<?= $selected_assignment['division'] ?>">
                        <input type="hidden" name="attendance_date" value="<?= $selected_date ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Enrollment No</th>
                                        <th>Student Name</th>
                                        <th class="text-center" style="width: 200px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        $status = $existing_attendance[$student['id']] ?? 'Present';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['enrollment_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="attendance[<?= $student['id'] ?>]" id="present_<?= $student['id'] ?>" value="Present" <?= $status === 'Present' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-success btn-sm" for="present_<?= $student['id'] ?>">P</label>
                                                
                                                <input type="radio" class="btn-check" name="attendance[<?= $student['id'] ?>]" id="absent_<?= $student['id'] ?>" value="Absent" <?= $status === 'Absent' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-danger btn-sm" for="absent_<?= $student['id'] ?>">A</label>
                                                
                                                <input type="radio" class="btn-check" name="attendance[<?= $student['id'] ?>]" id="leave_<?= $student['id'] ?>" value="Leave" <?= $status === 'Leave' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-warning btn-sm" for="leave_<?= $student['id'] ?>">L</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($students)): ?>
                                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No students found</h5><p>No students found in this class..</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($students)): ?>
                            <div class="text-end mt-4">
                                <button type="submit" name="save_attendance" class="btn btn-primary px-5 btn-lg">
                                    <i class="fas fa-save me-2"></i> Save Attendance
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
<?php include '../includes/footer.php'; ?>



