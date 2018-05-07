-- phpMyAdmin SQL Dump
-- version 4.7.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 26, 2018 at 06:02 PM
-- Server version: 10.1.19-MariaDB
-- PHP Version: 7.2.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `andromeda2`
--

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_core_config`
--

CREATE TABLE `a2_objects_core_config` (
  `id` varchar(16) NOT NULL,
  `datadir` varchar(255) DEFAULT NULL,
  `apps*json` text NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `features__debug_log` tinyint(1) NOT NULL,
  `features__debug_http` tinyint(1) NOT NULL,
  `features__debug_file` tinyint(1) NOT NULL,
  `features__read_only` tinyint(1) NOT NULL,
  `features__enabled` tinyint(1) NOT NULL,
  `features__email` tinyint(1) NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `a2_objects_core_config`
--

INSERT INTO `a2_objects_core_config` (`id`, `datadir`, `apps*json`, `dates__created`, `features__debug_log`, `features__debug_http`, `features__debug_file`, `features__read_only`, `features__enabled`, `features__email`, `emailers*objectrefs*Core\FullEmailer*config`) VALUES
('zFoHvcNrXTwxrsLm', NULL, '[\"server\"]', 0, 2, 1, 0, 0, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_core_exceptions_errorlogentry`
--

CREATE TABLE `a2_objects_core_exceptions_errorlogentry` (
  `id` varchar(16) NOT NULL,
  `time` bigint(20) NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` varchar(255) NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` text,
  `trace_full` text,
  `objects` text,
  `queries` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_core_fullemailer`
--

CREATE TABLE `a2_objects_core_fullemailer` (
  `id` varchar(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text,
  `secure` tinyint(1) NOT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a2_objects_core_config`
--
ALTER TABLE `a2_objects_core_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`);

--
-- Indexes for table `a2_objects_core_exceptions_errorlogentry`
--
ALTER TABLE `a2_objects_core_exceptions_errorlogentry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `time` (`time`),
  ADD KEY `addr` (`addr`),
  ADD KEY `code` (`code`),
  ADD KEY `app` (`app`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `a2_objects_core_fullemailer`
--
ALTER TABLE `a2_objects_core_fullemailer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
