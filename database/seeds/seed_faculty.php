<?php
/**
 * SMS 2.0: Faculty Demo Data Seed
 */
if (php_sapi_name() !== 'cli') {
    die("ERROR: This script must be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

echo "Cleaning existing faculty data...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM faculty_subjects");
$pdo->exec("DELETE FROM timetables");
$pdo->exec("DELETE FROM study_materials");
$pdo->exec("DELETE FROM assignments");
$pdo->exec("DELETE FROM users WHERE role = 'faculty'");
$pdo->exec("DELETE FROM faculty");
$pdo->exec("ALTER TABLE faculty AUTO_INCREMENT = 1");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$faculties = [
    ['Dr. Rahul', 'Shah', 'rahul.shah@glsuniversity.ac.in', '9876543210', 'Computer Science', 'Professor'],
    ['Dr. Sneha', 'Desai', 'sneha.desai@glsuniversity.ac.in', '9876543211', 'Computer Science', 'Associate Professor'],
    ['Mr. Amit', 'Patel', 'amit.patel@glsuniversity.ac.in', '9876543212', 'Information Technology', 'Assistant Professor'],
    ['Ms. Priya', 'Mehta', 'priya.mehta@glsuniversity.ac.in', '9876543213', 'Information Technology', 'Assistant Professor'],
    ['Dr. Vikram', 'Joshi', 'vikram.joshi@glsuniversity.ac.in', '9876543214', 'Management', 'Professor'],
    ['Ms. Neha', 'Bhatt', 'neha.bhatt@glsuniversity.ac.in', '9876543215', 'Management', 'Assistant Professor'],
];

echo "Creating faculty and accounts...\n";
$insertFac = $pdo->prepare("INSERT INTO faculty (faculty_id, first_name, last_name, email, phone, department, designation, joining_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$insertUser = $pdo->prepare("INSERT INTO users (faculty_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'faculty', 1, 1)");

$pdo->beginTransaction();
try {
    $i = 1;
    foreach ($faculties as $f) {
        $fac_id = 'FAC-2026-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $join_date = '2020-07-01';
        $insertFac->execute([$fac_id, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $join_date]);
        $fid = $pdo->lastInsertId();
        
        $passwordHash = password_hash('faculty123', PASSWORD_DEFAULT);
        $fullName = $f[0] . ' ' . $f[1];
        $insertUser->execute([$fid, $fac_id, $f[2], $passwordHash, $fullName]);
        $i++;
    }
    $pdo->commit();
    echo "Successfully created " . count($faculties) . " faculty members. Password is 'faculty123'.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage() . "\n");
}
