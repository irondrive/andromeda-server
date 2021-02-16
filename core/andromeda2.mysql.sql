
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
CREATE TABLE `a2_objects_core_config` (
  `id` char(12) NOT NULL,
  `datadir` text DEFAULT NULL,
  `apps` text NOT NULL,
  `dates__created` double NOT NULL,
  `features__debug` tinyint(2) NOT NULL,
  `features__debug_http` tinyint(1) NOT NULL,
  `features__debug_dblog` tinyint(1) NOT NULL,
  `features__debug_filelog` tinyint(1) NOT NULL,
  `features__read_only` tinyint(2) NOT NULL,
  `features__enabled` tinyint(1) NOT NULL,
  `features__email` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2_objects_core_emailer` (
  `id` char(12) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `hosts` text DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `features__reply` tinyint(1) DEFAULT NULL,
  `dates__created` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2_objects_core_exceptions_errorlogentry` (
  `id` char(12) NOT NULL,
  `time` double NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` text NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` text DEFAULT NULL,
  `trace_full` text DEFAULT NULL,
  `objects` text DEFAULT NULL,
  `queries` text DEFAULT NULL,
  `params` text DEFAULT NULL,
  `log` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `time` (`time`),
  KEY `code` (`code`(191)),
  KEY `app` (`app`(191)),
  KEY `action` (`action`(191)),
  KEY `addr` (`addr`(191))
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

