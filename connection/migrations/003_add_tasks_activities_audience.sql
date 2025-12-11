-- Migration: add audience and target_value to performance_tasks and activities
-- IMPORTANT: phpMyAdmin requires a selected database. This file will explicitly select the database below.
USE `learnHub`;

-- If your MySQL version supports ADD COLUMN IF NOT EXISTS you can run the following; otherwise run the alternate statements below.
ALTER TABLE `performance_tasks`
  ADD COLUMN IF NOT EXISTS `audience` VARCHAR(50) DEFAULT 'students',
  ADD COLUMN IF NOT EXISTS `target_value` VARCHAR(255) NULL;

ALTER TABLE `activities`
  ADD COLUMN IF NOT EXISTS `audience` VARCHAR(50) DEFAULT 'students',
  ADD COLUMN IF NOT EXISTS `target_value` VARCHAR(255) NULL;

-- ALTERNATIVE (for older MySQL versions that do NOT support IF NOT EXISTS):
-- ALTER TABLE `performance_tasks` ADD COLUMN `audience` VARCHAR(50) DEFAULT 'students';
-- ALTER TABLE `performance_tasks` ADD COLUMN `target_value` VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE `activities` ADD COLUMN `audience` VARCHAR(50) DEFAULT 'students';
-- ALTER TABLE `activities` ADD COLUMN `target_value` VARCHAR(255) DEFAULT NULL;
