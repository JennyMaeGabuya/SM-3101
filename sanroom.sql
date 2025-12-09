-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Dec 09, 2025 at 08:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sanroom`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_creation_logs`
--

CREATE TABLE `account_creation_logs` (
  `log_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `teacher_name` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `admin_user` varchar(100) NOT NULL,
  `action_status` enum('Success','Completed','Reversed','Failed') NOT NULL,
  `log_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--

CREATE TABLE `class_enrollments` (
  `id` int(11) NOT NULL,
  `schedule_fk` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_access_codes`
--

CREATE TABLE `login_access_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_access_codes`
--

INSERT INTO `login_access_codes` (`id`, `user_id`, `role`, `code`) VALUES
(1, NULL, 'student', 'WMJJMRYK'),
(2, NULL, 'student', 'VXF3JD7E'),
(3, NULL, 'student', 'RDSN5GJJ'),
(4, NULL, 'student', 'MJVRCBNU'),
(5, NULL, 'student', 'PKDFYDZT'),
(6, NULL, 'student', 'ETAFJ5SD'),
(11, NULL, 'student', 'ZW3CW5L2'),
(12, NULL, 'student', 'YVJVNXS5'),
(13, NULL, 'student', '53LAFWFH'),
(14, 23, 'student', 'D2Jnsi'),
(15, 26, 'student', '4GMHVL'),
(16, 27, 'student', 'YJ0VIJ'),
(18, 29, 'student', 'LP8NA5'),
(19, 30, 'student', '39ZTMG'),
(20, 31, 'student', 'A4HVAI'),
(21, 32, 'student', 'OXER8V'),
(23, 34, 'student', 'EIS03F'),
(26, 37, 'student', 'X5VY2Z');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `sender_role` enum('student','teacher') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `manual_status` enum('available','occupied','reserved') NOT NULL DEFAULT 'available',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `special_access_code` varchar(255) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `capacity`, `manual_status`, `is_archived`, `special_access_code`, `is_hidden`) VALUES
(6, 'Special Event', 20, 'available', 0, 'DFEPXT', 0),
(7, 'Secret', 8, 'reserved', 0, 'CBLKTK', 0),
(201, 'CICS 201', 0, 'available', 0, NULL, 1),
(202, 'CICS 202', 0, 'available', 0, NULL, 1),
(203, 'CICS 203', 0, 'available', 0, NULL, 1),
(204, 'CICS 204', 0, 'available', 0, NULL, 1),
(301, 'CICS 301', 0, 'available', 0, NULL, 1),
(302, 'CICS 302', 0, 'available', 0, NULL, 1),
(303, 'CICS 303', 0, 'available', 0, NULL, 1),
(304, 'CICS 304', 0, 'available', 0, NULL, 1),
(401, 'CICS 401', 0, 'available', 0, NULL, 1),
(402, 'CICS 402', 0, 'available', 0, NULL, 1),
(403, 'CICS 403', 0, 'available', 0, NULL, 1),
(404, 'CICS 404', 0, 'available', 0, NULL, 1),
(1001, 'CICS NEW', 0, 'available', 0, NULL, 1),
(1002, 'CICS OLD', 0, 'available', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `room_invitations`
--

CREATE TABLE `room_invitations` (
  `room_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `invited_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_invitations`
--

INSERT INTO `room_invitations` (`room_id`, `student_id`, `invited_at`) VALUES
(7, 37, '2025-12-09 18:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `room_participants`
--

CREATE TABLE `room_participants` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_participants`
--

INSERT INTO `room_participants` (`id`, `room_id`, `student_id`, `joined_at`) VALUES
(38, 6, 37, '2025-12-10 01:58:42'),
(44, 7, 37, '2025-12-10 03:23:56');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `class_name` varchar(255) NOT NULL,
  `instructor_name` varchar(255) NOT NULL,
  `instructor_email` varchar(255) NOT NULL,
  `instructor_phone` varchar(50) NOT NULL,
  `instructor_image` varchar(255) DEFAULT NULL,
  `course_code` varchar(100) NOT NULL,
  `join_code` varchar(100) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `room_name` varchar(50) NOT NULL,
  `day` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `room_capacity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `class_name`, `instructor_name`, `instructor_email`, `instructor_phone`, `instructor_image`, `course_code`, `join_code`, `room_id`, `room_name`, `day`, `start_time`, `end_time`, `status`, `room_capacity`) VALUES
(11, 'Programming 101', 'Asher Basco', 'Asherbasco92@gmail.com', '09153175475', '1765295319_IMG20250113092825.jpg', 'IT-101', 'GOOGLE_M34T', 202, 'CICS 202', 'Monday', '07:00:00', '19:00:00', 'active', 40);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_participants`
--

CREATE TABLE `schedule_participants` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedule_participants`
--

INSERT INTO `schedule_participants` (`id`, `schedule_id`, `student_id`, `joined_at`) VALUES
(31, 11, 37, '2025-12-10 03:23:09');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `username`, `password_hash`, `created_at`) VALUES
(1, 'super_admin', '$2y$10$LthJEaFcbDEe6I8RvqEkyeUHSbH3RKUFHzRtVqZqE6W6av4hdMGv.', '2025-11-26 13:57:39');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL,
  `activation_code` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `qr_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `full_name`, `email`, `password_hash`, `department`, `activation_code`, `is_active`, `created_at`, `updated_at`, `qr_token`) VALUES
(28, 'Ash Basco', 'Asherbasco92@gmail.com', '$2y$10$gNU.cAM1bFAm.G1Os7JaAeiTyGQpPl1gooAQpJnyTGISh/yNgj8gi', '', '0', 0, '2025-12-09 22:56:09', NULL, 'ANW2WT4W58UH'),
(32, 'Ash basco', 'Ash02@gmail.com', '$2y$10$n1TLVETMjivfLBU78gN5gOzSyIb1DSm5c957csKVzvFHjyv1V0pgG', '', '$2y$10$y/6xRQnQpl6yFjIIKlcq4.IT3H3m1pOdQwYy8Re/WEh2FQkA4SQ1q', 0, '2025-12-09 23:27:26', NULL, '3N89DRFNTNYV'),
(33, 'Ash Basco', 'Ash03@gmail.com', '$2y$10$CLlfhF4NVNFagE5Gtpi6ne6WRjxIMFTGS8VCuPyjyCYe4E8mlcH5u', '', NULL, 1, '2025-12-09 23:44:16', '2025-12-09 23:47:08', 'J2J9VH29Q5XC');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'student',
  `activation_code` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `is_activated` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `activation_code`, `qr_token`, `is_activated`) VALUES
(36, '', 'User_one@gmail.com', '$2y$10$EwByaYpnSevksEDA..iz/OKu4uffsIGqjgXYw.BcVe7R8JJugiNHe', 'student', NULL, 'KKHTJPSS8B53', 1),
(37, '', 'Ash123@gmail.com', '$2y$10$o07hWlAURMVIV4w/NBd4lONJ09RcAFBvf7iXZshckjpURO8dB9/Lm', 'student', '0', 'Y3WU3RENPM6M', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_creation_logs`
--
ALTER TABLE `account_creation_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_fk` (`schedule_fk`);

--
-- Indexes for table `login_access_codes`
--
ALTER TABLE `login_access_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_name` (`room_name`),
  ADD KEY `idx_room_name` (`room_name`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `room_invitations`
--
ALTER TABLE `room_invitations`
  ADD PRIMARY KEY (`room_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `room_participants`
--
ALTER TABLE `room_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_id` (`room_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedule_room` (`room_id`);

--
-- Indexes for table `schedule_participants`
--
ALTER TABLE `schedule_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_schedule_student` (`schedule_id`,`student_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `activation_code` (`activation_code`),
  ADD UNIQUE KEY `qr_token` (`qr_token`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_creation_logs`
--
ALTER TABLE `account_creation_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_access_codes`
--
ALTER TABLE `login_access_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1003;

--
-- AUTO_INCREMENT for table `room_participants`
--
ALTER TABLE `room_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `schedule_participants`
--
ALTER TABLE `schedule_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_creation_logs`
--
ALTER TABLE `account_creation_logs`
  ADD CONSTRAINT `account_creation_logs_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `class_enrollments_ibfk_1` FOREIGN KEY (`schedule_fk`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_invitations`
--
ALTER TABLE `room_invitations`
  ADD CONSTRAINT `room_invitations_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_invitations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_participants`
--
ALTER TABLE `room_participants`
  ADD CONSTRAINT `room_participants_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `room_participants_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedule_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL;

--
-- Constraints for table `schedule_participants`
--
ALTER TABLE `schedule_participants`
  ADD CONSTRAINT `fk_participants_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_participants_user` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
