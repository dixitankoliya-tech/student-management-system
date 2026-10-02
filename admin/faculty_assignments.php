<?php
/**
 * SMS 2.0: Admin Faculty Assignments (Subject Offerings)
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../config/academic_rules.php';

requireAuth();
requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['assign_faculty'])) {
        $faculty_id = (int)$_POST['faculty_id'];
        $subject_id = (int)$_POST['subject_id'];
        $course = $_POST['course'] ?? '';
        $academic_year = $_POST['academic_year'] ?? '';
        $semester = (int)($_POST['semester'] ?? 1);
        $division = $_POST['division'] ?? '';
        
        if (!$faculty_id || !$subject_id || empty($course) || empty($academic_year) || empty($division)) {
            $error = 'All fields are required.';
        } else {
            $academicEngine = new AcademicEngine($pdo);
            $semesterType = AcademicEngine::getSemesterType($semester);
            if (!$academicEngine->canAssignFacultySubject($faculty_id, $academic_year, $semesterType)) {
                $error = 'Faculty workload limit reached. Maximum active subjects for this academic period: 4.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id, course, academic_year, semester, division) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$faculty_id, $subject_id, $course, $academic_year, $semester, $division]);
                    logActivity($pdo, 'ASSIGN_FACULTY', null, "Assigned faculty $faculty_id to subject $subject_id for $course Sem $semester Div $division");
                    $success = 'Faculty assigned to subject offering successfully.';
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        $error = 'This assignment already exists.';
                    } else {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    } elseif (isset($_POST['remove_assignment'])) {
        $assign_id = (int)$_POST['assignment_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM faculty_subjects WHERE id = ?");
            $stmt->execute([$assign_id]);
            logActivity($pdo, 'REMOVE_ASSIGNMENT', null, "Removed subject offering assignment $assign_id");
            $success = 'Assignment removed successfully.';
        } catch (PDOException $e) {
            $error = 'Error removing assignment.';
        }
    }
}

// Fetch lookups
$facultyList = $pdo->query("SELECT id, faculty_id, first_name, last_name FROM faculty WHERE status='Active' ORDER BY first_name")->fetchAll();
$subjectsList = $pdo->query("SELECT id, subject_code, subject_name FROM subjects WHERE status='Active' ORDER BY subject_name")->fetchAll();
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query("SELECT year_name FROM academic_years WHERE status='Active' ORDER BY starting_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$divisions = $pdo->query("SELECT division_name FROM divisions WHERE status='Active' ORDER BY division_name")->fetchAll(PDO::FETCH_COLUMN);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filters
$filter_course = $_GET['fc'] ?? '';
$filter_faculty = $_GET['ff'] ?? '';

$where = [];
$params = [];
if (!empty($filter_course)) {
    $where[] = "fs.course = ?";
    $params[] = $filter_course;
}
if (!empty($filter_faculty)) {
    $where[] = "fs.faculty_id = ?";
    $params[] = (int)$filter_faculty;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects fs $where_sql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// Fetch Assignments
$stmt = $pdo->prepare("
    SELECT fs.*, 
    f.faculty_id as fac_code, f.first_name, f.last_name,
    s.subject_code, s.subject_name,
    (SELECT COUNT(*) FROM student_academic_enrollments sae 
     WHERE sae.course = fs.course 
       AND sae.semester = fs.semester 
       AND sae.academic_year = fs.academic_year 
       AND sae.division = fs.division 
       AND sae.status = 'Active') as enrolled_students
    FROM faculty_subjects fs
    JOIN faculty f ON fs.faculty_id = f.id
    JOIN subjects s ON fs.subject_id = s.id
    $where_sql
    ORDER BY fs.academic_year DESC, fs.course ASC, fs.semester ASC, f.first_name ASC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$assignments = $stmt->fetchAll();
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
                        <li class="breadcrumb-item active">Subject Offerings</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Subject Offerings</h2>
                        <small class="text-muted"><?= $total ?> total offerings</small>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>Assign Faculty
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

                <!-- Filters -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1">Program</label>
                                <select name="fc" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-1">Faculty</label>
                                <select name="ff" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <?php foreach ($facultyList as $f): ?>
                                        <option value="<?= $f['id'] ?>" <?= $filter_faculty == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                                <a href="faculty_assignments.php" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                                        <th>Faculty</th>
                                        <th>Subject</th>
                                        <th>Program</th>
                                        <th>Year / Sem</th>
                                        <th>Division</th>
                                        <th>Students</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $a): ?>
                                        <?php 
                                            $capacity = 100;
                                            $isFull = $a['enrolled_students'] >= $capacity;
                                            $available = $capacity - $a['enrolled_students'];
                                        ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($a['fac_code']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($a['subject_name']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($a['subject_code']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($a['course']) ?></td>
                                        <td><?= htmlspecialchars($a['academic_year']) ?> (Sem <?= $a['semester'] ?>)</td>
                                        <td><span class="badge bg-info text-dark">Div <?= htmlspecialchars($a['division']) ?></span></td>
                                        <td><span class="badge bg-primary"><?= $a['enrolled_students'] ?></span></td>
                                        <td><?= $capacity ?></td>
                                        <td>
                                            <?php if ($isFull): ?>
                                                <span class="badge bg-danger">FULL</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $available ?> SEATS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this offering assignment?');">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                                <button type="submit" name="remove_assignment" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($assignments)): ?>
                                        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No subject offerings found</h5><p>There are currently no records available in this section.</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&fc=<?= urlencode($filter_course) ?>&ff=<?= urlencode($filter_faculty) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
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
                    <h5 class="modal-title">Assign Faculty to Subject Offering</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-semibold">Faculty Member</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Select Faculty...</option>
                                <?php foreach ($facultyList as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name'] . ' (' . $f['faculty_id'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-semibold">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject...</option>
                                <?php foreach ($subjectsList as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name'] . ' (' . $s['subject_code'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-semibold">Program</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-semibold">Academic Year</label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>"><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-semibold">Semester</label>
                            <input type="number" name="semester" class="form-control" required min="1" max="12">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-semibold">Division</label>
                            <select name="division" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_faculty" class="btn btn-primary px-4">Assign Faculty</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

