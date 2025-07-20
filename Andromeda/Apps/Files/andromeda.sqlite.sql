CREATE TABLE `a2obj_apps_files_actionlog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  `item` char(16) DEFAULT NULL
,  `parent` char(16) DEFAULT NULL
,  `item_share` char(16) DEFAULT NULL
,  `parent_share` char(16) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(1) NOT NULL
,  `version` varchar(255) NOT NULL
,  `date_created` double NOT NULL
,  `apiurl` text DEFAULT NULL
,  `rwchunksize` integer NOT NULL
,  `crchunksize` integer NOT NULL
,  `upload_maxsize` integer DEFAULT NULL
,  `periodicstats` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_items_file` (
  `id` char(16) NOT NULL
,  `size` integer NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_items_file_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `CONSTRAINT_1` CHECK (`size` >= 0)
);
CREATE TABLE `a2obj_apps_files_items_folder` (
  `id` char(16) NOT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_subfiles` integer NOT NULL DEFAULT 0
,  `count_subfolders` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_items_folder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_items_item` (
  `id` char(16) NOT NULL
,  `owner` char(12) DEFAULT NULL
,  `storage` char(8) NOT NULL
,  `parent` char(16) DEFAULT NULL
,  `name` varchar(255) DEFAULT NULL
,  `isroot` integer DEFAULT NULL
,  `ispublic` integer DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `date_accessed` double DEFAULT NULL
,  `description` text DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`parent`)
,  UNIQUE (`storage`,`isroot`,`owner`)
,  UNIQUE (`storage`,`isroot`,`ispublic`)
,  CONSTRAINT `a2obj_apps_files_items_item_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_items_item_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`)
,  CONSTRAINT `a2obj_apps_files_items_item_ibfk_3` FOREIGN KEY (`parent`) REFERENCES `a2obj_apps_files_items_item` (`id`)
,  CONSTRAINT `CONSTRAINT_1` CHECK (`parent` is null and `name` is null and `isroot` is not null and `isroot` = 1 or `parent` is not null and `name` is not null and `isroot` is null)
,  CONSTRAINT `CONSTRAINT_2` CHECK (`owner` is null and `ispublic` is not null and `ispublic` = 1 or `owner` is not null and `ispublic` is null)
);
CREATE TABLE `a2obj_apps_files_policy_periodic` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `timestart` integer NOT NULL
,  `timeperiod` integer NOT NULL
,  `max_stats_age` integer DEFAULT NULL
,  `limit_pubdownloads` integer DEFAULT NULL
,  `limit_bandwidth` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_periodicaccount` (
  `id` char(12) NOT NULL
,  `account` char(12) NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_policy_periodicaccount_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_periodicaccount_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_periodicgroup` (
  `id` char(12) NOT NULL
,  `group` char(12) NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_policy_periodicgroup_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_periodicgroup_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_periodicstats` (
  `id` char(12) NOT NULL
,  `limit` char(12) NOT NULL
,  `dateidx` integer NOT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`limit`,`dateidx`)
,  CONSTRAINT `a2obj_apps_files_policy_periodicstats_ibfk_1` FOREIGN KEY (`limit`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_periodicstorage` (
  `id` char(8) NOT NULL
,  `storage` char(8) NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_policy_periodicstorage_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_periodic` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_periodicstorage_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_standard` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `date_download` double DEFAULT NULL
,  `date_upload` double DEFAULT NULL
,  `can_itemshare` integer DEFAULT NULL
,  `can_share2groups` integer DEFAULT NULL
,  `can_publicupload` integer DEFAULT NULL
,  `can_publicmodify` integer DEFAULT NULL
,  `can_randomwrite` integer DEFAULT NULL
,  `limit_size` integer DEFAULT NULL
,  `limit_items` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_standardaccount` (
  `id` char(12) NOT NULL
,  `account` char(12) NOT NULL
,  `can_emailshare` integer DEFAULT NULL
,  `can_userstorage` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`account`)
,  CONSTRAINT `a2obj_apps_files_policy_standardaccount_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_standardaccount_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_standardgroup` (
  `id` char(12) NOT NULL
,  `group` char(12) NOT NULL
,  `can_emailshare` integer DEFAULT NULL
,  `can_userstorage` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`group`)
,  CONSTRAINT `a2obj_apps_files_policy_standardgroup_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_standardgroup_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
);
CREATE TABLE `a2obj_apps_files_policy_standardstorage` (
  `id` char(8) NOT NULL
,  `storage` char(8) NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`storage`)
,  CONSTRAINT `a2obj_apps_files_policy_standardstorage_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_policy_standard` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_policy_standardstorage_ibfk_2` FOREIGN KEY (`storage`) REFERENCES `a2obj_apps_files_storage_storage` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_comment` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `value` text NOT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_like` (
  `id` char(12) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `date_created` double NOT NULL
,  `value` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`item`)
,  CONSTRAINT `a2obj_apps_files_social_like_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_like_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_share` (
  `id` char(16) NOT NULL
,  `item` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `dest` char(12) DEFAULT NULL
,  `label` text DEFAULT NULL
,  `authkey` binary(16) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_accessed` double DEFAULT NULL
,  `count_accessed` integer NOT NULL DEFAULT 0
,  `limit_accessed` integer DEFAULT NULL
,  `date_expires` double DEFAULT NULL
,  `can_read` integer NOT NULL
,  `can_upload` integer NOT NULL
,  `can_modify` integer NOT NULL
,  `can_social` integer NOT NULL
,  `can_reshare` integer NOT NULL
,  `keepowner` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`owner`,`dest`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_3` FOREIGN KEY (`dest`) REFERENCES `a2obj_apps_accounts_policybase` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_tag` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `value` varchar(127) NOT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`value`)
,  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `username` varbinary(255) DEFAULT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` tinyblob DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_local_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(8) NOT NULL
,  `endpoint` text NOT NULL
,  `path_style` integer DEFAULT NULL
,  `port` integer DEFAULT NULL
,  `usetls` integer DEFAULT NULL
,  `region` varchar(64) NOT NULL
,  `bucket` varchar(64) NOT NULL
,  `accesskey` varbinary(144) DEFAULT NULL
,  `accesskey_nonce` binary(24) DEFAULT NULL
,  `secretkey` varbinary(56) DEFAULT NULL
,  `secretkey_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_s3_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `hostkey` blob DEFAULT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `privkey` blob DEFAULT NULL
,  `keypass` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  `privkey_nonce` binary(24) DEFAULT NULL
,  `keypass_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_sftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `workgroup` varchar(255) DEFAULT NULL
,  `username` varbinary(255) DEFAULT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_smb_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_storage` (
  `id` char(8) NOT NULL
,  `date_created` double NOT NULL
,  `fstype` integer NOT NULL
,  `readonly` integer NOT NULL DEFAULT 0
,  `owner` char(12) DEFAULT NULL
,  `name` varchar(127) NOT NULL DEFAULT 'Default'
,  `crypto_masterkey` binary(32) DEFAULT NULL
,  `crypto_chunksize` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`name`)
,  CONSTRAINT `a2obj_apps_files_storage_storage_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `CONSTRAINT_1` CHECK (`crypto_masterkey` is null and `crypto_chunksize` is null or `crypto_masterkey` is not null and `crypto_chunksize` is not null)
);
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `endpoint` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_webdav_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX "idx_a2obj_apps_files_actionlog_account" ON "a2obj_apps_files_actionlog" (`account`);
CREATE INDEX "idx_a2obj_apps_files_actionlog_item" ON "a2obj_apps_files_actionlog" (`item`);
CREATE INDEX "idx_a2obj_apps_files_social_comment_item" ON "a2obj_apps_files_social_comment" (`item`);
CREATE INDEX "idx_a2obj_apps_files_social_comment_owner_item" ON "a2obj_apps_files_social_comment" (`owner`,`item`);
CREATE INDEX "idx_a2obj_apps_files_policy_periodicstorage_storage" ON "a2obj_apps_files_policy_periodicstorage" (`storage`);
CREATE INDEX "idx_a2obj_apps_files_storage_storage_owner" ON "a2obj_apps_files_storage_storage" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_storage_storage_name" ON "a2obj_apps_files_storage_storage" (`name`);
CREATE INDEX "idx_a2obj_apps_files_items_item_owner" ON "a2obj_apps_files_items_item" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_items_item_storage" ON "a2obj_apps_files_items_item" (`storage`);
CREATE INDEX "idx_a2obj_apps_files_items_item_parent" ON "a2obj_apps_files_items_item" (`parent`);
CREATE INDEX "idx_a2obj_apps_files_social_share_dest" ON "a2obj_apps_files_social_share" (`dest`);
CREATE INDEX "idx_a2obj_apps_files_social_share_owner" ON "a2obj_apps_files_social_share" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_social_share_item" ON "a2obj_apps_files_social_share" (`item`);
CREATE INDEX "idx_a2obj_apps_files_social_tag_owner" ON "a2obj_apps_files_social_tag" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_social_tag_item" ON "a2obj_apps_files_social_tag" (`item`);
CREATE INDEX "idx_a2obj_apps_files_policy_periodicaccount_account" ON "a2obj_apps_files_policy_periodicaccount" (`account`);
CREATE INDEX "idx_a2obj_apps_files_social_like_item" ON "a2obj_apps_files_social_like" (`item`);
CREATE INDEX "idx_a2obj_apps_files_policy_periodicgroup_group" ON "a2obj_apps_files_policy_periodicgroup" (`group`);
