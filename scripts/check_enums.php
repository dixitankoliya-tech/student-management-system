<?php
require_once __DIR__ . '/../config/database.php';

try {
    $r = $pdo->query('DESCRIBE examinations')->fetchAll(PDO::FETCH_ASSOC);
    print_r($r);
} catch(Exception $e) { echo $e->getMessage(); }
