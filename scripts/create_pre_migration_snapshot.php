<?php
require_once __DIR__ . '/../config/database.php';

echo "Creating pre-migration snapshot...\n";
$snapshot = [];

$snapshot['students'] = $pdo->query("SELECT id, enrollment_no, first_name, last_name, email FROM students")->fetchAll(PDO::FETCH_ASSOC);
$snapshot['faculty'] = $pdo->query("SELECT id, faculty_id, first_name, last_name, email, designation, role FROM faculty")->fetchAll(PDO::FETCH_ASSOC);
$snapshot['subjects'] = $pdo->query("SELECT id, subject_code, subject_name, course, semester FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
$snapshot['users'] = $pdo->query("SELECT id, username, email, full_name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);

$json = json_encode($snapshot, JSON_PRETTY_PRINT);
file_put_contents(__DIR__ . '/pre_migration_backup.json', $json);

echo "Snapshot saved successfully to scripts/pre_migration_backup.json\n";
echo "Total students backed up: " . count($snapshot['students']) . "\n";
echo "Total faculty backed up: " . count($snapshot['faculty']) . "\n";
echo "Total subjects backed up: " . count($snapshot['subjects']) . "\n";
echo "Total users backed up: " . count($snapshot['users']) . "\n";
