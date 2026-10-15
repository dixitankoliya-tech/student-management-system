<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Check if index already exists
    $idx = $pdo->query("SHOW INDEX FROM fee_payments WHERE Key_name = 'idx_fee_payments_created_at'")->fetchAll();
    if (empty($idx)) {
        echo "Adding index idx_fee_payments_created_at on fee_payments(created_at)...\n";
        $pdo->exec("ALTER TABLE fee_payments ADD INDEX idx_fee_payments_created_at (created_at)");
        echo "Index added successfully.\n";
    } else {
        echo "Index idx_fee_payments_created_at already exists.\n";
    }
} catch (PDOException $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}
