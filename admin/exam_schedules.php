<?php
/**
 * SMS 2.0: Admin Exam Timetable & Schedules
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if (!$exam_id) {
    header("Location: examinations.php");
    exit;
}

$error = '';
$success = '';

// Fetch Examination Details
$stmt = $pdo->prepare("SELECT * FROM examinations WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    header("Location: examinations.php");
    exit;
}

// Handle Add/Update Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    if (isset($_POST['save_schedule'])) {
        $subject_id = (int)$_POST['subject_id'];
        $exam_date = $_POST['exam_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room_no = trim($_POST['room_no']);
        $invigilator_id = !empty($_POST['invigilator_id']) ? (int)$_POST['invigilator_id'] : null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO exam_schedules (exam_id, subject_id, exam_date, start_time, end_time, room_no, invigilator_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$exam_id, $subject_id, $exam_date, $start_time, $end_time, $room_no, $invigilator_id]);
            $success = "Schedule added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding schedule: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_schedule'])) {
        $schedule_id = (int)$_POST['schedule_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM exam_schedules WHERE id = ? AND exam_id = ?");
            $stmt->execute([$schedule_id, $exam_id]);
            $success = "Schedule deleted.";
        } catch (PDOException $e) {
            $error = "Error deleting schedule.";
        }
    }
}

// Fetch available subjects for this course/semester
$stmt = $pdo->prepare("
    SELECT * FROM subjects 
    WHERE semester = ? AND (course = ? OR course IS NULL)
    ORDER BY subject_code ASC
");
$stmt->execute([$exam['semester'], $exam['course']]);
$subjects = $stmt->fetchAll();

// Fetch all faculty for invigilation
$faculty = $pdo->query("SELECT id, first_name, last_name, employee_id FROM faculty WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();

// Fetch existing schedules
$stmt = $pdo->prepare("
    SELECT es.*, s.subject_code, s.subject_name, f.first_name, f.last_name 
    FROM exam_schedules es
    JOIN subjects s ON es.subject_id = s.id
    LEFT JOIN faculty f ON es.invigilator_id = f.id
    WHERE es.exam_id = ?
    ORDER BY es.exam_date ASC, es.start_time ASC
");
$stmt->execute([$exam_id]);
$schedules = $stmt->fetchAll();

// Extract mapped subject IDs to avoid duplicates in dropdown
$mapped_subjects = array_column($schedules, 'subject_id');

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<div id="page-content-wrapper" class="w-100 bg-light">
<?php include '../includes/admin_navbar.php'; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h3 text-gray-800 fw-bold mb-1">
                <a href="examinations.php" class="text-decoration-none text-muted me-2"><i class="fas fa-arrow-left"></i></a>
                Exam Timetable
            </h1>
            <p class="text-muted small mb-0">Manage schedule for: <strong><?= htmlspecialchars($exam['exam_name']) ?></strong> (<?= htmlspecialchars($exam['course']) ?> - Sem <?= $exam['semester'] ?>)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="print_halltickets.php?exam_id=<?= $exam_id ?>" class="btn btn-outline-success shadow-sm rounded-pill px-3" target="_blank">
                <i class="fas fa-id-card me-2"></i> Print Hall Tickets
            </a>
            <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Add Schedule
            </button>
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
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Subject</th>
                            <th>Room No</th>
                            <th>Invigilator</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fs-4 mb-2"></i><br>No timetable scheduled yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $sch): ?>
                            <tr>
                                <td><strong><?= date('d M Y', strtotime($sch['exam_date'])) ?></strong><br><small class="text-muted"><?= date('l', strtotime($sch['exam_date'])) ?></small></td>
                                <td><?= date('h:i A', strtotime($sch['start_time'])) ?> - <?= date('h:i A', strtotime($sch['end_time'])) ?></td>
                                <td><span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($sch['subject_code']) ?></span> <?= htmlspecialchars($sch['subject_name']) ?></td>
                                <td><span class="fw-bold"><?= htmlspecialchars($sch['room_no']) ?: 'TBA' ?></span></td>
                                <td>
                                    <?php if ($sch['first_name']): ?>
                                        <?= htmlspecialchars($sch['first_name'] . ' ' . $sch['last_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this schedule?');" class="d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="schedule_id" value="<?= $sch['id'] ?>">
                                        <button type="submit" name="delete_schedule" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 shadow">
            <?php csrfField(); ?>
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">Schedule Subject Exam</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-muted fw-semibold">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach ($subjects as $s): ?>
                            <?php if (!in_array($s['id'], $mapped_subjects)): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_code'] . ' - ' . $s['subject_name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-semibold">Exam Date</label>
                    <input type="date" name="exam_date" class="form-control" required min="<?= $exam['start_date'] ?>" max="<?= $exam['end_date'] ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label text-muted fw-semibold">Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted fw-semibold">End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-semibold">Room No / Venue</label>
                    <input type="text" name="room_no" class="form-control" placeholder="e.g. Block A - Room 101" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-semibold">Invigilator (Faculty)</label>
                    <select name="invigilator_id" class="form-select">
                        <option value="">-- Assign Later --</option>
                        <?php foreach ($faculty as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name'] . ' (' . $f['employee_id'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_schedule" class="btn btn-primary px-4">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
