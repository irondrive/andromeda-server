SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_account` (
  `id` char(16) NOT NULL,
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `unlockcode` char(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `dates__passwordset` bigint(20) NOT NULL DEFAULT '0',
  `dates__loggedon` bigint(20) NOT NULL DEFAULT '0',
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `dates__modified` bigint(20) DEFAULT NULL,
  `max_session_age` bigint(20) DEFAULT NULL,
  `max_password_age` bigint(20) DEFAULT NULL,
  `features__admin` tinyint(1) DEFAULT NULL,
  `features__enabled` tinyint(1) DEFAULT NULL,
  `features__forcetf` tinyint(1) DEFAULT NULL,
  `features__allowcrypto` tinyint(1) DEFAULT NULL,
  `counters_limits__sessions` tinyint(4) DEFAULT NULL,
  `counters_limits__contactinfos` tinyint(4) DEFAULT NULL,
  `counters_limits__recoverykeys` tinyint(4) DEFAULT NULL,
  `comment` text,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `password` text,
  `authsource` varchar(64) DEFAULT NULL,
  `groups` tinyint(4) NOT NULL DEFAULT '0',
  `sessions` tinyint(4) NOT NULL DEFAULT '0',
  `contactinfos` tinyint(4) NOT NULL DEFAULT '0',
  `clients` tinyint(4) NOT NULL DEFAULT '0',
  `twofactors` tinyint(4) NOT NULL DEFAULT '0',
  `recoverykeys` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_ftp` (
  `id` char(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `manager` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_imap` (
  `id` char(16) NOT NULL,
  `protocol` tinyint(2) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `secauth` tinyint(1) NOT NULL,
  `manager` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_ldap` (
  `id` char(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `userprefix` varchar(255) NOT NULL,
  `manager` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_manager` (
  `id` char(16) NOT NULL,
  `authsource` varchar(64) NOT NULL,
  `description` varchar(255) NOT NULL,
  `default_group` char(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `authsource*objectpoly*Apps\Accounts\Auth\Source` (`authsource`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_client` (
  `id` char(16) NOT NULL,
  `authkey` text NOT NULL,
  `lastaddr` varchar(255) NOT NULL,
  `useragent` text NOT NULL,
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `dates__loggedon` bigint(20) NOT NULL DEFAULT '0',
  `account` char(16) NOT NULL,
  `session` char(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `account*object*Apps\Accounts\Account*clients` (`account`),
  KEY `session*object*Apps\Accounts\Session` (`session`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_config` (
  `id` char(16) NOT NULL,
  `features__createaccount` tinyint(1) NOT NULL,
  `features__emailasusername` tinyint(1) NOT NULL,
  `features__requirecontact` tinyint(2) NOT NULL,
  `default_group` char(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_contactinfo` (
  `id` char(16) CHARACTER SET latin1 NOT NULL,
  `type` tinyint(4) NOT NULL,
  `info` varchar(255) CHARACTER SET latin1 NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  `unlockcode` char(16) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  `account` char(16) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `alias` (`info`),
  KEY `type` (`type`),
  KEY `account*object*Apps\Accounts\Account*aliases` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_group` (
  `id` char(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `comment` text,
  `priority` tinyint(4) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) DEFAULT NULL,
  `features__admin` tinyint(1) DEFAULT NULL,
  `features__enabled` tinyint(1) DEFAULT NULL,
  `features__forcetf` tinyint(1) DEFAULT NULL,
  `features__allowcrypto` tinyint(1) DEFAULT NULL,
  `counters_limits__sessions` tinyint(4) DEFAULT NULL,
  `counters_limits__contactinfos` tinyint(4) DEFAULT NULL,
  `counters_limits__recoverykeys` tinyint(4) DEFAULT NULL,
  `max_session_age` bigint(20) DEFAULT NULL,
  `max_password_age` bigint(20) DEFAULT NULL,
  `accounts` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_groupjoin` (
  `id` char(16) NOT NULL,
  `dates__created` int(11) NOT NULL,
  `accounts` char(16) NOT NULL,
  `groups` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts*object*Apps\Accounts\Account*groups` (`accounts`),
  KEY `groups*object*Apps\Accounts\Group*accounts` (`groups`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_recoverykey` (
  `id` char(16) NOT NULL,
  `authkey` text NOT NULL,
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `account` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `account*object*Apps\Accounts\Account*recoverykeys` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_session` (
  `id` char(16) NOT NULL,
  `authkey` text NOT NULL,
  `dates__active` bigint(20) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL DEFAULT '0',
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `account` char(16) NOT NULL,
  `client` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `aid` (`account`),
  KEY `cid` (`client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_twofactor` (
  `id` char(16) NOT NULL,
  `comment` text,
  `secret` binary(48) NOT NULL,
  `nonce` binary(24) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '0',
  `dates__created` bigint(20) NOT NULL,
  `account` char(16) NOT NULL,
  `usedtokens` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `account*object*Apps\Accounts\Account` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_usedtoken` (
  `id` char(16) NOT NULL,
  `code` char(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `twofactor` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
