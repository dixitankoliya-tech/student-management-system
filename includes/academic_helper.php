<?php
/**
 * SMS 2.0: Academic Performance Intelligence Engine
 * Deterministic Rule-Based Analytics & Academic Calculation Helpers
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Ensures required academic intelligence tables and initial seed data exist
 */
function ensureAcademicTablesExist($pdo) {
    if (!empty($_SESSION['academic_tables_verified'])) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'subjects'");
        if ($stmt->rowCount() == 0) {
            $migrationFile = __DIR__ . '/../database/migration_academic_ai.sql';
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                // Remove 'USE student_management;' since we're already connected
                $sql = preg_replace('/USE\s+student_management;/i', '', $sql);
                // Remove SQL comments (lines starting with --)
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                // Split into individual statements and execute each one
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                $pdo->beginTransaction();
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                $pdo->commit();
            }
        }
        $_SESSION['academic_tables_verified'] = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Academic Tables Setup Warning: " . $e->getMessage());
    }
}

// Run table check once
ensureAcademicTablesExist($pdo);

/**
 * Fetch System Academic Settings (Weights and Thresholds)
 */
function getAcademicSettings($pdo) {
    $defaults = [
        'weight_academic' => 0.60,
        'weight_attendance' => 0.15,
        'weight_assignment' => 0.10,
        'weight_practical' => 0.15,
        'alert_red_attendance' => 65.0,
        'alert_red_percentage' => 50.0,
        'alert_yellow_attendance' => 75.0,
        'alert_yellow_percentage' => 60.0
    ];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($dbSettings as $k => $v) {
            $defaults[$k] = (float)$v;
        }
    } catch (PDOException $e) {
        error_log("Error reading academic settings: " . $e->getMessage());
    }

    return $defaults;
}

/**
 * Get Current Academic Year dynamically (e.g. 2024-2025)
 */
function getCurrentAcademicYear() {
    $month = (int)date('n');
    $year = (int)date('Y');
    if ($month < 6) {
        return ($year - 1) . '-' . $year;
    }
    return $year . '-' . ($year + 1);
}

/**
 * Calculate Standard Academic Letter Grade
 */
function calculateGrade($percentage) {
    $pct = round((float)$percentage, 2);
    if ($pct >= 90.0) return 'A+';
    if ($pct >= 80.0) return 'A';
    if ($pct >= 70.0) return 'B+';
    if ($pct >= 60.0) return 'B';
    if ($pct >= 50.0) return 'C';
    if ($pct >= 40.0) return 'D';
    return 'F';
}

/**
 * Classify Student into Performance Categories
 */
function calculatePerformanceCategory($score) {
    $s = round((float)$score, 2);
    if ($s >= 90.0) return 'Exceptional';
    if ($s >= 80.0) return 'High Performer';
    if ($s >= 70.0) return 'Strong Performer';
    if ($s >= 60.0) return 'On Track';
    if ($s >= 50.0) return 'Needs Improvement';
    return 'At Risk';
}

/**
 * Calculate Deterministic Performance Intelligence Score
 */
function calculatePerformanceScore($academic_pct, $attendance_pct, $assignment_score, $practical_score, $weights = null) {
    if (!$weights) {
        $weights = [
            'weight_academic' => 0.60,
            'weight_attendance' => 0.15,
            'weight_assignment' => 0.10,
            'weight_practical' => 0.15
        ];
    }

    $score = ($academic_pct * $weights['weight_academic']) +
             ($attendance_pct * $weights['weight_attendance']) +
             ($assignment_score * $weights['weight_assignment']) +
             ($practical_score * $weights['weight_practical']);

    return round(min(100, max(0, $score)), 2);
}

/**
 * Calculate Academic Alert Level (GREEN, YELLOW, ORANGE, RED)
 */
function calculateAlertLevel($percentage, $attendance, $failed_count = 0, $improvement = 0.0, $settings = null) {
    $red_att = $settings['alert_red_attendance'] ?? 65.0;
    $red_pct = $settings['alert_red_percentage'] ?? 50.0;
    $yellow_att = $settings['alert_yellow_attendance'] ?? 75.0;
    $yellow_pct = $settings['alert_yellow_percentage'] ?? 60.0;

    if ($percentage < $red_pct || $attendance < $red_att || $failed_count > 0) {
        return 'RED';
    }
    if ($percentage < $yellow_pct || $attendance < $yellow_att || $improvement <= -5.0) {
        return 'ORANGE';
    }
    if ($percentage < 70.0 || $attendance < 80.0) {
        return 'YELLOW';
    }
    return 'GREEN';
}

/**
 * Return Bootstrap badge HTML for Performance Category
 */
