<?php
/**
 * SMS 2.0: Logout Handler
 * Destroys session and redirects to login page.
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

if (isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'Unknown';
    logActivity($pdo, 'LOGOUT', null, 'User "' . $username . '" logged out');
    logoutUser();
}

header('Location: login.php');
exit();
?>
