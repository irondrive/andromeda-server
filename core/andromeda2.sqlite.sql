PRAGMA journal_mode = MEMORY;
CREATE TABLE `a2_objects_core_config` (
  `id` char(12) NOT NULL
,  `datadir` text DEFAULT NULL
,  `apps` text NOT NULL
,  `dates__created` double NOT NULL
,  `features__requestlog_db` integer NOT NULL
,  `features__requestlog_file` integer NOT NULL
,  `features__debug` integer NOT NULL
,  `features__debug_http` integer NOT NULL
,  `features__debug_dblog` integer NOT NULL
,  `features__debug_filelog` integer NOT NULL
,  `features__read_only` integer NOT NULL
,  `features__enabled` integer NOT NULL
,  `features__email` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE `a2_objects_core_emailer` (
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
,  UNIQUE (`id`)
);
CREATE TABLE `a2_objects_core_exceptions_errorlog` (
  `id` char(12) NOT NULL
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `app` varchar(255) DEFAULT NULL
,  `action` varchar(255) DEFAULT NULL
,  `code` varchar(255) NOT NULL
,  `file` text NOT NULL
,  `message` text NOT NULL
,  `trace_basic` text DEFAULT NULL
,  `trace_full` text DEFAULT NULL
,  `objects` text DEFAULT NULL
,  `queries` text DEFAULT NULL
,  `params` text DEFAULT NULL
,  `log` text DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE `a2_objects_core_logging_actionlog` (
  `id` char(16) NOT NULL
,  `request` char(16) NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `applog` varchar(64) DEFAULT NULL
,  `extra` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2_objects_core_logging_requestlog` (
  `id` char(16) NOT NULL
,  `actions` integer NOT NULL DEFAULT 0
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `errcode` integer DEFAULT NULL
,  `errtext` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlog_time" ON "a2_objects_core_exceptions_errorlog" (`time`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlog_code" ON "a2_objects_core_exceptions_errorlog" (`code`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlog_app" ON "a2_objects_core_exceptions_errorlog" (`app`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlog_action" ON "a2_objects_core_exceptions_errorlog" (`action`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlog_addr" ON "a2_objects_core_exceptions_errorlog" (`addr`);
CREATE INDEX "idx_a2_objects_core_logging_actionlog_request" ON "a2_objects_core_logging_actionlog" (`request`);
CREATE INDEX "idx_a2_objects_core_logging_actionlog_applog" ON "a2_objects_core_logging_actionlog" (`applog`);
CREATE INDEX "idx_a2_objects_core_config_id_2" ON "a2_objects_core_config" (`id`);
CREATE INDEX "idx_a2_objects_core_emailer_id_2" ON "a2_objects_core_emailer" (`id`);
