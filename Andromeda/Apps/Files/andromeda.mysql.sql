
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
  `obj_account` char(12) DEFAULT NULL,
  `obj_sudouser` char(12) DEFAULT NULL,
  `obj_client` char(12) DEFAULT NULL,
  `obj_file` char(16) DEFAULT NULL,
  `obj_folder` char(16) DEFAULT NULL,
  `obj_parent` char(16) DEFAULT NULL,
  `obj_file_share` char(16) DEFAULT NULL,
  `obj_folder_share` char(16) DEFAULT NULL,
  `obj_parent_share` char(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`obj_account`),
  KEY `file` (`obj_file`),
  KEY `folder` (`obj_folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_comment` (
  `id` char(16) NOT NULL,
  `obj_owner` char(12) NOT NULL,
  `obj_item` varchar(64) NOT NULL,
  `comment` text NOT NULL,
  `date_created` double NOT NULL,
  `date_modified` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`obj_item`),
  KEY `owner_item` (`obj_owner`,`obj_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(12) NOT NULL,
  `version` varchar(255) NOT NULL,
  `date_created` double NOT NULL,
  `apiurl` text DEFAULT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `upload_maxsize` bigint(20) DEFAULT NULL,
  `timedstats` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_file` (
  `id` char(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `date_accessed` double DEFAULT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  `obj_owner` char(12) DEFAULT NULL,
  `obj_parent` char(16) NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `objs_likes` int(11) NOT NULL DEFAULT 0,
  `count_likes` int(11) NOT NULL DEFAULT 0,
  `count_dislikes` int(11) NOT NULL DEFAULT 0,
  `objs_tags` int(11) NOT NULL DEFAULT 0,
  `objs_comments` int(11) NOT NULL DEFAULT 0,
  `objs_shares` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`obj_parent`),
  KEY `owner` (`obj_owner`),
  KEY `parent` (`obj_parent`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_filesystem_fsmanager` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `type` tinyint(2) NOT NULL,
  `readonly` tinyint(1) NOT NULL,
  `obj_storage` varchar(64) NOT NULL,
  `obj_owner` char(12) DEFAULT NULL,
  `name` varchar(127) DEFAULT NULL,
  `crypto_masterkey` binary(32) DEFAULT NULL,
  `crypto_chunksize` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_name` (`obj_owner`,`name`),
  KEY `owner` (`obj_owner`),
  KEY `name` (`name`),
  KEY `storage` (`obj_storage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_folder` (
  `id` char(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `date_accessed` double DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_pubvisits` int(11) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  `obj_owner` char(12) DEFAULT NULL,
  `obj_parent` char(16) DEFAULT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `objs_files` int(11) NOT NULL DEFAULT 0,
  `objs_folders` int(11) NOT NULL DEFAULT 0,
  `count_subfiles` int(11) NOT NULL DEFAULT 0,
  `count_subfolders` int(11) NOT NULL DEFAULT 0,
  `count_subshares` int(11) NOT NULL DEFAULT 0,
  `objs_likes` int(11) NOT NULL DEFAULT 0,
  `count_likes` int(11) NOT NULL DEFAULT 0,
  `count_dislikes` int(11) NOT NULL DEFAULT 0,
  `objs_tags` int(11) NOT NULL DEFAULT 0,
  `objs_comments` int(11) NOT NULL DEFAULT 0,
  `objs_shares` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent` (`name`,`obj_parent`),
  KEY `parent` (`obj_parent`),
  KEY `owner` (`obj_owner`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_like` (
  `id` char(16) NOT NULL,
  `obj_owner` char(12) NOT NULL,
  `obj_item` varchar(64) NOT NULL,
  `date_created` double NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_item` (`obj_owner`,`obj_item`),
  KEY `item` (`obj_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_authentitytotal` (
  `id` char(12) NOT NULL,
  `obj_object` varchar(64) NOT NULL,
  `date_created` double NOT NULL,
  `date_download` double DEFAULT NULL,
  `date_upload` double DEFAULT NULL,
  `itemsharing` tinyint(1) DEFAULT NULL,
  `share2everyone` tinyint(1) DEFAULT NULL,
  `share2groups` tinyint(1) DEFAULT NULL,
  `emailshare` tinyint(1) DEFAULT NULL,
  `publicupload` tinyint(1) DEFAULT NULL,
  `publicmodify` tinyint(1) DEFAULT NULL,
  `randomwrite` tinyint(1) DEFAULT NULL,
  `userstorage` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(2) DEFAULT NULL,
  `track_dlstats` tinyint(2) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_shares` int(11) NOT NULL DEFAULT 0,
  `limit_size` bigint(20) DEFAULT NULL,
  `limit_items` int(11) DEFAULT NULL,
  `limit_shares` int(11) DEFAULT NULL,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`obj_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(12) NOT NULL,
  `obj_object` varchar(64) NOT NULL,
  `date_created` double NOT NULL,
  `date_download` double DEFAULT NULL,
  `date_upload` double DEFAULT NULL,
  `itemsharing` tinyint(1) DEFAULT NULL,
  `share2everyone` tinyint(1) DEFAULT NULL,
  `share2groups` tinyint(1) DEFAULT NULL,
  `publicupload` tinyint(1) DEFAULT NULL,
  `publicmodify` tinyint(1) DEFAULT NULL,
  `randomwrite` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_shares` int(11) NOT NULL DEFAULT 0,
  `limit_size` bigint(20) DEFAULT NULL,
  `limit_items` int(11) DEFAULT NULL,
  `limit_shares` int(11) DEFAULT NULL,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`obj_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL,
  `obj_object` varchar(64) NOT NULL,
  `objs_stats` int(11) NOT NULL DEFAULT 0,
  `date_created` double NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `max_stats_age` bigint(20) DEFAULT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  `limit_pubdownloads` int(11) DEFAULT NULL,
  `limit_bandwidth` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_timeperiod` (`obj_object`,`timeperiod`),
  KEY `object` (`obj_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL,
  `obj_limitobj` varchar(64) NOT NULL,
  `date_created` double NOT NULL,
  `date_timestart` bigint(20) NOT NULL,
  `iscurrent` tinyint(1) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_shares` int(11) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `limitobj_timestart` (`obj_limitobj`,`date_timestart`),
  UNIQUE KEY `limitobj_iscurrent` (`obj_limitobj`,`iscurrent`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_share` (
  `id` char(16) NOT NULL,
  `obj_item` varchar(64) NOT NULL,
  `obj_owner` char(12) NOT NULL,
  `obj_dest` varchar(64) DEFAULT NULL,
  `label` text DEFAULT NULL,
  `authkey` text DEFAULT NULL,
  `password` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_accessed` double DEFAULT NULL,
  `count_accessed` int(11) NOT NULL DEFAULT 0,
  `limit_accessed` int(11) DEFAULT NULL,
  `date_expires` bigint(20) DEFAULT NULL,
  `read` tinyint(1) NOT NULL,
  `upload` tinyint(1) NOT NULL,
  `modify` tinyint(1) NOT NULL,
  `social` tinyint(1) NOT NULL,
  `reshare` tinyint(1) NOT NULL,
  `keepowner` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_owner_dest` (`obj_item`,`obj_owner`,`obj_dest`),
  KEY `owner` (`obj_owner`),
  KEY `item` (`obj_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `path` text NOT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` tinyblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `path` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
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
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
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
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `obj_filesystem` char(12) NOT NULL,
  `endpoint` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filesystem` (`obj_filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_tag` (
  `id` char(16) NOT NULL,
  `obj_owner` char(12) NOT NULL,
  `obj_item` varchar(64) NOT NULL,
  `tag` varchar(127) NOT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_tag` (`obj_item`,`tag`),
  KEY `owner` (`obj_owner`),
  KEY `item` (`obj_item`)
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

