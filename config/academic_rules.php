<?php
// config/academic_rules.php

class AcademicEngine {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getCurrentAcademicPeriod() {
        $stmt = $this->pdo->query("SELECT * FROM academic_calendars WHERE status = 'Active' LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getSemesterType($semester) {
        return ($semester % 2 == 0) ? 'Even' : 'Odd';
    }

    public static function getNextSemester($semester) {
        return $semester + 1;
    }

    public static function getPreviousSemester($semester) {
        return max(1, $semester - 1);
    }

    public function getClassStudentCount($course, $semester, $division, $academic_year) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM student_academic_enrollments 
            WHERE course = ? AND semester = ? AND division = ? AND academic_year = ? AND status = 'Active'
        ");
        $stmt->execute([$course, $semester, $division, $academic_year]);
        return (int) $stmt->fetchColumn();
    }

    public function canEnrollStudent($course, $semester, $division, $academic_year) {
        return $this->getClassStudentCount($course, $semester, $division, $academic_year) < 100;
    }

    public function getFacultySubjectCount($faculty_id, $academic_year, $semester_type) {
        $mod = ($semester_type === 'Odd') ? 1 : 0;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM faculty_subjects fs 
            WHERE fs.faculty_id = ? AND fs.academic_year = ? 
            AND (fs.semester % 2) = ?
        ");
        $stmt->execute([$faculty_id, $academic_year, $mod]);
        return (int) $stmt->fetchColumn();
    }

    public function canAssignFacultySubject($faculty_id, $academic_year, $semester_type) {
        return $this->getFacultySubjectCount($faculty_id, $academic_year, $semester_type) < 4;
    }

    public function checkAcademicCutoff() {
        $period = $this->getCurrentAcademicPeriod();
        if (!$period) return false;
        
        $currentDate = new DateTime();
        $cutoffDate = new DateTime($period['cutoff_date']);
        
        return $currentDate > $cutoffDate;
    }

    public function getFacultyUniqueStudentCount($faculty_id, $academic_year, $semester_type) {
        $mod = ($semester_type === 'Odd') ? 1 : 0;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT sae.student_id) 
            FROM student_academic_enrollments sae
            JOIN faculty_subjects fs 
              ON sae.course = fs.course 
             AND sae.semester = fs.semester 
             AND sae.division = fs.division 
             AND sae.academic_year = fs.academic_year
            WHERE fs.faculty_id = ? 
              AND fs.academic_year = ? 
              AND (fs.semester % 2) = ?
              AND sae.status = 'Active'
        ");
        $stmt->execute([$faculty_id, $academic_year, $mod]);
        return (int) $stmt->fetchColumn();
    }
}
