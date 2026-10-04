<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'faculty' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
