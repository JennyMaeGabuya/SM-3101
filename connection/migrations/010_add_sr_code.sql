-- Migration: Add sr_code column to accounts and unique index
-- Run this once (e.g., via your migration tool or manually in phpMyAdmin)

ALTER TABLE `accounts`
  ADD COLUMN `sr_code` VARCHAR(64) NULL AFTER `email`;

-- Create a unique index for sr_code so duplicate non-NULL values are rejected
CREATE UNIQUE INDEX `uq_accounts_sr_code` ON `accounts` (`sr_code`);

-- Note: MySQL allows multiple NULLs in a UNIQUE index. Existing rows without sr_code will not conflict.
