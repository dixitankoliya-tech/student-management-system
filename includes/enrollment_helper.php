<?php
/**
 * SMS 2.0: Automatic Enrollment Number System
 * 
 * Course codes are loaded from the database (courses.course_code).
 * Year-level mappings (FY/SY/TY) remain structural constants.
 */

/**
 * Returns enrollment mappings.
 * Course codes are loaded from the database when $pdo is available,
 * otherwise falls back to a hardcoded set for backward compatibility.
 */
function getEnrollmentMappings($pdo = null) {
    $courseCodes = [
        'BSc IT' => '0161901',
        'BCA'    => '0251701',
        'MBA'    => '0751301',
        'BBA'    => '0351501',
        'MCA'    => '0851401',
    ];

    // Attempt to load from database for latest data
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT course_name, course_code FROM courses WHERE status = 'Active' AND course_code IS NOT NULL AND course_code != ''");
            $dbCodes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($dbCodes)) {
                $courseCodes = $dbCodes;
            }
        } catch (PDOException $e) {
            error_log("Enrollment mapping DB fallback: " . $e->getMessage());
        }
    }

    return [
        'courseCodes' => $courseCodes,
        'startingYears' => [
            'FY' => '2026',
            'SY' => '2025',
            'TY' => '2024',
        ]
    ];
}

/**
 * Validates course and academic year, and returns the starting year and course code.
 * @return array|false [starting_year, course_code] or false if invalid
 */
function getEnrollmentPrefixData($course, $academicYear, $pdo = null) {
    $mappings = getEnrollmentMappings($pdo);
    
    if (!isset($mappings['courseCodes'][$course]) || !isset($mappings['startingYears'][$academicYear])) {
        return false;
    }
    
    return [
        $mappings['startingYears'][$academicYear],
        $mappings['courseCodes'][$course]
    ];
}

/**
 * Validates the structure of an enrollment number
 * @param string $enrollment_no
 * @param string $expected_course
 * @param string $expected_academic_year
 * @return bool
 */
function validateEnrollmentStructure($enrollment_no, $expected_course, $expected_academic_year) {
    if (!preg_match('/^[0-9]{15}$/', $enrollment_no)) {
        return false;
    }
    
    $prefixData = getEnrollmentPrefixData($expected_course, $expected_academic_year);
    if (!$prefixData) {
        return false;
    }
    
    list($expected_year, $expected_code) = $prefixData;
    $expected_prefix = $expected_year . $expected_code;
    
    return strpos($enrollment_no, $expected_prefix) === 0;
}

/**
 * Generates the next available enrollment number for a given course and academic year.
 * Must be called within a database transaction!
 * @param PDO $pdo
 * @param string $course
 * @param string $academicYear
 * @return string
 * @throws Exception if mappings are invalid
 */
function generateNextEnrollmentNumber(PDO $pdo, $course, $academicYear) {
    $prefixData = getEnrollmentPrefixData($course, $academicYear, $pdo);
    if (!$prefixData) {
        throw new Exception("Invalid Course or Academic Year mapping.");
    }
    
    list($starting_year, $course_code) = $prefixData;
    $prefix = $starting_year . $course_code;
    
    // We lock the table rows that match this prefix using FOR UPDATE
    $stmt = $pdo->prepare("SELECT enrollment_no FROM students WHERE enrollment_no LIKE ? ORDER BY enrollment_no DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$prefix . '%']);
    $last_enrollment = $stmt->fetchColumn();
    
    if ($last_enrollment) {
        $last_seq = (int) substr($last_enrollment, -4);
        $next_seq = $last_seq + 1;
    } else {
        $next_seq = 1;
    }
    
    return $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Derives academic year level (FY, SY, TY) from semester number.
 */
function getAcademicYearFromSemester($semester) {
    if ($semester == 1 || $semester == 2) return 'FY';
    if ($semester == 3 || $semester == 4) return 'SY';
    if ($semester == 5 || $semester == 6) return 'TY';
    if ($semester == 7 || $semester == 8) return 'FY4'; // 4-year programs
    return null;
}

/**
 * Get the full list of student lifecycle statuses
 */
function getStudentLifecycleStatuses() {
    return ['Active', 'Promoted', 'On Hold', 'Withdrawn', 'Completed', 'Alumni'];
}
?>
