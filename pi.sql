-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 17, 2025 at 06:59 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pi`
--

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int NOT NULL,
  `year_id` int NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`id`, `year_id`, `name`) VALUES
(1, 1, 'G1'),
(2, 1, 'G2'),
(3, 1, 'G3'),
(4, 1, 'G4'),
(5, 2, 'DSI 1'),
(6, 2, 'DSI 2'),
(7, 2, 'RSS'),
(8, 2, 'DWM'),
(9, 3, 'DSI 1'),
(10, 3, 'DSI 2'),
(11, 3, 'RSS'),
(12, 3, 'DWM');

-- --------------------------------------------------------

--
-- Table structure for table `professor_subjects`
--

CREATE TABLE `professor_subjects` (
  `id` int NOT NULL,
  `professor_id` int NOT NULL,
  `subject_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `professor_subjects`
--

INSERT INTO `professor_subjects` (`id`, `professor_id`, `subject_id`) VALUES
(1, 2, 4),
(2, 2, 7),
(3, 3, 5),
(4, 18, 5),
(5, 18, 7),
(6, 19, 4),
(7, 20, 1),
(9, 21, 2),
(8, 21, 3),
(10, 22, 6);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`) VALUES
(1, 'adobe'),
(2, 'algebre 1'),
(3, 'algebre 2'),
(4, 'BD 2'),
(5, 'C ++'),
(6, 'proba statistique'),
(7, 'python'),
(8, 'systeme d\'exploitation');

-- --------------------------------------------------------

--
-- Table structure for table `timetables`
--

CREATE TABLE `timetables` (
  `id` int NOT NULL,
  `year_id` int NOT NULL,
  `group_id` int NOT NULL,
  `day` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_slot` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `room` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `is_reschedule` tinyint(1) NOT NULL DEFAULT '0',
  `class_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_split` tinyint(1) NOT NULL DEFAULT '0',
  `split_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professor2_id` int DEFAULT NULL,
  `subject2_id` int DEFAULT NULL,
  `room2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subgroup1` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subgroup2` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subgroup` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professor1_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `professor1_rescheduled` tinyint(1) NOT NULL DEFAULT '0',
  `professor2_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `professor2_rescheduled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student','professor') NOT NULL,
  `group_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `group_id`, `created_at`) VALUES
(1, 'Admin User', 'admin@university.com', '$2y$10$5sc8bd7qJnf7TLPP.kWmFOG9PjBF19SjYrhnXyKB3kg/NhsU2qG36', 'admin', NULL, '2025-04-26 16:16:27'),
(2, 'Moussa', 'moussa@university.com', '$2y$10$PgDqhzV8fM08sajhpTlTvOnKiZVaz6sUS4qQYSChvJIK/o3Q6YqSq', 'professor', NULL, '2025-04-26 16:16:27'),
(3, 'cheikh', 'cheikh@university.com', '$2y$10$Hxv1AyFL/RZp4s9vtqoRqOKb04jQjxD9JL0K0HyruZgx7z/Rqxr/G', 'professor', NULL, '2025-04-26 16:16:27'),
(4, 'student1', 'student1@university.com', '$2y$10$tF1VwOtpjyhkPNc8Q7Xppe6ioB/XwCLTY3yH1.otNqs52maH2cA6a', 'student', 1, '2025-04-26 16:16:27'),
(5, 'student2', 'student2@university.com', '$2y$10$klvayYAQKW0fZcBU7d0isuF08GgRsgt1uxWL3kzfblwetp7G5CrQ6', 'student', 2, '2025-04-26 16:16:27'),
(6, 'kaber', 'kabermedhasni653@gmail.com', '$2y$10$bHtKy4T4Dh8gd/BhZaWt2.kcIk/h7Wb0UmXWlq43lOKNza1dLezAG', 'admin', NULL, '2025-04-26 16:16:27'),
(8, 'student3', 'student3@university.com', '$2y$10$WY7/KoeCnFcZ0Yhd86dk7O85nROM6yqdoFBKE/RHIi.UcKyq8BLkS', 'student', 3, '2025-04-26 16:16:27'),
(9, 'student4', 'student4@university.com', '$2y$10$/dvJfFdnhJlTj/jvmcuU2.kEru6Uv6Rj0XPMaRLjuZpEFxSYbaOxe', 'student', 4, '2025-04-26 16:16:27'),
(10, 'student5', 'student5@university.com', '$2y$10$RMdgOE902bm8MY9tHPsvOuxd12PEXDyoaDv7DsVK3gKftj36tpLqa', 'student', 5, '2025-04-26 16:16:27'),
(11, 'student6', 'student6@university.com', '$2y$10$k0Q.OnjbyLJYaSxCcMIukeRxbuIE3usbbDfG42tYwk4JndfTotpCK', 'student', 6, '2025-04-26 16:16:27'),
(12, 'student7', 'student7@university.com', '$2y$10$QeFlFU/FyhvjIpHrhHAlKeIQ0unNJVcvRewxKi35SZ8edk2/fP9nK', 'student', 7, '2025-04-26 16:16:27'),
(13, 'student8', 'student8@university.com', '$2y$10$FQRwvYwnxgzjtl4Dwjd.iee2RZE.z4q.hOZlHXA5EyC3FY9i0t7W2', 'student', 8, '2025-04-26 16:16:27'),
(18, 'sidi med', 'sidimed@gmail.com', '$2y$10$IA5P0gEH.bn4Re6qbS5Nd.xKQ5KQfWwO9LOAJjGdYXb0hSSt4.Ljm', 'professor', NULL, '2025-04-26 16:16:27'),
(19, 'med lemin', 'medlemin@gmail.com', '$2y$10$FsOco5944SDyUd//rLQ6BOm8Gtm4cUVjNPPVANzCCj8lwEpvpWGGm', 'professor', NULL, '2025-04-26 16:16:27'),
(20, 'cheikhani', 'cheikhani@gmail.com', '$2y$10$oA7cZW5OTbasBXLuOITD3Onr1PLyHZkVLkXoYnYRh7rWEuOx.LaaO', 'professor', NULL, '2025-04-26 16:16:27'),
(21, 'habeb', 'habeb@gmail.com', '$2y$10$HznabSJk16zitxatyryCSe4rRfuOOYT60wDPWxjlV3gib91UjTO16', 'professor', NULL, '2025-04-26 16:16:27'),
(22, 'bekar', 'bekar@gmail.com', '$2y$10$Cm7EYIng8zWbEXhfo/XN5eYOaGYFdbE0PVyqXZRbcefHzeBnMZqbq', 'professor', NULL, '2025-04-26 16:16:27'),
(24, 'Moussa', 'Moussa@gmail.com', '$2y$10$Nhr5tClmDNp1xxp4NZGbruicnPKvew72fiHp/Ec4w1Ty5vTgdaCnq', 'admin', NULL, '2025-05-20 19:24:59'),
(25, '2ymen', '24124@supnum.mr', '$2y$10$siG8PcIrlYfLS7ucdLQIdOo1uFrLnC4nv8ov4K/QsFX3HKcxd8FdO', 'student', 9, '2025-05-23 20:24:18'),
(26, 'Lhacen med', '2l7acenmed653@gmail.com', '$2y$10$9g.WT0vtftHHGU8B4spez.4np2Dw996PaawuU291NCagq0yjfvQSW', 'admin', NULL, '2025-06-01 14:43:26'),
(27, 'kaber sidi', 'kaber@hi.com', '$2y$10$sMVk.GAch6GU8D2vD6b01.JgTdASFY5iAv1ZhdXuIzmSKLFJ2sU8.', 'student', 7, '2025-11-14 16:20:20');

-- --------------------------------------------------------

--
-- Table structure for table `years`
--

CREATE TABLE `years` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `years`
--

INSERT INTO `years` (`id`, `name`) VALUES
(1, 'Première Année'),
(2, 'Deuxième Année'),
(3, 'Troisième Année');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `year_id` (`year_id`);

--
-- Indexes for table `professor_subjects`
--
ALTER TABLE `professor_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `professor_subject` (`professor_id`,`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `timetables`
--
ALTER TABLE `timetables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `year_group_index` (`year_id`,`group_id`),
  ADD KEY `professor_index` (`professor_id`),
  ADD KEY `subject_index` (`subject_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `years`
--
ALTER TABLE `years`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1014;

--
-- AUTO_INCREMENT for table `professor_subjects`
--
ALTER TABLE `professor_subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `timetables`
--
ALTER TABLE `timetables`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `years`
--
ALTER TABLE `years`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetables_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetables_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `timetables_ibfk_4` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
