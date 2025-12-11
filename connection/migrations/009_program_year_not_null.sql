-- Migration: make program and year NOT NULL with defaults
-- This migration will:
-- 1) Set sensible defaults for existing rows where program/year are NULL
-- 2) Alter the table to make columns NOT NULL with DEFAULT values

USE `learnHub`;

-- Safety: set a visible default program for records missing it
UPDATE `accounts` SET `program` = 'Undeclared' WHERE `program` IS NULL OR TRIM(`program`) = '';
-- Ensure year has a valid value (1 is a sensible default)
UPDATE `accounts` SET `year` = 1 WHERE `year` IS NULL OR `year` = 0;

-- Alter the columns to be NOT NULL with defaults
ALTER TABLE `accounts`
  MODIFY COLUMN `program` VARCHAR(255) NOT NULL DEFAULT 'Undeclared',
  MODIFY COLUMN `year` TINYINT(2) NOT NULL DEFAULT 1;

-- Notes:
-- - If your MySQL version rejects MODIFY with multiple columns, run separate ALTER TABLE statements:
--   ALTER TABLE `accounts` MODIFY COLUMN `program` VARCHAR(255) NOT NULL DEFAULT 'Undeclared';
--   ALTER TABLE `accounts` MODIFY COLUMN `year` TINYINT(2) NOT NULL DEFAULT 1;
-- - Always backup your DB before applying schema changes.