function getCategoryBadge($category) {
    switch ($category) {
        case 'Exceptional':
            return '<span class="badge bg-success text-white"><i class="fas fa-crown me-1"></i> Exceptional</span>';
        case 'High Performer':
            return '<span class="badge bg-primary text-white"><i class="fas fa-medal me-1"></i> High Performer</span>';
        case 'Strong Performer':
            return '<span class="badge bg-info text-dark"><i class="fas fa-thumbs-up me-1"></i> Strong Performer</span>';
        case 'On Track':
            return '<span class="badge bg-secondary text-white"><i class="fas fa-check-circle me-1"></i> On Track</span>';
        case 'Needs Improvement':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i> Needs Improvement</span>';
        case 'At Risk':
        default:
            return '<span class="badge bg-danger text-white"><i class="fas fa-radiation me-1"></i> At Risk</span>';
    }
}

/**
 * Return Bootstrap badge HTML for Alert Level
 */
function getAlertBadge($level) {
    switch ($level) {
        case 'GREEN':
            return '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> On Track</span>';
        case 'YELLOW':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1"><i class="fas fa-exclamation-circle me-1"></i> Needs Attention</span>';
        case 'ORANGE':
            return '<span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="fas fa-exclamation-triangle me-1"></i> Improvement Required</span>';
        case 'RED':
        default:
            return '<span class="badge bg-danger text-white border border-danger px-2 py-1"><i class="fas fa-fire me-1"></i> Critical: At Risk</span>';
    }
}

/**
 * Recalculate Aggregate Academic Performance for a Student in a Semester
 */
