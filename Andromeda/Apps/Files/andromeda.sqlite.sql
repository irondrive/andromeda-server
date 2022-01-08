PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2obj_apps_files_accesslog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `obj_account` char(12) DEFAULT NULL
,  `obj_sudouser` char(12) DEFAULT NULL
,  `obj_client` char(12) DEFAULT NULL
,  `obj_file` char(16) DEFAULT NULL
,  `obj_folder` char(16) DEFAULT NULL
,  `obj_parent` char(16) DEFAULT NULL
,  `obj_file_share` char(16) DEFAULT NULL
,  `obj_folder_share` char(16) DEFAULT NULL
,  `obj_parent_share` char(16) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_comment` (
  `id` char(16) NOT NULL
,  `obj_owner` char(12) NOT NULL
,  `obj_item` varchar(64) NOT NULL
,  `comment` text NOT NULL
,  `date_created` double NOT NULL
,  `date_modified` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(12) NOT NULL
,  `version` varchar(255) NOT NULL
,  `date_created` double NOT NULL
,  `apiurl` text DEFAULT NULL
,  `rwchunksize` integer NOT NULL
,  `crchunksize` integer NOT NULL
,  `upload_maxsize` integer DEFAULT NULL
,  `timedstats` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_file` (
  `id` char(16) NOT NULL
,  `name` varchar(255) NOT NULL
,  `description` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `date_accessed` double DEFAULT NULL
,  `size` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  `obj_owner` char(12) DEFAULT NULL
,  `obj_parent` char(16) NOT NULL
,  `obj_filesystem` char(12) NOT NULL
,  `objs_likes` integer NOT NULL DEFAULT 0
,  `count_likes` integer NOT NULL DEFAULT 0
,  `count_dislikes` integer NOT NULL DEFAULT 0
,  `objs_tags` integer NOT NULL DEFAULT 0
,  `objs_comments` integer NOT NULL DEFAULT 0
,  `objs_shares` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`obj_parent`)
);
CREATE TABLE `a2obj_apps_files_filesystem_fsmanager` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `type` integer NOT NULL
,  `readonly` integer NOT NULL
,  `obj_storage` varchar(64) NOT NULL
,  `obj_owner` char(12) DEFAULT NULL
,  `name` varchar(127) DEFAULT NULL
,  `crypto_masterkey` binary(32) DEFAULT NULL
,  `crypto_chunksize` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_owner`,`name`)
);
CREATE TABLE `a2obj_apps_files_folder` (
  `id` char(16) NOT NULL
,  `name` varchar(255) DEFAULT NULL
,  `description` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `date_accessed` double DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_pubvisits` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  `obj_owner` char(12) DEFAULT NULL
,  `obj_parent` char(16) DEFAULT NULL
,  `obj_filesystem` char(12) NOT NULL
,  `objs_files` integer NOT NULL DEFAULT 0
,  `objs_folders` integer NOT NULL DEFAULT 0
,  `count_subfiles` integer NOT NULL DEFAULT 0
,  `count_subfolders` integer NOT NULL DEFAULT 0
,  `count_subshares` integer NOT NULL DEFAULT 0
,  `objs_likes` integer NOT NULL DEFAULT 0
,  `count_likes` integer NOT NULL DEFAULT 0
,  `count_dislikes` integer NOT NULL DEFAULT 0
,  `objs_tags` integer NOT NULL DEFAULT 0
,  `objs_comments` integer NOT NULL DEFAULT 0
,  `objs_shares` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`obj_parent`)
);
CREATE TABLE `a2obj_apps_files_like` (
  `id` char(16) NOT NULL
,  `obj_owner` char(12) NOT NULL
,  `obj_item` varchar(64) NOT NULL
,  `date_created` double NOT NULL
,  `value` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_owner`,`obj_item`)
);
CREATE TABLE `a2obj_apps_files_limits_authentitytotal` (
  `id` char(12) NOT NULL
,  `obj_object` varchar(64) NOT NULL
,  `date_created` double NOT NULL
,  `date_download` double DEFAULT NULL
,  `date_upload` double DEFAULT NULL
,  `itemsharing` integer DEFAULT NULL
,  `share2everyone` integer DEFAULT NULL
,  `share2groups` integer DEFAULT NULL
,  `emailshare` integer DEFAULT NULL
,  `publicupload` integer DEFAULT NULL
,  `publicmodify` integer DEFAULT NULL
,  `randomwrite` integer DEFAULT NULL
,  `userstorage` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_shares` integer NOT NULL DEFAULT 0
,  `limit_size` integer DEFAULT NULL
,  `limit_items` integer DEFAULT NULL
,  `limit_shares` integer DEFAULT NULL
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_object`)
);
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(12) NOT NULL
,  `obj_object` varchar(64) NOT NULL
,  `date_created` double NOT NULL
,  `date_download` double DEFAULT NULL
,  `date_upload` double DEFAULT NULL
,  `itemsharing` integer DEFAULT NULL
,  `share2everyone` integer DEFAULT NULL
,  `share2groups` integer DEFAULT NULL
,  `publicupload` integer DEFAULT NULL
,  `publicmodify` integer DEFAULT NULL
,  `randomwrite` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_shares` integer NOT NULL DEFAULT 0
,  `limit_size` integer DEFAULT NULL
,  `limit_items` integer DEFAULT NULL
,  `limit_shares` integer DEFAULT NULL
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_object`)
);
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL
,  `obj_object` varchar(64) NOT NULL
,  `objs_stats` integer NOT NULL DEFAULT 0
,  `date_created` double NOT NULL
,  `timeperiod` integer NOT NULL
,  `max_stats_age` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  `limit_pubdownloads` integer DEFAULT NULL
,  `limit_bandwidth` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_object`,`timeperiod`)
);
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL
,  `obj_limitobj` varchar(64) NOT NULL
,  `date_created` double NOT NULL
,  `date_timestart` integer NOT NULL
,  `iscurrent` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_shares` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_limitobj`,`date_timestart`)
,  UNIQUE (`obj_limitobj`,`iscurrent`)
);
CREATE TABLE `a2obj_apps_files_share` (
  `id` char(16) NOT NULL
,  `obj_item` varchar(64) NOT NULL
,  `obj_owner` char(12) NOT NULL
,  `obj_dest` varchar(64) DEFAULT NULL
,  `label` text DEFAULT NULL
,  `authkey` text DEFAULT NULL
,  `password` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_accessed` double DEFAULT NULL
,  `count_accessed` integer NOT NULL DEFAULT 0
,  `limit_accessed` integer DEFAULT NULL
,  `date_expires` integer DEFAULT NULL
,  `read` integer NOT NULL
,  `upload` integer NOT NULL
,  `modify` integer NOT NULL
,  `social` integer NOT NULL
,  `reshare` integer NOT NULL
,  `keepowner` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_item`,`obj_owner`,`obj_dest`)
);
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
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
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
,  `path` text NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
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
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
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
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
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
,  `date_created` double NOT NULL
,  `obj_filesystem` char(12) NOT NULL
,  `endpoint` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_tag` (
  `id` char(16) NOT NULL
,  `obj_owner` char(12) NOT NULL
,  `obj_item` varchar(64) NOT NULL
,  `tag` varchar(127) NOT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`obj_item`,`tag`)
);
CREATE INDEX "idx_a2obj_apps_files_limits_timed_object" ON "a2obj_apps_files_limits_timed" (`obj_object`);
CREATE INDEX "idx_a2obj_apps_files_tag_owner" ON "a2obj_apps_files_tag" (`obj_owner`);
CREATE INDEX "idx_a2obj_apps_files_tag_item" ON "a2obj_apps_files_tag" (`obj_item`);
CREATE INDEX "idx_a2obj_apps_files_storage_smb_filesystem" ON "a2obj_apps_files_storage_smb" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_s3_filesystem" ON "a2obj_apps_files_storage_s3" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_share_owner" ON "a2obj_apps_files_share" (`obj_owner`);
CREATE INDEX "idx_a2obj_apps_files_share_item" ON "a2obj_apps_files_share" (`obj_item`);
CREATE INDEX "idx_a2obj_apps_files_comment_item" ON "a2obj_apps_files_comment" (`obj_item`);
CREATE INDEX "idx_a2obj_apps_files_comment_owner_item" ON "a2obj_apps_files_comment" (`obj_owner`,`obj_item`);
CREATE INDEX "idx_a2obj_apps_files_file_owner" ON "a2obj_apps_files_file" (`obj_owner`);
CREATE INDEX "idx_a2obj_apps_files_file_parent" ON "a2obj_apps_files_file" (`obj_parent`);
CREATE INDEX "idx_a2obj_apps_files_file_filesystem" ON "a2obj_apps_files_file" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_webdav_filesystem" ON "a2obj_apps_files_storage_webdav" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_sftp_filesystem" ON "a2obj_apps_files_storage_sftp" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_like_item" ON "a2obj_apps_files_like" (`obj_item`);
CREATE INDEX "idx_a2obj_apps_files_folder_parent" ON "a2obj_apps_files_folder" (`obj_parent`);
CREATE INDEX "idx_a2obj_apps_files_folder_owner" ON "a2obj_apps_files_folder" (`obj_owner`);
CREATE INDEX "idx_a2obj_apps_files_folder_filesystem" ON "a2obj_apps_files_folder" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_owner" ON "a2obj_apps_files_filesystem_fsmanager" (`obj_owner`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_name" ON "a2obj_apps_files_filesystem_fsmanager" (`name`);
CREATE INDEX "idx_a2obj_apps_files_filesystem_fsmanager_storage" ON "a2obj_apps_files_filesystem_fsmanager" (`obj_storage`);
CREATE INDEX "idx_a2obj_apps_files_storage_local_filesystem" ON "a2obj_apps_files_storage_local" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_storage_ftp_filesystem" ON "a2obj_apps_files_storage_ftp" (`obj_filesystem`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_account" ON "a2obj_apps_files_accesslog" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_file" ON "a2obj_apps_files_accesslog" (`obj_file`);
CREATE INDEX "idx_a2obj_apps_files_accesslog_folder" ON "a2obj_apps_files_accesslog" (`obj_folder`);
