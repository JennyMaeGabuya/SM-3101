USE `learnHub`;

ALTER TABLE `quizzes`
  ADD COLUMN `max_attempts` INT NOT NULL DEFAULT 2 AFTER `updated_at`;

-- Set recommended defaults for existing quizzes (2 attempts = 1 retake)
UPDATE `quizzes` SET `max_attempts` = 2 WHERE `max_attempts` IS NULL;
