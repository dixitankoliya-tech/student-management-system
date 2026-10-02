<?php
require_once '../../config/database.php';
try {
    $pdo->exec("ALTER TABLE fee_structures ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active'");
    echo "Added status to fee_structures.\n";
} catch (PDOException $e) {
    echo "fee_structures status column might already exist: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE student_fees ADD COLUMN status ENUM('Paid', 'Partially Paid', 'Pending', 'Overdue', 'Waived') DEFAULT 'Pending'");
    echo "Added status to student_fees.\n";
} catch (PDOException $e) {
    echo "student_fees status column might already exist: " . $e->getMessage() . "\n";
}
