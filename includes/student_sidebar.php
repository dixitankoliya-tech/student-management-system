        <?php
        // Load university branding from system_settings
        $_sidebar_uni_name = 'University';
        $_sidebar_uni_short = '';
        try {
            $_sidebar_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('university_name','university_short_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $_sidebar_uni_name = $_sidebar_settings['university_name'] ?? 'University';
            $_sidebar_uni_short = $_sidebar_settings['university_short_name'] ?? '';
        } catch (Exception $e) { /* fallback silently */ }
        ?>
        <!-- Sidebar -->
        <div class="border-end" id="sidebar-wrapper">
            <div class="sidebar-heading text-white py-4 px-3 text-center">
                <div class="mb-2">
                    <i class="fas fa-graduation-cap fs-5 text-primary"></i>
                </div>
                <div class="fs-5 fw-bold text-light" style="letter-spacing: -0.5px;">Student Portal</div>
                <div class="text-primary fw-semibold mt-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px;"><?php echo htmlspecialchars(strtoupper($_sidebar_uni_name)); ?></div>
            </div>

            <div class="list-group list-group-flush mt-2" style="overflow-y: auto; max-height: calc(100vh - 120px);">
                <!-- Core Navigation -->
                <div class="px-3 pt-2 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">My Academics</div>
                <a href="dashboard.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-th-large me-3 nav-icon"></i> Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user me-3 nav-icon"></i> My Profile
                </a>
                <a href="subjects.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'subjects.php') ? 'active' : ''; ?>">
                    <i class="fas fa-book me-3 nav-icon"></i> My Subjects
                </a>
                <a href="timetable.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'timetable.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clock me-3 nav-icon"></i> Timetable
                </a>
                <a href="attendance.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check me-3 nav-icon"></i> Attendance
                </a>
                
                <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Learning & Tests</div>
                <a href="study_material.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'study_material.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-pdf me-3 nav-icon"></i> Study Material
                </a>
                <a href="assignments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'assignments.php' || basename($_SERVER['PHP_SELF']) == 'submit_assignment.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tasks me-3 nav-icon"></i> Assignments
                </a>
                <a href="examinations.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'examinations.php') ? 'active' : ''; ?>">
                    <i class="fas fa-edit me-3 nav-icon"></i> Examinations
                </a>
                <a href="marks.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'marks.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list me-3 nav-icon"></i> Marks
                </a>
                <a href="results.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'results.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-3 nav-icon"></i> Results
                </a>
                <a href="report_card.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'report_card.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice me-3 nav-icon"></i> Report Card
                </a>
                
                <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Finance</div>
                <a href="fees.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'fees.php') ? 'active' : ''; ?>">
                    <i class="fas fa-rupee-sign text-success me-3 nav-icon"></i> My Fees
                </a>

                <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Updates</div>
                <a href="announcements.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn me-3 nav-icon"></i> Announcements
                </a>
                <a href="notifications.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bell me-3 nav-icon"></i> Notifications
                </a>
                <a href="achievements.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'achievements.php') ? 'active' : ''; ?>">
                    <i class="fas fa-award text-warning me-3 nav-icon"></i> Achievements
                </a>
                
                <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Settings</div>
                <a href="change_password.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'change_password.php') ? 'active' : ''; ?>">
                    <i class="fas fa-key text-info me-3 nav-icon"></i> Change Password
                </a>
                <br>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->
