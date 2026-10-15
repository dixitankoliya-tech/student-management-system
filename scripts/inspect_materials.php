<?php
require 'config/database.php';
$rows = $pdo->query("SELECT id, title, file_path, link_url FROM study_materials LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
