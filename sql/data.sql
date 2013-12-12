-- MySQL dump 10.13  Distrib 5.5.33, for Linux (x86_64)
--
-- Host: localhost    Database: scalr
-- ------------------------------------------------------
-- Server version	5.5.33-31.1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `acl_roles`
--

LOCK TABLES `acl_roles` WRITE;
/*!40000 ALTER TABLE `acl_roles` DISABLE KEYS */;
INSERT INTO `acl_roles` VALUES (10,'Full access'),(1,'No access');
/*!40000 ALTER TABLE `acl_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `acl_role_resources`
--

LOCK TABLES `acl_role_resources` WRITE;
/*!40000 ALTER TABLE `acl_role_resources` DISABLE KEYS */;
INSERT INTO `acl_role_resources` VALUES (10,256,1),(10,257,1),(10,258,1),(10,259,1),(10,260,1),(10,261,1),(10,262,1),(10,272,1),(10,273,1),(10,274,1),(10,288,1),(10,289,1),(10,290,1),(10,291,1),(10,292,1),(10,293,1),(10,294,1),(10,304,1),(10,305,1),(10,306,1),(10,320,1),(10,321,1),(10,322,1),(10,336,1),(10,337,1),(10,338,1),(10,339,1),(10,352,1),(10,353,1),(10,354,1),(10,355,1),(10,356,1),(10,357,1),(10,368,1),(10,369,1),(10,370,1),(10,384,1),(10,385,1),(10,386,1),(10,400,1),(10,514,1),(10,528,1),(10,529,1),(10,530,1);
/*!40000 ALTER TABLE `acl_role_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `acl_role_resource_permissions`
--

LOCK TABLES `acl_role_resource_permissions` WRITE;
/*!40000 ALTER TABLE `acl_role_resource_permissions` DISABLE KEYS */;
INSERT INTO `acl_role_resource_permissions` VALUES (10,256,'clone',1),(10,256,'launch',1),(10,256,'manage',1),(10,256,'not-owned-farms',1),(10,256,'terminate',1),(10,260,'bundletasks',1),(10,260,'clone',1),(10,260,'create',1),(10,260,'manage',1),(10,262,'execute',1),(10,262,'fork',1),(10,262,'manage',1),(10,368,'remove',1),(10,369,'phpmyadmin',1);
/*!40000 ALTER TABLE `acl_role_resource_permissions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-12-12  8:42:35

INSERT INTO `role_categories` (`id`, `env_id`, `name`) VALUES
(1, 0, 'Base'),
(2, 0, 'Databases'),
(3, 0, 'Application Servers'),
(4, 0, 'Load Balancers'),
(5, 0, 'Message Queues'),
(6, 0, 'Caches'),
(7, 0, 'Cloudfoundry'),
(8, 0, 'Mixed');

INSERT INTO `account_users` (`id`, `account_id`, `status`, `email`, `fullname`, `password`, `dtcreated`, `dtlastlogin`, `type`, `comments`) VALUES
(1, 0, 'Active', 'admin', 'Scalr Admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', '2011-09-09 10:49:07', '2011-12-06 02:28:16', 'ScalrAdmin', NULL);

INSERT INTO `default_records` (`id`, `clientid`, `type`, `ttl`, `priority`, `value`, `name`) VALUES
(1, 0, 'CNAME', 14400, 0, '%hostname%', 'www');

INSERT INTO `ipaccess` (`id`, `ipaddress`, `comment`) VALUES
(1, '*.*.*.*', 'Disable IP whitelist');

INSERT INTO `scaling_metrics` (`id`, `client_id`, `env_id`, `name`, `file_path`, `retrieve_method`, `calc_function`, `algorithm`, `alias`) VALUES
(1, 0, 0, 'LoadAverages', NULL, NULL, 'avg', 'Sensor', 'la'),
(2, 0, 0, 'FreeRam', NULL, NULL, 'avg', 'Sensor', 'ram'),
(3, 0, 0, 'URLResponseTime', NULL, NULL, NULL, 'Sensor', 'http'),
(4, 0, 0, 'SQSQueueSize', NULL, NULL, NULL, 'Sensor', 'sqs'),
(5, 0, 0, 'DateAndTime', NULL, NULL, NULL, 'DateTime', 'time'),
(6, 0, 0, 'BandWidth', NULL, NULL, NULL, 'Sensor', 'bw');
