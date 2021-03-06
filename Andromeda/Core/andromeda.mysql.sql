
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_core_accesslog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) DEFAULT NULL,
  `sudouser` char(12) DEFAULT NULL,
  `client` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_config` (
  `id` char(12) NOT NULL,
  `version` varchar(255) NOT NULL,
  `datadir` text DEFAULT NULL,
  `apps` text NOT NULL,
  `dates__created` double NOT NULL,
  `features__requestlog_db` tinyint(2) NOT NULL,
  `features__requestlog_file` tinyint(1) NOT NULL,
  `features__requestlog_details` tinyint(2) NOT NULL,
  `features__debug` tinyint(2) NOT NULL,
  `features__debug_http` tinyint(1) NOT NULL,
  `features__debug_dblog` tinyint(1) NOT NULL,
  `features__debug_filelog` tinyint(1) NOT NULL,
  `features__metrics` tinyint(2) NOT NULL,
  `features__metrics_dblog` tinyint(1) NOT NULL,
  `features__metrics_filelog` tinyint(1) NOT NULL,
  `features__read_only` tinyint(1) NOT NULL,
  `features__enabled` tinyint(1) NOT NULL,
  `features__email` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_emailer` (
  `id` char(12) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `hosts` text DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `features__reply` tinyint(1) DEFAULT NULL,
  `dates__created` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_exceptions_errorlog` (
  `id` char(12) NOT NULL,
  `time` double NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` text NOT NULL,
  `app` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` longtext DEFAULT NULL,
  `trace_full` longtext DEFAULT NULL,
  `objects` longtext DEFAULT NULL,
  `queries` longtext DEFAULT NULL,
  `params` longtext DEFAULT NULL,
  `log` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `code` (`code`(191)),
  KEY `app` (`app`(191)),
  KEY `action` (`action`(191)),
  KEY `addr` (`addr`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_actionlog` (
  `id` char(20) NOT NULL,
  `request` char(20) NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `applog` varchar(64) DEFAULT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `request` (`request`),
  KEY `applog` (`applog`),
  KEY `app_action` (`app`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_actionmetrics` (
  `id` char(20) NOT NULL,
  `request` char(20) NOT NULL,
  `actionlog` char(20) NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `dates__created` double NOT NULL,
  `stats__db_reads` int(11) NOT NULL,
  `stats__db_read_time` double NOT NULL,
  `stats__db_writes` int(11) NOT NULL,
  `stats__db_write_time` double NOT NULL,
  `stats__code_time` double NOT NULL,
  `stats__total_time` double NOT NULL,
  `stats__queries` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_action` (`app`,`action`),
  KEY `request` (`request`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_commitmetrics` (
  `id` char(20) NOT NULL,
  `request` char(20) NOT NULL,
  `dates__created` double NOT NULL,
  `stats__db_reads` int(11) NOT NULL,
  `stats__db_read_time` double NOT NULL,
  `stats__db_writes` int(11) NOT NULL,
  `stats__db_write_time` double NOT NULL,
  `stats__code_time` double NOT NULL,
  `stats__total_time` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request` (`request`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_requestlog` (
  `id` char(20) NOT NULL,
  `actions` smallint(6) NOT NULL DEFAULT 0,
  `time` double NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` text NOT NULL,
  `errcode` smallint(6) DEFAULT NULL,
  `errtext` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_requestmetrics` (
  `id` char(20) NOT NULL,
  `actions` smallint(6) NOT NULL DEFAULT 0,
  `commits` smallint(6) NOT NULL DEFAULT 0,
  `requestlog` char(20) DEFAULT NULL,
  `dates__created` double NOT NULL,
  `peak_memory` int(11) NOT NULL,
  `nincludes` smallint(6) NOT NULL,
  `nobjects` int(11) NOT NULL,
  `construct__db_reads` int(11) NOT NULL,
  `construct__db_read_time` double NOT NULL,
  `construct__db_writes` int(11) NOT NULL,
  `construct__db_write_time` double NOT NULL,
  `construct__code_time` double NOT NULL,
  `construct__total_time` double NOT NULL,
  `construct__queries` text DEFAULT NULL,
  `total__db_reads` int(11) NOT NULL,
  `total__db_read_time` double NOT NULL,
  `total__db_writes` int(11) NOT NULL,
  `total__db_write_time` double NOT NULL,
  `total__code_time` double NOT NULL,
  `total__total_time` double NOT NULL,
  `gcstats` text DEFAULT NULL,
  `rusage` text DEFAULT NULL,
  `includes` longtext DEFAULT NULL,
  `objects` longtext DEFAULT NULL,
  `queries` longtext DEFAULT NULL,
  `debuglog` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

