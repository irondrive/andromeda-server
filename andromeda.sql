-- phpMyAdmin SQL Dump
-- version 4.7.9
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 06, 2018 at 03:44 AM
-- Server version: 5.7.21-0ubuntu0.16.04.1
-- PHP Version: 7.2.2-3+ubuntu16.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `andromeda`
--

-- --------------------------------------------------------

--
-- Table structure for table `a2_core_log_access`
--

CREATE TABLE `a2_core_log_access` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_core_log_error`
--

CREATE TABLE `a2_core_log_error` (
  `id` int(11) NOT NULL,
  `time` bigint(20) NOT NULL,
  `ipaddr` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` text NOT NULL,
  `trace_full` text NOT NULL,
  `objects` text NOT NULL,
  `queries` text NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `client` varchar(16) NOT NULL,
  `account` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_core_emailer`
--

CREATE TABLE `a2_objects_core_emailer` (
  `id` varchar(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text,
  `secure` tinyint(1) NOT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL,
  `server*object*Core\Server` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_core_server`
--

CREATE TABLE `a2_objects_core_server` (
  `id` varchar(16) NOT NULL,
  `datadir` varchar(255) DEFAULT NULL,
  `apps*json` text NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `features__debug_http` tinyint(1) NOT NULL,
  `features__debug_log` tinyint(1) NOT NULL,
  `features__debug_file` tinyint(1) NOT NULL,
  `features__read_only` tinyint(1) NOT NULL,
  `features__enabled` tinyint(1) NOT NULL,
  `features__email` tinyint(1) NOT NULL,
  `emailers*objectrefs*Core\Emailer*server` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `a2_objects_core_server`
--

INSERT INTO `a2_objects_core_server` (`id`, `datadir`, `apps*json`, `dates__created`, `features__debug_http`, `features__debug_log`, `features__debug_file`, `features__read_only`, `features__enabled`, `features__email`, `emailers*objectrefs*Core\Emailer*server`) VALUES
('zFoHvcNrXTwxrsLm', NULL, '[\"server\"]', 0, 1, 2, 1, 0, 1, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a2_core_log_error`
--
ALTER TABLE `a2_core_log_error`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`),
  ADD KEY `time` (`time`),
  ADD KEY `ipaddr` (`ipaddr`),
  ADD KEY `code` (`code`),
  ADD KEY `app` (`app`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `a2_objects_core_emailer`
--
ALTER TABLE `a2_objects_core_emailer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`);

--
-- Indexes for table `a2_objects_core_server`
--
ALTER TABLE `a2_objects_core_server`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `a2_core_log_error`
--
ALTER TABLE `a2_core_log_error`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=731;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
