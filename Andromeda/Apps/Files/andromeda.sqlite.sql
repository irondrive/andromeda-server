PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2obj_apps_files_accesslog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  `file` char(16) DEFAULT NULL
,  `folder` char(16) DEFAULT NULL
,  `parent` char(16) DEFAULT NULL
,  `file_share` char(16) DEFAULT NULL
,  `folder_share` char(16) DEFAULT NULL
,  `parent_share` char(16) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_comment` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` varchar(64) NOT NULL
,  `comment` text NOT NULL
,  `dates__created` double NOT NULL
,  `dates__modified` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(12) NOT NULL
,  `version` varchar(255) NOT NULL
,  `dates__created` double NOT NULL
,  `apiurl` text DEFAULT NULL
,  `rwchunksize` integer NOT NULL
,  `crchunksize` integer NOT NULL
,  `upload_maxsize` integer DEFAULT NULL
,  `features__timedstats` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_file` (
  `id` char(16) NOT NULL
,  `name` varchar(255) NOT NULL
,  `description` text DEFAULT NULL
,  `dates__created` double NOT NULL
,  `dates__modified` double DEFAULT NULL
,  `dates__accessed` double DEFAULT NULL
,  `size` integer NOT NULL DEFAULT 0
,  `counters__pubdownloads` integer NOT NULL DEFAULT 0
,  `counters__bandwidth` integer NOT NULL DEFAULT 0
,  `owner` char(12) DEFAULT NULL
,  `parent` char(16) NOT NULL
,  `filesystem` char(12) NOT NULL
,  `likes` integer NOT NULL DEFAULT 0
,  `counters__likes` integer NOT NULL DEFAULT 0
,  `counters__dislikes` integer NOT NULL DEFAULT 0
,  `tags` integer NOT NULL DEFAULT 0
,  `comments` integer NOT NULL DEFAULT 0
,  `shares` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`parent`)
);
CREATE TABLE `a2obj_apps_files_filesystem_fsmanager` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `type` integer NOT NULL
,  `readonly` integer NOT NULL
,  `storage` varchar(64) NOT NULL
,  `owner` char(12) DEFAULT NULL
,  `name` varchar(127) DEFAULT NULL
,  `crypto_masterkey` binary(32) DEFAULT NULL
,  `crypto_chunksize` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`name`)
);
CREATE TABLE `a2obj_apps_files_folder` (
  `id` char(16) NOT NULL
,  `name` varchar(255) DEFAULT NULL
,  `description` text DEFAULT NULL
,  `dates__created` double NOT NULL
,  `dates__modified` double DEFAULT NULL
,  `dates__accessed` double DEFAULT NULL
,  `counters__size` integer NOT NULL DEFAULT 0
,  `counters__pubvisits` integer NOT NULL DEFAULT 0
,  `counters__pubdownloads` integer NOT NULL DEFAULT 0
,  `counters__bandwidth` integer NOT NULL DEFAULT 0
,  `owner` char(12) DEFAULT NULL
,  `parent` char(16) DEFAULT NULL
,  `filesystem` char(12) NOT NULL
,  `files` integer NOT NULL DEFAULT 0
,  `folders` integer NOT NULL DEFAULT 0
,  `counters__subfiles` integer NOT NULL DEFAULT 0
,  `counters__subfolders` integer NOT NULL DEFAULT 0
,  `counters__subshares` integer NOT NULL DEFAULT 0
,  `likes` integer NOT NULL DEFAULT 0
,  `counters__likes` integer NOT NULL DEFAULT 0
,  `counters__dislikes` integer NOT NULL DEFAULT 0
,  `tags` integer NOT NULL DEFAULT 0
,  `comments` integer NOT NULL DEFAULT 0
,  `shares` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`parent`)
);
CREATE TABLE `a2obj_apps_files_like` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` varchar(64) NOT NULL
,  `dates__created` double NOT NULL
,  `value` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`item`)
);
CREATE TABLE `a2obj_apps_files_limits_authentitytotal` (
  `id` char(12) NOT NULL
,  `object` varchar(64) NOT NULL
,  `dates__created` double NOT NULL
,  `dates__download` double DEFAULT NULL
,  `dates__upload` double DEFAULT NULL
,  `features__itemsharing` integer DEFAULT NULL
,  `features__share2everyone` integer DEFAULT NULL
,  `features__share2groups` integer DEFAULT NULL
,  `features__emailshare` integer DEFAULT NULL
,  `features__publicupload` integer DEFAULT NULL
,  `features__publicmodify` integer DEFAULT NULL
,  `features__randomwrite` integer DEFAULT NULL
,  `features__userstorage` integer DEFAULT NULL
,  `features__track_items` integer DEFAULT NULL
,  `features__track_dlstats` integer DEFAULT NULL
,  `counters__size` integer NOT NULL DEFAULT 0
,  `counters__items` integer NOT NULL DEFAULT 0
,  `counters__shares` integer NOT NULL DEFAULT 0
,  `counters_limits__size` integer DEFAULT NULL
,  `counters_limits__items` integer DEFAULT NULL
,  `counters_limits__shares` integer DEFAULT NULL
,  `counters__pubdownloads` integer NOT NULL DEFAULT 0
,  `counters__bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`object`)
);
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(12) NOT NULL
,  `object` varchar(64) NOT NULL
,  `dates__created` double NOT NULL
,  `dates__download` double DEFAULT NULL
,  `dates__upload` double DEFAULT NULL
,  `features__itemsharing` integer DEFAULT NULL
,  `features__share2everyone` integer DEFAULT NULL
,  `features__share2groups` integer DEFAULT NULL
,  `features__publicupload` integer DEFAULT NULL
,  `features__publicmodify` integer DEFAULT NULL
,  `features__randomwrite` integer DEFAULT NULL
,  `features__track_items` integer DEFAULT NULL
,  `features__track_dlstats` integer DEFAULT NULL
,  `counters__size` integer NOT NULL DEFAULT 0
,  `counters__items` integer NOT NULL DEFAULT 0
,  `counters__shares` integer NOT NULL DEFAULT 0
,  `counters_limits__size` integer DEFAULT NULL
,  `counters_limits__items` integer DEFAULT NULL
,  `counters_limits__shares` integer DEFAULT NULL
,  `counters__pubdownloads` integer NOT NULL DEFAULT 0
,  `counters__bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`object`)
);
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL
,  `object` varchar(64) NOT NULL
,  `stats` integer NOT NULL DEFAULT 0
,  `dates__created` double NOT NULL
,  `timeperiod` integer NOT NULL
,  `max_stats_age` integer DEFAULT NULL
,  `features__track_items` integer DEFAULT NULL
,  `features__track_dlstats` integer DEFAULT NULL
,  `counters_limits__pubdownloads` integer DEFAULT NULL
,  `counters_limits__bandwidth` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`object`,`timeperiod`)
);
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL
,  `limitobj` varchar(64) NOT NULL
,  `dates__created` double NOT NULL
,  `dates__timestart` integer NOT NULL
,  `iscurrent` integer DEFAULT NULL
,  `counters__size` integer NOT NULL DEFAULT 0
,  `counters__items` integer NOT NULL DEFAULT 0
,  `counters__shares` integer NOT NULL DEFAULT 0
,  `counters__pubdownloads` integer NOT NULL DEFAULT 0
,  `counters__bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`limitobj`,`dates__timestart`)
,  UNIQUE (`limitobj`,`iscurrent`)
);
CREATE TABLE `a2obj_apps_files_share` (
  `id` char(16) NOT NULL
,  `item` varchar(64) NOT NULL
,  `owner` char(12) NOT NULL
,  `dest` varchar(64) DEFAULT NULL
,  `label` text DEFAULT NULL
,  `authkey` text DEFAULT NULL
,  `password` text DEFAULT NULL
,  `dates__created` double NOT NULL
,  `dates__accessed` double DEFAULT NULL
,  `counters__accessed` integer NOT NULL DEFAULT 0
,  `counters_limits__accessed` integer DEFAULT NULL
,  `dates__expires` integer DEFAULT NULL
,  `features__read` integer NOT NULL
,  `features__upload` integer NOT NULL
,  `features__modify` integer NOT NULL
,  `features__social` integer NOT NULL
,  `features__reshare` integer NOT NULL
,  `features__keepowner` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`owner`,`dest`)
);
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `path` text NOT NULL
,  `username` varbinary(255) DEFAULT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` tinyblob DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `path` text NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `endpoint` text NOT NULL
,  `path_style` integer DEFAULT NULL
,  `port` integer DEFAULT NULL
,  `usetls` integer DEFAULT NULL
,  `region` varchar(64) NOT NULL
,  `bucket` varchar(64) NOT NULL
,  `accesskey` varbinary(144) NOT NULL
,  `accesskey_nonce` binary(24) DEFAULT NULL
,  `secretkey` varbinary(56) DEFAULT NULL
,  `secretkey_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `hostkey` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `privkey` blob DEFAULT NULL
,  `keypass` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  `privkey_nonce` binary(24) DEFAULT NULL
,  `keypass_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `workgroup` varchar(255) DEFAULT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(12) NOT NULL
,  `dates__created` double NOT NULL
,  `filesystem` char(12) NOT NULL
,  `endpoint` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_tag` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` varchar(64) NOT NULL
,  `tag` varchar(127) NOT NULL
,  `dates__created` double NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`tag`)
);
CREATE INDEX "idx_a2obj_apps_files_limits_timed_object" ON "a2obj_apps_files_limits_timed" (`object`);
CREATE INDEX "idx_a2obj_apps_files_tag_owner" ON "a2obj_apps_files_tag" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_tag_item" ON "a2obj_apps_files_tag" (`item`);
CREATE INDEX "idx_a2obj_apps_files_storage_smb_filesystem" ON "a2obj_apps_files_storage_smb" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_s3_filesystem" ON "a2obj_apps_files_storage_s3" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_share_owner" ON "a2obj_apps_files_share" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_share_item" ON "a2obj_apps_files_share" (`item`);
CREATE INDEX "idx_a2obj_apps_files_comment_item" ON "a2obj_apps_files_comment" (`item`);
CREATE INDEX "idx_a2obj_apps_files_comment_owner_item" ON "a2obj_apps_files_comment" (`owner`,`item`);
CREATE INDEX "idx_a2obj_apps_files_file_owner" ON "a2obj_apps_files_file" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_file_parent" ON "a2obj_apps_files_file" (`parent`);
CREATE INDEX "idx_a2obj_apps_files_file_filesystem" ON "a2obj_apps_files_file" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_webdav_filesystem" ON "a2obj_apps_files_storage_webdav" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_sftp_filesystem" ON "a2obj_apps_files_storage_sftp" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_like_item" ON "a2obj_apps_files_like" (`item`);
CREATE INDEX "idx_a2obj_apps_files_folder_parent" ON "a2obj_apps_files_folder" (`parent`);
CREATE INDEX "idx_a2obj_apps_files_folder_owner" ON "a2obj_apps_files_folder" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_folder_filesystem" ON "a2obj_apps_files_folder" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_owner" ON "a2obj_apps_files_filesystem_fsmanager" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_name" ON "a2obj_apps_files_filesystem_fsmanager" (`name`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_storage" ON "a2obj_apps_files_filesystem_fsmanager" (`storage`);
CREATE INDEX "idx_a2obj_apps_files_storage_local_filesystem" ON "a2obj_apps_files_storage_local" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_ftp_filesystem" ON "a2obj_apps_files_storage_ftp" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_account" ON "a2obj_apps_files_accesslog" (`account`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_file" ON "a2obj_apps_files_accesslog" (`file`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_folder" ON "a2obj_apps_files_accesslog" (`folder`);
