PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2obj_apps_core_accesslog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `obj_account` char(12) DEFAULT NULL
,  `obj_sudouser` char(12) DEFAULT NULL
,  `obj_client` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_config` (
  `id` char(12) NOT NULL
,  `version` varchar(255) NOT NULL
,  `datadir` text DEFAULT NULL
,  `apps` text NOT NULL
,  `date_created` double NOT NULL
,  `requestlog_db` integer NOT NULL
,  `requestlog_file` integer NOT NULL
,  `requestlog_details` integer NOT NULL
,  `debug` integer NOT NULL
,  `debug_http` integer NOT NULL
,  `debug_dblog` integer NOT NULL
,  `debug_filelog` integer NOT NULL
,  `metrics` integer NOT NULL
,  `metrics_dblog` integer NOT NULL
,  `metrics_filelog` integer NOT NULL
,  `read_only` integer NOT NULL
,  `enabled` integer NOT NULL
,  `email` integer NOT NULL
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
,  `reply` integer DEFAULT NULL
,  `date_created` double NOT NULL
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
,  `obj_request` char(20) NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `obj_applog` varchar(64) DEFAULT NULL
,  `details` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_actionmetrics` (
  `id` char(20) NOT NULL
,  `obj_request` char(20) NOT NULL
,  `obj_actionlog` char(20) NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `date_created` double NOT NULL
,  `stats_db_reads` integer NOT NULL
,  `stats_db_read_time` double NOT NULL
,  `stats_db_writes` integer NOT NULL
,  `stats_db_write_time` double NOT NULL
,  `stats_code_time` double NOT NULL
,  `stats_total_time` double NOT NULL
,  `stats_queries` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_commitmetrics` (
  `id` char(20) NOT NULL
,  `obj_request` char(20) NOT NULL
,  `date_created` double NOT NULL
,  `stats_db_reads` integer NOT NULL
,  `stats_db_read_time` double NOT NULL
,  `stats_db_writes` integer NOT NULL
,  `stats_db_write_time` double NOT NULL
,  `stats_code_time` double NOT NULL
,  `stats_total_time` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_requestlog` (
  `id` char(20) NOT NULL
,  `objs_actions` integer NOT NULL DEFAULT 0
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `errcode` integer DEFAULT NULL
,  `errtext` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_requestmetrics` (
  `id` char(20) NOT NULL
,  `objs_actions` integer NOT NULL DEFAULT 0
,  `objs_commits` integer NOT NULL DEFAULT 0
,  `obj_requestlog` char(20) DEFAULT NULL
,  `date_created` double NOT NULL
,  `peak_memory` integer NOT NULL
,  `nincludes` integer NOT NULL
,  `nobjects` integer NOT NULL
,  `construct_db_reads` integer NOT NULL
,  `construct_db_read_time` double NOT NULL
,  `construct_db_writes` integer NOT NULL
,  `construct_db_write_time` double NOT NULL
,  `construct_code_time` double NOT NULL
,  `construct_total_time` double NOT NULL
,  `construct_queries` text DEFAULT NULL
,  `total_db_reads` integer NOT NULL
,  `total_db_read_time` double NOT NULL
,  `total_db_writes` integer NOT NULL
,  `total_db_write_time` double NOT NULL
,  `total_code_time` double NOT NULL
,  `total_total_time` double NOT NULL
,  `gcstats` text DEFAULT NULL
,  `rusage` text DEFAULT NULL
,  `includes` longtext DEFAULT NULL
,  `objects` longtext DEFAULT NULL
,  `queries` longtext DEFAULT NULL
,  `debuglog` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE INDEX "idx_a2obj_core_logging_actionmetrics_app_action" ON "a2obj_core_logging_actionmetrics" (`app`,`action`);
CREATE INDEX "idx_a2obj_core_logging_actionmetrics_request" ON "a2obj_core_logging_actionmetrics" (`obj_request`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_time" ON "a2obj_core_exceptions_errorlog" (`time`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_code" ON "a2obj_core_exceptions_errorlog" (`code`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_app" ON "a2obj_core_exceptions_errorlog" (`app`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_action" ON "a2obj_core_exceptions_errorlog" (`action`);
CREATE INDEX "idx_a2obj_core_exceptions_errorlog_addr" ON "a2obj_core_exceptions_errorlog" (`addr`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_request" ON "a2obj_core_logging_actionlog" (`obj_request`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_applog" ON "a2obj_core_logging_actionlog" (`obj_applog`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_app_action" ON "a2obj_core_logging_actionlog" (`app`,`action`);
CREATE INDEX "idx_a2obj_core_logging_commitmetrics_request" ON "a2obj_core_logging_commitmetrics" (`obj_request`);
