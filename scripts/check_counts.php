<?php
require_once __DIR__ . '/../config/database.php';

echo "Database Counts:\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach($tables as $t) {
    try {
        $cnt = $pdo->query("SELECT count(*) FROM `$t`")->fetchColumn();
        echo str_pad($t, 32) . ": $cnt\n";
    } catch(Exception $e) {
        echo str_pad($t, 32) . ": Error - " . $e->getMessage() . "\n";
    }
}
