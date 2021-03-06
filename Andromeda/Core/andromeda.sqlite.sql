PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2obj_apps_core_accesslog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_config` (
  `id` char(12) NOT NULL
,  `version` varchar(255) NOT NULL
,  `datadir` text DEFAULT NULL
,  `apps` text NOT NULL
,  `dates__created` double NOT NULL
,  `features__requestlog_db` integer NOT NULL
,  `features__requestlog_file` integer NOT NULL
,  `features__requestlog_details` integer NOT NULL
,  `features__debug` integer NOT NULL
,  `features__debug_http` integer NOT NULL
,  `features__debug_dblog` integer NOT NULL
,  `features__debug_filelog` integer NOT NULL
,  `features__metrics` integer NOT NULL
,  `features__metrics_dblog` integer NOT NULL
,  `features__metrics_filelog` integer NOT NULL
,  `features__read_only` integer NOT NULL
,  `features__enabled` integer NOT NULL
,  `features__email` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_emailer` (
  `id` char(12) NOT NULL
,  `type` integer NOT NULL
,  `hosts` text DEFAULT NULL
,  `username` varchar(255) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `from_address` varchar(255) NOT NULL
,  `from_name` varchar(255) DEFAULT NULL
,  `features__reply` integer DEFAULT NULL
,  `dates__created` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_exceptions_errorlog` (
  `id` char(12) NOT NULL
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `app` varchar(255) DEFAULT NULL
,  `action` varchar(255) DEFAULT NULL
,  `code` varchar(255) NOT NULL
,  `file` text NOT NULL
,  `message` text NOT NULL
,  `trace_basic` longtext DEFAULT NULL
,  `trace_full` longtext DEFAULT NULL
,  `objects` longtext DEFAULT NULL
,  `queries` longtext DEFAULT NULL
,  `params` longtext DEFAULT NULL
,  `log` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_actionlog` (
  `id` char(20) NOT NULL
,  `request` char(20) NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `applog` varchar(64) DEFAULT NULL
,  `details` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_actionmetrics` (
  `id` char(20) NOT NULL
,  `request` char(20) NOT NULL
,  `actionlog` char(20) NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `dates__created` double NOT NULL
,  `stats__db_reads` integer NOT NULL
,  `stats__db_read_time` double NOT NULL
,  `stats__db_writes` integer NOT NULL
,  `stats__db_write_time` double NOT NULL
,  `stats__code_time` double NOT NULL
,  `stats__total_time` double NOT NULL
,  `stats__queries` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_commitmetrics` (
  `id` char(20) NOT NULL
,  `request` char(20) NOT NULL
,  `dates__created` double NOT NULL
,  `stats__db_reads` integer NOT NULL
,  `stats__db_read_time` double NOT NULL
,  `stats__db_writes` integer NOT NULL
,  `stats__db_write_time` double NOT NULL
,  `stats__code_time` double NOT NULL
,  `stats__total_time` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_requestlog` (
  `id` char(20) NOT NULL
,  `actions` integer NOT NULL DEFAULT 0
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `errcode` integer DEFAULT NULL
,  `errtext` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_requestmetrics` (
  `id` char(20) NOT NULL
,  `actions` integer NOT NULL DEFAULT 0
,  `commits` integer NOT NULL DEFAULT 0
,  `requestlog` char(20) DEFAULT NULL
,  `dates__created` double NOT NULL
,  `peak_memory` integer NOT NULL
,  `nincludes` integer NOT NULL
,  `nobjects` integer NOT NULL
,  `construct__db_reads` integer NOT NULL
,  `construct__db_read_time` double NOT NULL
,  `construct__db_writes` integer NOT NULL
,  `construct__db_write_time` double NOT NULL
,  `construct__code_time` double NOT NULL
,  `construct__total_time` double NOT NULL
,  `construct__queries` text DEFAULT NULL
,  `total__db_reads` integer NOT NULL
,  `total__db_read_time` double NOT NULL
,  `total__db_writes` integer NOT NULL
,  `total__db_write_time` double NOT NULL
,  `total__code_time` double NOT NULL
,  `total__total_time` double NOT NULL
,  `gcstats` text DEFAULT NULL
,  `rusage` text DEFAULT NULL
,  `includes` longtext DEFAULT NULL
,  `objects` longtext DEFAULT NULL
,  `queries` longtext DEFAULT NULL
,  `debuglog` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE INDEX "idx_a2obj_core_logging_actionmetrics_app_action" ON "a2obj_core_logging_actionmetrics" (`app`,`action`);
CREATE INDEX "idx_a2obj_core_logging_actionmetrics_request" ON "a2obj_core_logging_actionmetrics" (`request`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_time" ON "a2obj_core_exceptions_errorlog" (`time`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_code" ON "a2obj_core_exceptions_errorlog" (`code`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_app" ON "a2obj_core_exceptions_errorlog" (`app`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_action" ON "a2obj_core_exceptions_errorlog" (`action`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_addr" ON "a2obj_core_exceptions_errorlog" (`addr`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_request" ON "a2obj_core_logging_actionlog" (`request`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_applog" ON "a2obj_core_logging_actionlog" (`applog`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_app_action" ON "a2obj_core_logging_actionlog" (`app`,`action`);
CREATE INDEX "idx_a2obj_core_logging_commitmetrics_request" ON "a2obj_core_logging_commitmetrics" (`request`);
