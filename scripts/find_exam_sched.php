<?php
require_once __DIR__ . '/../config/database.php';

$res = $pdo->query("
    SELECT e.id, e.exam_name, e.course, e.semester, COUNT(es.id) as sched_count 
    FROM examinations e 
    LEFT JOIN exam_schedules es ON e.id = es.exam_id 
    GROUP BY e.id 
    HAVING sched_count > 0 
    ORDER BY sched_count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
