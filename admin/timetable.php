<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $course = $_POST['course'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    $semester = (int)($_POST['semester'] ?? 0);
    $division = $_POST['division'] ?? '';
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $faculty_id = (int)($_POST['faculty_id'] ?? 0);
    $room_no = $_POST['room_no'] ?? '';
    
    if (empty($day_of_week) || empty($start_time) || empty($end_time) || empty($course) || empty($academic_year) || !$semester || empty($division) || !$subject_id || !$faculty_id) {
        $error = "All fields except room number are required.";
    } elseif ($start_time >= $end_time) {
        $error = "Start time must be before end time.";
    } else {
        try {
            // Check for conflicts
            $conflictStmt = $pdo->prepare("
                SELECT * FROM timetables 
                WHERE day_of_week = ? 
                AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
                AND (faculty_id = ? OR (room_no != '' AND room_no = ?) OR (course = ? AND semester = ? AND division = ?))
            ");
            // The time overlap condition checks if new times overlap with existing times
            $conflictStmt->execute([
                $day_of_week, 
                $end_time, $start_time, // existing starts before new ends AND existing ends after new starts
                $end_time, $start_time, 
                $start_time, $end_time,
                $faculty_id, $room_no, $course, $semester, $division
            ]);
            
            $conflicts = $conflictStmt->fetchAll();
            if (count($conflicts) > 0) {
                $error = "Conflict detected! The selected time slot overlaps with an existing schedule for this Faculty, Room, or Division.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO timetables (day_of_week, start_time, end_time, course, academic_year, semester, division, subject_id, faculty_id, room_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$day_of_week, $start_time, $end_time, $course, $academic_year, $semester, $division, $subject_id, $faculty_id, $room_no]);
                $success = "Timetable entry saved successfully.";
                
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Add Timetable Entry', "$course Sem $semester $division", "Added $day_of_week $start_time-$end_time"]);
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch lookups
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query("SELECT year_name FROM academic_years WHERE status='Active' ORDER BY starting_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$divisions = $pdo->query("SELECT division_name FROM divisions WHERE status='Active' ORDER BY division_name")->fetchAll(PDO::FETCH_COLUMN);
$subjects = $pdo->query("SELECT id, subject_name, subject_code FROM subjects WHERE status='Active' ORDER BY subject_name")->fetchAll();
$faculty = $pdo->query("SELECT id, first_name, last_name, faculty_id FROM faculty WHERE status='Active' ORDER BY first_name")->fetchAll();

// Fetch timetable entries
$timetableStmt = $pdo->query("
    SELECT t.*, s.subject_name, f.first_name, f.last_name 
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.id
    JOIN faculty f ON t.faculty_id = f.id
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time
");
$entries = $timetableStmt->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Timetable Management</h2>
                    <p class="text-muted mb-0">Manage course schedules and assignments</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addTimetableModal">
                    <i class="fas fa-plus-circle me-2"></i> Add Entry
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <?php if (empty($entries)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-calendar-alt fs-3 mb-3 text-light"></i>
                            <h5 class="fw-bold">No Timetable Entries</h5>
                            <p>Timetable data will be displayed here in a grid format.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Faculty</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($entry['start_time'])) . ' - ' . date('h:i A', strtotime($entry['end_time'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($entry['course']); ?><br>
                                            <small class="text-muted">Sem <?php echo htmlspecialchars($entry['semester']); ?> | Div <?php echo htmlspecialchars($entry['division']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['room_no'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Timetable Modal -->
<div class="modal fade" id="addTimetableModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Timetable Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Day of Week</label>
                            <select name="day_of_week" class="form-select" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Course</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Academic Year</label>
                            <select name="academic_year" class="form-select" required>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y); ?>"><?php echo htmlspecialchars($y); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Semester</label>
                            <input type="number" name="semester" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Division</label>
                            <select name="division" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject...</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['subject_name'] . ' (' . $s['subject_code'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Faculty</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Select Faculty...</option>
                                <?php foreach ($faculty as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Room No (Optional)</label>
                            <input type="text" name="room_no" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
