<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target = trim($_POST['target'] ?? 'All');
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
    
    if ($target == 'Students') $target = 'Student'; // map form value to enum

    if (empty($title) || empty($content)) {
        $error = "Title and content are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, audience, expiry_date, published_by, publish_date) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$title, $content, $target, $expiry, $_SESSION['user_id']]);
            $success = "Announcement published successfully.";
            
            // Log
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Publish Announcement', $target, "Published announcement: $title"]);

            // Send Notifications
            $notification_title = "New Announcement: " . substr($title, 0, 50);
            $notification_link = "announcements.php";
            if ($target === 'All' || $target === 'Student') {
                $q = $pdo->query("SELECT student_id FROM users WHERE role = 'student'");
                $students = $q->fetchAll();
                foreach($students as $s) {
                    send_notification($pdo, 'student', $s['student_id'], $notification_title, substr($content, 0, 100), 'system', $notification_link);
                }
            }
            if ($target === 'All' || $target === 'Faculty') {
                $q = $pdo->query("SELECT faculty_id FROM users WHERE role = 'faculty'");
                $faculties = $q->fetchAll();
                foreach($faculties as $f) {
                    send_notification($pdo, 'faculty', $f['faculty_id'], $notification_title, substr($content, 0, 100), 'system', $notification_link);
                }
            }
        } catch (PDOException $e) {
            $error = "Error publishing announcement: " . $e->getMessage();
        }
    }
}

// Fetch announcements
$stmt = $pdo->query("SELECT a.*, u.full_name as author_name FROM announcements a LEFT JOIN users u ON a.published_by = u.id ORDER BY a.publish_date DESC");
$announcements = $stmt->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Announcements</h2>
                    <p class="text-muted mb-0">Publish university-wide announcements</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus-circle me-2"></i> New Announcement
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
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($announcements)): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 text-center text-muted">
                    <i class="fas fa-bullhorn fs-3 mb-3 text-light"></i>
                    <h5 class="fw-bold">No Announcements</h5>
                    <p>Published announcements will appear here.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($announcements as $ann): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($ann['title']); ?></h5>
                                <span class="badge bg-<?php echo $ann['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $ann['status']; ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="badge bg-info text-dark me-2">Audience: <?php echo htmlspecialchars($ann['audience']); ?></span>
                                <small class="text-muted"><i class="fas fa-clock me-1"></i> <?php echo date('M d, Y', strtotime($ann['publish_date'])); ?></small>
                            </div>
                            <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0 pb-4 px-4 text-muted small">
                            <i class="fas fa-user-edit me-1"></i> Published by <?php echo htmlspecialchars($ann['author_name'] ?? 'Admin'); ?>
                            <?php if ($ann['expiry_date']): ?>
                            <span class="float-end text-danger"><i class="fas fa-hourglass-end me-1"></i> Expires: <?php echo date('M d, Y', strtotime($ann['expiry_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Publish Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Content</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Target Audience</label>
                            <select name="target" class="form-select">
                                <option value="All">All (University-wide)</option>
                                <option value="Students">Students Only</option>
                                <option value="Faculty">Faculty Only</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Expiry Date</label>
                            <input type="date" name="expiry" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

