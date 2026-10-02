<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($total / $limit);

$logs = $pdo->prepare("
    SELECT al.*, a.username 
    FROM audit_logs al 
    LEFT JOIN users a ON al.user_id = a.id 
    ORDER BY al.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$logs->bindValue(':limit', $limit, PDO::PARAM_INT);
$logs->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs->execute();
$auditLogs = $logs->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Audit Logs</h2>
                    <p class="text-muted mb-0">System-wide administrative activity trail</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-uppercase fw-bold">Timestamp</th>
                                    <th class="text-uppercase fw-bold">User</th>
                                    <th class="text-uppercase fw-bold">Action</th>
                                    <th class="text-uppercase fw-bold">Target</th>
                                    <th class="text-uppercase fw-bold">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($auditLogs) > 0): ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td class="text-muted" style="font-size: 0.85rem;"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td><span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['target']); ?></td>
                                        <td class="text-muted" style="font-size: 0.9rem;"><?php echo htmlspecialchars($log['description']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No audit logs found</h5><p>There are currently no records available in this section.</p></div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


