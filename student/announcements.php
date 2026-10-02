<?php
/**
 * SMS 2.0: Student Announcements
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch relevant announcements
// Criteria:
// - status = 'Active'
// - expiry_date is NULL or >= CURDATE()
// - audience = 'All' OR
// - audience = 'Student' OR
// - (audience = 'Course' AND course = student's course) OR
// - (audience = 'Subject' AND subject_id IN (student's subjects))

$annStmt = $pdo->prepare("
    SELECT a.*, u.full_name as publisher_name, s.subject_name
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.id
    LEFT JOIN subjects s ON a.subject_id = s.id
    WHERE a.status = 'Active' 
      AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
      AND (
          a.audience IN ('All', 'Student')
          OR (a.audience = 'Course' AND a.course = ?)
          OR (a.audience = 'Subject' AND a.subject_id IN (
              SELECT subject_id FROM faculty_subjects fs WHERE fs.course = ? AND fs.semester = ? AND fs.division = ?
          ))
      )
    ORDER BY FIELD(a.priority, 'High', 'Medium', 'Low'), a.publish_date DESC
");
$annStmt->execute([
    $student['course'],
    $student['course'], $student['semester'], $student['division']
]);
$announcements = $annStmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Announcements</h2>
        <p class="text-muted mb-0">Important updates and notices from the university</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if (empty($announcements)): ?>
            <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                <div class="card-body">
                    <i class="fas fa-bullhorn fs-1 text-light mb-3"></i>
                    <h5 class="fw-bold text-muted">No Announcements</h5>
                    <p class="text-muted mb-0">There are no active announcements at this time.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann): 
                $border_class = 'primary';
                if ($ann['priority'] === 'High') $border_class = 'danger';
                elseif ($ann['priority'] === 'Medium') $border_class = 'warning';
                
                $badge_class = $border_class;
                $audience_label = 'University Wide';
                if ($ann['audience'] === 'Course') $audience_label = $ann['course'];
                elseif ($ann['audience'] === 'Subject') $audience_label = $ann['subject_name'];
            ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-<?php echo $border_class; ?> border-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($ann['title']); ?></h5>
                                <div class="text-muted small">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($ann['publisher_name'] ?? 'Admin'); ?> &bull; 
                                    <i class="fas fa-calendar-alt me-1 ms-2"></i> <?php echo date('M d, Y', strtotime($ann['publish_date'])); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo $badge_class; ?>-subtle text-<?php echo $badge_class; ?> fs-6 rounded-pill px-3 py-1 mb-1 d-block">
                                    <?php echo $ann['priority']; ?> Priority
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">
                                    <?php echo htmlspecialchars($audience_label); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="text-dark bg-light p-3 rounded-3 mt-3">
                            <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
