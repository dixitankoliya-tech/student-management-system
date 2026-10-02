<?php
// Main Entry Point Redirector
$port = $_SERVER['SERVER_PORT'];

if ($port == 8000) {
    header("Location: /admin/");
    exit;
} elseif ($port == 9100) {
    header("Location: /faculty/");
    exit;
} elseif ($port == 9000) {
    header("Location: /student/");
    exit;
} else {
    // Default fallback if accessed on standard port (e.g., 80)
    // Send to student portal as default public face, or a generic selection page
    header("Location: /student/");
    exit;
}
