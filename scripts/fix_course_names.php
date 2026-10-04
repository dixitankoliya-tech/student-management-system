<?php
require 'config/database.php';

$map = [
    'B.Sc. IT (Dual)' => 'B.Sc. Information Technology (Dual)',
    'BCA' => 'Bachelor of Computer Applications',
    'BCA (Dual)' => 'Bachelor of Computer Applications (Dual)',
    'I.M.Sc. IT' => 'Integrated M.Sc. IT',
    'M.Sc. IT' => 'Master of Science in IT'
];

foreach ($map as $short => $long) {
    // Update students
    $stmt1 = $pdo->prepare("UPDATE students SET course = ? WHERE course = ?");
    $stmt1->execute([$long, $short]);
    $affected1 = $stmt1->rowCount();
    
    // Update enrollments
    $stmt2 = $pdo->prepare("UPDATE student_academic_enrollments SET course = ? WHERE course = ?");
    $stmt2->execute([$long, $short]);
    $affected2 = $stmt2->rowCount();
    
    echo "Updated $short to $long: Students: $affected1, Enrollments: $affected2\n";
}
echo "Done.\n";
?>
