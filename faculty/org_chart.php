<?php
/**
 * SMS 2.0: Dean - Organization Chart
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

// Ensure only Dean can access
if (!isDean($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;font-family:sans-serif;"><h1>403 — Access Denied</h1><p>Only the Dean can access this page.</p></div>');
}

$deanInfo = getCurrentDean($pdo);
$coordinators = getAllCoordinators($pdo);

// Group by department (we assume FCAIT is the only active one)
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<!-- MAIN CONTENT -->
<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Organization Chart</h2>
        <p class="text-muted mb-0">Hierarchical view of FCAIT Department</p>
    </div>
</header>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4 text-center">
        <!-- University -->
        <div class="d-inline-block border rounded-3 p-3 bg-light mb-4" style="min-width: 250px;">
            <h5 class="mb-0 fw-bold"><i class="fas fa-university text-primary me-2"></i>GLS University</h5>
        </div>
        
        <div style="height: 30px; border-left: 2px solid #ccc; width: 1px; margin: 0 auto;"></div>
        
        <!-- Department -->
        <div class="d-inline-block border border-primary border-2 rounded-3 p-3 bg-white mb-4 shadow-sm" style="min-width: 300px;">
            <h5 class="mb-1 fw-bold text-primary"><?php echo htmlspecialchars($deanInfo['department_name'] ?? 'FCAIT Department'); ?></h5>
            <div class="text-muted small">Department</div>
        </div>

        <div style="height: 30px; border-left: 2px solid #ccc; width: 1px; margin: 0 auto;"></div>

        <!-- Dean -->
        <div class="d-inline-block border border-success border-2 rounded-3 p-3 bg-white mb-4 shadow-sm" style="min-width: 300px;">
            <div class="d-flex align-items-center justify-content-center">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="text-start">
                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars(($deanInfo['first_name'] ?? '') . ' ' . ($deanInfo['last_name'] ?? '')); ?></h5>
                    <div class="text-muted small">Dean / Head of Department</div>
                </div>
            </div>
        </div>
        
        <!-- Coordinators Connector -->
        <?php if (!empty($coordinators)): ?>
        <div style="height: 30px; border-left: 2px solid #ccc; width: 1px; margin: 0 auto;"></div>
        <div style="border-top: 2px solid #ccc; margin: 0 auto; width: <?php echo (count($coordinators) - 1) * 220; ?>px; max-width: 100%;"></div>
        
        <div class="d-flex justify-content-center flex-wrap" style="gap: 20px; margin-top: -2px;">
            <?php foreach ($coordinators as $idx => $coord): ?>
            <div style="width: 200px;">
                <div style="height: 30px; border-left: 2px solid #ccc; width: 1px; margin: 0 auto;"></div>
                <div class="border border-warning rounded-3 p-3 bg-white shadow-sm text-start" style="font-size: 0.9rem;">
                    <div class="fw-bold text-dark mb-1 border-bottom pb-2">
                        <i class="fas fa-graduation-cap text-warning me-1"></i> <?php echo htmlspecialchars($coord['course_code']); ?>
                    </div>
                    <div class="mt-2 text-primary fw-semibold"><i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($coord['first_name'] . ' ' . $coord['last_name']); ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Course Coordinator</div>
                    
                    <div class="mt-3 pt-2 border-top text-center text-muted" style="font-size: 0.8rem;">
                        <i class="fas fa-level-down-alt"></i><br>
                        Faculty & Students
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mt-3">No course coordinators assigned.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
