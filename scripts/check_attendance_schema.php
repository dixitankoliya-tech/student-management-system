<?php
require 'config/database.php';
$stmt = $pdo->query('SHOW CREATE TABLE attendance');
echo $stmt->fetchColumn(1);
?>
