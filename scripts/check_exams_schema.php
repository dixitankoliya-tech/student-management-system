<?php
require 'config/database.php';
$stmt = $pdo->query('SHOW CREATE TABLE examinations');
echo $stmt->fetchColumn(1);
echo "\n";
$stmt2 = $pdo->query('SHOW CREATE TABLE student_marks');
echo $stmt2->fetchColumn(1);
?>
