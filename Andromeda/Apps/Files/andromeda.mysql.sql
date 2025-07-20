/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_actionlog` (
  `id` char(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `sudouser` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `client` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `item` char(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `parent` char(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `item_share` char(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `parent_share` char(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `version` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `date_created` double NOT NULL,
  `apiurl` text DEFAULT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `upload_maxsize` bigint(20) DEFAULT NULL,
  `periodicstats` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_items_file` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `size` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_items_file_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `CONSTRAINT_1` CHECK (`size` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_items_folder` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_subfiles` int(11) NOT NULL DEFAULT 0,
  `count_subfolders` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_items_folder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_items_item` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `storage` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `parent` char(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `isroot` tinyint(1) DEFAULT NULL,
  `ispublic` tinyint(1) DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `date_accessed` double DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent` (`name`,`parent`),
  UNIQUE KEY `storage_isroot_owner` (`storage`,`isroot`,`owner`),
  UNIQUE KEY `storage_isroot_ispublic` (`storage`,`isroot`,`ispublic`),
  KEY `owner` (`owner`),
  KEY `storage` (`storage`),
  KEY `parent` (`parent`),
  CONSTRAINT `a2obj_apps_files_items_item_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_items_item_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`),
  CONSTRAINT `a2obj_apps_files_items_item_ibfk_3` FOREIGN KEY (`parent`) REFERENCES `a2obj_apps_files_items_item` (`id`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`parent` is null and `name` is null and `isroot` is not null and `isroot` = 1 or `parent` is not null and `name` is not null and `isroot` is null),
  CONSTRAINT `CONSTRAINT_2` CHECK (`owner` is null and `ispublic` is not null and `ispublic` = 1 or `owner` is not null and `ispublic` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_periodic` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `date_created` double NOT NULL,
  `timestart` bigint(20) NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `max_stats_age` bigint(20) DEFAULT NULL,
  `limit_pubdownloads` int(11) DEFAULT NULL,
  `limit_bandwidth` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_periodicaccount` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `account` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_files_policy_periodicaccount_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_periodicaccount_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_periodicgroup` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `group` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `track_items` tinyint(2) DEFAULT NULL,
  `track_dlstats` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group` (`group`),
  CONSTRAINT `a2obj_apps_files_policy_periodicgroup_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_periodicgroup_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_periodicstats` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `limit` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `dateidx` bigint(20) NOT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `limit_dateidx` (`limit`,`dateidx`),
  CONSTRAINT `a2obj_apps_files_policy_periodicstats_ibfk_1` FOREIGN KEY (`limit`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_periodicstorage` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `storage` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `storage` (`storage`),
  CONSTRAINT `a2obj_apps_files_policy_periodicstorage_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_periodicstorage_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_standard` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `date_created` double NOT NULL,
  `date_download` double DEFAULT NULL,
  `date_upload` double DEFAULT NULL,
  `can_itemshare` tinyint(1) DEFAULT NULL,
  `can_share2groups` tinyint(1) DEFAULT NULL,
  `can_publicupload` tinyint(1) DEFAULT NULL,
  `can_publicmodify` tinyint(1) DEFAULT NULL,
  `can_randomwrite` tinyint(1) DEFAULT NULL,
  `limit_size` bigint(20) DEFAULT NULL,
  `limit_items` int(11) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_standardaccount` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `account` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `can_emailshare` tinyint(1) DEFAULT NULL,
  `can_userstorage` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_files_policy_standardaccount_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_standardaccount_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_standardgroup` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `group` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `can_emailshare` tinyint(1) DEFAULT NULL,
  `can_userstorage` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(2) DEFAULT NULL,
  `track_dlstats` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group` (`group`),
  CONSTRAINT `a2obj_apps_files_policy_standardgroup_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_standardgroup_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_policy_standardstorage` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `storage` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage` (`storage`),
  CONSTRAINT `a2obj_apps_files_policy_standardstorage_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_policy_standardstorage_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_social_comment` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `item` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value` text NOT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`item`),
  KEY `owner_item` (`owner`,`item`),
  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_social_like` (
  `id` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `item` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `date_created` double NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_item` (`owner`,`item`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_social_like_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_social_like_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_social_share` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `item` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `dest` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `label` text DEFAULT NULL,
  `authkey` binary(16) DEFAULT NULL,
  `password` text CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_accessed` double DEFAULT NULL,
  `count_accessed` int(11) NOT NULL DEFAULT 0,
  `limit_accessed` int(11) DEFAULT NULL,
  `date_expires` double DEFAULT NULL,
  `can_read` tinyint(1) NOT NULL,
  `can_upload` tinyint(1) NOT NULL,
  `can_modify` tinyint(1) NOT NULL,
  `can_social` tinyint(1) NOT NULL,
  `can_reshare` tinyint(1) NOT NULL,
  `keepowner` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_owner_dest` (`item`,`owner`,`dest`),
  KEY `dest` (`dest`),
  KEY `owner` (`owner`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_social_share_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_social_share_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`),
  CONSTRAINT `a2obj_apps_files_social_share_ibfk_3` FOREIGN KEY (`dest`) REFERENCES `a2obj_apps_accounts_policybase` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_social_tag` (
  `id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `item` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value` varchar(127) NOT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_value` (`item`,`value`),
  KEY `owner` (`owner`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` tinyblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `path` text NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_local_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `endpoint` text NOT NULL,
  `path_style` tinyint(1) DEFAULT NULL,
  `port` smallint(6) DEFAULT NULL,
  `usetls` tinyint(1) DEFAULT NULL,
  `region` varchar(64) NOT NULL,
  `bucket` varchar(64) NOT NULL,
  `accesskey` varbinary(144) DEFAULT NULL,
  `accesskey_nonce` binary(24) DEFAULT NULL,
  `secretkey` varbinary(56) DEFAULT NULL,
  `secretkey_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_s3_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `hostkey` blob DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `privkey` blob DEFAULT NULL,
  `keypass` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  `privkey_nonce` binary(24) DEFAULT NULL,
  `keypass_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_sftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_smb_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_storage` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `date_created` double NOT NULL,
  `fstype` tinyint(2) NOT NULL,
  `readonly` tinyint(1) NOT NULL DEFAULT 0,
  `owner` char(12) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `name` varchar(127) NOT NULL DEFAULT 'Default',
  `crypto_masterkey` binary(32) DEFAULT NULL,
  `crypto_chunksize` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_name` (`owner`,`name`),
  KEY `owner` (`owner`),
  KEY `name` (`name`),
  CONSTRAINT `a2obj_apps_files_storage_storage_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`crypto_masterkey` is null and `crypto_chunksize` is null or `crypto_masterkey` is not null and `crypto_chunksize` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `path` text NOT NULL,
  `endpoint` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_webdav_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

