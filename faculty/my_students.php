<?php
/**
 * SMS 2.0: Faculty - My Students
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];
$filter_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$filter_division = isset($_GET['division']) ? $_GET['division'] : null;

require_once '../includes/faculty_role_helper.php';

// Validate that the requested subject/division belongs to this faculty (or is accessible by their role)
if ($filter_subject_id) {
    // If they are dean/coordinator, we might need a broader check, but for now let's allow it 
    // if the subject is part of their accessible courses.
    $stmt = $pdo->prepare("SELECT course_id FROM subjects WHERE id = ?");
    $stmt->execute([$filter_subject_id]);
    $subj_course_id = $stmt->fetchColumn();
    
    if (!hasCourseAccess($pdo, $faculty_id, $subj_course_id)) {
        die("Unauthorized access to this subject's students.");
    }
}

// Fetch students
try {
    $accessible_student_ids = getAccessibleStudentIds($pdo, $faculty_id);
    if (empty($accessible_student_ids)) {
        $students = [];
    } else {
        if ($filter_subject_id) {
            // When filtering by subject, include subject info
            $query = "
                SELECT st.*, fs.subject_id, s.subject_name, sae.division as active_division, sae.academic_year
                FROM students st
                JOIN student_academic_enrollments sae ON st.id = sae.student_id AND sae.status = 'Active'
                JOIN faculty_subjects fs ON 
                    sae.course = fs.course AND 
                    sae.semester = fs.semester AND 
                    sae.division = fs.division AND
                    sae.academic_year = fs.academic_year
                JOIN subjects s ON fs.subject_id = s.id
                WHERE fs.subject_id = :subject_id 
                AND st.id IN (" . implode(',', $accessible_student_ids) . ")
            ";
        } else {
            // When showing all students, use the accessible IDs list
            $query = "
                SELECT st.*, NULL as subject_id, NULL as subject_name, sae.division as active_division, sae.academic_year 
                FROM students st
                JOIN student_academic_enrollments sae ON st.id = sae.student_id AND sae.status = 'Active'
                WHERE st.id IN (" . implode(',', $accessible_student_ids) . ")
            ";
        }
        
        $params = [];
    
    if ($filter_subject_id) {
        $query .= " AND fs.subject_id = :subject_id";
        $params[':subject_id'] = $filter_subject_id;
    }
    
    if ($filter_division) {
        if ($filter_subject_id) {
            $query .= " AND fs.division = :division";
        } else {
            $query .= " AND sae.division = :division";
        }
        $params[':division'] = $filter_division;
    }
    
    $query .= " ORDER BY st.enrollment_no ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $students = [];
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">My Students</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active">My Students</li>
                    </ol>
                </nav>
            </div>
            <?php if ($filter_subject_id): ?>
                <a href="my_students.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-2"></i>Clear Filter</a>
            <?php endif; ?>
        </header>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <?php if ($filter_subject_id && !empty($students)): ?>
                        <h5 class="text-primary mb-3 mb-md-0"><i class="fas fa-filter me-2"></i>Filtered by Subject: <strong><?= htmlspecialchars($students[0]['subject_name']) ?></strong> (Div <?= htmlspecialchars($filter_division) ?>)</h5>
                    <?php else: ?>
                        <h5 class="text-dark fw-bold mb-3 mb-md-0">Student Directory</h5>
                    <?php endif; ?>
                    
                    <div style="max-width: 300px; width: 100%;">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control bg-light border-start-0" placeholder="Search students...">
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>Enrollment No</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Sem / Div</th>
                                <th>Email</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No students found</h5><p>No students found in your assigned classes..</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['enrollment_no']) ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($student['course']) ?></td>
                                        <td>Sem <?= htmlspecialchars($student['semester']) ?> / Div <?= htmlspecialchars($student['division']) ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td class="text-center">
                                            <a href="student_detail.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="View Profile">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if(searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const term = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        // Skip empty state row
                        if(row.querySelector('.empty-state')) return;
                        
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
        });
    </script>



