<?php
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];
$user_id = $_SESSION['user_id']; // notifications.user_id maps to users.id, NOT faculty.id

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit();
}
// Handle mark all as read
if (isset($_POST['mark_all'])) {
    requireValidCsrf();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread_count = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unread_count++;
}

?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">Notifications</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active">Notifications</li>
                    </ol>
                </nav>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST">
                    <?php csrfField(); ?>
                    <button type="submit" name="mark_all" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-check-double me-2"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </header>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if (empty($notifications)): ?>
                    <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                        <div class="text-muted mb-3"><i class="fas fa-bell-slash fs-3"></i></div>
                        <h4 class="text-muted">No Notifications</h4>
                        <p class="mb-0">You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($notifications as $n): ?>
                            <div class="card notification-card <?= $n['is_read'] == 0 ? 'unread' : '' ?> border-0 shadow-sm rounded-4">
                                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($n['title']) ?></h6>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($n['message']) ?></p>
                                        <div class="text-muted mt-2" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i> <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                                        </div>
                                    </div>
                                    <?php if ($n['is_read'] == 0): ?>
                                        <a href="?mark_read=<?= $n['id'] ?>" class="btn btn-sm btn-light border rounded-circle" title="Mark as Read">
                                            <i class="fas fa-check text-primary"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php include '../includes/footer.php'; ?>

