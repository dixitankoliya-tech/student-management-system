<?php
require_once __DIR__ . '/../config/database.php';

if (!file_exists(__DIR__ . '/pre_migration_backup.json')) {
    die("Error: pre_migration_backup.json not found!\n");
}

echo "Reading pre_migration_backup.json...\n";
$snapshot = json_decode(file_get_contents(__DIR__ . '/pre_migration_backup.json'), true);

$pdo->beginTransaction();
try {
    // 1. Rollback students
    $updStu = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
    foreach ($snapshot['students'] as $s) {
        $updStu->execute([$s['first_name'], $s['last_name'], $s['email'], $s['id']]);
    }
    echo "Restored " . count($snapshot['students']) . " students.\n";

    // 2. Rollback faculty
    $updFac = $pdo->prepare("UPDATE faculty SET first_name = ?, last_name = ?, email = ?, designation = ? WHERE id = ?");
    foreach ($snapshot['faculty'] as $f) {
        $updFac->execute([$f['first_name'], $f['last_name'], $f['email'], $f['designation'], $f['id']]);
    }
    echo "Restored " . count($snapshot['faculty']) . " faculty.\n";

    // 3. Rollback subjects
    $updSub = $pdo->prepare("UPDATE subjects SET subject_name = ? WHERE id = ?");
    foreach ($snapshot['subjects'] as $sub) {
        $updSub->execute([$sub['subject_name'], $sub['id']]);
    }
    echo "Restored " . count($snapshot['subjects']) . " subjects.\n";

    // 4. Rollback users
    $updUser = $pdo->prepare("UPDATE users SET email = ?, full_name = ? WHERE id = ?");
    foreach ($snapshot['users'] as $u) {
        $updUser->execute([$u['email'], $u['full_name'], $u['id']]);
    }
    echo "Restored " . count($snapshot['users']) . " users.\n";

    $pdo->commit();
    echo "Rollback successfully completed.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Rollback failed: " . $e->getMessage() . "\n";
}
