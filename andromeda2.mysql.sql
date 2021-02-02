SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_comment` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `comment` text NOT NULL,
  `private` tinyint(1) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`),
  KEY `item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_config` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `features__timedstats` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_file` (
  `id` char(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) DEFAULT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `counters__downloads` int(11) NOT NULL DEFAULT 0,
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
  KEY `id` (`id`),
  KEY `owner` (`owner`),
  KEY `parent` (`parent`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_filesystem_fsmanager` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `type` tinyint(2) NOT NULL,
  `readonly` tinyint(1) NOT NULL,
  `storage` varchar(64) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `name` varchar(127) DEFAULT NULL,
  `crypto_masterkey` binary(32) DEFAULT NULL,
  `crypto_chunksize` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_2` (`owner`,`name`),
  KEY `owner` (`owner`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_folder` (
  `id` char(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) DEFAULT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__visits` int(11) NOT NULL DEFAULT 0,
  `counters__downloads` int(11) NOT NULL DEFAULT 0,
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
  KEY `parent` (`parent`),
  KEY `owner` (`owner`),
  KEY `id` (`id`),
  KEY `filesystem` (`filesystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_like` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner` (`owner`,`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_limits_authtotal` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__download` bigint(20) DEFAULT NULL,
  `dates__upload` bigint(20) DEFAULT NULL,
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
  `counters__downloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_limits_filesystemtotal` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__download` bigint(20) DEFAULT NULL,
  `dates__upload` bigint(20) DEFAULT NULL,
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
  `counters__downloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_limits_timed` (
  `id` char(12) NOT NULL,
  `object` varchar(64) NOT NULL,
  `stats` int(11) NOT NULL DEFAULT 0,
  `dates__created` bigint(20) NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `max_stats_age` bigint(20) DEFAULT NULL,
  `features__track_items` tinyint(1) DEFAULT NULL,
  `features__track_dlstats` tinyint(1) DEFAULT NULL,
  `counters_limits__downloads` int(11) DEFAULT NULL,
  `counters_limits__bandwidth` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_2` (`object`,`timeperiod`),
  KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL,
  `limitobj` varchar(64) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__timestart` bigint(20) NOT NULL,
  `iscurrent` tinyint(1) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT 0,
  `counters__items` int(11) NOT NULL DEFAULT 0,
  `counters__shares` int(11) NOT NULL DEFAULT 0,
  `counters__downloads` int(11) NOT NULL DEFAULT 0,
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `limitobj` (`limitobj`,`dates__timestart`),
  UNIQUE KEY `limitobj_2` (`limitobj`,`iscurrent`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_share` (
  `id` char(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `owner` char(12) NOT NULL,
  `dest` varchar(64) DEFAULT NULL,
  `authkey` text DEFAULT NULL,
  `password` text DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `counters__accessed` int(11) NOT NULL DEFAULT 0,
  `counters_limits__accessed` int(11) DEFAULT NULL,
  `dates__expires` bigint(20) DEFAULT NULL,
  `features__read` tinyint(1) NOT NULL,
  `features__upload` tinyint(1) NOT NULL,
  `features__modify` tinyint(1) NOT NULL,
  `features__social` tinyint(1) NOT NULL,
  `features__reshare` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item` (`item`,`dest`),
  KEY `owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_storage_ftp` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` char(12) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `path` text NOT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` tinyblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_storage_local` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` char(12) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `path` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_storage_sftp` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` char(12) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `privkey` text DEFAULT NULL,
  `pubkey` text DEFAULT NULL,
  `keypass` tinyblob DEFAULT NULL,
  `hostauth` tinyint(1) DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  `keypass_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_storage_smb` (
  `id` char(12) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` char(12) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `a2_objects_apps_files_tag` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` varchar(64) NOT NULL,
  `tag` varchar(127) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item` (`item`,`tag`),
  KEY `owner` (`owner`),
  KEY `item_2` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
