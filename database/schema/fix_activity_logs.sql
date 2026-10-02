ALTER TABLE `activity_logs` ADD COLUMN `user_id` int(11) DEFAULT NULL AFTER `id`;
ALTER TABLE `activity_logs` ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
