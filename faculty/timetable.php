<?php
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];

// Fetch timetable entries for this faculty
$stmt = $pdo->prepare("
    SELECT t.*, s.subject_name, s.subject_code 
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.faculty_id = ?
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time ASC
");
$stmt->execute([$faculty_id]);
$timetable_entries = $stmt->fetchAll();

// Group by day
$timetable = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 
    'Thursday' => [], 'Friday' => [], 'Saturday' => []
];

foreach ($timetable_entries as $entry) {
    if (array_key_exists($entry['day_of_week'], $timetable)) {
        $timetable[$entry['day_of_week']][] = $entry;
    }
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">My Timetable</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active">Timetable</li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-2"></i> Print Schedule</button>
        </header>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <?php $has_classes = false; ?>
                <div class="row">
                    <?php $today_day = date('l'); ?>
                    <?php foreach ($timetable as $day => $classes): ?>
                        <?php if (count($classes) > 0): $has_classes = true; $is_today = ($day === $today_day); ?>
                            <div class="col-md-6 mb-4">
                                <div class="day-header <?= $is_today ? 'text-primary fw-bold' : '' ?>">
                                    <i class="fas fa-calendar-day me-2 <?= $is_today ? 'text-primary' : 'text-muted' ?>"></i> 
                                    <?= $day ?> <?= $is_today ? '<span class="badge bg-primary ms-2">Today</span>' : '' ?>
                                </div>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($classes as $class): ?>
                                        <div class="card class-card border-0 shadow-sm <?= $is_today ? 'bg-primary bg-opacity-10 border border-primary-subtle' : 'bg-light' ?>">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($class['subject_name']) ?></h6>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.7rem;">
                                                            <?= htmlspecialchars($class['subject_code']) ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-end fw-bold text-primary" style="font-size: 0.9rem;">
                                                        <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                                    </div>
                                                </div>
                                                <div class="text-muted small mt-2">
                                                    <div class="row g-2">
                                                        <div class="col-7">
                                                            <i class="fas fa-users-class me-1"></i> <?= htmlspecialchars($class['course']) ?> - Sem <?= $class['semester'] ?> (Div <?= htmlspecialchars($class['division']) ?>)
                                                        </div>
                                                        <div class="col-5 text-end">
                                                            <i class="fas fa-door-open me-1"></i> Room: <?= htmlspecialchars($class['room_no'] ?: 'TBA') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!$has_classes): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fs-3 mb-3 opacity-50"></i>
                        <h5>No Schedule Available</h5>
                        <p>You have not been assigned any classes in the timetable yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php include '../includes/footer.php'; ?>

