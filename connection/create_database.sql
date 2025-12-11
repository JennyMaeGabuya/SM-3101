

CREATE DATABASE IF NOT EXISTS `learnHub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `learnHub`;

-- ===========================
-- ACCOUNTS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `accounts` (
  `accountId` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- TEACHERS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `teachers` (
  `teacher_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(255),
  `last_name` VARCHAR(255),
  `avatar` VARCHAR(255),
  `department` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- QUIZZES TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `scheduled_at` DATETIME,
  `deadline` DATETIME,
  `duration_minutes` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`teacher_id`) ON DELETE CASCADE,
  INDEX `idx_teacher` (`teacher_id`),
  INDEX `idx_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- PERFORMANCE TASKS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `performance_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `due_date` DATETIME,
  `group_allowed` TINYINT(1) DEFAULT 0,
  `rubric` TEXT,
  `audience` VARCHAR(50) DEFAULT 'students',
  `target_value` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`teacher_id`) ON DELETE CASCADE,
  INDEX `idx_teacher` (`teacher_id`),
  INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- ACTIVITIES TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `scheduled_at` DATETIME,
  `deadline` DATETIME,
  `audience` VARCHAR(50) DEFAULT 'students',
  `target_value` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`teacher_id`) ON DELETE CASCADE,
  INDEX `idx_teacher` (`teacher_id`),
  INDEX `idx_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- LEARNING MATERIALS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `learning_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `file_path` VARCHAR(500),
  `link` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`teacher_id`) ON DELETE CASCADE,
  INDEX `idx_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- SUBMISSIONS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `learnHub`.`submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `student_id` VARCHAR(255) NOT NULL,
  `resource_type` VARCHAR(60) NOT NULL,
  `resource_id` INT NOT NULL,
  `file_path` VARCHAR(500),
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(40) DEFAULT 'pending',
  `feedback` TEXT,
  INDEX `idx_teacher` (`teacher_id`),
  INDEX `idx_student` (`student_id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `learnHub`.`teachers`(`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- NOTIFICATIONS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `audience` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sender` (`sender_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- INSERT SAMPLE TEACHER DATA
-- ===========================

USE `learnHub`;

INSERT INTO `teachers` (username, email, password, first_name, last_name, department) 
VALUES ('teacher', 'teacher@batstateu.edu.ph', 'Teach@2024', 'Teacher', 'Account', 'Computer Science')
ON DUPLICATE KEY UPDATE password = VALUES(password);
 
-- ===========================
-- INSERT SAMPLE STUDENT DATA
-- ===========================
INSERT INTO `accounts` (username, email, password) 
VALUES ('student', 'student@batstateu.edu.ph', 'Demo@2024')
ON DUPLICATE KEY UPDATE password = VALUES(password);
