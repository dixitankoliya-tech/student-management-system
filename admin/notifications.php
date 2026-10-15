<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Handle Mark as Read Actions with Post-Redirect-Get (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireValidCsrf();
    
    if ($_POST['action'] === 'mark_read' && !empty($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
        header("Location: notifications.php?msg=read");
        exit();
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        header("Location: notifications.php?msg=all_read");
        exit();
    }
}

// Flash messages after redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'all_read') {
        $success = "All notifications marked as read.";
    } elseif ($_GET['msg'] === 'read') {
        $success = "Notification marked as read.";
    }
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Precompute unread count to optimize navbar queries
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h2 class="h3 mb-0 text-dark fw-bold">System Notifications</h2>
        <p class="text-muted mb-0">System alerts, fee updates, and profile change requests</p>
    </div>
    <?php if ($unread_count > 0): ?>
        <div class="mt-3 mt-md-0">
            <form method="POST" class="m-0">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fas fa-check-double me-2"></i>Mark All as Read
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-bell-slash fs-1 text-muted opacity-50"></i>
                </div>
                <h5 class="fw-bold text-muted">All caught up!</h5>
                <p class="text-muted mb-0">There are no notifications at this time.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): 
                    $is_read = (bool)$n['is_read'];
                    $bg_class = $is_read ? 'bg-white' : 'bg-primary-subtle';
                    $icon = 'fa-info-circle text-primary';
                    $typeLower = strtolower($n['type'] ?? '');
                    if (strpos($typeLower, 'alert') !== false) {
                        $icon = 'fa-exclamation-triangle text-danger';
                    } elseif (strpos($typeLower, 'finance') !== false || strpos($typeLower, 'fee') !== false) {
                        $icon = 'fa-receipt text-success';
                    } elseif (strpos($typeLower, 'assignment') !== false) {
                        $icon = 'fa-tasks text-warning';
                    } elseif (strpos($typeLower, 'mark') !== false || strpos($typeLower, 'result') !== false) {
                        $icon = 'fa-chart-bar text-info';
                    } elseif (strpos($typeLower, 'system') !== false) {
                        $icon = 'fa-shield-alt text-primary';
                    }
                ?>
                    <div class="list-group-item <?php echo $bg_class; ?> px-4 py-3 border-bottom position-relative">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1"><i class="fas <?php echo $icon; ?> fa-lg"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark <?php echo !$is_read ? 'text-primary' : ''; ?>">
                                        <?php echo htmlspecialchars($n['title']); ?>
                                        <?php if (!$is_read): ?>
                                            <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">NEW</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($n['message']); ?></p>
                                
                                <div class="d-flex gap-2 align-items-center mt-2">
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1">
                                            <i class="fas fa-external-link-alt me-1"></i>View Details
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$is_read): ?>
                                        <form method="POST" class="d-inline m-0">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?php echo (int)$n['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-muted border rounded-pill px-3 py-1">
                                                <i class="fas fa-check me-1"></i>Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


