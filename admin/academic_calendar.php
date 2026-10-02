<?php
/**
 * SMS 2.0: Admin Academic Calendar Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_event'])) {
        $academic_session = trim($_POST['academic_session'] ?? '');
        $event_type = $_POST['event_type'] ?? '';
        $event_name = trim($_POST['event_name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $semester = !empty($_POST['semester']) ? (int)$_POST['semester'] : null;
        $course = !empty($_POST['course']) ? $_POST['course'] : null;
        $description = trim($_POST['description'] ?? '');
        
        if (empty($academic_session) || empty($event_type) || empty($event_name) || empty($start_date)) {
            $error = 'Session, event type, name, and start date are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO academic_calendar (academic_session, event_type, event_name, start_date, end_date, semester, course, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$academic_session, $event_type, $event_name, $start_date, $end_date, $semester, $course, $description]);
                logActivity($pdo, 'ADD_CALENDAR_EVENT', null, "Added calendar event: $event_name");
                $success = 'Calendar event added successfully.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_event'])) {
        $event_id = (int)$_POST['event_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM academic_calendar WHERE id = ?");
            $stmt->execute([$event_id]);
            logActivity($pdo, 'DELETE_CALENDAR_EVENT', null, "Deleted calendar event $event_id");
            $success = 'Event deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Error deleting event.';
        }
    }
}

// Fetch filter
$filter_session = $_GET['session'] ?? '';

// Fetch sessions for filter
$sessions = $pdo->query("SELECT DISTINCT academic_session FROM academic_calendar ORDER BY academic_session DESC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch events
$query = "SELECT * FROM academic_calendar";
$params = [];
if (!empty($filter_session)) {
    $query .= " WHERE academic_session = ?";
    $params[] = $filter_session;
}
$query .= " ORDER BY start_date ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Fetch lookups
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);

$event_types = ['Semester Start', 'Semester End', 'Exam Period', 'Holiday', 'Result Publication', 'Assignment Period', 'Registration', 'Other'];
?>
<?php
include '../includes/admin_header.php';
?>
    <div class="d-flex" id="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <?php include '../includes/admin_navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Academic Calendar</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0"><i class="fas fa-calendar-day me-2 text-primary"></i>Academic Calendar</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Filter -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1">Academic Session</label>
                                <select name="session" class="form-select form-select-sm">
                                    <option value="">All Sessions</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= $filter_session === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                                <a href="academic_calendar.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Session</th>
                                        <th>Type</th>
                                        <th>Event</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Semester</th>
                                        <th>Program</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $ev): ?>
                                    <tr>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($ev['academic_session']) ?></span></td>
                                        <td>
                                            <?php
                                            $type_colors = [
                                                'Semester Start' => 'success', 'Semester End' => 'danger',
                                                'Exam Period' => 'warning', 'Holiday' => 'info',
                                                'Result Publication' => 'primary', 'Assignment Period' => 'secondary',
                                                'Registration' => 'success', 'Other' => 'light'
                                            ];
                                            $color = $type_colors[$ev['event_type']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($ev['event_type']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
                                            <?php if (!empty($ev['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($ev['description']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d M Y', strtotime($ev['start_date'])) ?></td>
                                        <td><?= $ev['end_date'] ? date('d M Y', strtotime($ev['end_date'])) : '—' ?></td>
                                        <td><?= $ev['semester'] ? 'Sem ' . $ev['semester'] : 'All' ?></td>
                                        <td><?= $ev['course'] ? htmlspecialchars($ev['course']) : 'All' ?></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this event?');">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                                <button type="submit" name="delete_event" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($events)): ?>
                                        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No calendar events found</h5><p>There are currently no records available in this section.</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add Calendar Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Academic Session</label>
                            <input type="text" name="academic_session" class="form-control" required placeholder="e.g., 2026-2027" value="2026-2027">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Event Type</label>
                            <select name="event_type" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($event_types as $et): ?>
                                    <option value="<?= $et ?>"><?= $et ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Event Name</label>
                            <input type="text" name="event_name" class="form-control" required placeholder="e.g., Mid-Semester Exams">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Semester (optional)</label>
                            <input type="number" name="semester" class="form-control" min="1" max="12">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Program (optional)</label>
                            <select name="course" class="form-select">
                                <option value="">All Programs</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description (optional)</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_event" class="btn btn-primary px-4">Add Event</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

