<?php
/**
 * SMS 2.0: Student Notifications
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Mark as Read Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireValidCsrf();
    
    if ($_POST['action'] === 'mark_read' && !empty($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success = "All notifications marked as read.";
    }
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Notifications</h2>
        <p class="text-muted mb-0">System alerts and academic updates</p>
    </div>
    <?php
        $has_unread = false;
        foreach($notifications as $n) { if(!$n['is_read']) { $has_unread = true; break; } }
    ?>
    <?php if ($has_unread): ?>
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-check-double me-2"></i>Mark All as Read</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success rounded-3"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fs-1 text-light mb-3"></i>
                <h5 class="fw-bold text-muted">No Notifications</h5>
                <p class="text-muted mb-0">You're all caught up!</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush rounded-4">
                <?php foreach ($notifications as $n): 
                    $is_read = $n['is_read'];
                    $bg_class = $is_read ? 'bg-white' : 'bg-primary-subtle';
                    $icon = 'fa-info-circle text-primary';
                    if (strpos(strtolower($n['type']), 'alert') !== false) $icon = 'fa-exclamation-triangle text-danger';
                    elseif (strpos(strtolower($n['type']), 'assignment') !== false) $icon = 'fa-tasks text-warning';
                    elseif (strpos(strtolower($n['type']), 'mark') !== false || strpos(strtolower($n['type']), 'result') !== false) $icon = 'fa-chart-bar text-success';
                ?>
                    <div class="list-group-item <?php echo $bg_class; ?> px-4 py-3 border-bottom position-relative">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1"><i class="fas <?php echo $icon; ?> fa-lg"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark <?php echo !$is_read ? 'text-primary' : ''; ?>">
                                        <?php echo htmlspecialchars($n['title']); ?>
                                        <?php if (!$is_read): ?><span class="badge bg-danger ms-2" style="font-size: 0.6rem;">NEW</span><?php endif; ?>
                                    </h6>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($n['message']); ?></p>
                                
                                <div class="d-flex gap-2 align-items-center mt-2">
                                    <?php if ($n['link']): ?>
                                        <a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1">View Details</a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$is_read): ?>
                                        <form method="POST" class="d-inline m-0">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-muted border rounded-pill px-3 py-1">Mark as Read</button>
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
