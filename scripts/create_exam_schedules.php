<?php
require 'config/database.php';
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `exam_schedules` (
            `id` int NOT NULL AUTO_INCREMENT,
            `exam_id` int NOT NULL,
            `subject_id` int NOT NULL,
            `exam_date` date NOT NULL,
            `start_time` time NOT NULL,
            `end_time` time NOT NULL,
            `room_no` varchar(50) DEFAULT NULL,
            `invigilator_id` int DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`exam_id`) REFERENCES `examinations` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`invigilator_id`) REFERENCES `faculty` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "exam_schedules table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
