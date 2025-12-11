-- Migration: add task_submissions table
USE `learnHub`;

CREATE TABLE IF NOT EXISTS `task_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(1024) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'submitted',
  `grade` VARCHAR(32) DEFAULT NULL,
  `graded_by` INT UNSIGNED DEFAULT NULL,
  `graded_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`task_id`),
  INDEX (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
