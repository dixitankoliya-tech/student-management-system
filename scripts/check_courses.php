<?php
require 'config/database.php';

echo "--- Departments ---\n";
print_r($pdo->query('SELECT id, department_name, department_code FROM departments')->fetchAll(PDO::FETCH_ASSOC));

echo "--- Courses in `courses` table ---\n";
print_r($pdo->query('SELECT course_name FROM courses')->fetchAll(PDO::FETCH_COLUMN));

echo "--- Courses in `students` table ---\n";
print_r($pdo->query('SELECT DISTINCT course FROM students')->fetchAll(PDO::FETCH_COLUMN));
?>
