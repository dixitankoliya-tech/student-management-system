<?php
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];

// Get courses/subjects assigned to this faculty
$stmt = $pdo->prepare("SELECT DISTINCT course FROM faculty_subjects WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$assigned_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Announcements
// Faculty should see 'All', 'Faculty', and specific 'Course' announcements they teach
$query = "
    SELECT a.*, u.full_name as author_name 
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.id
    WHERE a.status = 'Active' 
    AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
    AND (
        a.audience = 'All' 
        OR a.audience = 'Faculty' 
";

if (!empty($assigned_courses)) {
    $in = str_repeat('?,', count($assigned_courses) - 1) . '?';
    $query .= " OR (a.audience = 'Course' AND a.course IN ($in))";
}

$query .= ") ORDER BY a.priority DESC, a.publish_date DESC";

$stmt = $pdo->prepare($query);

$params = [];
if (!empty($assigned_courses)) {
    $params = $assigned_courses;
}
$stmt->execute($params);
$announcements = $stmt->fetchAll();

?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">Announcements</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active">Announcements</li>
                    </ol>
                </nav>
            </div>
        </header>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if (empty($announcements)): ?>
                    <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                        <div class="text-muted mb-3"><i class="fas fa-bell-slash fs-3"></i></div>
                        <h4 class="text-muted">No Announcements</h4>
                        <p class="mb-0">There are no active announcements at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($announcements as $a): ?>
                            <div class="card announcement-card priority-<?= htmlspecialchars($a['priority']) ?> border-0 shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($a['title']) ?></h5>
                                        <?php if ($a['priority'] == 'High'): ?>
                                            <span class="badge bg-danger rounded-pill px-3">Urgent</span>
                                        <?php elseif ($a['audience'] == 'Faculty'): ?>
                                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3">Faculty Only</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small mb-3">
                                        <i class="fas fa-calendar-alt me-1"></i> Published: <?= date('M d, Y', strtotime($a['publish_date'])) ?>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-user me-1"></i> <?= htmlspecialchars($a['author_name'] ?? 'Admin') ?>
                                    </div>
                                    <div class="text-dark" style="line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($a['message'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php include '../includes/footer.php'; ?>

