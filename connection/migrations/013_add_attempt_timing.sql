-- Migration: add timing columns to quiz_attempts
-- Run with mysql client or phpMyAdmin to enable timing info for attempts

USE `learnHub`;

ALTER TABLE `quiz_attempts`
  ADD COLUMN `started_at` DATETIME NULL,
  ADD COLUMN `duration_seconds` INT NULL;
