<?php
require_once __DIR__ . '/../config/database.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$out = "";
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_ASSOC);
    $out .= $create['Create Table'] . ";\n\n";
}
file_put_contents(__DIR__ . '/schema.sql', $out);
echo "Schema dumped to schema.sql\n";
