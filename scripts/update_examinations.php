<?php
$file = 'admin/examinations.php';
$content = file_get_contents($file);

// Replace PHP array logic
$content = str_replace(
    '$exam_name = trim($_POST[\'exam_name\'] ?? \'\');',
    "\$exam_name = trim(\$_POST['exam_name'] ?? '');\n        \$exam_type = \$_POST['exam_type'] ?? 'End Semester';",
    $content
);

$content = str_replace(
    'INSERT INTO examinations (exam_name, course, academic_year, semester, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)',
    'INSERT INTO examinations (exam_name, exam_type, course, academic_year, semester, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)',
    $content
);

$content = str_replace(
    '[$exam_name, $course, $academic_year, $semester, $start_date, $end_date]',
    '[$exam_name, $exam_type, $course, $academic_year, $semester, $start_date, $end_date]',
    $content
);

$content = str_replace(
    '<td><strong><?= htmlspecialchars($ex[\'exam_name\']) ?></strong></td>',
    '<td><strong><?= htmlspecialchars($ex[\'exam_name\']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($ex[\'exam_type\']) ?></small></td>',
    $content
);

// Replace UI
$searchUI = '<div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Exam Name (e.g. Mid-Term, Final)</label>
                        <input type="text" name="exam_name" class="form-control" required>
                    </div>';

$replaceUI = '<div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label text-muted fw-semibold">Exam Name (e.g. Mid-Term, Final)</label>
                            <input type="text" name="exam_name" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-muted fw-semibold">Exam Type</label>
                            <select name="exam_type" class="form-select" required>
                                <option value="End Semester">End Semester</option>
                                <option value="Mid Semester">Mid Semester</option>
                                <option value="Internal">Internal</option>
                                <option value="Practical">Practical</option>
                                <option value="Backlog">Backlog / ATKT</option>
                            </select>
                        </div>
                    </div>';

$content = str_replace($searchUI, $replaceUI, $content);
// Also try to replace with different line endings if previous fails
$searchUI2 = str_replace("\n", "\r\n", $searchUI);
$replaceUI2 = str_replace("\n", "\r\n", $replaceUI);
$content = str_replace($searchUI2, $replaceUI2, $content);

file_put_contents($file, $content);
echo "Updated examinations.php\n";
?>
