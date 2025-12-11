-- Migration: Create `submissions` table (legacy submissions used by quiz/teacher pages)
-- Run in phpMyAdmin SQL tab or via mysql client while connected to the `learnHub` database.

USE `learnHub`;

-- Create the table if it does not already exist
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `student_id` VARCHAR(255) NOT NULL,
  `resource_type` VARCHAR(60) NOT NULL,
  `resource_id` INT NOT NULL,
  `file_path` VARCHAR(500) DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(40) DEFAULT 'pending',
  `feedback` TEXT,
  `grade` VARCHAR(100) NULL,
  `graded_by` INT NULL,
  `graded_at` DATETIME NULL,
  INDEX (`teacher_id`),
  INDEX (`student_id`),
  INDEX (`resource_type`),
  INDEX (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure grading columns exist (MySQL 8+ supports ADD COLUMN IF NOT EXISTS)
ALTER TABLE `submissions`
  ADD COLUMN IF NOT EXISTS `grade` VARCHAR(100) NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `graded_by` INT NULL AFTER `grade`,
  ADD COLUMN IF NOT EXISTS `graded_at` DATETIME NULL AFTER `graded_by`;

-- Fallback guidance: if your MySQL does NOT support `ADD COLUMN IF NOT EXISTS`, run these commands
-- only for the columns that are missing (phpMyAdmin will report "Duplicate column" if already present):
-- ALTER TABLE `submissions` ADD COLUMN `grade` VARCHAR(100) NULL AFTER `status`;
-- ALTER TABLE `submissions` ADD COLUMN `graded_by` INT NULL AFTER `grade`;
-- ALTER TABLE `submissions` ADD COLUMN `graded_at` DATETIME NULL AFTER `graded_by`;

-- End of migration
