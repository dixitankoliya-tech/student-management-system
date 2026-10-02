<?php
session_name('SMS_FACULTY_SESSION');
session_start();
$_SESSION['user_id'] = 2; // Assuming 2 is a faculty user
$_SESSION['faculty_db_id'] = 1; // Assuming 1 is a faculty profile
$_SESSION['role'] = 'faculty';

// Include the dashboard
require_once 'dashboard.php';
