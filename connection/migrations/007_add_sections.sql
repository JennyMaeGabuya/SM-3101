-- NOTE: This migration will create and use a database named `learnhub` if one
-- doesn't already exist. If you use a different database name, replace
-- `learnhub` below or run this migration while your target database is selected.
CREATE DATABASE IF NOT EXISTS `learnhub` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `learnhub`;

CREATE TABLE IF NOT EXISTS `sections` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sections` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_section_unique` (`user_id`, `section_id`),
  INDEX (`user_id`),
  INDEX (`section_id`),
  CONSTRAINT `fk_user_sections_user` FOREIGN KEY (`user_id`) REFERENCES `accounts`(`accountId`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_sections_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed some example sections (idempotent)
INSERT IGNORE INTO `sections` (`id`, `name`)
VALUES
  (1, 'Section A'),
  (2, 'Section B'),
  (3, 'Section C');

