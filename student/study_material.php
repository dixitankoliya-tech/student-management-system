<?php
/**
 * SMS 2.0: Student Study Material
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

// Fetch active enrollment to determine academic year
$enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
$enrStmt->execute([$student_id]);
$student['academic_year'] = $enrStmt->fetchColumn();

// Fetch study materials for student's course, sem, division and academic year
$matStmt = $pdo->prepare("
    SELECT DISTINCT
        m.*,
        s.subject_name,
        s.subject_code,
        f.first_name as fac_first_name,
        f.last_name as fac_last_name
    FROM study_materials m
    JOIN subjects s ON m.subject_id = s.id
    JOIN faculty f ON m.faculty_id = f.id
    JOIN faculty_subjects fs ON m.faculty_id = fs.faculty_id AND m.subject_id = fs.subject_id AND m.course = fs.course AND m.semester = fs.semester AND m.division = fs.division
    WHERE m.course = ? AND m.semester = ? AND m.division = ? AND fs.academic_year = ? AND m.visibility = 'Visible'
    ORDER BY m.upload_date DESC
");
$matStmt->execute([$student['course'], $student['semester'], $student['division'], $student['academic_year']]);
$materials = $matStmt->fetchAll();


include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Study Materials</h2>
        <p class="text-muted mb-0">Resources shared by your faculty</p>
    </div>
</div>

<div class="row">
    <?php if (empty($materials)): ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-folder-open fs-1 text-light mb-3"></i>
            <h5 class="fw-bold text-muted">No Study Materials</h5>
            <p class="text-muted">There are currently no study materials shared for your class.</p>
        </div>
    <?php else: ?>
        <?php foreach ($materials as $m): ?>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 rounded-4 hover-shadow transition-all">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-primary-subtle text-primary mb-2"><?php echo htmlspecialchars($m['subject_name']); ?></span>
                                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($m['title']); ?></h5>
                                <div class="text-muted small">
                                    <i class="fas fa-user-tie me-1"></i> Prof. <?php echo htmlspecialchars($m['fac_first_name'] . ' ' . $m['fac_last_name']); ?>
                                </div>
                            </div>
                            <?php if ($m['file_type']): ?>
                                <div class="bg-light rounded p-2 text-center" style="width: 45px; height: 45px;">
                                    <?php 
                                        $ext = strtolower($m['file_type']);
                                        if ($ext == 'pdf') echo '<i class="fas fa-file-pdf text-danger fa-lg"></i>';
                                        elseif (in_array($ext, ['doc','docx'])) echo '<i class="fas fa-file-word text-primary fa-lg"></i>';
                                        elseif (in_array($ext, ['ppt','pptx'])) echo '<i class="fas fa-file-powerpoint text-warning fa-lg"></i>';
                                        elseif (in_array($ext, ['zip','rar'])) echo '<i class="fas fa-file-archive text-secondary fa-lg"></i>';
                                        else echo '<i class="fas fa-file-alt text-info fa-lg"></i>';
                                    ?>
                                </div>
                            <?php elseif ($m['link_url']): ?>
                                <div class="bg-light rounded p-2 text-center" style="width: 45px; height: 45px;">
                                    <i class="fas fa-link text-primary fa-lg"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($m['description']): ?>
                            <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($m['description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="text-muted" style="font-size: 0.75rem;">
                            <i class="fas fa-clock me-1"></i> <?php echo date('M d, Y h:i A', strtotime($m['upload_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light border-top-0 rounded-bottom-4 py-3 text-center">
                        <?php if ($m['file_path']): ?>
                            <div class="d-flex gap-2">
                                <a href="serve.php?file=<?php echo urlencode($m['file_path']); ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm rounded-pill w-50">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                                <a href="serve.php?file=<?php echo urlencode($m['file_path']); ?>&action=download" class="btn btn-primary btn-sm rounded-pill w-50">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if ($m['link_url']): ?>
                            <a href="<?php echo htmlspecialchars($m['link_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm px-4 rounded-pill w-100 mt-2">
                                <i class="fas fa-external-link-alt me-2"></i> Open Link
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<style>
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
.transition-all {
    transition: all .3s ease;
}
</style>

<?php include '../includes/footer.php'; ?>
