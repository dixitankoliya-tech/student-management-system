<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS academic_calendars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_year VARCHAR(20) NOT NULL,
            semester_type ENUM('Odd', 'Even') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            cutoff_date DATE NOT NULL,
            status ENUM('Active', 'Upcoming', 'Completed') DEFAULT 'Upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert current calendar if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM academic_calendars");
    if ($stmt->fetchColumn() == 0) {
        // Set cutoff as 30 Nov of the academic year's start
        $pdo->exec("
            INSERT INTO academic_calendars (academic_year, semester_type, start_date, end_date, cutoff_date, status)
            VALUES ('2026-2027', 'Odd', '2026-07-01', '2026-12-15', '2026-11-30', 'Active')
        ");
    }
    
    echo "academic_calendars table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
