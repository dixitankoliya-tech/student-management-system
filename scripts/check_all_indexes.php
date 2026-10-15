<?php
require_once __DIR__ . '/../config/database.php';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $t) {
    echo "=== $t ===\n";
    $stmt = $pdo->query("SHOW INDEX FROM `$t`");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $grouped = [];
    foreach ($indexes as $idx) {
        $grouped[$idx['Key_name']][] = $idx['Column_name'];
    }
    foreach ($grouped as $k => $cols) {
        echo "  - $k (" . implode(', ', $cols) . ")\n";
    }
}
