SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `a2_objects_apps_files_comment` (
  `id` varchar(16) NOT NULL,
  `owner` varchar(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `comment` text NOT NULL,
  `private` tinyint(1) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_config` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `features__userstorage` tinyint(1) NOT NULL,
  `features__randomwrite` tinyint(1) NOT NULL,
  `features__publicmodify` tinyint(1) NOT NULL,
  `features__publicupload` tinyint(1) NOT NULL,
  `features__shareeveryone` tinyint(1) NOT NULL,
  `features__emailshare` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_file` (
  `id` varchar(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) DEFAULT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `size` bigint(20) NOT NULL DEFAULT '0',
  `counters__downloads` int(11) NOT NULL DEFAULT '0',
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT '0',
  `owner` varchar(16) DEFAULT NULL,
  `parent` varchar(16) NOT NULL,
  `filesystem` varchar(16) NOT NULL,
  `likes` int(11) NOT NULL DEFAULT '0',
  `counters__likes` int(11) NOT NULL DEFAULT '0',
  `counters__dislikes` int(11) NOT NULL DEFAULT '0',
  `tags` int(11) NOT NULL DEFAULT '0',
  `comments` int(11) NOT NULL DEFAULT '0',
  `shares` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_filesystem_fsmanager` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `readonly` tinyint(1) NOT NULL,
  `storage` varchar(255) NOT NULL,
  `owner` varchar(16) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `crypto_masterkey` tinyblob,
  `crypto_chunksize` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_folder` (
  `id` varchar(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `dates__created` bigint(20) NOT NULL,
  `dates__modified` bigint(20) DEFAULT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `counters__size` bigint(20) NOT NULL DEFAULT '0',
  `counters__visits` int(11) NOT NULL DEFAULT '0',
  `counters__downloads` int(11) NOT NULL DEFAULT '0',
  `counters__bandwidth` bigint(20) NOT NULL DEFAULT '0',
  `owner` varchar(16) DEFAULT NULL,
  `parent` varchar(16) DEFAULT NULL,
  `filesystem` varchar(16) NOT NULL,
  `files` int(11) NOT NULL DEFAULT '0',
  `folders` int(11) NOT NULL DEFAULT '0',
  `counters__subfiles` int(11) NOT NULL DEFAULT '0',
  `counters__subfolders` int(11) NOT NULL DEFAULT '0',
  `likes` int(11) NOT NULL DEFAULT '0',
  `counters__likes` int(11) NOT NULL DEFAULT '0',
  `counters__dislikes` int(11) NOT NULL DEFAULT '0',
  `tags` int(11) NOT NULL DEFAULT '0',
  `comments` int(11) NOT NULL DEFAULT '0',
  `shares` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_like` (
  `id` varchar(16) NOT NULL,
  `owner` varchar(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `value` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_share` (
  `id` varchar(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `owner` varchar(16) NOT NULL,
  `dest` varchar(64) DEFAULT NULL,
  `authkey` text,
  `password` text,
  `dates__created` bigint(20) NOT NULL,
  `dates__accessed` bigint(20) DEFAULT NULL,
  `counters__accessed` int(11) NOT NULL DEFAULT '0',
  `counters_limits__accessed` int(11) DEFAULT NULL,
  `dates__expire` bigint(20) DEFAULT NULL,
  `features__read` tinyint(1) NOT NULL DEFAULT '1',
  `features__upload` tinyint(4) NOT NULL DEFAULT '0',
  `features__modify` tinyint(4) NOT NULL DEFAULT '0',
  `features__social` tinyint(4) NOT NULL DEFAULT '1',
  `features__reshare` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_storage_ftp` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` varchar(16) NOT NULL,
  `owner` varchar(16) DEFAULT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `path` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text,
  `username_nonce` tinyblob,
  `password_nonce` tinyblob
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_storage_local` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` varchar(16) NOT NULL,
  `owner` varchar(16) DEFAULT NULL,
  `path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_storage_sftp` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` varchar(16) NOT NULL,
  `owner` varchar(16) DEFAULT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` text,
  `privkey` text,
  `pubkey` text,
  `keypass` text,
  `hostauth` tinyint(1) DEFAULT NULL,
  `username_nonce` tinyblob,
  `password_nonce` tinyblob,
  `keypass_nonce` tinyblob
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_storage_smb` (
  `id` varchar(16) NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `filesystem` varchar(16) NOT NULL,
  `owner` varchar(16) DEFAULT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `password` text,
  `username_nonce` tinyblob,
  `password_nonce` tinyblob
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `a2_objects_apps_files_tag` (
  `id` varchar(16) NOT NULL,
  `owner` varchar(16) NOT NULL,
  `item` varchar(64) NOT NULL,
  `tag` varchar(255) NOT NULL,
  `dates__created` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `a2_objects_apps_files_comment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `owner_2` (`owner`,`item`),
  ADD KEY `owner` (`owner`),
  ADD KEY `item` (`item`);

ALTER TABLE `a2_objects_apps_files_config`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `a2_objects_apps_files_file`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`),
  ADD KEY `owner` (`owner`),
  ADD KEY `parent` (`parent`),
  ADD KEY `filesystem` (`filesystem`);

ALTER TABLE `a2_objects_apps_files_filesystem_fsmanager`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `owner_2` (`owner`,`name`),
  ADD KEY `owner` (`owner`),
  ADD KEY `name` (`name`);

ALTER TABLE `a2_objects_apps_files_folder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent` (`parent`),
  ADD KEY `owner` (`owner`),
  ADD KEY `id` (`id`),
  ADD KEY `filesystem` (`filesystem`);

ALTER TABLE `a2_objects_apps_files_like`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `owner` (`owner`,`item`);

ALTER TABLE `a2_objects_apps_files_share`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item` (`item`,`dest`),
  ADD KEY `owner` (`owner`);

ALTER TABLE `a2_objects_apps_files_storage_ftp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `owner` (`owner`);

ALTER TABLE `a2_objects_apps_files_storage_local`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner` (`owner`);

ALTER TABLE `a2_objects_apps_files_storage_sftp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `a2_objects_apps_files_storage_smb`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `a2_objects_apps_files_tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item` (`item`,`tag`),
  ADD KEY `owner` (`owner`),
  ADD KEY `item_2` (`item`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
