-- Migration: create schedule_participants table
-- Run this in your MySQL client (phpMyAdmin, mysql CLI, etc.)

CREATE TABLE IF NOT EXISTS `schedule_participants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_schedule_student` (`schedule_id`,`student_id`),
  KEY `idx_schedule_id` (`schedule_id`),
  KEY `idx_student_id` (`student_id`),
  CONSTRAINT `fk_participants_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
