-- Migration: Add audience and target_value to quizzes
-- Run this file with mysql.exe or via phpMyAdmin to enable targeted quizzes

ALTER TABLE `learnHub`.`quizzes`
  ADD COLUMN IF NOT EXISTS `audience` VARCHAR(50) NOT NULL DEFAULT 'all',
  ADD COLUMN IF NOT EXISTS `target_value` VARCHAR(255) DEFAULT NULL;

-- Optional: normalize notification audiences to 'students' or 'user:<email>'
-- Existing notifications are left unchanged.
