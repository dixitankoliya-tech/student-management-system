<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';

// Pagination variables
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [20, 50, 100])) $limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search & Filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$filter_course = isset($_GET['course']) ? trim($_GET['course']) : '';
$filter_year = isset($_GET['admission_year']) ? trim($_GET['admission_year']) : '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(enrollment_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
}

if (!empty($filter_semester)) {
    $whereConditions[] = "semester = ?";
    $params[] = $filter_semester;
}

if (!empty($filter_course)) {
    $whereConditions[] = "course = ?";
    $params[] = $filter_course;
}

if (!empty($filter_year)) {
    $whereConditions[] = "admission_year = ?";
    $params[] = $filter_year;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}
$countParams = $params;

// Fetch total records for pagination
try {
    $countSql = "SELECT COUNT(*) FROM students $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch(PDOException $e) {
    $error = "Error fetching counts.";
    error_log("DB Count Error: " . $e->getMessage());
}

// Fetch students
try {
    $sql = "SELECT * FROM students $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error fetching data.";
    error_log("DB Fetch Error: " . $e->getMessage());
}

$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active'")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Student Directory</h1>
        <p class="text-muted small mb-0">Manage and view all enrolled students in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_students.php?<?php echo isset($_SERVER['QUERY_STRING']) ? htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-outline-success shadow-sm rounded-pill px-3">
            <i class="fas fa-file-csv me-1"></i> Export
        </a>
        <a href="add_student.php" class="btn btn-primary shadow-sm rounded-pill px-3">
            <i class="fas fa-plus me-1"></i> Add Student
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body bg-light rounded">
        <form method="GET" action="students.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Search Students</label>
                <div class="input-group shadow-sm rounded">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Name, Email, or Enrollment No..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Program</label>
                <select name="course" class="form-select">
                    <option value="">All Programs</option>
                    <?php foreach($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($filter_course == $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Sem</label>
                <select name="semester" class="form-select">
                    <option value="">All Sem</option>
                    <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($filter_semester == $i) ? 'selected' : ''; ?>>Sem <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-bold">Adm Year</label>
                <select name="admission_year" class="form-select">
                    <option value="">All Years</option>
                    <?php for($y=date('Y'); $y>=2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($filter_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label text-muted small fw-bold">Limit</label>
                <select name="limit" class="form-select">
                    <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="col-md-2 d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="students.php" class="btn btn-outline-secondary" title="Clear Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Data Table Card -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Student Directory <span class="badge bg-secondary ms-2 rounded-pill"><?php echo isset($total_records) ? $total_records : 0; ?> Total</span></h6>
    </div>
    <div class="card-body p-0">
        <div class="p-3 bg-light border-bottom">
            <p class="text-muted small mb-0">Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?>–<?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> students</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="dataTable" width="100%" cellspacing="0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Division</th>
                        <th>Academic Year</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if(!empty($students)): ?>
                        <?php foreach($students as $student): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold text-dark" style="font-size: 15px;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                <div class="text-muted" style="font-size: 13px;"><?php echo htmlspecialchars($student['enrollment_no']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['course']); ?></span></td>
                            <td>Sem <?php echo htmlspecialchars($student['semester']); ?></td>
                            <td><?php echo htmlspecialchars($student['division']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($student['admission_year']); ?></td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px;">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item py-2" href="view_student.php?id=<?php echo $student['id']; ?>"><i class="fas fa-eye text-primary me-2"></i> View Profile & Intelligence</a></li>
                                        <li><a class="dropdown-item py-2" href="manage_marks.php?student_id=<?php echo $student['id']; ?>"><i class="fas fa-marker text-success me-2"></i> Enter / Edit Marks</a></li>
                                        <li><a class="dropdown-item py-2" href="report_card.php?student_id=<?php echo $student['id']; ?>" target="_blank"><i class="fas fa-file-invoice text-info me-2"></i> Report Card</a></li>
                                        <li><a class="dropdown-item py-2" href="certificate.php?student_id=<?php echo $student['id']; ?>" target="_blank"><i class="fas fa-certificate text-warning me-2"></i> Certificate</a></li>
                                        <li><a class="dropdown-item py-2" href="edit_student.php?id=<?php echo $student['id']; ?>"><i class="fas fa-edit text-secondary me-2"></i> Edit Bio Details</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger py-2" href="#" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-bs-id="<?php echo $student['id']; ?>" data-bs-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>" data-bs-enrollment="<?php echo htmlspecialchars($student['enrollment_no']); ?>">
                                                <i class="fas fa-trash-alt me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fas fa-search text-light" style="font-size: 4rem;"></i>
                                </div>
                                <h5 class="text-muted fw-bold">No records found</h5>
                                <?php if(!empty($search) || !empty($filter_semester) || !empty($filter_gender)): ?>
                                    <p class="text-muted mb-4">No students matched your current filter criteria.</p>
                                    <a href="students.php" class="btn btn-outline-primary rounded-pill px-4">Clear All Filters</a>
                                <?php else: ?>
                                    <p class="text-muted mb-4">Your directory is empty. Start by adding some student records.</p>
                                    <a href="add_student.php" class="btn btn-primary rounded-pill px-4"><i class="fas fa-plus me-2"></i>Add New Student</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(isset($total_pages) && $total_pages > 1): ?>
        <div class="card-footer bg-white py-3 border-top-0">
            <ul class="pagination justify-content-end mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&course=<?php echo urlencode($filter_course); ?>&admission_year=<?php echo urlencode($filter_year); ?>&limit=<?php echo $limit; ?>" tabindex="-1">Previous</a>
                </li>
                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&course=<?php echo urlencode($filter_course); ?>&admission_year=<?php echo urlencode($filter_year); ?>&limit=<?php echo $limit; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&course=<?php echo urlencode($filter_course); ?>&admission_year=<?php echo urlencode($filter_year); ?>&limit=<?php echo $limit; ?>">Next</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
