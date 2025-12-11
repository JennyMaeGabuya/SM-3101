-- Migration: add program and year columns to accounts
-- Idempotent: will not fail if columns already exist

USE `learnHub`;

ALTER TABLE `accounts`
  ADD COLUMN IF NOT EXISTS `program` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `year` TINYINT(2) DEFAULT NULL;

-- Note: Some MySQL versions do not support ADD COLUMN IF NOT EXISTS combined; if you get an error
-- run the following instead in phpMyAdmin or mysql CLI:
-- ALTER TABLE `accounts` ADD COLUMN `program` VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE `accounts` ADD COLUMN `year` TINYINT(2) DEFAULT NULL;

-- Optional: set existing demo user program/year
UPDATE `accounts` SET `program` = 'Bachelor of Science in Computer Science', `year` = 1 WHERE `email` = 'student@batstateu.edu.ph' LIMIT 1;
