-- Migration: Add duration_minutes to quizzes
-- Run this file with mysql.exe or via phpMyAdmin to enable per-quiz timers

USE `learnHub`;

ALTER TABLE `quizzes` 
  ADD COLUMN `duration_minutes` INT DEFAULT NULL;

-- Set a default of NULL for existing quizzes (no timer). Teachers can set it via the editor.
