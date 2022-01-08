PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2obj_apps_accounts_accesslog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `obj_account` char(12) DEFAULT NULL
,  `obj_sudouser` char(12) DEFAULT NULL
,  `obj_client` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_account` (
  `id` char(12) NOT NULL
,  `username` varchar(127) NOT NULL
,  `fullname` varchar(255) DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_passwordset` double DEFAULT NULL
,  `date_loggedon` double DEFAULT NULL
,  `date_active` double DEFAULT NULL
,  `date_modified` double DEFAULT NULL
,  `session_timeout` integer DEFAULT NULL
,  `client_timeout` integer DEFAULT NULL
,  `max_password_age` integer DEFAULT NULL
,  `admin` integer DEFAULT NULL
,  `disabled` integer DEFAULT NULL
,  `forcetf` integer DEFAULT NULL
,  `allowcrypto` integer DEFAULT NULL
,  `accountsearch` integer DEFAULT NULL
,  `groupsearch` integer DEFAULT NULL
,  `userdelete` integer DEFAULT NULL
,  `limit_sessions` integer DEFAULT NULL
,  `limit_contacts` integer DEFAULT NULL
,  `limit_recoverykeys` integer DEFAULT NULL
,  `comment` text DEFAULT NULL
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `obj_authsource` varchar(64) DEFAULT NULL
,  `objs_groups` integer NOT NULL DEFAULT 0
,  `objs_sessions` integer NOT NULL DEFAULT 0
,  `objs_contacts` integer NOT NULL DEFAULT 0
,  `objs_clients` integer NOT NULL DEFAULT 0
,  `objs_twofactors` integer NOT NULL DEFAULT 0
,  `objs_recoverykeys` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`username`)
);
CREATE TABLE `a2obj_apps_accounts_auth_ftp` (
  `id` char(12) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `obj_manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_auth_imap` (
  `id` char(12) NOT NULL
,  `protocol` integer NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `secauth` integer NOT NULL
,  `obj_manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_auth_ldap` (
  `id` char(12) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `secure` integer NOT NULL
,  `userprefix` varchar(255) NOT NULL
,  `obj_manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_auth_manager` (
  `id` char(12) NOT NULL
,  `enabled` integer NOT NULL
,  `obj_authsource` varchar(64) NOT NULL
,  `description` text DEFAULT NULL
,  `obj_default_group` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_client` (
  `id` char(12) NOT NULL
,  `name` varchar(255) DEFAULT NULL
,  `authkey` text NOT NULL
,  `lastaddr` varchar(255) NOT NULL
,  `useragent` text NOT NULL
,  `date_active` double DEFAULT 0
,  `date_created` double NOT NULL DEFAULT 0
,  `date_loggedon` double NOT NULL DEFAULT 0
,  `obj_account` char(12) NOT NULL
,  `obj_session` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_config` (
  `id` char(12) NOT NULL
,  `version` varchar(255) NOT NULL
,  `createaccount` integer NOT NULL
,  `usernameiscontact` integer NOT NULL
,  `requirecontact` integer NOT NULL
,  `obj_default_group` char(12) DEFAULT NULL
,  `obj_default_auth` char(12) DEFAULT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_contact` (
  `id` char(12) NOT NULL
,  `type` integer NOT NULL
,  `info` varchar(127) NOT NULL
,  `valid` integer NOT NULL DEFAULT 0
,  `usefrom` integer DEFAULT NULL
,  `public` integer NOT NULL DEFAULT 0
,  `authkey` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `obj_account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`type`,`info`)
,  UNIQUE (`usefrom`,`obj_account`)
);
CREATE TABLE `a2obj_apps_accounts_group` (
  `id` char(12) NOT NULL
,  `name` varchar(127) NOT NULL
,  `comment` text DEFAULT NULL
,  `priority` integer NOT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `admin` integer DEFAULT NULL
,  `disabled` integer DEFAULT NULL
,  `forcetf` integer DEFAULT NULL
,  `allowcrypto` integer DEFAULT NULL
,  `accountsearch` integer DEFAULT NULL
,  `groupsearch` integer DEFAULT NULL
,  `userdelete` integer DEFAULT NULL
,  `limit_sessions` integer DEFAULT NULL
,  `limit_contacts` integer DEFAULT NULL
,  `limit_recoverykeys` integer DEFAULT NULL
,  `session_timeout` integer DEFAULT NULL
,  `client_timeout` integer DEFAULT NULL
,  `max_password_age` integer DEFAULT NULL
,  `objs_accounts` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`)
);
CREATE TABLE `a2obj_apps_accounts_groupjoin` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `objs_accounts` char(12) NOT NULL
,  `objs_groups` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`objs_accounts`,`objs_groups`)
);
CREATE TABLE `a2obj_apps_accounts_recoverykey` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `date_created` double NOT NULL DEFAULT 0
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `obj_account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_session` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `date_active` double DEFAULT NULL
,  `date_created` double NOT NULL DEFAULT 0
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `obj_account` char(12) NOT NULL
,  `obj_client` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_twofactor` (
  `id` char(12) NOT NULL
,  `comment` text DEFAULT NULL
,  `secret` varbinary(48) NOT NULL
,  `nonce` binary(24) DEFAULT NULL
,  `valid` integer NOT NULL DEFAULT 0
,  `date_created` double NOT NULL
,  `date_used` double DEFAULT NULL
,  `obj_account` char(12) NOT NULL
,  `objs_usedtokens` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_usedtoken` (
  `id` char(12) NOT NULL
,  `code` char(6) NOT NULL
,  `date_created` double NOT NULL
,  `obj_twofactor` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_whitelist` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `type` integer NOT NULL
,  `value` varchar(127) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`type`,`value`)
);
CREATE INDEX "idx_a2obj_apps_accounts_contact_info" ON "a2obj_apps_accounts_contact" (`info`);
CREATE INDEX "idx_a2obj_apps_accounts_contact_account" ON "a2obj_apps_accounts_contact" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_recoverykey_account" ON "a2obj_apps_accounts_recoverykey" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_accesslog_account" ON "a2obj_apps_accounts_accesslog" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_usedtoken_date_created" ON "a2obj_apps_accounts_usedtoken" (`date_created`);
CREATE INDEX "idx_a2obj_apps_accounts_usedtoken_twofactor" ON "a2obj_apps_accounts_usedtoken" (`obj_twofactor`);
CREATE INDEX "idx_a2obj_apps_accounts_twofactor_account" ON "a2obj_apps_accounts_twofactor" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_groupjoin_accounts" ON "a2obj_apps_accounts_groupjoin" (`objs_accounts`);
CREATE INDEX "idx_a2obj_apps_accounts_groupjoin_groups" ON "a2obj_apps_accounts_groupjoin" (`objs_groups`);
CREATE INDEX "idx_a2obj_apps_accounts_account_fullname" ON "a2obj_apps_accounts_account" (`fullname`);
CREATE INDEX "idx_a2obj_apps_accounts_account_authsource" ON "a2obj_apps_accounts_account" (`obj_authsource`);
CREATE INDEX "idx_a2obj_apps_accounts_session_account" ON "a2obj_apps_accounts_session" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_session_client" ON "a2obj_apps_accounts_session" (`obj_client`);
CREATE INDEX "idx_a2obj_apps_accounts_session_date_active_account" ON "a2obj_apps_accounts_session" (`date_active`,`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_auth_manager_authsource" ON "a2obj_apps_accounts_auth_manager" (`obj_authsource`);
CREATE INDEX "idx_a2obj_apps_accounts_client_account" ON "a2obj_apps_accounts_client" (`obj_account`);
CREATE INDEX "idx_a2obj_apps_accounts_client_session" ON "a2obj_apps_accounts_client" (`obj_session`);
CREATE INDEX "idx_a2obj_apps_accounts_client_date_active_account" ON "a2obj_apps_accounts_client" (`date_active`,`obj_account`);
