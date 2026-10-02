<?php
// MOCK SESSION
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'faculty';
$_SESSION['faculty_db_id'] = 1;
$_SESSION['faculty_string_id'] = 1;
$_SESSION['faculty_id'] = 1;

// Mock Server variables so dashboard thinks it's a real request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/faculty/dashboard.php';

// Include the dashboard
ob_start();
try {
    chdir(__DIR__ . '/../faculty');
    include 'dashboard.php';
    $output = ob_get_clean();
    echo "DASHBOARD INCLUDED SUCCESSFULLY. No 500 error.\n";
    echo "Output length: " . strlen($output) . " bytes.\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "FATAL ERROR CAUGHT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
