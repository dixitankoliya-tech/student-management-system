<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['faculty_id'];
        $new_status = $_POST['current_status'] === 'Active' ? 'Inactive' : 'Active';
        
        $stmt = $pdo->prepare("UPDATE faculty SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        
        // Audit log
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], 'Update Faculty Status', "Faculty ID $id", "Status changed to $new_status"]);
        
        $success = "Faculty status updated to $new_status.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $faculty_id = trim($_POST['emp_id']);
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($faculty_id)) {
            $error = "Required fields are missing.";
        } else {
            // Check uniqueness
            $check = $pdo->prepare("SELECT id FROM faculty WHERE email = ? OR faculty_id = ?");
            $check->execute([$email, $faculty_id]);
            if ($check->fetch()) {
                $error = "Faculty with this Email or Employee ID already exists.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO faculty (faculty_id, first_name, last_name, email, department, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                    $stmt->execute([$faculty_id, $first_name, $last_name, $email, $department]);
                    $db_faculty_id = $pdo->lastInsertId();
                    
                    // Create login credentials in users table
                    $password = password_hash('password123', PASSWORD_DEFAULT);
                    $full_name = $first_name . ' ' . $last_name;
                    $userStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, faculty_id, is_active, must_change_password) VALUES (?, ?, ?, ?, 'faculty', ?, 1, 1)");
                    $userStmt->execute([$faculty_id, $email, $password, $full_name, $db_faculty_id]);
                    
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], 'Add Faculty', "Faculty $faculty_id", "Created new faculty account"]);
                    
                    $success = "Faculty added successfully. Default password is 'password123'.";
                } catch (PDOException $e) {
                    $error = "Error adding faculty: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Departments
$departments = $pdo->query("SELECT department_name FROM departments WHERE status = 'Active'")->fetchAll();

// Pagination variables
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [20, 50, 100])) $limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_dept = isset($_GET['department_filter']) ? trim($_GET['department_filter']) : '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR faculty_id LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
}

if (!empty($filter_dept)) {
    $whereConditions[] = "department = ?";
    $params[] = $filter_dept;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Total Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM faculty $whereClause");
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Faculty
$stmt = $pdo->prepare("SELECT * FROM faculty $whereClause ORDER BY first_name ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$faculty = $stmt->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Faculty Management</h2>
                    <p class="text-muted mb-0">Manage university faculty and staff</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                    <i class="fas fa-user-plus me-2"></i> Add Faculty
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
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body bg-light rounded">
                    <form method="GET" action="faculty.php" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Search Faculty</label>
                            <input type="text" class="form-control" placeholder="Name, Email, or Emp ID..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Department</label>
                            <select name="department_filter" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d['department_name']); ?>" <?php echo ($filter_dept == $d['department_name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Per Page</label>
                            <select name="limit" class="form-select">
                                <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                            <a href="faculty.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <p class="text-muted small">Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?>–<?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> faculty</p>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-uppercase fw-bold">Emp ID</th>
                                    <th class="text-uppercase fw-bold">Name</th>
                                    <th class="text-uppercase fw-bold">Department</th>
                                    <th class="text-uppercase fw-bold">Contact</th>
                                    <th class="text-uppercase fw-bold">Status</th>
                                    <th class="text-uppercase fw-bold text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($faculty) > 0): ?>
                                    <?php foreach ($faculty as $f): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($f['faculty_id']); ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($f['department']); ?></td>
                                        <td>
                                            <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($f['email']); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($f['phone'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($f['status'] === 'Active'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-2">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-2">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="faculty_id" value="<?php echo $f['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $f['status']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $f['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="<?php echo $f['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas <?php echo $f['status'] === 'Active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No faculty members found</h5><p>There are currently no records available in this section.</p></div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-end mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&department_filter=<?php echo urlencode($filter_dept); ?>&limit=<?php echo $limit; ?>">Previous</a>
                            </li>
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department_filter=<?php echo urlencode($filter_dept); ?>&limit=<?php echo $limit; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&department_filter=<?php echo urlencode($filter_dept); ?>&limit=<?php echo $limit; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Add New Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Employee ID</label>
                            <input type="text" name="emp_id" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Department</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d['department_name']); ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="alert alert-info py-2" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i> Initial password will be set to: <strong>password123</strong>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Create Faculty Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


