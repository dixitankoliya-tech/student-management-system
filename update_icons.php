<?php

$dir = __DIR__;

// 1. Update sidebars
$sidebars = [
    "$dir/includes/admin_sidebar.php",
    "$dir/includes/faculty_sidebar.php",
    "$dir/includes/student_sidebar.php"
];

foreach ($sidebars as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Replace style="width: 20px;" and add nav-icon to the class
        // Example: <i class="fas fa-home me-3" style="width: 20px;"></i> 
        // Becomes: <i class="fas fa-home nav-icon me-3"></i>
        
        // Match: class="something me-3" style="width: 20px;"
        $content = preg_replace('/class="([^"]*?)\s*" style="width: 20px;"/', 'class="$1 nav-icon"', $content);
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}

// 2. Update dashboards
$dashboards = [
    "$dir/student/dashboard.php",
    "$dir/faculty/dashboard.php"
];

foreach ($dashboards as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Remove style="width: 36px; height: 36px; border-radius: 10px;"
        // Add kpi-icon to the div class
        // Find: class="(something) d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; border-radius: 10px;"
        // Replace: class="kpi-icon $1"
        $content = preg_replace('/class="([^"]*?) d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; border-radius: 10px;"/', 'class="kpi-icon $1"', $content);
        
        // Also remove style="font-size: 20px;" from the i tag inside since kpi-icon CSS handles it
        $content = preg_replace('/style="font-size: 20px;"/', '', $content);
        
        // Also fix the large icons that might have been missed in earlier cleanup
        $content = preg_replace('/fa-2x/', '', $content);
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}

// 3. Update admin dashboard
$adminDash = "$dir/admin/dashboard.php";
if (file_exists($adminDash)) {
    $content = file_get_contents($adminDash);
    
    // Change icon-box to kpi-icon
    $content = str_replace('class="icon-box ', 'class="kpi-icon ', $content);
    // Remove the inline style for icon-box
    $content = preg_replace('/\.icon-box\s*\{.*?\}/s', '', $content);
    
    file_put_contents($adminDash, $content);
    echo "Updated $adminDash\n";
}

echo "Icon update complete!\n";
