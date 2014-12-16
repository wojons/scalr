-- MySQL dump 10.13  Distrib 5.5.33, for Linux (x86_64)
--
-- Host: localhost    Database: analysis
-- ------------------------------------------------------
-- Server version	5.5.33-31.1-log
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_tag_values`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_tag_values` (
  `account_id` int(11) NOT NULL COMMENT 'The ID of the account',
  `tag_id` int(11) unsigned NOT NULL COMMENT 'The ID of the tag',
  `value_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'The unique identifier of the value for the associated tag',
  `value_name` varchar(255) DEFAULT NULL COMMENT 'Display name for the tag value may be omitted.',
  PRIMARY KEY (`account_id`,`tag_id`,`value_id`),
  KEY `idx_tag` (`tag_id`,`value_id`),
  CONSTRAINT `fk_account_tag_values_tags` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Account level tag values';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_usage_d`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_usage_d` (
  `account_id` int(11) NOT NULL COMMENT 'scalr.clients.id ref',
  `farm_role_id` int(11) NOT NULL COMMENT 'scalr.farm_roles.id ref',
  `instance_type` varchar(45) NOT NULL COMMENT 'Type of the instance',
  `cc_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.ccs.cc_id ref',
  `project_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.projects.project_id ref',
  `date` date NOT NULL COMMENT 'UTC Date',
  `platform` varchar(20) NOT NULL COMMENT 'cloud platform',
  `cloud_location` varchar(255) NOT NULL COMMENT 'cloud location',
  `env_id` int(11) NOT NULL COMMENT 'scalr.client_account_environments.id ref',
  `farm_id` int(11) NOT NULL COMMENT 'scalr.farms.id ref',
  `role_id` int(11) NOT NULL COMMENT 'scalr.roles.id ref',
  `cost` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'total usage',
  `min_instances` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'min instances count',
  `max_instances` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'max instances count',
  `instance_hours` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'total instance hours',
  `working_hours` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'hours when farm is running',
  PRIMARY KEY (`account_id`,`farm_role_id`,`instance_type`,`cc_id`,`project_id`,`date`),
  KEY `idx_farm_role_id` (`farm_role_id`),
  KEY `idx_instance_type` (`instance_type`),
  KEY `idx_date` (`date`),
  KEY `idx_farm_id` (`farm_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_cloud_location` (`cloud_location`),
  KEY `idx_platform` (`platform`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Farm daily usage'
/*!50100 PARTITION BY HASH (account_id)
PARTITIONS 100 */;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `managed`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `managed` (
  `sid` binary(16) NOT NULL COMMENT 'The identifier of the poll session',
  `server_id` binary(16) NOT NULL COMMENT 'scalr.servers.server_id ref',
  `instance_type` varchar(45) NOT NULL COMMENT 'The type of the instance',
  `os` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 - linux, 1 - windows',
  PRIMARY KEY (`sid`,`server_id`),
  KEY `idx_server_id` (`server_id`),
  KEY `idx_instance_type` (`instance_type`),
  CONSTRAINT `fk_managed_poller_sessions` FOREIGN KEY (`sid`) REFERENCES `poller_sessions` (`sid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The presence of the managed servers on cloud';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nm_subjects_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nm_subjects_h` (
  `subject_id` binary(16) NOT NULL COMMENT 'ID of the subject',
  `env_id` int(11) NOT NULL COMMENT 'client_environments.id reference',
  `cc_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of cost centre',
  `account_id` int(11) NOT NULL COMMENT 'clients.id reference',
  PRIMARY KEY (`subject_id`,`env_id`),
  UNIQUE KEY `idx_unique` (`env_id`,`cc_id`),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Subjects to associate with usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nm_usage_d`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nm_usage_d` (
  `date` date NOT NULL COMMENT 'UTC Date',
  `platform` varchar(20) NOT NULL COMMENT 'Cloud platform',
  `cc_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of Cost centre',
  `env_id` int(11) NOT NULL COMMENT 'ID of Environment',
  `cost` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'Daily usage',
  PRIMARY KEY (`date`,`platform`,`cc_id`,`env_id`),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Not managed daily usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nm_usage_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nm_usage_h` (
  `usage_id` binary(16) NOT NULL COMMENT 'ID of the usage',
  `dtime` datetime NOT NULL COMMENT 'Time in Y-m-d H:00:00',
  `platform` varchar(20) NOT NULL COMMENT 'The type of the cloud',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` varchar(255) NOT NULL COMMENT 'Cloud location',
  `instance_type` varchar(45) NOT NULL COMMENT 'The type of the instance',
  `os` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 - linux, 1 - windows',
  `num` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of the same instances',
  `cost` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'The cost of the usage',
  PRIMARY KEY (`usage_id`),
  KEY `idx_platform` (`platform`,`url`,`cloud_location`),
  KEY `idx_dtime` (`dtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Not managed servers hourly usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nm_usage_servers_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nm_usage_servers_h` (
  `usage_id` binary(16) NOT NULL COMMENT 'nm_usage_h.usage_id ref',
  `instance_id` varchar(36) NOT NULL COMMENT 'Instance ID',
  PRIMARY KEY (`usage_id`,`instance_id`),
  KEY `idx_instance_id` (`instance_id`),
  CONSTRAINT `fk_22300db65385` FOREIGN KEY (`usage_id`) REFERENCES `nm_usage_h` (`usage_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Instances associated with the usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nm_usage_subjects_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nm_usage_subjects_h` (
  `usage_id` binary(16) NOT NULL COMMENT 'ID of the usage',
  `subject_id` binary(16) NOT NULL COMMENT 'ID of the subject',
  PRIMARY KEY (`usage_id`,`subject_id`),
  KEY `idx_subject_id` (`subject_id`),
  CONSTRAINT `fk_nmusagesubjectsh_nmsubjectsh` FOREIGN KEY (`subject_id`) REFERENCES `nm_subjects_h` (`subject_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_nmusagesubjectsh_nmusageh` FOREIGN KEY (`usage_id`) REFERENCES `nm_usage_h` (`usage_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Subjects - Usages';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `uuid` binary(16) NOT NULL COMMENT 'unique identifier',
  `subject_type` tinyint(4) NOT NULL COMMENT '1- CC, 2 - Project',
  `subject_id` binary(16) DEFAULT NULL,
  `notification_type` tinyint(4) NOT NULL COMMENT 'Type of the notification',
  `threshold` decimal(12,2) NOT NULL,
  `recipient_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1 - Leads 2 - Emails',
  `emails` text COMMENT 'Comma separated recipients',
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_subject_type` (`subject_type`),
  KEY `idx_recipient_type` (`recipient_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Notifications';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notmanaged`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notmanaged` (
  `sid` binary(16) NOT NULL COMMENT 'The ID of the poller session',
  `instance_id` varchar(36) NOT NULL COMMENT 'The ID of the instance which is not managed by Scalr',
  `instance_type` varchar(45) NOT NULL COMMENT 'The type of the instance',
  `os` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`,`instance_id`),
  KEY `idx_instance_id` (`instance_id`),
  KEY `idx_instance_type` (`instance_type`),
  CONSTRAINT `fk_notmanaged_poller_sessions` FOREIGN KEY (`sid`) REFERENCES `poller_sessions` (`sid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The presence of the not managed nodes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poller_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poller_sessions` (
  `sid` binary(16) NOT NULL COMMENT 'The unique identifier of the poll session',
  `account_id` int(11) NOT NULL COMMENT 'clients.id reference',
  `env_id` int(11) NOT NULL COMMENT 'client_environments.id reference',
  `dtime` datetime NOT NULL COMMENT 'The timestamp retrieved from the response',
  `platform` varchar(20) NOT NULL COMMENT 'The ID of the Platform',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` varchar(255) DEFAULT NULL COMMENT 'Cloud location ID',
  `cloud_account` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `idx_dtime` (`dtime`),
  KEY `idx_platform` (`platform`,`url`,`cloud_location`),
  KEY `idx_cloud_id` (`account_id`),
  KEY `idx_account` (`account_id`,`env_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Poller sessions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `price_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_history` (
  `price_id` binary(16) NOT NULL COMMENT 'The ID of the price',
  `platform` varchar(20) NOT NULL COMMENT 'Platform name',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` varchar(255) NOT NULL DEFAULT '' COMMENT 'The cloud location',
  `account_id` int(11) NOT NULL DEFAULT '0' COMMENT 'The ID of the account',
  `applied` date NOT NULL COMMENT 'The date after which new prices are applied',
  `deny_override` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'It is used only with account_id = 0',
  PRIMARY KEY (`price_id`),
  UNIQUE KEY `idx_unique` (`platform`,`url`,`cloud_location`,`applied`,`account_id`),
  KEY `idx_applied` (`applied`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The price changes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prices` (
  `price_id` binary(16) NOT NULL COMMENT 'The ID of the revision',
  `instance_type` varchar(45) NOT NULL COMMENT 'The type of the instance',
  `os` tinyint(1) NOT NULL COMMENT '0 - linux, 1 - windows',
  `name` varchar(45) NOT NULL DEFAULT '' COMMENT 'The display name',
  `cost` decimal(9,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT 'The hourly cost of usage (USD)',
  PRIMARY KEY (`price_id`,`instance_type`,`os`),
  KEY `idx_instance_type` (`instance_type`,`os`),
  KEY `idx_name` (`name`(3)) USING BTREE,
  CONSTRAINT `fk_prices_price_revisions` FOREIGN KEY (`price_id`) REFERENCES `price_history` (`price_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The Cloud prices for specific revision';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quarterly_budget`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_budget` (
  `year` smallint(6) NOT NULL COMMENT 'The year [2014]',
  `subject_type` tinyint(4) NOT NULL COMMENT '1 - CC, 2 - Project',
  `subject_id` binary(16) NOT NULL COMMENT 'ID of the CC or Project',
  `quarter` tinyint(4) NOT NULL COMMENT 'Quarter [1-4]',
  `budget` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Budget dollar amount',
  `final` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Final spent',
  `spentondate` datetime DEFAULT NULL COMMENT 'Spent on date',
  `cumulativespend` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'Cumulative spend',
  PRIMARY KEY (`year`,`subject_type`,`subject_id`,`quarter`),
  KEY `idx_year` (`year`,`quarter`),
  KEY `idx_quarter` (`quarter`),
  KEY `idx_subject_type` (`subject_type`,`subject_id`),
  KEY `idx_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Quarterly budget';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_payloads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_payloads` (
  `uuid` binary(16) NOT NULL COMMENT 'UUID',
  `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)',
  `secret` binary(20) NOT NULL COMMENT 'Secret hash (SHA1)',
  `payload` mediumtext NOT NULL COMMENT 'Payload',
  PRIMARY KEY (`uuid`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Report payloads';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `uuid` binary(16) NOT NULL COMMENT 'unique identifier',
  `subject_type` tinyint(4) DEFAULT NULL COMMENT '1- CC, 2 - Project, NULL - Summary',
  `subject_id` binary(16) DEFAULT NULL,
  `period` tinyint(4) NOT NULL COMMENT 'Period',
  `emails` text NOT NULL COMMENT 'Comma separated recipients',
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `idx_subject_type` (`subject_type`),
  KEY `idx_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Reports';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` varchar(64) NOT NULL COMMENT 'setting ID',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='system settings';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `tag_id` int(11) unsigned NOT NULL COMMENT 'The unique identifier of the tag',
  `name` varchar(127) NOT NULL COMMENT 'The display name of the tag',
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Tags';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timeline_event_ccs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_event_ccs` (
  `event_id` binary(16) NOT NULL COMMENT 'timeline_events.uuid reference',
  `cc_id` binary(16) NOT NULL COMMENT 'scalr.ccs.cc_id reference',
  PRIMARY KEY (`event_id`,`cc_id`),
  KEY `idx_cc_id` (`cc_id`),
  CONSTRAINT `fk_2af56955167b` FOREIGN KEY (`event_id`) REFERENCES `timeline_events` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timeline_event_projects`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_event_projects` (
  `event_id` binary(16) NOT NULL COMMENT 'timeline_events.uuid ref',
  `project_id` binary(16) NOT NULL COMMENT 'scalr.projects.project_id ref',
  PRIMARY KEY (`event_id`,`project_id`),
  KEY `idx_project_id` (`project_id`),
  CONSTRAINT `fk_e0325ab740c9` FOREIGN KEY (`event_id`) REFERENCES `timeline_events` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timeline_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_events` (
  `uuid` binary(16) NOT NULL COMMENT 'UUID',
  `event_type` tinyint(3) unsigned NOT NULL COMMENT 'The type of the event',
  `dtime` datetime NOT NULL COMMENT 'The time of the event',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who triggered this event',
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `description` text NOT NULL COMMENT 'Description',
  PRIMARY KEY (`uuid`),
  KEY `idx_dtime` (`dtime`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_env_id` (`env_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Timeline events';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `upgrade_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upgrade_messages` (
  `uuid` binary(16) NOT NULL COMMENT 'upgrades.uuid reference',
  `created` datetime NOT NULL COMMENT 'Creation timestamp',
  `message` text COMMENT 'Error messages',
  KEY `idx_uuid` (`uuid`),
  CONSTRAINT `upgrade_messages_ibfk_1` FOREIGN KEY (`uuid`) REFERENCES `upgrades` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `upgrades`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upgrades` (
  `uuid` binary(16) NOT NULL COMMENT 'Unique identifier of update',
  `released` datetime NOT NULL COMMENT 'The time when upgrade script is issued',
  `appears` datetime NOT NULL COMMENT 'The time when upgrade does appear',
  `applied` datetime DEFAULT NULL COMMENT 'The time when update is successfully applied',
  `status` tinyint(4) NOT NULL COMMENT 'Upgrade status',
  `hash` varbinary(20) DEFAULT NULL COMMENT 'SHA1 hash of the upgrade file',
  PRIMARY KEY (`uuid`),
  KEY `idx_status` (`status`),
  KEY `idx_appears` (`appears`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usage_d`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usage_d` (
  `date` date NOT NULL COMMENT 'UTC Date',
  `platform` varchar(20) NOT NULL COMMENT 'Cloud platform',
  `cc_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of the CC',
  `project_id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of the project',
  `farm_id` int(11) NOT NULL DEFAULT '0' COMMENT 'ID of the farm',
  `cost` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'daily usage',
  `env_id` int(11) NOT NULL DEFAULT '0' COMMENT 'ID of the environment',
  PRIMARY KEY (`date`,`farm_id`,`platform`,`cc_id`,`project_id`),
  KEY `idx_farm_id` (`farm_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_platform` (`platform`),
  KEY `idx_env_id` (`env_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Daily usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usage_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usage_h` (
  `usage_id` binary(16) NOT NULL COMMENT 'The unique identifier for the usage record',
  `account_id` int(11) NOT NULL COMMENT 'clients.id reference',
  `dtime` datetime NOT NULL COMMENT 'Time in Y-m-d H:00:00',
  `platform` varchar(20) NOT NULL COMMENT 'The cloud type',
  `url` varchar(255) NOT NULL DEFAULT '',
  `cloud_location` varchar(255) NOT NULL COMMENT 'The cloud location',
  `instance_type` varchar(45) NOT NULL COMMENT 'The type of the instance',
  `os` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 - linux, 1 - windows',
  `cc_id` binary(16) DEFAULT NULL COMMENT 'ID of cost centre',
  `project_id` binary(16) DEFAULT NULL COMMENT 'ID of the project',
  `env_id` int(11) DEFAULT NULL COMMENT 'client_environments.id reference',
  `farm_id` int(11) DEFAULT NULL COMMENT 'farms.id reference',
  `farm_role_id` int(11) DEFAULT NULL COMMENT 'farm_roles.id reference',
  `role_id` int(11) DEFAULT NULL COMMENT 'scalr.roles.id ref',
  `num` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'The number of the same instances',
  `cost` decimal(12,6) NOT NULL DEFAULT '0.000000' COMMENT 'Cost of usage',
  PRIMARY KEY (`usage_id`),
  KEY `idx_platform` (`platform`,`url`,`cloud_location`),
  KEY `idx_instance_type` (`instance_type`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_farm_id` (`farm_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_farm_role_id` (`farm_role_id`),
  KEY `idx_find` (`account_id`,`dtime`),
  KEY `idx_dtime` (`dtime`),
  KEY `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Hourly usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usage_h_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usage_h_tags` (
  `usage_id` binary(16) NOT NULL,
  `tag_id` int(11) unsigned NOT NULL,
  `value_id` varchar(64) NOT NULL,
  PRIMARY KEY (`usage_id`,`tag_id`,`value_id`),
  KEY `idx_tag` (`tag_id`,`value_id`),
  CONSTRAINT `fk_usage_h_tags_account_tag_values` FOREIGN KEY (`tag_id`, `value_id`) REFERENCES `account_tag_values` (`tag_id`, `value_id`),
  CONSTRAINT `fk_usage_h_tags_usage_h` FOREIGN KEY (`usage_id`) REFERENCES `usage_h` (`usage_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Hourly usage tags';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usage_servers_h`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usage_servers_h` (
  `usage_id` binary(16) NOT NULL,
  `server_id` binary(16) NOT NULL COMMENT 'scalr.servers.server_id ref',
  `instance_id` varchar(36) NOT NULL COMMENT 'cloud server id',
  PRIMARY KEY (`usage_id`,`server_id`),
  KEY `idx_server_id` (`server_id`),
  KEY `idx_instance_id` (`instance_id`),
  CONSTRAINT `fk_26ff9423b1bc` FOREIGN KEY (`usage_id`) REFERENCES `usage_h` (`usage_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Servers associated with usage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'analysis'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-12-16  5:07:42
