
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
CREATE TABLE `a2obj_apps_files_accesslog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) DEFAULT NULL,
  `sudouser` char(12) DEFAULT NULL,
  `client` char(12) DEFAULT NULL,
  `file` char(16) DEFAULT NULL,
  `folder` char(16) DEFAULT NULL,
  `parent` char(16) DEFAULT NULL,
  `file_share` char(16) DEFAULT NULL,
  `folder_share` char(16) DEFAULT NULL,
  `parent_share` char(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  KEY `file` (`file`),
  KEY `folder` (`folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_comment` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `comment` text NOT NULL,
  `dates__created` double NOT NULL,
  `dates__modified` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`item`),
  KEY `owner_item` (`owner`,`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(12) NOT NULL,
  `version` varchar(255) NOT NULL,
  `dates__created` double NOT NULL,
  `apiurl` text DEFAULT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `upload_maxsize` bigint(20) DEFAULT NULL,
  `features__timedstats` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_file` (
  `id` char(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `dates__created` double NOT NULL,
  `dates__modified` double DEFAULT NULL,
  `dates__accessed` double DEFAULT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `counters__pubdownloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  `owner` char(12) DEFAULT NULL,
  `parent` char(16) NOT NULL,
  `filesystem` char(12) NOT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `counters__likes` int(11) NOT NULL DEFAULT 0,
  `counters__dislikes` int(11) NOT NULL DEFAULT 0,
  `tags` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `shares` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`parent`),
  KEY `owner` (`owner`),
  KEY `parent` (`parent`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_filesystem_fsmanager` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `type` tinyint(2) NOT NULL,
  `readonly` tinyint(1) NOT NULL,
  `storage` varchar(64) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `name` varchar(127) DEFAULT NULL,
  `crypto_masterkey` binary(32) DEFAULT NULL,
  `crypto_chunksize` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_name` (`owner`,`name`),
  KEY `owner` (`owner`),
  KEY `name` (`name`),
  KEY `storage` (`storage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_folder` (
  `id` char(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dates__created` double NOT NULL,
  `dates__modified` double DEFAULT NULL,
  `dates__accessed` double DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__pubvisits` int(11) NOT NULL DEFAULT 0,
  `counters__pubdownloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  `owner` char(12) DEFAULT NULL,
  `parent` char(16) DEFAULT NULL,
  `filesystem` char(12) NOT NULL,
  `files` int(11) NOT NULL DEFAULT 0,
  `folders` int(11) NOT NULL DEFAULT 0,
  `counters__subfiles` int(11) NOT NULL DEFAULT 0,
  `counters__subfolders` int(11) NOT NULL DEFAULT 0,
  `counters__subshares` int(11) NOT NULL DEFAULT 0,
  `likes` int(11) NOT NULL DEFAULT 0,
  `counters__likes` int(11) NOT NULL DEFAULT 0,
  `counters__dislikes` int(11) NOT NULL DEFAULT 0,
  `tags` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `shares` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent` (`name`,`parent`),
  KEY `parent` (`parent`),
  KEY `owner` (`owner`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_like` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `dates__created` double NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_item` (`owner`,`item`),
  KEY `item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_authentitytotal` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `dates__created` double NOT NULL,
  `dates__download` double DEFAULT NULL,
  `dates__upload` double DEFAULT NULL,
  `features__itemsharing` tinyint(1) DEFAULT NULL,
  `features__shareeveryone` tinyint(1) DEFAULT NULL,
  `features__emailshare` tinyint(1) DEFAULT NULL,
  `features__publicupload` tinyint(1) DEFAULT NULL,
  `features__publicmodify` tinyint(1) DEFAULT NULL,
  `features__randomwrite` tinyint(1) DEFAULT NULL,
  `features__userstorage` tinyint(1) DEFAULT NULL,
  `features__track_items` tinyint(2) DEFAULT NULL,
  `features__track_dlstats` tinyint(2) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__items` int(11) NOT NULL DEFAULT 0,
  `counters__shares` int(11) NOT NULL DEFAULT 0,
  `counters_limits__size` bigint(20) DEFAULT NULL,
  `counters_limits__items` int(11) DEFAULT NULL,
  `counters_limits__shares` int(11) DEFAULT NULL,
  `counters__pubdownloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `dates__created` double NOT NULL,
  `dates__download` double DEFAULT NULL,
  `dates__upload` double DEFAULT NULL,
  `features__itemsharing` tinyint(1) DEFAULT NULL,
  `features__shareeveryone` tinyint(1) DEFAULT NULL,
  `features__publicupload` tinyint(1) DEFAULT NULL,
  `features__publicmodify` tinyint(1) DEFAULT NULL,
  `features__randomwrite` tinyint(1) DEFAULT NULL,
  `features__track_items` tinyint(1) DEFAULT NULL,
  `features__track_dlstats` tinyint(1) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__items` int(11) NOT NULL DEFAULT 0,
  `counters__shares` int(11) NOT NULL DEFAULT 0,
  `counters_limits__size` bigint(20) DEFAULT NULL,
  `counters_limits__items` int(11) DEFAULT NULL,
  `counters_limits__shares` int(11) DEFAULT NULL,
  `counters__pubdownloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `stats` int(11) NOT NULL DEFAULT 0,
  `dates__created` double NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `max_stats_age` bigint(20) DEFAULT NULL,
  `features__track_items` tinyint(1) DEFAULT NULL,
  `features__track_dlstats` tinyint(1) DEFAULT NULL,
  `counters_limits__pubdownloads` int(11) DEFAULT NULL,
  `counters_limits__bandwidth` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_timeperiod` (`object`,`timeperiod`),
  KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL,
  `limitobj` varchar(64) NOT NULL,
  `dates__created` double NOT NULL,
  `dates__timestart` bigint(20) NOT NULL,
  `iscurrent` tinyint(1) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__items` int(11) NOT NULL DEFAULT 0,
  `counters__shares` int(11) NOT NULL DEFAULT 0,
  `counters__pubdownloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `limitobj_timestart` (`limitobj`,`dates__timestart`),
  UNIQUE KEY `limitobj_iscurrent` (`limitobj`,`iscurrent`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_share` (
  `id` char(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `owner` char(12) NOT NULL,
  `dest` varchar(64) DEFAULT NULL,
  `authkey` text DEFAULT NULL,
  `password` text DEFAULT NULL,
  `dates__created` double NOT NULL,
  `dates__accessed` double DEFAULT NULL,
  `counters__accessed` int(11) NOT NULL DEFAULT 0,
  `counters_limits__accessed` int(11) DEFAULT NULL,
  `dates__expires` bigint(20) DEFAULT NULL,
  `features__read` tinyint(1) NOT NULL,
  `features__upload` tinyint(1) NOT NULL,
  `features__modify` tinyint(1) NOT NULL,
  `features__social` tinyint(1) NOT NULL,
  `features__reshare` tinyint(1) NOT NULL,
  `features__keepowner` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_owner_dest` (`item`,`owner`,`dest`),
  KEY `owner` (`owner`),
  KEY `item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `path` text NOT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` tinyblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `path` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `import_chunksize` int(11) DEFAULT NULL,
  `endpoint` text NOT NULL,
  `path_style` tinyint(1) DEFAULT NULL,
  `port` smallint(6) DEFAULT NULL,
  `usetls` tinyint(1) DEFAULT NULL,
  `region` varchar(64) NOT NULL,
  `bucket` varchar(64) NOT NULL,
  `accesskey` varbinary(144) NOT NULL,
  `accesskey_nonce` binary(24) DEFAULT NULL,
  `secretkey` varbinary(56) DEFAULT NULL,
  `secretkey_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `hostkey` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `privkey` blob DEFAULT NULL,
  `keypass` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  `privkey_nonce` binary(24) DEFAULT NULL,
  `keypass_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(12) NOT NULL,
  `dates__created` double NOT NULL,
  `filesystem` char(12) NOT NULL,
  `endpoint` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_tag` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `tag` varchar(127) NOT NULL,
  `dates__created` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_tag` (`item`,`tag`),
  KEY `owner` (`owner`),
  KEY `item` (`item`)
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

