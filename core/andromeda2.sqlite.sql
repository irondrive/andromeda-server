PRAGMA journal_mode = MEMORY;
CREATE TABLE IF NOT EXISTS `a2_objects_core_config` (
  `id` char(12) NOT NULL
,  `datadir` text DEFAULT NULL
,  `apps` text NOT NULL
,  `apiurl` text DEFAULT NULL
,  `dates__created` integer NOT NULL
,  `features__debug_log` integer NOT NULL
,  `features__debug_http` integer NOT NULL
,  `features__debug_file` integer NOT NULL
,  `features__read_only` integer NOT NULL
,  `features__enabled` integer NOT NULL
,  `features__email` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_core_emailer` (
  `id` char(12) NOT NULL
,  `type` integer NOT NULL
,  `hosts` text
,  `username` varchar(255) DEFAULT NULL
,  `password` text
,  `from_address` varchar(255) NOT NULL
,  `from_name` varchar(255) DEFAULT NULL
,  `features__reply` integer DEFAULT NULL
,  `dates__created` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE TABLE IF NOT EXISTS `a2_objects_core_exceptions_errorlogentry` (
  `id` char(12) NOT NULL
,  `time` integer NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `code` varchar(255) NOT NULL
,  `file` text NOT NULL
,  `message` text NOT NULL
,  `trace_basic` text
,  `trace_full` text
,  `objects` text
,  `queries` text
,  `params` text
,  `log` text
,  PRIMARY KEY (`id`)
,  UNIQUE (`id`)
);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlogentry_time" ON "a2_objects_core_exceptions_errorlogentry" (`time`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlogentry_code" ON "a2_objects_core_exceptions_errorlogentry" (`code`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlogentry_app" ON "a2_objects_core_exceptions_errorlogentry" (`app`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlogentry_action" ON "a2_objects_core_exceptions_errorlogentry" (`action`);
CREATE INDEX "idx_a2_objects_core_exceptions_errorlogentry_addr" ON "a2_objects_core_exceptions_errorlogentry" (`addr`);
CREATE INDEX "idx_a2_objects_core_config_id_2" ON "a2_objects_core_config" (`id`);
CREATE INDEX "idx_a2_objects_core_emailer_id_2" ON "a2_objects_core_emailer" (`id`);
