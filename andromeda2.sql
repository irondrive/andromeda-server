SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `a2_objects_core_config` (
  `id` varchar(16) NOT NULL,
  `datadir` varchar(255) DEFAULT NULL,
  `apps` text NOT NULL,
  `dates__created` bigint(20) NOT NULL,
  `features__debug_log` tinyint(1) DEFAULT NULL,
  `features__debug_http` tinyint(1) DEFAULT NULL,
  `features__debug_file` tinyint(1) DEFAULT NULL,
  `features__read_only` tinyint(1) DEFAULT NULL,
  `features__email` tinyint(1) DEFAULT NULL,
  `features__enabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_core_exceptions_errorlogentry` (
  `id` varchar(16) NOT NULL,
  `time` bigint(20) NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` varchar(255) NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` text,
  `trace_full` text,
  `objects` text,
  `queries` text,
  `params` text,
  `log` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `time` (`time`),
  KEY `code` (`code`),
  KEY `app` (`app`),
  KEY `action` (`action`),
  KEY `addr` (`addr`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `a2_objects_core_fullemailer` (
  `id` varchar(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text,
  `secure` tinyint(1) NOT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
