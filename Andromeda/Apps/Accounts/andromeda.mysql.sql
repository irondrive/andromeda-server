
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_accesslog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `obj_account` char(12) DEFAULT NULL,
  `obj_sudouser` char(12) DEFAULT NULL,
  `obj_client` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_account` (
  `id` char(12) NOT NULL,
  `username` varchar(127) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_passwordset` double DEFAULT NULL,
  `date_loggedon` double DEFAULT NULL,
  `date_active` double DEFAULT NULL,
  `date_modified` double DEFAULT NULL,
  `session_timeout` bigint(20) DEFAULT NULL,
  `client_timeout` bigint(20) DEFAULT NULL,
  `max_password_age` bigint(20) DEFAULT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `disabled` tinyint(2) DEFAULT NULL,
  `forcetf` tinyint(1) DEFAULT NULL,
  `allowcrypto` tinyint(1) DEFAULT NULL,
  `accountsearch` tinyint(4) DEFAULT NULL,
  `groupsearch` tinyint(4) DEFAULT NULL,
  `userdelete` tinyint(1) DEFAULT NULL,
  `limit_sessions` tinyint(4) DEFAULT NULL,
  `limit_contacts` tinyint(4) DEFAULT NULL,
  `limit_recoverykeys` tinyint(4) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `obj_authsource` varchar(64) DEFAULT NULL,
  `objs_groups` tinyint(4) NOT NULL DEFAULT 0,
  `objs_sessions` tinyint(4) NOT NULL DEFAULT 0,
  `objs_contacts` tinyint(4) NOT NULL DEFAULT 0,
  `objs_clients` tinyint(4) NOT NULL DEFAULT 0,
  `objs_twofactors` tinyint(4) NOT NULL DEFAULT 0,
  `objs_recoverykeys` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fullname` (`fullname`),
  KEY `authsource` (`obj_authsource`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_auth_ftp` (
  `id` char(12) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `obj_manager` char(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_auth_imap` (
  `id` char(12) NOT NULL,
  `protocol` tinyint(2) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `secauth` tinyint(1) NOT NULL,
  `obj_manager` char(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_auth_ldap` (
  `id` char(12) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `userprefix` varchar(255) NOT NULL,
  `obj_manager` char(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_auth_manager` (
  `id` char(12) NOT NULL,
  `enabled` tinyint(2) NOT NULL,
  `obj_authsource` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `obj_default_group` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `authsource` (`obj_authsource`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_client` (
  `id` char(12) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `authkey` text NOT NULL,
  `lastaddr` varchar(255) NOT NULL,
  `useragent` text NOT NULL,
  `date_active` double DEFAULT 0,
  `date_created` double NOT NULL DEFAULT 0,
  `date_loggedon` double NOT NULL DEFAULT 0,
  `obj_account` char(12) NOT NULL,
  `obj_session` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`),
  KEY `session` (`obj_session`),
  KEY `date_active_account` (`date_active`,`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_config` (
  `id` char(12) NOT NULL,
  `version` varchar(255) NOT NULL,
  `createaccount` smallint(1) NOT NULL,
  `usernameiscontact` tinyint(1) NOT NULL,
  `requirecontact` tinyint(2) NOT NULL,
  `obj_default_group` char(12) DEFAULT NULL,
  `obj_default_auth` char(12) DEFAULT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_contact` (
  `id` char(12) NOT NULL,
  `type` tinyint(2) NOT NULL,
  `info` varchar(127) NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 0,
  `usefrom` tinyint(1) DEFAULT NULL,
  `public` tinyint(1) NOT NULL DEFAULT 0,
  `authkey` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `obj_account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_info` (`type`,`info`),
  UNIQUE KEY `prefer` (`usefrom`,`obj_account`),
  KEY `info` (`info`),
  KEY `account` (`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_group` (
  `id` char(12) NOT NULL,
  `name` varchar(127) NOT NULL,
  `comment` text DEFAULT NULL,
  `priority` tinyint(4) NOT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT NULL,
  `forcetf` tinyint(1) DEFAULT NULL,
  `allowcrypto` tinyint(1) DEFAULT NULL,
  `accountsearch` tinyint(4) DEFAULT NULL,
  `groupsearch` tinyint(4) DEFAULT NULL,
  `userdelete` tinyint(1) DEFAULT NULL,
  `limit_sessions` tinyint(4) DEFAULT NULL,
  `limit_contacts` tinyint(4) DEFAULT NULL,
  `limit_recoverykeys` tinyint(4) DEFAULT NULL,
  `session_timeout` bigint(20) DEFAULT NULL,
  `client_timeout` bigint(11) DEFAULT NULL,
  `max_password_age` bigint(20) DEFAULT NULL,
  `objs_accounts` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_groupjoin` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `objs_accounts` char(12) NOT NULL,
  `objs_groups` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pair` (`objs_accounts`,`objs_groups`),
  KEY `accounts` (`objs_accounts`),
  KEY `groups` (`objs_groups`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_recoverykey` (
  `id` char(12) NOT NULL,
  `authkey` text NOT NULL,
  `date_created` double NOT NULL DEFAULT 0,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `obj_account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_session` (
  `id` char(12) NOT NULL,
  `authkey` text NOT NULL,
  `date_active` double DEFAULT NULL,
  `date_created` double NOT NULL DEFAULT 0,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `obj_account` char(12) NOT NULL,
  `obj_client` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`),
  KEY `client` (`obj_client`),
  KEY `date_active_account` (`date_active`,`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_twofactor` (
  `id` char(12) NOT NULL,
  `comment` text DEFAULT NULL,
  `secret` varbinary(48) NOT NULL,
  `nonce` binary(24) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` double NOT NULL,
  `date_used` double DEFAULT NULL,
  `obj_account` char(12) NOT NULL,
  `objs_usedtokens` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_usedtoken` (
  `id` char(12) NOT NULL,
  `code` char(6) NOT NULL,
  `date_created` double NOT NULL,
  `obj_twofactor` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date_created` (`date_created`),
  KEY `twofactor` (`obj_twofactor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_whitelist` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `type` smallint(6) NOT NULL,
  `value` varchar(127) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

