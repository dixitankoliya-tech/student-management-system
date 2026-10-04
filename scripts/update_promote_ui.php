<?php
$file = 'admin/promote_students.php';
$content = file_get_contents($file);

$search = '<?php if ($selected_course && $selected_semester): ?>';
$replace = '<?php 
$max_semesters = 6;
if ($selected_course) {
    $stmtC = $pdo->prepare("SELECT duration_years FROM courses WHERE course_name = ?");
    $stmtC->execute([$selected_course]);
    $courseObj = $stmtC->fetch();
    $max_semesters = $courseObj ? ($courseObj[\'duration_years\'] * 2) : 6;
}
$is_graduating = ($selected_semester == $max_semesters);
?>
<?php if ($selected_course && $selected_semester): ?>';

$search2 = '<h6 class="m-0 fw-bold text-primary">Students Eligible for Promotion (Sem <?php echo htmlspecialchars($selected_semester); ?> &rarr; <?php echo (int)$selected_semester + 1; ?>)</h6>';
$replace2 = '<h6 class="m-0 fw-bold text-primary">Students Eligible for <?= $is_graduating ? "Graduation" : "Promotion (Sem $selected_semester &rarr; " . ((int)$selected_semester + 1) . ")" ?></h6>';

$search3 = '<div>
                        <span class="fw-bold">Promote to:</span> <span class="badge bg-primary fs-6 ms-2">Semester <?php echo (int)$selected_semester + 1; ?></span>
                    </div>
                    <button type="submit" class="btn btn-success px-4" onclick="return confirm(\'Are you sure you want to promote the selected students?\');">
                        <i class="fas fa-forward me-2"></i> Promote Selected Students
                    </button>';
$replace3 = '<div>
                        <?php if ($is_graduating): ?>
                            <span class="fw-bold">Action:</span> <span class="badge bg-success fs-6 ms-2">Graduate Students (Mark Alumni)</span>
                        <?php else: ?>
                            <span class="fw-bold">Promote to:</span> <span class="badge bg-primary fs-6 ms-2">Semester <?= (int)$selected_semester + 1 ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-success px-4" onclick="return confirm(\'Are you sure you want to <?= $is_graduating ? "graduate" : "promote" ?> the selected students?\');">
                        <i class="fas <?= $is_graduating ? "fa-graduation-cap" : "fa-forward" ?> me-2"></i> <?= $is_graduating ? "Graduate" : "Promote" ?> Selected Students
                    </button>';

$content = str_replace($search, $replace, $content);
$content = str_replace($search2, $replace2, $content);
$content = str_replace(str_replace("\n", "\r\n", $search3), $replace3, $content);
$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);
echo "Updated promote_students.php\n";
?>
