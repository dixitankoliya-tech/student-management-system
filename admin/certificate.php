<?php
/**
 * SMS 2.0: Certificate Generation
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$search = $_GET['search'] ?? '';
$students = [];
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 10");
    $term = "%$search%";
    $stmt->execute([$term, $term, $term]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<?php include '../includes/admin_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Certificate Generator</h2>
        <p class="text-muted mb-0">Generate Bonafide or Completion certificates</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control form-control-lg" placeholder="Search Student by Enrollment No or Name" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($search && empty($students)): ?>
    <div class="alert alert-warning">No students found matching your search.</div>
<?php elseif (!empty($students)): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Enrollment No</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($stu['enrollment_no']) ?></td>
                            <td><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
                            <td><?= htmlspecialchars($stu['course']) ?></td>
                            <td><span class="badge bg-<?= $stu['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($stu['status']) ?></span></td>
                            <td class="pe-4 text-end">
                                <a href="print_certificate.php?id=<?= $stu['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-print"></i> Bonafide</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
