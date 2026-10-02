<?php
/**
 * SMS 2.0: System Audit Trail & Activity Logs
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
require_once '../includes/academic_helper.php';

$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($action_filter)) {
    $where[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($search)) {
    $where[] = "(al.description LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.enrollment_no LIKE ?)";
    $sp = "%$search%";
    array_push($params, $sp, $sp, $sp, $sp);
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$logs = [];
$total_records = 0;
$total_pages = 1;

try {
    $cntStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM activity_logs al 
        LEFT JOIN students s ON al.student_id = s.id 
        $whereClause
    ");
    $cntStmt->execute($params);
    $total_records = (int)$cntStmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $limit));

    $sql = "
        SELECT al.*, s.first_name, s.last_name, s.enrollment_no 
        FROM activity_logs al 
        LEFT JOIN students s ON al.student_id = s.id 
        $whereClause 
        ORDER BY al.id DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Activity Logs Error: " . $e->getMessage());
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div>
        <h1 class="h3 text-gray-800 fw-bold mb-1"><i class="fas fa-history text-primary me-2"></i>Institutional Activity Audit Trail</h1>
        <p class="text-muted small mb-0">Immutable system log recording database modifications, marks entry, and data export events.</p>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded py-3 px-4">
        <form method="GET" action="activity_logs.php" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Search Narrative / Student</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search keyword, name, enrollment..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Filter by Action Type</label>
                <select name="action" class="form-select" onchange="this.form.submit()">
                    <option value="">All Administrative Actions</option>
                    <option value="CREATE" <?php echo ($action_filter === 'CREATE') ? 'selected' : ''; ?>>CREATE (Student Added)</option>
                    <option value="UPDATE" <?php echo ($action_filter === 'UPDATE') ? 'selected' : ''; ?>>UPDATE (Record Modified)</option>
                    <option value="DELETE" <?php echo ($action_filter === 'DELETE') ? 'selected' : ''; ?>>DELETE (Student Removed)</option>
                    <option value="MARKS_ENTRY" <?php echo ($action_filter === 'MARKS_ENTRY') ? 'selected' : ''; ?>>MARKS_ENTRY (Scores Recorded)</option>
                    <option value="EXPORT" <?php echo ($action_filter === 'EXPORT') ? 'selected' : ''; ?>>EXPORT (CSV Generated)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="fas fa-filter me-1"></i> Filter Logs</button>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Audit Log Entries (Total: <?php echo $total_records; ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 15%;">Action</th>
                    <th style="width: 20%;">Student Reference</th>
                    <th style="width: 37%;">Audit Description</th>
                    <th style="width: 20%;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No activity logs found</h5><p>There are currently no records available in this section.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="font-monospace text-muted">#<?php echo $l['id']; ?></td>
                            <td>
                                <?php 
                                    $act = $l['action'];
                                    $badge = 'bg-secondary';
                                    if ($act === 'CREATE') $badge = 'bg-success';
                                    elseif ($act === 'UPDATE') $badge = 'bg-warning text-dark';
                                    elseif ($act === 'DELETE') $badge = 'bg-danger';
                                    elseif ($act === 'MARKS_ENTRY') $badge = 'bg-primary';
                                    elseif ($act === 'EXPORT') $badge = 'bg-info text-dark';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($act); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($l['first_name'])): ?>
                                    <a href="view_student.php?id=<?php echo $l['student_id']; ?>" class="fw-semibold text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?>
                                    </a>
                                    <div class="small text-muted font-monospace"><?php echo htmlspecialchars($l['enrollment_no']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">System / Global</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($l['description']); ?></td>
                            <td class="small text-muted">
                                <i class="far fa-clock me-1"></i>
                                <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <span class="small text-muted">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($action_filter); ?>&search=<?php echo urlencode($search); ?>">Prev</a>
                </li>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?php echo ($page == $p) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $p; ?>&action=<?php echo urlencode($action_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($action_filter); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

