<?php
require_once __DIR__ . '/../config/database.php';

echo "=== TESTING ASSIGN QUERY WITH 'start_date' ===\n";
try {
    $stmt = $pdo->prepare("INSERT INTO department_heads (department_id, faculty_id, start_date, status) VALUES (1, 1, CURDATE(), 'Active')");
    $stmt->execute();
    echo "SUCCESS (Unexpected!)\n";
} catch (PDOException $e) {
    echo "CAUGHT EXPECTED ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING ASSIGN QUERY WITH 'assigned_date' ===\n";
try {
    $stmt = $pdo->prepare("EXPLAIN INSERT INTO department_heads (department_id, faculty_id, assigned_date, status) VALUES (1, 1, CURDATE(), 'Active')");
    $stmt->execute();
    echo "SUCCESS: 'assigned_date' is valid column in department_heads!\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING COURSE_COORDINATORS ASSIGN QUERY WITH 'assigned_date' ===\n";
try {
    $stmt = $pdo->prepare("EXPLAIN INSERT INTO course_coordinators (course_id, faculty_id, assigned_date, status) VALUES (1, 1, CURDATE(), 'Active')");
    $stmt->execute();
    echo "SUCCESS: 'assigned_date' is valid column in course_coordinators!\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
