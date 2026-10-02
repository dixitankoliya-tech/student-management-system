<?php
/**
 * SMS 2.0: Admin Assignments Directory
 * Allows admins to view all academic assignments across the university.
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    requireValidCsrf();
    $assignment_id = (int)$_POST['assignment_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);
        logActivity($pdo, 'DELETE_ASSIGNMENT', null, "Admin deleted assignment ID $assignment_id");
        $success = 'Assignment deleted successfully.';
    } catch (PDOException $e) {
        $error = 'Error deleting assignment. It may have student submissions.';
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search & Filter
$search = trim($_GET['search'] ?? '');
$filter_course = trim($_GET['course'] ?? '');
$filter_semester = (int)($_GET['semester'] ?? 0);

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(a.title LIKE ? OR a.description LIKE ? OR s.subject_name LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term);
}
if (!empty($filter_course)) {
    $where[] = "a.course = ?";
    $params[] = $filter_course;
}
if ($filter_semester > 0) {
    $where[] = "a.semester = ?";
    $params[] = $filter_semester;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN faculty f ON a.faculty_id = f.id
    $where_sql
");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$stmt = $pdo->prepare("
    SELECT a.*, 
    s.subject_name, s.subject_code,
    f.first_name, f.last_name,
    (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) as submission_count
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN faculty f ON a.faculty_id = f.id
    $where_sql
    ORDER BY a.due_date DESC, a.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Lookups for filters
$courses = $pdo->query("SELECT course_name FROM courses WHERE status='Active' ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid px-4 py-4">
            
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Assignments</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0 text-dark"><i class="fas fa-file-signature me-2 text-primary"></i>Academic Assignments</h2>
                    <p class="text-muted mb-0">University-wide assignment directory (Total: <?= $total ?>)</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold mb-1 small text-muted">Search</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search Title, Subject, Faculty..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold mb-1 small text-muted">Program</label>
                            <select name="course" class="form-select form-select-sm">
                                <option value="">All Programs</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-1 small text-muted">Semester</label>
                            <select name="semester" class="form-select form-select-sm">
                                <option value="0">All</option>
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filter_semester == $i ? 'selected' : '' ?>>Sem <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                            <a href="assignments.php" class="btn btn-sm btn-light">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3">Assignment Details</th>
                                    <th class="py-3">Target Audience</th>
                                    <th class="py-3">Faculty</th>
                                    <th class="py-3">Deadline</th>
                                    <th class="py-3 text-center">Submissions</th>
                                    <th class="px-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $a): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-semibold text-dark mb-1"><?= htmlspecialchars($a['title']) ?></div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($a['subject_name']) ?> <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($a['subject_code']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="small">
                                            <span class="badge bg-primary me-1"><?= htmlspecialchars($a['course']) ?></span>
                                            <span class="badge bg-secondary me-1">Sem <?= $a['semester'] ?></span>
                                            <?php if($a['division']): ?>
                                                <span class="badge bg-info text-dark">Div <?= htmlspecialchars($a['division']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-light text-primary me-2 shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600; font-size: 0.8rem;">
                                                <?= strtoupper(substr($a['first_name'], 0, 1) . substr($a['last_name'], 0, 1)) ?>
                                            </div>
                                            <span class="small fw-medium"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <?php 
                                        $due = new DateTime($a['due_date']);
                                        $now = new DateTime();
                                        $is_past = $due < $now;
                                        ?>
                                        <div class="small <?= $is_past ? 'text-danger fw-semibold' : 'text-success fw-semibold' ?>">
                                            <i class="fas fa-<?= $is_past ? 'exclamation-circle' : 'clock' ?> me-1"></i>
                                            <?= $due->format('d M Y, h:i A') ?>
                                        </div>
                                    </td>
                                    <td class="py-3 text-center">
                                        <span class="badge rounded-pill bg-<?= $a['submission_count'] > 0 ? 'success' : 'secondary' ?>">
                                            <?= $a['submission_count'] ?> Submitted
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <?php if ($a['attachment_path']): ?>
                                            <a href="serve.php?file=<?= urlencode($a['attachment_path']) ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info rounded-circle me-1" title="View Attachment">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="serve.php?file=<?= urlencode($a['attachment_path']) ?>&action=download" class="btn btn-sm btn-info text-white rounded-circle me-1" title="Download Attachment">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assignment? All submissions will also be deleted if CASCADE is configured.');">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                            <button type="submit" name="delete_assignment" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete Assignment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <div class="mb-3"><i class="fas fa-file-excel fs-3 text-light-gray"></i></div>
                                            <h5 class="fw-semibold">No Assignments Found</h5>
                                            <p class="small mb-0">Try adjusting your filters or search terms.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white border-top-0 py-3">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&course=<?= urlencode($filter_course) ?>&semester=<?= $filter_semester ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
