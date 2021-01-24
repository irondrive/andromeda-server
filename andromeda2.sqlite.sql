PRAGMA journal_mode = MEMORY;
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_account` (
  `id` char(12) NOT NULL
,  `username` varchar(127) NOT NULL
,  `fullname` varchar(255) DEFAULT NULL
,  `unlockcode` char(8) DEFAULT NULL
,  `dates__created` integer NOT NULL DEFAULT '0'
,  `dates__passwordset` integer NOT NULL DEFAULT '0'
,  `dates__loggedon` integer NOT NULL DEFAULT '0'
,  `dates__active` integer NOT NULL DEFAULT '0'
,  `dates__modified` integer DEFAULT NULL
,  `max_session_age` integer DEFAULT NULL
,  `max_password_age` integer DEFAULT NULL
,  `features__admin` integer DEFAULT NULL
,  `features__enabled` integer DEFAULT NULL
,  `features__forcetf` integer DEFAULT NULL
,  `features__allowcrypto` integer DEFAULT NULL
,  `counters_limits__sessions` integer DEFAULT NULL
,  `counters_limits__contactinfos` integer DEFAULT NULL
,  `counters_limits__recoverykeys` integer DEFAULT NULL
,  `comment` text
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `password` text
,  `authsource` varchar(64) DEFAULT NULL
,  `groups` integer NOT NULL DEFAULT '0'
,  `sessions` integer NOT NULL DEFAULT '0'
,  `contactinfos` integer NOT NULL DEFAULT '0'
,  `clients` integer NOT NULL DEFAULT '0'
,  `twofactors` integer NOT NULL DEFAULT '0'
,  `recoverykeys` integer NOT NULL DEFAULT '0'
,  PRIMARY KEY (`id`)
,  UNIQUE (`username`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_ftp` (
  `id` char(12) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_imap` (
  `id` char(12) NOT NULL
,  `protocol` integer NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `secauth` integer NOT NULL
,  `manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_ldap` (
  `id` char(12) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `secure` integer NOT NULL
,  `userprefix` varchar(255) NOT NULL
,  `manager` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_auth_manager` (
  `id` char(12) NOT NULL
,  `authsource` varchar(64) NOT NULL
,  `description` text NOT NULL
,  `default_group` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_client` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `lastaddr` varchar(255) NOT NULL
,  `useragent` text NOT NULL
,  `dates__active` integer NOT NULL DEFAULT '0'
,  `dates__created` integer NOT NULL DEFAULT '0'
,  `dates__loggedon` integer NOT NULL DEFAULT '0'
,  `account` char(12) NOT NULL
,  `session` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_config` (
  `id` char(12) NOT NULL
,  `features__createaccount` integer NOT NULL
,  `features__emailasusername` integer NOT NULL
,  `features__requirecontact` integer NOT NULL
,  `default_group` char(12) DEFAULT NULL
,  `dates__created` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_contactinfo` (
  `id` char(12) NOT NULL
,  `type` integer NOT NULL
,  `info` varchar(127) NOT NULL
,  `valid` integer NOT NULL DEFAULT '1'
,  `unlockcode` char(8) DEFAULT NULL
,  `dates__created` integer NOT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
,  UNIQUE (`info`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_group` (
  `id` char(12) NOT NULL
,  `name` varchar(127) NOT NULL
,  `comment` text
,  `priority` integer NOT NULL
,  `dates__created` integer NOT NULL
,  `dates__modified` integer DEFAULT NULL
,  `features__admin` integer DEFAULT NULL
,  `features__enabled` integer DEFAULT NULL
,  `features__forcetf` integer DEFAULT NULL
,  `features__allowcrypto` integer DEFAULT NULL
,  `counters_limits__sessions` integer DEFAULT NULL
,  `counters_limits__contactinfos` integer DEFAULT NULL
,  `counters_limits__recoverykeys` integer DEFAULT NULL
,  `max_session_age` integer DEFAULT NULL
,  `max_password_age` integer DEFAULT NULL
,  `accounts` integer NOT NULL DEFAULT '0'
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
,  UNIQUE (`name`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_groupjoin` (
  `id` char(12) NOT NULL
,  `dates__created` integer NOT NULL
,  `accounts` char(12) NOT NULL
,  `groups` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`accounts`,`groups`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_recoverykey` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `dates__created` integer NOT NULL DEFAULT '0'
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_session` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `dates__active` integer NOT NULL DEFAULT '0'
,  `dates__created` integer NOT NULL DEFAULT '0'
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `account` char(12) NOT NULL
,  `client` char(12) NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_twofactor` (
  `id` char(12) NOT NULL
,  `comment` text
,  `secret` binary(48) NOT NULL
,  `nonce` binary(24) DEFAULT NULL
,  `valid` integer NOT NULL DEFAULT '0'
,  `dates__created` integer NOT NULL
,  `account` char(12) NOT NULL
,  `usedtokens` integer NOT NULL DEFAULT '0'
,  PRIMARY KEY (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_apps_accounts_usedtoken` (
  `id` char(12) NOT NULL
,  `code` char(6) NOT NULL
,  `dates__created` integer NOT NULL
,  `twofactor` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE INDEX "idx_a2_objects_apps_accounts_auth_manager_authsource*objectpoly*Apps\Accounts\Auth\Source" ON "a2_objects_apps_accounts_auth_manager" (`authsource`);
CREATE INDEX "idx_a2_objects_apps_accounts_twofactor_account*object*Apps\Accounts\Account" ON "a2_objects_apps_accounts_twofactor" (`account`);
CREATE INDEX "idx_a2_objects_apps_accounts_recoverykey_id" ON "a2_objects_apps_accounts_recoverykey" (`id`);
CREATE INDEX "idx_a2_objects_apps_accounts_recoverykey_account*object*Apps\Accounts\Account*recoverykeys" ON "a2_objects_apps_accounts_recoverykey" (`account`);
CREATE INDEX "idx_a2_objects_apps_accounts_groupjoin_accounts*object*Apps\Accounts\Account*groups" ON "a2_objects_apps_accounts_groupjoin" (`accounts`);
CREATE INDEX "idx_a2_objects_apps_accounts_groupjoin_groups*object*Apps\Accounts\Group*accounts" ON "a2_objects_apps_accounts_groupjoin" (`groups`);
CREATE INDEX "idx_a2_objects_apps_accounts_groupjoin_id" ON "a2_objects_apps_accounts_groupjoin" (`id`);
CREATE INDEX "idx_a2_objects_apps_accounts_contactinfo_type" ON "a2_objects_apps_accounts_contactinfo" (`type`);
CREATE INDEX "idx_a2_objects_apps_accounts_contactinfo_account*object*Apps\Accounts\Account*aliases" ON "a2_objects_apps_accounts_contactinfo" (`account`);
CREATE INDEX "idx_a2_objects_apps_accounts_session_aid" ON "a2_objects_apps_accounts_session" (`account`);
CREATE INDEX "idx_a2_objects_apps_accounts_session_cid" ON "a2_objects_apps_accounts_session" (`client`);
CREATE INDEX "idx_a2_objects_apps_accounts_client_account*object*Apps\Accounts\Account*clients" ON "a2_objects_apps_accounts_client" (`account`);
CREATE INDEX "idx_a2_objects_apps_accounts_client_session*object*Apps\Accounts\Session" ON "a2_objects_apps_accounts_client" (`session`);