function recalculateStudentAcademicProfile($pdo, $student_id, $semester = 5) {
    try {
        $settings = getAcademicSettings($pdo);

        // Fetch all subject marks for this student & semester
        $stmt = $pdo->prepare("
            SELECT sm.*, s.max_internal, s.max_external 
            FROM student_marks sm 
            JOIN subjects s ON sm.subject_id = s.id 
            WHERE sm.student_id = ? AND sm.semester = ?
        ");
        $stmt->execute([$student_id, $semester]);
        $marks = $stmt->fetchAll();

        $total_obtained = 0.0;
        $total_max = 0.0;
        $failed_count = 0;

        foreach ($marks as $m) {
            $sub_max = (float)($m['max_internal'] + $m['max_external']);
            $sub_total = (float)$m['total_marks'];
            $total_obtained += $sub_total;
            $total_max += $sub_max;
            if ($m['grade'] === 'F') {
                $failed_count++;
            }
        }

        $overall_percentage = ($total_max > 0) ? round(($total_obtained / $total_max) * 100, 2) : 0.0;
        $grade = calculateGrade($overall_percentage);

        // Fetch existing attendance, assignment, practical, previous semester records
        $stmt = $pdo->prepare("SELECT * FROM academic_performance WHERE student_id = ? AND semester = ?");
        $stmt->execute([$student_id, $semester]);
        $existing = $stmt->fetch();

        $attendance = $existing['attendance_percentage'] ?? 80.0;
        $assignment = $existing['assignment_score'] ?? 80.0;
        $practical = $existing['practical_score'] ?? 80.0;
        $prev_pct = isset($existing['previous_semester_percentage']) ? (float)$existing['previous_semester_percentage'] : null;

        $improvement = ($prev_pct !== null) ? round($overall_percentage - $prev_pct, 2) : 0.0;

        $performance_score = calculatePerformanceScore($overall_percentage, $attendance, $assignment, $practical, $settings);
        $performance_category = calculatePerformanceCategory($performance_score);
        $alert_level = calculateAlertLevel($overall_percentage, $attendance, $failed_count, $improvement, $settings);

        // Upsert into academic_performance
        $academicYear = getCurrentAcademicYear();
        $upsert = $pdo->prepare("
            INSERT INTO academic_performance 
            (student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, previous_semester_percentage, improvement_percentage, performance_score, performance_category, alert_level)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_marks_obtained = VALUES(total_marks_obtained),
                total_max_marks = VALUES(total_max_marks),
                overall_percentage = VALUES(overall_percentage),
                grade = VALUES(grade),
                improvement_percentage = VALUES(improvement_percentage),
                performance_score = VALUES(performance_score),
                performance_category = VALUES(performance_category),
                alert_level = VALUES(alert_level)
        ");
        $upsert->execute([
            $student_id, $semester, $academicYear, $total_obtained, $total_max, $overall_percentage, $grade,
            $attendance, $assignment, $practical, $prev_pct, $improvement,
            $performance_score, $performance_category, $alert_level
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Recalculate Profile Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate Class Ranks and Evaluate System Achievements for all students
 */
function recalculateClassRanksAndAchievements($pdo, $semester = 5) {
    try {
        // 1. Assign Class Ranks based on overall_percentage DESC, performance_score DESC
        $stmt = $pdo->prepare("
            SELECT id, student_id, overall_percentage, performance_score, improvement_percentage, attendance_percentage, practical_score 
            FROM academic_performance 
            WHERE semester = ? 
            ORDER BY overall_percentage DESC, performance_score DESC
        ");
        $stmt->execute([$semester]);
        $rows = $stmt->fetchAll();

        $rank = 1;
        $updateRank = $pdo->prepare("UPDATE academic_performance SET class_rank = ? WHERE id = ?");
        foreach ($rows as $row) {
            $updateRank->execute([$rank, $row['id']]);
            $rank++;
        }

        // 2. Automated Achievement Evaluation
        // Topper (Rank 1)
        if (!empty($rows)) {
            $topper = $rows[0];
            awardAchievementIfNotExists($pdo, $topper['student_id'], 'Class Topper', 'fa-trophy', 'warning', "Rank #1 in Semester $semester with {$topper['overall_percentage']}% aggregate score");

            // Evaluate 90%+ Academic Excellence
            foreach ($rows as $r) {
                if ($r['overall_percentage'] >= 90.0) {
                    awardAchievementIfNotExists($pdo, $r['student_id'], 'Academic Excellence', 'fa-star', 'warning', "Achieved distinction with {$r['overall_percentage']}% in Semester $semester");
                }
            }

            // Most Improved Student
            $mostImproved = null;
            $maxImp = 0;
            foreach ($rows as $r) {
                if ($r['improvement_percentage'] > $maxImp && $r['improvement_percentage'] >= 5.0) {
                    $maxImp = $r['improvement_percentage'];
                    $mostImproved = $r;
                }
            }
            if ($mostImproved) {
                awardAchievementIfNotExists($pdo, $mostImproved['student_id'], 'Most Improved Student', 'fa-chart-line', 'success', "Recorded greatest academic leap of +{$mostImproved['improvement_percentage']}% points");
            }

            // Best Attendance (>= 92%)
            foreach ($rows as $r) {
                if ($r['attendance_percentage'] >= 92.0) {
                    awardAchievementIfNotExists($pdo, $r['student_id'], 'Best Attendance', 'fa-calendar-check', 'success', "Consistently demonstrated high attendance rate of {$r['attendance_percentage']}%");
                }
            }

            // Best Practical Performance (>= 95%)
            foreach ($rows as $r) {
                if ($r['practical_score'] >= 95.0) {
                    awardAchievementIfNotExists($pdo, $r['student_id'], 'Practical Performance Champion', 'fa-laptop-code', 'info', "Secured superior practical laboratory score of {$r['practical_score']}%");
                }
            }
        }

        // 3. Subject Toppers
        $stmt = $pdo->prepare("
            SELECT sm.student_id, sm.subject_id, sm.percentage, s.subject_name 
            FROM student_marks sm 
            JOIN subjects s ON sm.subject_id = s.id 
            WHERE sm.semester = ? 
            ORDER BY sm.subject_id, sm.percentage DESC
        ");
        $stmt->execute([$semester]);
        $subMarks = $stmt->fetchAll();

        $awardedSubjects = [];
        foreach ($subMarks as $sm) {
            if (!in_array($sm['subject_id'], $awardedSubjects)) {
                $awardedSubjects[] = $sm['subject_id'];
                awardAchievementIfNotExists(
                    $pdo,
                    $sm['student_id'],
                    "Subject Topper (" . htmlspecialchars($sm['subject_name']) . ")",
                    'fa-medal',
                    'primary',
                    "Secured top position in {$sm['subject_name']} with {$sm['percentage']}% score"
                );
            }
        }

        return true;
    } catch (PDOException $e) {
        error_log("Achievement Recalculate Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Award achievement if not already awarded to student
 */
function awardAchievementIfNotExists($pdo, $student_id, $title, $icon, $color, $description) {
    try {
        $check = $pdo->prepare("SELECT id FROM achievements WHERE student_id = ? AND title = ?");
        $check->execute([$student_id, $title]);
        if ($check->rowCount() == 0) {
            $insert = $pdo->prepare("
                INSERT INTO achievements (student_id, title, badge_icon, badge_color, description, awarded_date) 
                VALUES (?, ?, ?, ?, ?, CURRENT_DATE)
            ");
            $insert->execute([$student_id, $title, $icon, $color, $description]);
        }
    } catch (PDOException $e) {
        error_log("Award Achievement Error: " . $e->getMessage());
    }
}

/**
 * Generate Real, Deterministic Academic Insights from Student Database Records
 */
function generateSmartInsights($pdo, $student_id, $semester = 5) {
    $insights = [];

    try {
        // Fetch academic performance
        $stmt = $pdo->prepare("SELECT * FROM academic_performance WHERE student_id = ? AND semester = ?");
        $stmt->execute([$student_id, $semester]);
        $perf = $stmt->fetch();

        if (!$perf) {
            return ["No academic performance record found for current semester."];
        }

        // Fetch subject marks
        $stmt = $pdo->prepare("
            SELECT sm.*, s.subject_name, s.subject_code 
            FROM student_marks sm 
            JOIN subjects s ON sm.subject_id = s.id 
            WHERE sm.student_id = ? AND sm.semester = ? 
            ORDER BY sm.percentage DESC
        ");
        $stmt->execute([$student_id, $semester]);
        $subjects = $stmt->fetchAll();

        // 1. Strongest & Weakest Subjects
        if (!empty($subjects)) {
            $strongest = $subjects[0];
            $weakest = end($subjects);

            $insights[] = [
                'type' => 'success',
                'icon' => 'fa-arrow-up text-success',
                'title' => 'Strongest Academic Subject',
                'text' => "Excels in <strong>{$strongest['subject_name']}</strong> ({$strongest['subject_code']}) with a high score of <strong>{$strongest['percentage']}% (Grade {$strongest['grade']})</strong>."
            ];

            if ($weakest['subject_id'] != $strongest['subject_id']) {
                $weakType = ($weakest['percentage'] < 50) ? 'danger' : 'warning';
                $weakIcon = ($weakest['percentage'] < 50) ? 'fa-exclamation-triangle text-danger' : 'fa-info-circle text-warning';
                $insights[] = [
                    'type' => $weakType,
                    'icon' => $weakIcon,
                    'title' => 'Subject Requiring Attention',
                    'text' => "Lowest scoring area is <strong>{$weakest['subject_name']}</strong> with <strong>{$weakest['percentage']}% (Grade {$weakest['grade']})</strong>. Additional remedial focus recommended."
                ];
            }

            // Consistency analysis
            $minPct = $weakest['percentage'];
            $maxPct = $strongest['percentage'];
            if ($minPct >= 75.0) {
                $insights[] = [
                    'type' => 'info',
                    'icon' => 'fa-check-double text-info',
                    'title' => 'High Curriculum Consistency',
                    'text' => "Maintains uniform high mastery across all enrolled curriculum units (every subject scored at or above 75%)."
                ];
            }
        }

        // 2. Attendance Assessment
        $att = (float)$perf['attendance_percentage'];
        if ($att >= 90.0) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'fa-user-check text-success',
                'title' => 'Exemplary Attendance',
                'text' => "Institutional attendance is outstanding at <strong>{$att}%</strong>, strongly supporting academic retention and class participation."
            ];
        } elseif ($att < 65.0) {
            $insights[] = [
                'type' => 'danger',
                'icon' => 'fa-user-times text-danger',
                'title' => 'Critical Attendance Shortfall',
                'text' => "Attendance has dropped to <strong>{$att}%</strong> (below statutory 75% requirement). Mandatory faculty counseling and advisory notice required."
            ];
        } elseif ($att < 75.0) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'fa-exclamation-circle text-warning',
                'title' => 'Attendance Watch',
                'text' => "Current attendance stands at <strong>{$att}%</strong>, which is bordering institutional minimum standards."
            ];
        }

        // 3. Semester-over-Semester Trend Analysis
        $imp = (float)$perf['improvement_percentage'];
        $prev = $perf['previous_semester_percentage'];
        if ($prev !== null) {
            if ($imp >= 5.0) {
                $insights[] = [
                    'type' => 'success',
                    'icon' => 'fa-chart-line text-success',
                    'title' => 'Positive Growth Trajectory',
                    'text' => "Remarkable academic progression: Aggregate score rose by <strong>+{$imp}%</strong> points compared to Semester " . ($semester - 1) . " (from {$prev}% to {$perf['overall_percentage']}%)."
                ];
            } elseif ($imp <= -5.0) {
                $insights[] = [
                    'type' => 'danger',
                    'icon' => 'fa-arrow-trend-down text-danger',
                    'title' => 'Performance Decline Alert',
                    'text' => "Academic performance contracted by <strong>{$imp}%</strong> points relative to the prior semester baseline ({$prev}% down to {$perf['overall_percentage']}%)."
                ];
            } else {
                $insights[] = [
                    'type' => 'secondary',
                    'icon' => 'fa-equals text-muted',
                    'title' => 'Stable Academic Standing',
                    'text' => "Maintained steady academic performance with minimal variance ({$imp}% points delta) compared to Semester " . ($semester - 1) . "."
                ];
            }
        }

    } catch (PDOException $e) {
        error_log("Smart Insights Error: " . $e->getMessage());
    }

    return $insights;
}
