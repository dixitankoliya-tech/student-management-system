ALTER TABLE `attendance` ADD KEY `idx_student_id` (`student_id`);
ALTER TABLE `attendance` DROP INDEX `unique_attendance`;
ALTER TABLE `attendance` ADD UNIQUE KEY `unique_attendance_subject` (`student_id`,`attendance_date`,`subject_id`);
