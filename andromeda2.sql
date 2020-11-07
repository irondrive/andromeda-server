-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 07, 2020 at 03:43 PM
-- Server version: 10.1.19-MariaDB
-- PHP Version: 7.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
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
-- Table structure for table `a2_objects_apps_accounts_account`
--

CREATE TABLE `a2_objects_apps_accounts_account` (
  `id` varchar(16) NOT NULL,
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `unlockcode` varchar(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `dates__passwordset` bigint(20) NOT NULL DEFAULT '0',
  `dates__loggedon` bigint(20) NOT NULL DEFAULT '0',
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `max_client_age__inherits` bigint(20) DEFAULT NULL,
  `max_session_age__inherits` bigint(20) DEFAULT NULL,
  `max_password_age__inherits` bigint(20) DEFAULT NULL,
  `features__admin__inherits` tinyint(1) DEFAULT NULL,
  `features__enabled__inherits` tinyint(1) DEFAULT NULL,
  `features__forcetwofactor__inherits` tinyint(1) DEFAULT NULL,
  `comment` text,
  `master_key` tinyblob,
  `master_nonce` tinyblob,
  `master_salt` tinyblob,
  `password` text,
  `authsource` varchar(255) DEFAULT NULL,
  `groups` tinyint(4) NOT NULL DEFAULT '0',
  `sessions` tinyint(4) NOT NULL DEFAULT '0',
  `contactinfos` tinyint(4) NOT NULL DEFAULT '0',
  `clients` tinyint(4) NOT NULL DEFAULT '0',
  `twofactors` tinyint(4) NOT NULL DEFAULT '0',
  `recoverykeys` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_auth_ftp`
--

CREATE TABLE `a2_objects_apps_accounts_auth_ftp` (
  `id` varchar(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `account_group` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_auth_imap`
--

CREATE TABLE `a2_objects_apps_accounts_auth_imap` (
  `id` varchar(16) NOT NULL,
  `protocol` tinyint(1) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `account_group` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_auth_ldap`
--

CREATE TABLE `a2_objects_apps_accounts_auth_ldap` (
  `id` varchar(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `userprefix` varchar(255) NOT NULL,
  `account_group` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_auth_local`
--

CREATE TABLE `a2_objects_apps_accounts_auth_local` (
  `id` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_auth_pointer`
--

CREATE TABLE `a2_objects_apps_accounts_auth_pointer` (
  `id` varchar(16) NOT NULL,
  `authsource` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_client`
--

CREATE TABLE `a2_objects_apps_accounts_client` (
  `id` varchar(16) NOT NULL,
  `authkey` text NOT NULL,
  `lastaddr` varchar(255) NOT NULL,
  `useragent` text NOT NULL,
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `dates__loggedon` bigint(20) NOT NULL DEFAULT '0',
  `account` varchar(16) NOT NULL,
  `session` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_config`
--

CREATE TABLE `a2_objects_apps_accounts_config` (
  `id` varchar(16) NOT NULL,
  `features__createaccount` tinyint(1) NOT NULL,
  `features__emailasusername` tinyint(1) NOT NULL,
  `features__requirecontact` tinyint(1) NOT NULL,
  `features__allowcrypto` tinyint(1) NOT NULL,
  `default_group` varchar(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_contactinfo`
--

CREATE TABLE `a2_objects_apps_accounts_contactinfo` (
  `id` varchar(16) CHARACTER SET latin1 NOT NULL,
  `type` tinyint(4) NOT NULL,
  `info` varchar(255) CHARACTER SET latin1 NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  `unlockcode` varchar(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  `account` varchar(16) CHARACTER SET latin1 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_group`
--

CREATE TABLE `a2_objects_apps_accounts_group` (
  `id` varchar(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `comment` text,
  `priority` tinyint(4) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL,
  `members__features__admin` tinyint(1) DEFAULT NULL,
  `members__features__enabled` tinyint(1) DEFAULT NULL,
  `members__features__forcetwofactor` tinyint(1) DEFAULT NULL,
  `members__max_client_age` bigint(20) DEFAULT NULL,
  `members__max_session_age` bigint(20) DEFAULT NULL,
  `members__max_password_age` bigint(20) DEFAULT NULL,
  `accounts` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_groupjoin`
--

CREATE TABLE `a2_objects_apps_accounts_groupjoin` (
  `id` varchar(16) NOT NULL,
  `dates__created` int(11) NOT NULL,
  `accounts` varchar(16) NOT NULL,
  `groups` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_recoverykey`
--

CREATE TABLE `a2_objects_apps_accounts_recoverykey` (
  `id` varchar(16) NOT NULL,
  `authkey` text NOT NULL,
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `master_key` tinyblob,
  `master_nonce` tinyblob,
  `master_salt` tinyblob,
  `account` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_session`
--

CREATE TABLE `a2_objects_apps_accounts_session` (
  `id` varchar(16) NOT NULL,
  `authkey` text NOT NULL,
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `master_key` tinyblob,
  `master_nonce` tinyblob,
  `master_salt` tinyblob,
  `account` varchar(16) NOT NULL,
  `client` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_twofactor`
--

CREATE TABLE `a2_objects_apps_accounts_twofactor` (
  `id` varchar(16) NOT NULL,
  `comment` text,
  `secret` tinyblob NOT NULL,
  `nonce` tinyblob,
  `valid` tinyint(1) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL,
  `account` varchar(16) NOT NULL,
  `usedtokens` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `a2_objects_apps_accounts_usedtoken`
--

CREATE TABLE `a2_objects_apps_accounts_usedtoken` (
  `id` varchar(16) NOT NULL,
  `code` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `twofactor` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a2_objects_apps_accounts_account`
--
ALTER TABLE `a2_objects_apps_accounts_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `username_2` (`username`);

--
-- Indexes for table `a2_objects_apps_accounts_auth_ftp`
--
ALTER TABLE `a2_objects_apps_accounts_auth_ftp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_auth_imap`
--
ALTER TABLE `a2_objects_apps_accounts_auth_imap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_auth_ldap`
--
ALTER TABLE `a2_objects_apps_accounts_auth_ldap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_auth_local`
--
ALTER TABLE `a2_objects_apps_accounts_auth_local`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_auth_pointer`
--
ALTER TABLE `a2_objects_apps_accounts_auth_pointer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `authsource*objectpoly*Apps\Accounts\Auth\Source` (`authsource`);

--
-- Indexes for table `a2_objects_apps_accounts_client`
--
ALTER TABLE `a2_objects_apps_accounts_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `account*object*Apps\Accounts\Account*clients` (`account`),
  ADD KEY `session*object*Apps\Accounts\Session` (`session`);

--
-- Indexes for table `a2_objects_apps_accounts_config`
--
ALTER TABLE `a2_objects_apps_accounts_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_contactinfo`
--
ALTER TABLE `a2_objects_apps_accounts_contactinfo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `alias` (`info`),
  ADD KEY `type` (`type`),
  ADD KEY `account*object*Apps\Accounts\Account*aliases` (`account`);

--
-- Indexes for table `a2_objects_apps_accounts_group`
--
ALTER TABLE `a2_objects_apps_accounts_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `a2_objects_apps_accounts_groupjoin`
--
ALTER TABLE `a2_objects_apps_accounts_groupjoin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accounts*object*Apps\Accounts\Account*groups` (`accounts`),
  ADD KEY `groups*object*Apps\Accounts\Group*accounts` (`groups`),
  ADD KEY `id` (`id`);

--
-- Indexes for table `a2_objects_apps_accounts_recoverykey`
--
ALTER TABLE `a2_objects_apps_accounts_recoverykey`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`),
  ADD KEY `account*object*Apps\Accounts\Account*recoverykeys` (`account`);

--
-- Indexes for table `a2_objects_apps_accounts_session`
--
ALTER TABLE `a2_objects_apps_accounts_session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aid` (`account`),
  ADD KEY `cid` (`client`);

--
-- Indexes for table `a2_objects_apps_accounts_twofactor`
--
ALTER TABLE `a2_objects_apps_accounts_twofactor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account*object*Apps\Accounts\Account` (`account`);

--
-- Indexes for table `a2_objects_apps_accounts_usedtoken`
--
ALTER TABLE `a2_objects_apps_accounts_usedtoken`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
