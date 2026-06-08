-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.byetcluster.com
-- Generation Time: Jun 04, 2026 at 10:52 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `b31_41617640_my_polyfriends_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `name` varchar(50) NOT NULL,
  `role` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`name`, `role`, `bio`, `image`) VALUES
('עידן', 'מייסד ומפתח', 'מפתח פול-סטאק', 'https://ui-avatars.com/api/?name=Idan&background=0D8ABC&color=fff');

-- --------------------------------------------------------

--
-- Table structure for table `bets`
--

CREATE TABLE `bets` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `creator` varchar(50) DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `winning_option` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bets`
--

INSERT INTO `bets` (`id`, `title`, `amount`, `category`, `creator`, `status`, `winning_option`) VALUES
('bet_1', 'מי ינצח בבחירות הקרובות בארה\"ב?', '100.00', 'פוליטיקה', 'admin', 'open', NULL),
('bet_1780472366640', 'מי ינצח', '500.00', 'מדע וטכנולוגיה', 'guy098', 'open', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bet_options`
--

CREATE TABLE `bet_options` (
  `bet_id` varchar(50) NOT NULL,
  `option_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bet_options`
--

INSERT INTO `bet_options` (`bet_id`, `option_name`) VALUES
('bet_1', 'ביידן'),
('bet_1', 'טראמפ'),
('bet_1780472366640', 'אילון'),
('bet_1780472366640', 'גפרי');

-- --------------------------------------------------------

--
-- Table structure for table `bet_participants`
--

CREATE TABLE `bet_participants` (
  `bet_id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `option_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bet_participants`
--

INSERT INTO `bet_participants` (`bet_id`, `username`, `option_name`) VALUES
('bet_1', 'guy098', 'ביידן'),
('bet_1', 'idan', 'טראמפ');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`name`) VALUES
('כלכלה'),
('מדע וטכנולוגיה'),
('ספורט'),
('פוליטיקה'),
('קריפטו'),
('תרבות פופ');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` varchar(50) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `timestamp` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlements`
--

CREATE TABLE `settlements` (
  `id` int(11) NOT NULL,
  `from_user` varchar(50) NOT NULL,
  `to_user` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `timestamp` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`username`, `password`, `full_name`, `balance`) VALUES
('admin', '123', 'מנהל מערכת', '0.00'),
('guy098', '6965652', 'guy sahar', '0.00'),
('idan', '123', 'עידן', '0.00'),
('עידן לין', '123', 'עידן לין', '0.00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `bets`
--
ALTER TABLE `bets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `creator` (`creator`);

--
-- Indexes for table `bet_options`
--
ALTER TABLE `bet_options`
  ADD PRIMARY KEY (`bet_id`,`option_name`);

--
-- Indexes for table `bet_participants`
--
ALTER TABLE `bet_participants`
  ADD PRIMARY KEY (`bet_id`,`username`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `settlements`
--
ALTER TABLE `settlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_settlement` (`from_user`,`to_user`,`timestamp`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `settlements`
--
ALTER TABLE `settlements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bets`
--
ALTER TABLE `bets`
  ADD CONSTRAINT `fk_bets_category` FOREIGN KEY (`category`) REFERENCES `categories` (`name`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bets_creator` FOREIGN KEY (`creator`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bet_options`
--
ALTER TABLE `bet_options`
  ADD CONSTRAINT `fk_bet_options_bet` FOREIGN KEY (`bet_id`) REFERENCES `bets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bet_participants`
--
ALTER TABLE `bet_participants`
  ADD CONSTRAINT `fk_participants_bet` FOREIGN KEY (`bet_id`) REFERENCES `bets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_participants_user` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `fk_messages_user` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `settlements`
--
ALTER TABLE `settlements`
  ADD CONSTRAINT `fk_settlements_from` FOREIGN KEY (`from_user`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_settlements_to` FOREIGN KEY (`to_user`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
