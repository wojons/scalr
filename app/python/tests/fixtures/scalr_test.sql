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
-- Table structure for table `account_ccs`
--

DROP TABLE IF EXISTS `account_ccs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_ccs` (
  `account_id` int(11) NOT NULL COMMENT 'clients.id reference',
  `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  PRIMARY KEY (`account_id`,`cc_id`),
  KEY `idx_cc_id` (`cc_id`),
  CONSTRAINT `fk_bd44f317af5fc8ab` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_b6e70905def505d6` FOREIGN KEY (`cc_id`) REFERENCES `ccs` (`cc_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_limits`
--

DROP TABLE IF EXISTS `account_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `limit_name` varchar(45) DEFAULT NULL,
  `limit_value` int(11) DEFAULT NULL,
  `limit_type` enum('soft','hard') DEFAULT 'hard',
  `limit_type_value` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_account_limits_clients` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=79468 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_scripts`
--

DROP TABLE IF EXISTS `account_scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `event_name` varchar(50) DEFAULT NULL,
  `target` varchar(15) DEFAULT NULL,
  `script_id` int(11) DEFAULT NULL,
  `version` varchar(10) DEFAULT NULL,
  `timeout` int(5) DEFAULT NULL,
  `issync` tinyint(1) DEFAULT NULL,
  `params` text,
  `order_index` int(11) NOT NULL DEFAULT '0',
  `script_path` varchar(255) DEFAULT NULL,
  `run_as` varchar(15) DEFAULT NULL,
  `script_type` enum('local','scalr','chef') DEFAULT 'scalr',
  PRIMARY KEY (`id`),
  KEY `role_id` (`account_id`),
  KEY `script_id` (`script_id`),
  CONSTRAINT `account_scripts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_team_envs`
--

DROP TABLE IF EXISTS `account_team_envs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_team_envs` (
  `env_id` int(11) NOT NULL DEFAULT '0',
  `team_id` int(11) NOT NULL,
  PRIMARY KEY (`team_id`,`env_id`),
  KEY `fk_account_team_envs_account_teams1` (`team_id`),
  KEY `fk_account_team_envs_client_environments1` (`env_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_team_user_acls`
--

DROP TABLE IF EXISTS `account_team_user_acls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_team_user_acls` (
  `account_team_user_id` int(11) NOT NULL,
  `account_role_id` varchar(20) NOT NULL,
  PRIMARY KEY (`account_team_user_id`,`account_role_id`),
  KEY `fk_9888fac48291b3452f82_idx` (`account_team_user_id`),
  KEY `fk_d7ced79d114b481574f8_idx` (`account_role_id`),
  CONSTRAINT `fk_241cb20f77a84d2a9a95` FOREIGN KEY (`account_team_user_id`) REFERENCES `account_team_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_4c65ed7fea80e0f3792d` FOREIGN KEY (`account_role_id`) REFERENCES `acl_account_roles` (`account_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_team_users`
--

DROP TABLE IF EXISTS `account_team_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_team_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permissions` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique` (`team_id`,`user_id`),
  KEY `fk_account_team_users_account_teams1` (`team_id`),
  KEY `fk_account_team_users_account_users1` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4967 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_teams`
--

DROP TABLE IF EXISTS `account_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `name` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
  `description` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `account_role_id` varchar(20) DEFAULT NULL COMMENT 'Default ACL role for team users',
  PRIMARY KEY (`id`),
  KEY `fk_account_teams_clients1` (`account_id`),
  KEY `idx_account_role_id` (`account_role_id`),
  CONSTRAINT `FK_315e023acf4b65b9203` FOREIGN KEY (`account_role_id`) REFERENCES `acl_account_roles` (`account_role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=857 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_apikeys`
--

DROP TABLE IF EXISTS `account_user_apikeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_apikeys` (
  `key_id` char(20) NOT NULL COMMENT 'The unique identifier of the key',
  `name` varchar(255) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL COMMENT 'scalr.account_users.id ref',
  `secret_key` varchar(255) NOT NULL COMMENT 'Encrypted secret key',
  `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - active, 0 - inactive',
  PRIMARY KEY (`key_id`),
  KEY `idx_user_keys` (`user_id`,`active`),
  KEY `idx_active` (`active`),
  CONSTRAINT `fk_0a036c6a9223` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='API keys';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_dashboard`
--

DROP TABLE IF EXISTS `account_user_dashboard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_dashboard` (
  `user_id` int(11) NOT NULL,
  `env_id` int(11) DEFAULT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `user_id` (`user_id`,`env_id`),
  KEY `env_id` (`env_id`),
  CONSTRAINT `account_user_dashboard_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `account_user_dashboard_ibfk_2` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_dashboard_backup`
--

DROP TABLE IF EXISTS `account_user_dashboard_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_dashboard_backup` (
  `user_id` int(11) NOT NULL,
  `env_id` int(11) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_settings`
--

DROP TABLE IF EXISTS `account_user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_settings` (
  `user_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`name`),
  KEY `name` (`name`),
  CONSTRAINT `fk_account_users_id_user_settings` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_vars`
--

DROP TABLE IF EXISTS `account_user_vars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_vars` (
  `user_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`user_id`,`name`),
  KEY `name` (`name`),
  CONSTRAINT `account_user_vars_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_users`
--

DROP TABLE IF EXISTS `account_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `password` varchar(64) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `dtlastlogin` datetime DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `comments` text,
  `loginattempts` int(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_account_users_clients1` (`account_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=14642 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_variables`
--

DROP TABLE IF EXISTS `account_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_variables` (
  `account_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('','env','role','farm','farmrole') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`account_id`),
  KEY `fk_account_variables_clients_id` (`account_id`),
  CONSTRAINT `fk_account_variables_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_account_role_resource_permissions`
--

DROP TABLE IF EXISTS `acl_account_role_resource_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_account_role_resource_permissions` (
  `account_role_id` varchar(20) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `perm_id` varchar(64) NOT NULL,
  `granted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`account_role_id`,`resource_id`,`perm_id`),
  KEY `fk_f123f4826415e04bfa12_idx` (`account_role_id`,`resource_id`),
  CONSTRAINT `fk_a98e2a51b27453360594` FOREIGN KEY (`account_role_id`, `resource_id`) REFERENCES `acl_account_role_resources` (`account_role_id`, `resource_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_account_role_resources`
--

DROP TABLE IF EXISTS `acl_account_role_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_account_role_resources` (
  `account_role_id` varchar(20) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `granted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`account_role_id`,`resource_id`),
  KEY `fk_e81073f7212f04f8db61_idx` (`account_role_id`),
  CONSTRAINT `fk_2a0b54035678ca90222b` FOREIGN KEY (`account_role_id`) REFERENCES `acl_account_roles` (`account_role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_account_roles`
--

DROP TABLE IF EXISTS `acl_account_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_account_roles` (
  `account_role_id` varchar(20) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `color` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `is_automatic` int(1) NOT NULL DEFAULT '0' COMMENT 'Whether the role is created automatically.',
  PRIMARY KEY (`account_role_id`),
  KEY `base_role_id` (`role_id`),
  KEY `account_id` (`account_id`),
  KEY `idx_accountid_roleid` (`account_id`,`role_id`,`is_automatic`),
  CONSTRAINT `fk_acl_account_roles_acl_roles1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`),
  CONSTRAINT `fk_acl_account_roles_clients1` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Account level roles override global roles';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_role_resource_permissions`
--

DROP TABLE IF EXISTS `acl_role_resource_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_role_resource_permissions` (
  `role_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `perm_id` varchar(64) NOT NULL,
  `granted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`role_id`,`resource_id`,`perm_id`),
  KEY `fk_b0bc67f70c85735d0da2_idx` (`role_id`,`resource_id`),
  CONSTRAINT `fk_8a0eafb8ae6ea4f2d276` FOREIGN KEY (`role_id`, `resource_id`) REFERENCES `acl_role_resources` (`role_id`, `resource_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Grants privileges to unique permissions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_role_resources`
--

DROP TABLE IF EXISTS `acl_role_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_role_resources` (
  `role_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `granted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`resource_id`,`role_id`),
  KEY `fk_aa1q8565e63a6f2d299_idx` (`role_id`),
  CONSTRAINT `fk_3a1qafb85e63a6f2d276` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Grants access permissions to resources.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acl_roles`
--

DROP TABLE IF EXISTS `acl_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_roles` (
  `role_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Global ACL roles';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apache_vhosts`
--

DROP TABLE IF EXISTS `apache_vhosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `apache_vhosts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `is_ssl_enabled` tinyint(1) DEFAULT '0',
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `ssl_cert` text,
  `ssl_key` text,
  `ca_cert` text,
  `last_modified` datetime DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `httpd_conf` text,
  `httpd_conf_vars` text,
  `advanced_mode` tinyint(1) DEFAULT '0',
  `httpd_conf_ssl` text,
  `ssl_cert_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_name` (`name`,`env_id`,`farm_id`,`farm_roleid`),
  KEY `clientid` (`client_id`),
  KEY `env_id` (`env_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14405 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_log`
--

DROP TABLE IF EXISTS `api_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(36) DEFAULT NULL,
  `dtadded` int(11) DEFAULT NULL,
  `action` varchar(25) DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  `request` text,
  `response` text,
  `clientid` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `client_index` (`clientid`)
) ENGINE=MyISAM AUTO_INCREMENT=321244 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditlog`
--

DROP TABLE IF EXISTS `auditlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditlog` (
  `id` varchar(36) NOT NULL,
  `sessionid` varchar(36) NOT NULL,
  `accountid` int(11) DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `envid` int(11) DEFAULT NULL,
  `ip` int(10) unsigned DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` varchar(255) DEFAULT NULL,
  `datatype` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sessionid` (`sessionid`),
  KEY `accountid` (`accountid`),
  KEY `userid` (`userid`),
  KEY `envid` (`envid`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditlog_data`
--

DROP TABLE IF EXISTS `auditlog_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditlog_data` (
  `logid` varchar(36) NOT NULL,
  `key` varchar(255) NOT NULL,
  `old_value` text,
  `new_value` text,
  PRIMARY KEY (`logid`,`key`),
  KEY `key` (`key`),
  KEY `idx_old_value` (`old_value`(8)),
  KEY `idx_new_value` (`new_value`(8)),
  CONSTRAINT `FK_auditlog_data_logid` FOREIGN KEY (`logid`) REFERENCES `auditlog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditlog_tags`
--

DROP TABLE IF EXISTS `auditlog_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditlog_tags` (
  `logid` varchar(36) NOT NULL,
  `tag` varchar(36) NOT NULL,
  PRIMARY KEY (`logid`,`tag`),
  KEY `tag` (`tag`),
  CONSTRAINT `FK_auditlog_tags_logid` FOREIGN KEY (`logid`) REFERENCES `auditlog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `autosnap_settings`
--

DROP TABLE IF EXISTS `autosnap_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `autosnap_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `period` int(5) DEFAULT NULL,
  `dtlastsnapshot` datetime DEFAULT NULL,
  `rotate` int(11) DEFAULT NULL,
  `last_snapshotid` varchar(50) DEFAULT NULL,
  `region` varchar(50) DEFAULT 'us-east-1',
  `objectid` varchar(20) DEFAULT NULL,
  `object_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `env_id` (`env_id`),
  KEY `idx_dtlastsnapshot` (`dtlastsnapshot`),
  CONSTRAINT `autosnap_settings_ibfk_1` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1096 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_packages`
--

DROP TABLE IF EXISTS `billing_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `cost` float(7,2) DEFAULT NULL,
  `group` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bundle_task_log`
--

DROP TABLE IF EXISTS `bundle_task_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bundle_task_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_task_id` int(11) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`bundle_task_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3082228 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bundle_tasks`
--

DROP TABLE IF EXISTS `bundle_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bundle_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prototype_role_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `replace_type` varchar(20) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `rolename` varchar(50) DEFAULT NULL,
  `failure_reason` text,
  `bundle_type` varchar(20) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `dtstarted` datetime DEFAULT NULL,
  `dtfinished` datetime DEFAULT NULL,
  `remove_proto_role` tinyint(1) DEFAULT '0',
  `snapshot_id` varchar(255) DEFAULT NULL,
  `platform_status` varchar(50) DEFAULT NULL,
  `description` text,
  `role_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  `meta_data` text,
  `os_family` varchar(20) DEFAULT NULL,
  `os_name` varchar(255) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_by_email` varchar(100) DEFAULT NULL,
  `generation` tinyint(1) DEFAULT '1',
  `object` varchar(20) DEFAULT 'role',
  `os_id` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `clientid` (`client_id`),
  KEY `env_id` (`env_id`),
  KEY `server_id` (`server_id`),
  KEY `status` (`status`),
  CONSTRAINT `bundle_tasks_ibfk_1` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=74506 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cc_properties`
--

DROP TABLE IF EXISTS `cc_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cc_properties` (
  `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` mediumtext COMMENT 'The value',
  PRIMARY KEY (`cc_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CC properties';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ccs`
--

DROP TABLE IF EXISTS `ccs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ccs` (
  `cc_id` binary(16) NOT NULL COMMENT 'ID of the cost centre',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator',
  `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator',
  `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)',
  `archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether it is archived',
  PRIMARY KEY (`cc_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_name` (`name`(3)),
  KEY `idx_created_by_id` (`created_by_id`),
  CONSTRAINT `fk_ccs_clients` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Cost Centers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_environment_properties`
--

DROP TABLE IF EXISTS `client_environment_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_environment_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `group` varchar(20) NOT NULL,
  `cloud` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `env_id_2` (`env_id`,`name`,`group`),
  KEY `env_id` (`env_id`),
  KEY `name_value` (`name`(100),`value`(100)),
  KEY `name` (`name`(100))
) ENGINE=InnoDB AUTO_INCREMENT=136901096 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_environment_variables`
--

DROP TABLE IF EXISTS `client_environment_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_environment_variables` (
  `env_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('','role','farm','farmrole') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`env_id`),
  KEY `fk_client_env_variables_client_env_id` (`env_id`),
  CONSTRAINT `fk_client_env_variables_client_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_environments`
--

DROP TABLE IF EXISTS `client_environments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_environments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `client_id` int(11) NOT NULL,
  `dt_added` datetime NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14833 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_settings`
--

DROP TABLE IF EXISTS `client_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_settings` (
  `clientid` int(11) NOT NULL DEFAULT '0',
  `key` varchar(255) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`clientid`,`key`),
  KEY `settingskey` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `isbilled` tinyint(1) DEFAULT '0',
  `dtdue` datetime DEFAULT NULL,
  `isactive` tinyint(1) DEFAULT '0',
  `fullname` varchar(60) DEFAULT NULL,
  `org` varchar(60) DEFAULT NULL,
  `country` varchar(60) DEFAULT NULL,
  `state` varchar(60) DEFAULT NULL,
  `city` varchar(60) DEFAULT NULL,
  `zipcode` varchar(60) DEFAULT NULL,
  `address1` varchar(60) DEFAULT NULL,
  `address2` varchar(60) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `fax` varchar(60) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `iswelcomemailsent` tinyint(1) DEFAULT '0',
  `login_attempts` int(5) DEFAULT '0',
  `dtlastloginattempt` datetime DEFAULT NULL,
  `comments` text,
  `priority` int(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_dtadded` (`dtadded`),
  KEY `idx_isactive` (`isactive`),
  KEY `idx_dtdue` (`dtdue`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=13873 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cloud_instance_types`
--

DROP TABLE IF EXISTS `cloud_instance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloud_instance_types` (
  `cloud_location_id` binary(16) NOT NULL COMMENT 'cloud_locations.cloud_location_id ref',
  `instance_type_id` varchar(45) NOT NULL COMMENT 'ID of the instance type',
  `name` varchar(255) NOT NULL COMMENT 'Display name',
  `ram` varchar(255) NOT NULL COMMENT 'Memory info',
  `vcpus` varchar(255) NOT NULL DEFAULT '' COMMENT 'CPU info',
  `disk` varchar(255) NOT NULL DEFAULT '' COMMENT 'Disk info',
  `type` varchar(255) NOT NULL DEFAULT '' COMMENT 'Storage type info',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT 'Notes',
  `options` text NOT NULL COMMENT 'Json encoded options',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '0-inactive, 1-active, 2-obsolete',
  PRIMARY KEY (`cloud_location_id`,`instance_type_id`),
  KEY `idx_instance_type_id` (`instance_type_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_e3824ee2da38` FOREIGN KEY (`cloud_location_id`) REFERENCES `cloud_locations` (`cloud_location_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Instance types for each cloud location';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cloud_locations`
--

DROP TABLE IF EXISTS `cloud_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloud_locations` (
  `cloud_location_id` binary(16) NOT NULL COMMENT 'UUID',
  `platform` varchar(20) NOT NULL COMMENT 'Cloud platform',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Normalized endpoint url',
  `cloud_location` varchar(255) NOT NULL COMMENT 'Cloud location',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cloud_location_id`),
  UNIQUE KEY `idx_unique` (`platform`,`url`,`cloud_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Known cloud locations for each platform';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) NOT NULL,
  `rule` varchar(255) NOT NULL,
  `sg_name` varchar(255) NOT NULL,
  `comment` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main` (`env_id`,`sg_name`,`rule`)
) ENGINE=InnoDB AUTO_INCREMENT=4500 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL DEFAULT '',
  `code` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `IDX_COUNTRIES_NAME` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `debug_scripting`
--

DROP TABLE IF EXISTS `debug_scripting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debug_scripting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request` text,
  `server_id` varchar(36) DEFAULT NULL,
  `params` text,
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `account_id` int(6) DEFAULT NULL,
  `farm_id` int(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3288 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `default_records`
--

DROP TABLE IF EXISTS `default_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `default_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT '0',
  `type` enum('NS','MX','CNAME','A','TXT') DEFAULT NULL,
  `ttl` int(11) DEFAULT '14400',
  `priority` int(11) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3605 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `distributions`
--

DROP TABLE IF EXISTS `distributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cfid` varchar(25) DEFAULT NULL,
  `cfurl` varchar(255) DEFAULT NULL,
  `cname` varchar(255) DEFAULT NULL,
  `zone` varchar(255) DEFAULT NULL,
  `bucket` varchar(255) DEFAULT NULL,
  `clientid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=114 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dm_applications`
--

DROP TABLE IF EXISTS `dm_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dm_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `dm_source_id` int(11) DEFAULT NULL,
  `pre_deploy_script` text,
  `post_deploy_script` text,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1517 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dm_deployment_task_logs`
--

DROP TABLE IF EXISTS `dm_deployment_task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dm_deployment_task_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dm_deployment_task_id` varchar(12) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` tinytext,
  PRIMARY KEY (`id`),
  KEY `idx_dm_deployment_task_id` (`dm_deployment_task_id`)
) ENGINE=MyISAM AUTO_INCREMENT=373850 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dm_deployment_tasks`
--

DROP TABLE IF EXISTS `dm_deployment_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dm_deployment_tasks` (
  `id` varchar(12) NOT NULL,
  `env_id` int(11) DEFAULT NULL,
  `farm_role_id` int(11) DEFAULT NULL,
  `dm_application_id` int(11) DEFAULT NULL,
  `remote_path` varchar(255) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `dtdeployed` datetime DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `last_error` text,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dm_sources`
--

DROP TABLE IF EXISTS `dm_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dm_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) DEFAULT NULL,
  `url` text,
  `env_id` int(11) DEFAULT NULL,
  `auth_type` enum('password','certificate') DEFAULT NULL,
  `auth_info` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1509 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dns_zone_records`
--

DROP TABLE IF EXISTS `dns_zone_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dns_zone_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` int(10) unsigned NOT NULL DEFAULT '0',
  `type` varchar(6) DEFAULT NULL,
  `ttl` int(10) unsigned DEFAULT NULL,
  `priority` int(10) unsigned DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `issystem` tinyint(1) DEFAULT NULL,
  `weight` int(10) DEFAULT NULL,
  `port` int(10) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zoneid` (`zone_id`,`type`(1),`value`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=61901156 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dns_zones`
--

DROP TABLE IF EXISTS `dns_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dns_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `zone_name` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `soa_owner` varchar(100) DEFAULT NULL,
  `soa_ttl` int(10) unsigned DEFAULT NULL,
  `soa_parent` varchar(100) DEFAULT NULL,
  `soa_serial` int(10) unsigned DEFAULT NULL,
  `soa_refresh` int(10) unsigned DEFAULT NULL,
  `soa_retry` int(10) unsigned DEFAULT NULL,
  `soa_expire` int(10) unsigned DEFAULT NULL,
  `soa_min_ttl` int(10) unsigned DEFAULT NULL,
  `dtlastmodified` datetime DEFAULT NULL,
  `axfr_allowed_hosts` tinytext,
  `allow_manage_system_records` tinyint(1) DEFAULT '0',
  `isonnsserver` tinyint(1) DEFAULT '0',
  `iszoneconfigmodified` tinyint(1) DEFAULT '0',
  `allowed_accounts` text,
  `private_root_records` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `zones_index3945` (`zone_name`),
  KEY `farmid` (`farm_id`),
  KEY `clientid` (`client_id`),
  KEY `env_id` (`env_id`),
  KEY `iszoneconfigmodified` (`iszoneconfigmodified`)
) ENGINE=InnoDB AUTO_INCREMENT=21568 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ebs_snaps_info`
--

DROP TABLE IF EXISTS `ebs_snaps_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ebs_snaps_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapid` varchar(50) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `ebs_array_snapid` int(11) DEFAULT '0',
  `region` varchar(255) DEFAULT 'us-east-1',
  `autosnapshotid` int(11) DEFAULT '0',
  `is_autoebs_master_snap` tinyint(1) DEFAULT '0',
  `farm_roleid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mainindex` (`farm_roleid`,`is_autoebs_master_snap`),
  KEY `autosnapid` (`autosnapshotid`),
  KEY `snapid` (`snapid`)
) ENGINE=InnoDB AUTO_INCREMENT=928165 DEFAULT CHARSET=latin1 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ec2_ebs`
--

DROP TABLE IF EXISTS `ec2_ebs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ec2_ebs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `volume_id` varchar(15) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `attachment_status` varchar(30) DEFAULT NULL,
  `mount_status` varchar(20) DEFAULT NULL,
  `device` varchar(15) DEFAULT NULL,
  `server_index` int(3) DEFAULT NULL,
  `mount` tinyint(1) DEFAULT '0',
  `mountpoint` varchar(50) DEFAULT NULL,
  `ec2_avail_zone` varchar(30) DEFAULT NULL,
  `ec2_region` varchar(30) DEFAULT NULL,
  `isfsexist` tinyint(1) DEFAULT '0',
  `ismanual` tinyint(1) DEFAULT '0',
  `size` int(11) DEFAULT NULL,
  `snap_id` varchar(50) DEFAULT NULL,
  `type` enum('standard','io1') NOT NULL DEFAULT 'standard',
  `iops` int(4) DEFAULT NULL,
  `ismysqlvolume` tinyint(1) DEFAULT '0',
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `env_id` (`env_id`),
  KEY `server_id` (`server_id`),
  KEY `farm_roleid_index` (`farm_roleid`,`server_index`)
) ENGINE=InnoDB AUTO_INCREMENT=6831 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `elastic_ips`
--

DROP TABLE IF EXISTS `elastic_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elastic_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `role_name` varchar(100) DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  `state` tinyint(1) DEFAULT '0',
  `instance_id` varchar(20) DEFAULT NULL,
  `clientid` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `instance_index` int(11) DEFAULT '0',
  `farm_roleid` int(11) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `allocation_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farmid` (`farmid`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `env_id` (`env_id`),
  KEY `server_id` (`server_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9136 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_definitions`
--

DROP TABLE IF EXISTS `event_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `name` varchar(25) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_event_defs_client_envs_id` (`env_id`),
  KEY `fk_event_defs_clients_id` (`account_id`),
  CONSTRAINT `fk_event_defs_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_defs_client_envs_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=376 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `ishandled` tinyint(1) DEFAULT '0',
  `short_message` varchar(255) DEFAULT NULL,
  `event_object` text,
  `event_id` varchar(36) DEFAULT NULL,
  `event_server_id` varchar(36) DEFAULT NULL,
  `msg_expected` int(11) DEFAULT NULL,
  `msg_created` int(11) DEFAULT NULL,
  `msg_sent` int(11) DEFAULT '0',
  `wh_total` int(3) DEFAULT '0',
  `wh_completed` int(3) DEFAULT '0',
  `wh_failed` int(3) DEFAULT '0',
  `scripts_total` int(3) DEFAULT '0',
  `scripts_completed` int(3) DEFAULT '0',
  `scripts_failed` int(3) DEFAULT '0',
  `scripts_timedout` int(3) DEFAULT '0',
  `is_suspend` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`),
  KEY `farmid` (`farmid`),
  KEY `event_server_id` (`event_server_id`),
  KEY `idx_ishandled` (`ishandled`),
  KEY `idx_dtadded` (`dtadded`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=12097610 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_event_observers`
--

DROP TABLE IF EXISTS `farm_event_observers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_event_observers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `event_observer_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`farmid`)
) ENGINE=InnoDB AUTO_INCREMENT=1065 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_event_observers_config`
--

DROP TABLE IF EXISTS `farm_event_observers_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_event_observers_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `observerid` int(11) DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`observerid`)
) ENGINE=InnoDB AUTO_INCREMENT=20251 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_lease_requests`
--

DROP TABLE IF EXISTS `farm_lease_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_lease_requests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `request_days` int(11) NOT NULL,
  `request_time` datetime DEFAULT NULL,
  `request_comment` text,
  `request_user_id` int(11) NOT NULL,
  `answer_comment` text,
  `answer_user_id` text,
  `status` char(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_id` (`farm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_cloud_services`
--

DROP TABLE IF EXISTS `farm_role_cloud_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_cloud_services` (
  `id` varchar(36) NOT NULL,
  `type` varchar(10) NOT NULL,
  `env_id` int(11) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `farm_role_id` int(11) NOT NULL,
  `platform` varchar(36) DEFAULT NULL,
  `cloud_location` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_role_id` (`farm_role_id`),
  KEY `farm_id` (`farm_id`),
  CONSTRAINT `farm_role_cloud_services_ibfk_1` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_config_presets`
--

DROP TABLE IF EXISTS `farm_role_config_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_config_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `behavior` varchar(25) DEFAULT NULL,
  `cfg_filename` varchar(25) DEFAULT NULL,
  `cfg_key` varchar(100) DEFAULT NULL,
  `cfg_value` text,
  PRIMARY KEY (`id`),
  KEY `main` (`farm_roleid`,`behavior`),
  CONSTRAINT `farm_role_config_presets_ibfk_1` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=15063 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_options`
--

DROP TABLE IF EXISTS `farm_role_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `ami_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` text,
  `hash` varchar(255) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farmid` (`farmid`),
  KEY `farm_roleid` (`farm_roleid`)
) ENGINE=InnoDB AUTO_INCREMENT=1097745 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_scaling_metrics`
--

DROP TABLE IF EXISTS `farm_role_scaling_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_scaling_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `metric_id` int(11) DEFAULT NULL,
  `dtlastpolled` datetime DEFAULT NULL,
  `last_value` varchar(255) DEFAULT NULL,
  `settings` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NewIndex4` (`farm_roleid`,`metric_id`),
  KEY `NewIndex1` (`farm_roleid`),
  KEY `NewIndex2` (`metric_id`),
  CONSTRAINT `farm_role_scaling_metrics_ibfk_1` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=8124 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_scaling_times`
--

DROP TABLE IF EXISTS `farm_role_scaling_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_scaling_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `start_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  `days_of_week` varchar(75) DEFAULT NULL,
  `instances_count` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farmroleid` (`farm_roleid`)
) ENGINE=InnoDB AUTO_INCREMENT=5127 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_scripting_params`
--

DROP TABLE IF EXISTS `farm_role_scripting_params`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_scripting_params` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_role_id` int(11) DEFAULT NULL,
  `role_script_id` int(11) DEFAULT NULL,
  `farm_role_script_id` int(11) DEFAULT NULL,
  `hash` varchar(12) DEFAULT NULL,
  `params` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq` (`farm_role_id`,`hash`,`farm_role_script_id`),
  KEY `farm_roleid` (`farm_role_id`),
  KEY `role_script_id` (`role_script_id`),
  CONSTRAINT `farm_role_scripting_params_ibfk_3` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=8189 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_scripting_targets`
--

DROP TABLE IF EXISTS `farm_role_scripting_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_scripting_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_role_script_id` int(11) DEFAULT NULL,
  `target_type` enum('farmrole','behavior') DEFAULT NULL,
  `target` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_role_script_id` (`farm_role_script_id`),
  CONSTRAINT `farm_role_scripting_targets_ibfk_3` FOREIGN KEY (`farm_role_script_id`) REFERENCES `farm_role_scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=21838 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_scripts`
--

DROP TABLE IF EXISTS `farm_role_scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scriptid` int(11) DEFAULT NULL,
  `farmid` int(11) DEFAULT NULL,
  `ami_id` varchar(255) DEFAULT NULL,
  `params` text,
  `event_name` varchar(255) DEFAULT NULL,
  `target` varchar(50) DEFAULT NULL,
  `version` varchar(20) DEFAULT 'latest',
  `timeout` int(5) DEFAULT '120',
  `issync` tinyint(1) DEFAULT '0',
  `ismenuitem` tinyint(1) DEFAULT '0',
  `order_index` int(5) DEFAULT '0',
  `farm_roleid` int(11) DEFAULT NULL,
  `issystem` tinyint(1) DEFAULT '0',
  `debug` varchar(50) DEFAULT NULL,
  `script_path` varchar(255) DEFAULT NULL,
  `run_as` varchar(15) DEFAULT NULL,
  `script_type` enum('local','scalr','chef') DEFAULT 'scalr',
  PRIMARY KEY (`id`),
  KEY `farmid` (`farmid`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `event_name` (`event_name`),
  KEY `UniqueIndex` (`scriptid`,`farmid`,`event_name`,`farm_roleid`,`script_path`),
  CONSTRAINT `fk_farm_role_scripts_farm_roles_id` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15476397 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_service_config_presets`
--

DROP TABLE IF EXISTS `farm_role_service_config_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_service_config_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `preset_id` int(11) NOT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `behavior` varchar(25) DEFAULT NULL,
  `restart_service` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_farm_role_service_config_presets_service_config_presets1` (`preset_id`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `preset_id` (`preset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=550 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_settings`
--

DROP TABLE IF EXISTS `farm_role_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_settings` (
  `farm_roleid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` text,
  `type` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`farm_roleid`,`name`),
  KEY `name` (`name`(30))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_storage_config`
--

DROP TABLE IF EXISTS `farm_role_storage_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_storage_config` (
  `id` varchar(36) NOT NULL,
  `farm_role_id` int(11) DEFAULT NULL,
  `index` tinyint(3) DEFAULT NULL,
  `type` varchar(15) DEFAULT NULL,
  `fs` varchar(15) DEFAULT NULL,
  `re_use` tinyint(1) DEFAULT NULL,
  `rebuild` tinyint(1) DEFAULT '0',
  `mount` tinyint(1) DEFAULT NULL,
  `mountpoint` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `farm_role_id` (`farm_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_storage_devices`
--

DROP TABLE IF EXISTS `farm_role_storage_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_storage_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_role_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  `server_index` tinyint(4) DEFAULT NULL,
  `placement` varchar(36) DEFAULT NULL,
  `storage_config_id` varchar(36) DEFAULT NULL,
  `config` text,
  `storage_id` varchar(36) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_id` (`storage_id`),
  KEY `storage_config_id` (`storage_config_id`),
  CONSTRAINT `farm_role_storage_devices_ibfk_1` FOREIGN KEY (`storage_config_id`) REFERENCES `farm_role_storage_config` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=25313 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_storage_settings`
--

DROP TABLE IF EXISTS `farm_role_storage_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_storage_settings` (
  `storage_config_id` varchar(36) NOT NULL DEFAULT '',
  `name` varchar(45) NOT NULL DEFAULT '',
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`storage_config_id`,`name`),
  CONSTRAINT `farm_role_storage_settings_ibfk_1` FOREIGN KEY (`storage_config_id`) REFERENCES `farm_role_storage_config` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_role_variables`
--

DROP TABLE IF EXISTS `farm_role_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_role_variables` (
  `farm_role_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`farm_role_id`),
  KEY `fk_farm_role_variables_farm_roles_id` (`farm_role_id`),
  CONSTRAINT `fk_farm_role_variables_farm_roles_id` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_roles`
--

DROP TABLE IF EXISTS `farm_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `alias` varchar(50) DEFAULT NULL,
  `dtlastsync` datetime DEFAULT NULL,
  `reboot_timeout` int(10) DEFAULT '300',
  `launch_timeout` int(10) DEFAULT '300',
  `status_timeout` int(10) DEFAULT '20',
  `launch_index` int(5) DEFAULT '0',
  `role_id` int(11) DEFAULT NULL,
  `new_role_id` int(11) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `farmid` (`farmid`),
  KEY `platform` (`platform`),
  KEY `idx_new_role_id` (`new_role_id`),
  CONSTRAINT `farm_roles_ibfk_1` FOREIGN KEY (`farmid`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=90079 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_settings`
--

DROP TABLE IF EXISTS `farm_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_settings` (
  `farmid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`farmid`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farm_variables`
--

DROP TABLE IF EXISTS `farm_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_variables` (
  `farm_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('','farmrole') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`farm_id`),
  KEY `fk_farm_variables_farms_id` (`farm_id`),
  CONSTRAINT `fk_farm_variables_farms_id` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `farms`
--

DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `iscompleted` tinyint(1) DEFAULT '0',
  `hash` varchar(25) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `dtlaunched` datetime DEFAULT NULL,
  `term_on_sync_fail` tinyint(1) DEFAULT '1',
  `region` varchar(255) DEFAULT 'us-east-1',
  `farm_roles_launch_order` tinyint(1) DEFAULT '0',
  `comments` text,
  `created_by_id` int(11) DEFAULT NULL,
  `created_by_email` varchar(250) DEFAULT NULL,
  `changed_by_id` int(11) NOT NULL,
  `changed_time` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clientid` (`clientid`),
  KEY `env_id` (`env_id`),
  KEY `idx_created_by_id` (`created_by_id`),
  KEY `idx_changed_by_id` (`changed_by_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `farms_ibfk_1` FOREIGN KEY (`clientid`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=21272 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `governance`
--

DROP TABLE IF EXISTS `governance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `governance` (
  `env_id` int(11) NOT NULL,
  `category` varchar(20) NOT NULL,
  `name` varchar(60) NOT NULL,
  `enabled` int(1) NOT NULL,
  `value` text,
  PRIMARY KEY (`env_id`,`category`,`name`),
  CONSTRAINT `governance_ibfk_1` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `governance_ibfk_2` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `governance_ibfk_3` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `image_software`
--

DROP TABLE IF EXISTS `image_software`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `image_software` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `image_hash` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `name` varchar(45) NOT NULL DEFAULT '',
  `version` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_image_hash` (`image_hash`),
  CONSTRAINT `fk_images_hash_image_software` FOREIGN KEY (`image_hash`) REFERENCES `images` (`hash`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=108175 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `images`
--

DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `images` (
  `hash` binary(16) NOT NULL,
  `id` varchar(128) NOT NULL DEFAULT '',
  `env_id` int(11) DEFAULT NULL,
  `bundle_task_id` int(11) DEFAULT NULL,
  `platform` varchar(25) NOT NULL DEFAULT '',
  `cloud_location` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) DEFAULT NULL,
  `os` varchar(60) DEFAULT NULL,
  `os_family` varchar(30) DEFAULT NULL,
  `os_generation` varchar(10) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `dt_added` datetime DEFAULT NULL,
  `dt_last_used` datetime DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_by_email` varchar(100) DEFAULT NULL,
  `architecture` enum('i386','x86_64') NOT NULL DEFAULT 'x86_64',
  `size` int(11) DEFAULT NULL,
  `is_deprecated` tinyint(1) NOT NULL DEFAULT '0',
  `source` enum('BundleTask','Manual') NOT NULL DEFAULT 'Manual',
  `type` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `status_error` varchar(255) DEFAULT NULL,
  `agent_version` varchar(20) DEFAULT NULL,
  `os_id` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`hash`),
  UNIQUE KEY `idx_id` (`id`,`platform`,`cloud_location`,`env_id`),
  KEY `env_id_idx` (`env_id`),
  CONSTRAINT `fk_images_client_environmnets_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logentries`
--

DROP TABLE IF EXISTS `logentries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logentries` (
  `id` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `serverid` varchar(36) NOT NULL,
  `message` text NOT NULL,
  `severity` tinyint(1) DEFAULT '0',
  `time` int(11) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `farmid` int(11) DEFAULT NULL,
  `cnt` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_time` (`time`),
  KEY `idx_farmid_severity` (`farmid`,`severity`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `messageid` varchar(75) NOT NULL,
  `processing_time` float DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `handle_attempts` int(2) DEFAULT '1',
  `dtlasthandleattempt` datetime DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` longtext,
  `server_id` varchar(36) NOT NULL,
  `event_server_id` varchar(36) DEFAULT NULL,
  `type` enum('in','out') DEFAULT NULL,
  `message_name` varchar(30) DEFAULT NULL,
  `message_version` int(2) DEFAULT NULL,
  `message_format` enum('xml','json') DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  `event_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`messageid`,`server_id`),
  KEY `server_id` (`server_id`),
  KEY `messageid` (`messageid`),
  KEY `status` (`status`,`type`),
  KEY `message_name` (`message_name`),
  KEY `dt` (`dtlasthandleattempt`),
  KEY `msg_format` (`message_format`),
  KEY `event_id` (`event_id`),
  KEY `idx_type_status_dt` (`type`,`status`,`dtlasthandleattempt`),
  KEY `dtadded_idx` (`dtadded`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages_backup_1425900373`
--

DROP TABLE IF EXISTS `messages_backup_1425900373`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages_backup_1425900373` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `messageid` varchar(75) DEFAULT NULL,
  `processing_time` float DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `handle_attempts` int(2) DEFAULT '1',
  `dtlasthandleattempt` datetime DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` longtext,
  `server_id` varchar(36) DEFAULT NULL,
  `event_server_id` varchar(36) DEFAULT NULL,
  `type` enum('in','out') DEFAULT NULL,
  `message_name` varchar(30) DEFAULT NULL,
  `message_version` int(2) DEFAULT NULL,
  `message_format` enum('xml','json') DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  `event_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_message` (`messageid`(36),`server_id`),
  KEY `server_id` (`server_id`),
  KEY `messageid` (`messageid`),
  KEY `status` (`status`,`type`),
  KEY `message_name` (`message_name`),
  KEY `dt` (`dtlasthandleattempt`),
  KEY `msg_format` (`message_format`),
  KEY `event_id` (`event_id`),
  KEY `idx_type_status_dt` (`type`,`status`,`dtlasthandleattempt`)
) ENGINE=InnoDB AUTO_INCREMENT=105256237 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nameservers`
--

DROP TABLE IF EXISTS `nameservers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nameservers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(100) DEFAULT NULL,
  `port` int(10) unsigned DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` text,
  `rndc_path` varchar(255) DEFAULT NULL,
  `named_path` varchar(255) DEFAULT NULL,
  `namedconf_path` varchar(255) DEFAULT NULL,
  `isproxy` tinyint(1) DEFAULT '0',
  `isbackup` tinyint(1) DEFAULT '0',
  `ipaddress` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os`
--

DROP TABLE IF EXISTS `os`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os` (
  `id` varchar(25) NOT NULL,
  `name` varchar(50) NOT NULL,
  `family` varchar(20) NOT NULL,
  `generation` varchar(10) NOT NULL,
  `version` varchar(15) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'inactive',
  `is_system` tinyint(1) DEFAULT '0',
  `created` datetime NOT NULL COMMENT 'Created at timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `main` (`version`,`name`,`family`,`generation`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `transactionid` varchar(255) DEFAULT NULL,
  `subscriptionid` varchar(255) DEFAULT NULL,
  `dtpaid` datetime DEFAULT NULL,
  `amount` float(6,2) DEFAULT NULL,
  `payer_email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5464 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_properties`
--

DROP TABLE IF EXISTS `project_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_properties` (
  `project_id` binary(16) NOT NULL COMMENT 'projects.project_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` mediumtext COMMENT 'The value',
  PRIMARY KEY (`project_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Project properties';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `project_id` binary(16) NOT NULL COMMENT 'ID of the project',
  `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  `shared` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Type of the share',
  `env_id` int(11) DEFAULT NULL COMMENT 'Associated environment',
  `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator',
  `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator',
  `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)',
  `archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether it is archived',
  PRIMARY KEY (`project_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_name` (`name`(3)),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_created_by_id` (`created_by_id`,`shared`),
  KEY `idx_shared` (`shared`),
  CONSTRAINT `fk_projects_ccs` FOREIGN KEY (`cc_id`) REFERENCES `ccs` (`cc_id`),
  CONSTRAINT `fk_projects_clients` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Projects';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rds_snaps_info`
--

DROP TABLE IF EXISTS `rds_snaps_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rds_snaps_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapid` varchar(50) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `region` varchar(255) DEFAULT 'us-east-1',
  `autosnapshotid` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13773 DEFAULT CHARSET=latin1 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `records`
--

DROP TABLE IF EXISTS `records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `zoneid` int(10) unsigned NOT NULL DEFAULT '0',
  `rtype` varchar(6) DEFAULT NULL,
  `ttl` int(10) unsigned DEFAULT NULL,
  `rpriority` int(10) unsigned DEFAULT NULL,
  `rvalue` varchar(255) DEFAULT NULL,
  `rkey` varchar(255) DEFAULT NULL,
  `issystem` tinyint(1) DEFAULT NULL,
  `rweight` int(10) DEFAULT NULL,
  `rport` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zoneid` (`zoneid`,`rtype`(1),`rvalue`,`rkey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_behaviors`
--

DROP TABLE IF EXISTS `role_behaviors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_behaviors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `behavior` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id_behavior` (`role_id`,`behavior`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_behaviors_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=380297 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_categories`
--

DROP TABLE IF EXISTS `role_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_image_history`
--

DROP TABLE IF EXISTS `role_image_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_image_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `platform` varchar(25) NOT NULL,
  `cloud_location` varchar(36) NOT NULL,
  `image_id` varchar(255) NOT NULL DEFAULT '',
  `old_image_id` varchar(255) NOT NULL DEFAULT '',
  `dt_added` datetime NOT NULL,
  `added_by_id` int(11) DEFAULT NULL,
  `added_by_email` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_role_id` (`role_id`),
  CONSTRAINT `fk_role_image_history_roles_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9575 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_images`
--

DROP TABLE IF EXISTS `role_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `cloud_location` varchar(36) DEFAULT NULL,
  `image_id` varchar(255) DEFAULT NULL,
  `platform` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_image` (`platform`,`cloud_location`,`image_id`,`role_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_image_id` (`image_id`),
  CONSTRAINT `role_images_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=95346 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_parameters`
--

DROP TABLE IF EXISTS `role_parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_parameters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `isrequired` tinyint(1) DEFAULT NULL,
  `defval` text,
  `allow_multiple_choice` tinyint(1) DEFAULT NULL,
  `options` text,
  `hash` varchar(45) DEFAULT NULL,
  `issystem` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_parameters_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=12610 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_properties`
--

DROP TABLE IF EXISTS `role_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_properties` (
  `role_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`role_id`,`name`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_properties_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_scripts`
--

DROP TABLE IF EXISTS `role_scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `event_name` varchar(50) DEFAULT NULL,
  `target` varchar(15) DEFAULT NULL,
  `script_id` int(11) DEFAULT NULL,
  `version` varchar(10) DEFAULT NULL,
  `timeout` int(5) DEFAULT NULL,
  `issync` tinyint(1) DEFAULT NULL,
  `params` text,
  `order_index` int(11) NOT NULL DEFAULT '0',
  `hash` varchar(12) DEFAULT NULL,
  `script_path` varchar(255) DEFAULT NULL,
  `run_as` varchar(15) DEFAULT NULL,
  `script_type` enum('local','scalr','chef') DEFAULT 'scalr',
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `script_id` (`script_id`),
  CONSTRAINT `role_scripts_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `role_scripts_ibfk_2` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5161 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_security_rules`
--

DROP TABLE IF EXISTS `role_security_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_security_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `rule` varchar(90) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_security_rules_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=111814 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_software_deleted`
--

DROP TABLE IF EXISTS `role_software_deleted`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_software_deleted` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `software_name` varchar(45) DEFAULT NULL,
  `software_version` varchar(20) DEFAULT NULL,
  `software_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_software_deleted_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=168841 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_variables`
--

DROP TABLE IF EXISTS `role_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_variables` (
  `role_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('','farmrole') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`role_id`),
  KEY `fk_role_variables_roles_id` (`role_id`),
  CONSTRAINT `fk_role_variables_roles_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `origin` enum('SHARED','CUSTOM') DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `cat_id` int(11) DEFAULT NULL,
  `description` text,
  `behaviors` varchar(90) DEFAULT NULL,
  `is_devel` tinyint(1) NOT NULL DEFAULT '0',
  `is_deprecated` tinyint(1) DEFAULT '0',
  `generation` tinyint(4) DEFAULT '1',
  `os` varchar(60) DEFAULT NULL,
  `os_family` varchar(30) DEFAULT NULL,
  `os_generation` varchar(10) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `dt_last_used` datetime DEFAULT NULL,
  `added_by_userid` int(11) DEFAULT NULL,
  `added_by_email` varchar(50) DEFAULT NULL,
  `os_id` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`origin`),
  KEY `NewIndex2` (`client_id`),
  KEY `NewIndex3` (`env_id`),
  KEY `idx_os_family` (`os_family`)
) ENGINE=InnoDB AUTO_INCREMENT=75396 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scaling_metrics`
--

DROP TABLE IF EXISTS `scaling_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scaling_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `retrieve_method` varchar(20) DEFAULT NULL,
  `calc_function` varchar(20) DEFAULT NULL,
  `algorithm` varchar(15) DEFAULT NULL,
  `alias` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NewIndex3` (`client_id`,`name`),
  KEY `NewIndex1` (`client_id`),
  KEY `NewIndex2` (`env_id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scheduler`
--

DROP TABLE IF EXISTS `scheduler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('script_exec','terminate_farm','launch_farm','fire_event') DEFAULT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL COMMENT 'id of farm, farm_role from other tables',
  `target_server_index` int(11) DEFAULT NULL,
  `target_type` enum('farm','role','instance') DEFAULT NULL,
  `script_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL COMMENT 'start task''s time',
  `end_time` datetime DEFAULT NULL COMMENT 'end task by this time',
  `last_start_time` datetime DEFAULT NULL COMMENT 'the last time task was started',
  `restart_every` int(11) DEFAULT '0' COMMENT 'restart task every N minutes',
  `config` text COMMENT 'arguments for action',
  `order_index` int(11) DEFAULT NULL COMMENT 'task order',
  `timezone` varchar(100) DEFAULT NULL,
  `status` varchar(11) DEFAULT NULL COMMENT 'active, suspended, finished',
  `account_id` int(11) DEFAULT NULL COMMENT 'Task belongs to selected account',
  `env_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `account_id` (`account_id`,`env_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1854 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `script_shortcuts`
--

DROP TABLE IF EXISTS `script_shortcuts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `script_shortcuts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `script_id` int(11) DEFAULT NULL,
  `script_path` varchar(255) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `farm_role_id` int(11) DEFAULT NULL,
  `is_sync` tinyint(1) NOT NULL DEFAULT '0',
  `timeout` int(11) NOT NULL DEFAULT '0',
  `version` int(11) NOT NULL,
  `params` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_script_shortcuts_scripts_id` (`script_id`),
  KEY `fk_script_shortcuts_farms_id` (`farm_id`),
  KEY `fk_script_shortcuts_farm_roles_id` (`farm_role_id`),
  CONSTRAINT `fk_script_shortcuts_farms_id` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_script_shortcuts_farm_roles_id` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_script_shortcuts_scripts_id` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=326 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `script_versions`
--

DROP TABLE IF EXISTS `script_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `script_versions` (
  `script_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `dt_created` datetime NOT NULL,
  `variables` text,
  `changed_by_id` int(11) DEFAULT NULL,
  `changed_by_email` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`script_id`,`version`),
  CONSTRAINT `fk_script_versions_scripts_id` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scripting_log`
--

DROP TABLE IF EXISTS `scripting_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scripting_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `event_server_id` varchar(36) DEFAULT NULL,
  `script_name` varchar(50) DEFAULT NULL,
  `exec_time` int(11) DEFAULT NULL,
  `exec_exitcode` int(11) DEFAULT NULL,
  `run_as` varchar(20) DEFAULT NULL,
  `event_id` varchar(36) DEFAULT NULL,
  `execution_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farmid` (`farmid`),
  KEY `server_id` (`server_id`),
  KEY `event_id` (`event_id`),
  KEY `event_server_id` (`event_server_id`),
  KEY `execution_id` (`execution_id`),
  KEY `idx_dtadded` (`dtadded`),
  KEY `idx_script_name` (`script_name`),
  KEY `idx_event` (`event`)
) ENGINE=MyISAM AUTO_INCREMENT=68259119 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scripts`
--

DROP TABLE IF EXISTS `scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `dt_created` datetime DEFAULT NULL,
  `dt_changed` datetime DEFAULT NULL,
  `os` varchar(16) NOT NULL,
  `is_sync` tinyint(1) DEFAULT '0',
  `timeout` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_by_email` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_env_id` (`env_id`),
  CONSTRAINT `fk_scripts_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_scripts_client_environments_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9385 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `security_group_rules_comments`
--

DROP TABLE IF EXISTS `security_group_rules_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_group_rules_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) NOT NULL,
  `platform` varchar(20) NOT NULL,
  `cloud_location` varchar(50) NOT NULL,
  `vpc_id` varchar(20) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `rule` varchar(255) NOT NULL,
  `comment` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_main` (`env_id`,`platform`,`cloud_location`,`vpc_id`,`group_name`,`rule`),
  CONSTRAINT `FK_security_group_rules_comments_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5045 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_alerts`
--

DROP TABLE IF EXISTS `server_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `server_index` int(11) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `metric` varchar(20) DEFAULT NULL,
  `dtoccured` datetime DEFAULT NULL,
  `dtlastcheck` datetime DEFAULT NULL,
  `dtsolved` datetime DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `status` enum('resolved','failed') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main2` (`server_id`,`metric`,`status`),
  KEY `env_id` (`env_id`),
  KEY `farm_role` (`farm_id`,`farm_roleid`),
  KEY `farm_roleid` (`farm_roleid`),
  CONSTRAINT `server_alerts_ibfk_1` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `server_alerts_ibfk_2` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `server_alerts_ibfk_3` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=93188 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_operation_progress`
--

DROP TABLE IF EXISTS `server_operation_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_operation_progress` (
  `operation_id` varchar(36) NOT NULL,
  `timestamp` int(11) DEFAULT NULL,
  `phase` varchar(100) NOT NULL,
  `step` varchar(100) NOT NULL,
  `status` varchar(15) NOT NULL,
  `progress` int(11) DEFAULT NULL,
  `stepno` int(11) DEFAULT NULL,
  `message` text,
  `trace` text,
  `handler` varchar(255) DEFAULT NULL,
  UNIQUE KEY `unique` (`operation_id`,`phase`,`step`),
  KEY `operation_id` (`operation_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `server_operation_progress_ibfk_1` FOREIGN KEY (`operation_id`) REFERENCES `server_operations` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_operations`
--

DROP TABLE IF EXISTS `server_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_operations` (
  `id` varchar(36) NOT NULL DEFAULT '',
  `timestamp` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `server_id` varchar(36) NOT NULL DEFAULT '',
  `name` varchar(50) DEFAULT NULL,
  `phases` text,
  UNIQUE KEY `id` (`id`),
  KEY `server_id` (`server_id`,`name`(20)),
  CONSTRAINT `server_operations_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`server_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_properties`
--

DROP TABLE IF EXISTS `server_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_properties` (
  `server_id` varchar(36) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`server_id`,`name`),
  KEY `serverid` (`server_id`),
  KEY `name_value` (`name`(20),`value`(20))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_termination_errors`
--

DROP TABLE IF EXISTS `server_termination_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_termination_errors` (
  `server_id` varchar(36) NOT NULL COMMENT 'servers.server_id ref',
  `retry_after` datetime NOT NULL COMMENT 'After what time it should be revalidated',
  `attempts` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'The number of unsuccessful attempts',
  `last_error` text COMMENT 'Error message',
  PRIMARY KEY (`server_id`),
  KEY `idx_retry_after` (`retry_after`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Server termination process errors';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `server_variables`
--

DROP TABLE IF EXISTS `server_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_variables` (
  `server_id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`server_id`),
  KEY `fk_server_variables_servers_server_id` (`server_id`),
  CONSTRAINT `fk_server_variables_servers_server_id` FOREIGN KEY (`server_id`) REFERENCES `servers` (`server_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servers`
--

DROP TABLE IF EXISTS `servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` varchar(36) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) NOT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `status` varchar(25) DEFAULT NULL,
  `remote_ip` varchar(15) DEFAULT NULL,
  `local_ip` varchar(15) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `index` int(11) DEFAULT NULL,
  `cloud_location` varchar(255) DEFAULT NULL,
  `cloud_location_zone` varchar(255) DEFAULT NULL,
  `image_id` varchar(255) DEFAULT NULL,
  `dtshutdownscheduled` datetime DEFAULT NULL,
  `dtrebootstart` datetime DEFAULT NULL,
  `replace_server_id` varchar(36) DEFAULT NULL,
  `dtlastsync` datetime DEFAULT NULL,
  `os_type` enum('windows','linux') DEFAULT 'linux',
  PRIMARY KEY (`id`),
  KEY `serverid` (`server_id`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `farmid_status` (`farm_id`,`status`),
  KEY `local_ip` (`local_ip`),
  KEY `env_id` (`env_id`),
  KEY `client_id` (`client_id`),
  KEY `idx_dtshutdownscheduled` (`dtshutdownscheduled`),
  KEY `idx_image_id` (`image_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1981200 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servers_history`
--

DROP TABLE IF EXISTS `servers_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servers_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `cloud_server_id` varchar(50) DEFAULT NULL,
  `dtlaunched` datetime DEFAULT NULL,
  `dtterminated` datetime DEFAULT NULL,
  `launch_reason_id` tinyint(1) DEFAULT NULL,
  `launch_reason` varchar(255) DEFAULT NULL,
  `terminate_reason_id` tinyint(1) DEFAULT NULL,
  `terminate_reason` varchar(255) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `server_index` int(5) DEFAULT NULL,
  `scu_used` float(11,2) DEFAULT '0.00',
  `scu_reported` float(11,2) DEFAULT '0.00',
  `scu_updated` tinyint(1) DEFAULT '0',
  `scu_collecting` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `servers_history_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1864316 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servers_launch_timelog`
--

DROP TABLE IF EXISTS `servers_launch_timelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servers_launch_timelog` (
  `server_id` varchar(36) NOT NULL DEFAULT '',
  `os_family` varchar(15) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `cloud` varchar(10) DEFAULT NULL,
  `cloud_location` varchar(36) DEFAULT NULL,
  `server_type` varchar(36) DEFAULT NULL,
  `behaviors` varchar(255) DEFAULT NULL,
  `ts_created` int(11) DEFAULT NULL,
  `ts_launched` int(11) DEFAULT NULL,
  `ts_hi` int(11) DEFAULT NULL,
  `ts_bhu` int(11) DEFAULT NULL,
  `ts_hu` int(11) DEFAULT NULL,
  `time_to_boot` int(5) DEFAULT NULL,
  `time_to_hi` int(5) DEFAULT NULL,
  `last_init_status` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servers_stats`
--

DROP TABLE IF EXISTS `servers_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servers_stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usage` int(11) DEFAULT NULL,
  `instance_type` varchar(15) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `month` int(2) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `cloud_location` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main` (`instance_type`,`cloud_location`,`farm_id`,`env_id`,`month`,`year`),
  KEY `envid` (`env_id`),
  KEY `farm_id` (`farm_id`),
  KEY `year` (`year`)
) ENGINE=MyISAM AUTO_INCREMENT=71180 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_config_preset_data`
--

DROP TABLE IF EXISTS `service_config_preset_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_config_preset_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `preset_id` int(11) NOT NULL,
  `key` varchar(45) DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34330 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_config_presets`
--

DROP TABLE IF EXISTS `service_config_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_config_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `name` varchar(45) DEFAULT NULL,
  `role_behavior` varchar(20) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `dtlastmodified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `env_id` (`env_id`),
  KEY `client_id` (`client_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=499 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_chef_runlists`
--

DROP TABLE IF EXISTS `services_chef_runlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_chef_runlists` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `chef_server_id` int(11) DEFAULT NULL,
  `name` varchar(30) NOT NULL,
  `description` varchar(255) NOT NULL,
  `runlist` text,
  `attributes` text,
  `chef_environment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_chef_servers`
--

DROP TABLE IF EXISTS `services_chef_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_chef_servers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `level` tinyint(4) NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env',
  `url` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `auth_key` text,
  `v_username` varchar(255) DEFAULT NULL,
  `v_auth_key` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_db_backup_parts`
--

DROP TABLE IF EXISTS `services_db_backup_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_db_backup_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_id` int(11) DEFAULT NULL,
  `path` text,
  `size` int(11) DEFAULT NULL,
  `seq_number` int(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_id` (`backup_id`),
  CONSTRAINT `services_db_backup_parts_ibfk_1` FOREIGN KEY (`backup_id`) REFERENCES `services_db_backups` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1624052 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_db_backups`
--

DROP TABLE IF EXISTS `services_db_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_db_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(25) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `service` varchar(50) DEFAULT NULL,
  `platform` varchar(25) DEFAULT NULL,
  `provider` varchar(20) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `env_id` (`env_id`),
  CONSTRAINT `services_db_backups_ibfk_1` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=439718 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_db_backups_history`
--

DROP TABLE IF EXISTS `services_db_backups_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_db_backups_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_role_id` int(11) NOT NULL,
  `operation` enum('backup','bundle') NOT NULL,
  `date` datetime NOT NULL,
  `status` enum('ok','error') NOT NULL,
  `error` text,
  PRIMARY KEY (`id`),
  KEY `main` (`farm_role_id`),
  CONSTRAINT `services_db_backups_history_ibfk_1` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=741368 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_mongodb_cluster_log`
--

DROP TABLE IF EXISTS `services_mongodb_cluster_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_mongodb_cluster_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `severity` enum('INFO','WARNING','ERROR') DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7896 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_mongodb_config_servers`
--

DROP TABLE IF EXISTS `services_mongodb_config_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_mongodb_config_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_role_id` int(11) NOT NULL,
  `config_server_index` tinyint(1) NOT NULL,
  `shard_index` tinyint(2) NOT NULL,
  `replica_set_index` tinyint(2) NOT NULL,
  `volume_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_roleid_index` (`farm_role_id`,`config_server_index`),
  KEY `farm_role_id` (`farm_role_id`),
  CONSTRAINT `services_mongodb_config_servers_ibfk_1` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=394 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_mongodb_snapshots_map`
--

DROP TABLE IF EXISTS `services_mongodb_snapshots_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_mongodb_snapshots_map` (
  `farm_roleid` int(11) NOT NULL,
  `shard_index` int(11) NOT NULL,
  `snapshot_id` varchar(25) NOT NULL,
  PRIMARY KEY (`farm_roleid`,`shard_index`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_mongodb_volumes_map`
--

DROP TABLE IF EXISTS `services_mongodb_volumes_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_mongodb_volumes_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) NOT NULL,
  `replica_set_index` int(11) NOT NULL,
  `shard_index` int(11) NOT NULL,
  `volume_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main` (`farm_roleid`,`replica_set_index`,`shard_index`)
) ENGINE=InnoDB AUTO_INCREMENT=5117 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_ssl_certs`
--

DROP TABLE IF EXISTS `services_ssl_certs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_ssl_certs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `ssl_pkey` text,
  `ssl_cert` text,
  `ssl_cabundle` text,
  `ssl_pkey_password` text,
  `ssl_simple_pkey` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1174 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` varchar(64) NOT NULL COMMENT 'setting ID',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='settings';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ssh_keys`
--

DROP TABLE IF EXISTS `ssh_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ssh_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `private_key` text,
  `public_key` text,
  `cloud_location` varchar(255) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `cloud_key_name` varchar(255) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform` (`platform`,`type`,`env_id`,`farm_id`,`cloud_location`,`cloud_key_name`),
  KEY `fk_ssh_keys_client_environments_id` (`env_id`),
  KEY `fk_ssh_keys_farms_id` (`farm_id`),
  CONSTRAINT `fk_ssh_keys_client_environments_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=18533 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storage_restore_configs`
--

DROP TABLE IF EXISTS `storage_restore_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_restore_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `manifest` text,
  `type` enum('full','incremental') NOT NULL,
  `parent_manifest` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9409 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storage_snapshots`
--

DROP TABLE IF EXISTS `storage_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_snapshots` (
  `id` varchar(36) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `config` text,
  `description` text,
  `ismysql` tinyint(1) DEFAULT '0',
  `dtcreated` datetime DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `service` varchar(50) DEFAULT NULL,
  `cloud_location` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_roleid` (`farm_roleid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storage_volumes`
--

DROP TABLE IF EXISTS `storage_volumes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_volumes` (
  `id` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `attachment_status` varchar(255) DEFAULT NULL,
  `mount_status` varchar(255) DEFAULT NULL,
  `config` text,
  `type` varchar(20) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `fstype` varchar(255) DEFAULT NULL,
  `farm_roleid` int(11) DEFAULT NULL,
  `server_index` int(11) DEFAULT NULL,
  `purpose` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `subscriptionid` varchar(255) DEFAULT NULL,
  `dtstart` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `test` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog`
--

DROP TABLE IF EXISTS `syslog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=978734 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_0015042015`
--

DROP TABLE IF EXISTS `syslog_0015042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_0015042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1142717 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_0016042015`
--

DROP TABLE IF EXISTS `syslog_0016042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_0016042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1124969 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_0017042015`
--

DROP TABLE IF EXISTS `syslog_0017042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_0017042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1161046 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_0815042015`
--

DROP TABLE IF EXISTS `syslog_0815042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_0815042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1189390 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_0816042015`
--

DROP TABLE IF EXISTS `syslog_0816042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_0816042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1168031 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_1615042015`
--

DROP TABLE IF EXISTS `syslog_1615042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_1615042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1167610 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_1616042015`
--

DROP TABLE IF EXISTS `syslog_1616042015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_1616042015` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `severity` varchar(10) DEFAULT NULL,
  `dtadded_time` bigint(20) DEFAULT NULL,
  `transactionid` varchar(50) DEFAULT NULL,
  `backtrace` text,
  `caller` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `sub_transactionid` varchar(50) DEFAULT NULL,
  `farmid` varchar(20) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`transactionid`),
  KEY `NewIndex2` (`sub_transactionid`),
  KEY `idx_dtadded` (`dtadded`)
) ENGINE=MyISAM AUTO_INCREMENT=1163644 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_metadata`
--

DROP TABLE IF EXISTS `syslog_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transactionid` varchar(50) DEFAULT NULL,
  `errors` int(5) DEFAULT NULL,
  `warnings` int(5) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transid` (`transactionid`)
) ENGINE=MyISAM AUTO_INCREMENT=1858 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tag_link`
--

DROP TABLE IF EXISTS `tag_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tag_link` (
  `tag_id` int(11) NOT NULL,
  `resource` varchar(32) NOT NULL,
  `resource_id` int(11) NOT NULL,
  UNIQUE KEY `idx_id` (`tag_id`,`resource`,`resource_id`),
  KEY `idx_resource` (`resource`,`resource_id`),
  CONSTRAINT `fk_tag_link_tags_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `account_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`,`account_id`),
  KEY `fk_tags_clients_id` (`account_id`),
  CONSTRAINT `fk_tags_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_queue`
--

DROP TABLE IF EXISTS `task_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_name` varchar(255) DEFAULT NULL,
  `data` text,
  `dtadded` datetime DEFAULT NULL,
  `failed_attempts` int(3) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5227353 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tmp`
--

DROP TABLE IF EXISTS `tmp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tmp` (
  `test1` varchar(36) NOT NULL,
  `test2` char(36) NOT NULL,
  `test3` varbinary(18) NOT NULL,
  `test4` binary(18) NOT NULL,
  UNIQUE KEY `test1` (`test1`,`test2`,`test3`,`test4`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ui_debug_log`
--

DROP TABLE IF EXISTS `ui_debug_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ui_debug_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ipaddress` varchar(15) DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `url` varchar(255) DEFAULT NULL,
  `report` text,
  `env_id` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=191 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ui_errors`
--

DROP TABLE IF EXISTS `ui_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ui_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tm` datetime NOT NULL,
  `file` varchar(255) NOT NULL,
  `lineno` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `short` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `browser` varchar(255) NOT NULL,
  `cnt` int(11) NOT NULL DEFAULT '1',
  `account_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `info` (`file`,`lineno`,`short`,`account_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1055 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `upgrade_messages`
--

DROP TABLE IF EXISTS `upgrade_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upgrade_messages` (
  `uuid` varbinary(16) NOT NULL COMMENT 'upgrades.uuid reference',
  `created` datetime NOT NULL COMMENT 'Creation timestamp',
  `message` text COMMENT 'Error messages',
  KEY `idx_uuid` (`uuid`),
  CONSTRAINT `upgrade_messages_ibfk_1` FOREIGN KEY (`uuid`) REFERENCES `upgrades` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `upgrades`
--

DROP TABLE IF EXISTS `upgrades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upgrades` (
  `uuid` varbinary(16) NOT NULL COMMENT 'Unique identifier of update',
  `released` datetime NOT NULL COMMENT 'The time when upgrade script is issued',
  `appears` datetime NOT NULL COMMENT 'The time when upgrade does appear',
  `applied` datetime DEFAULT NULL COMMENT 'The time when update is successfully applied',
  `status` tinyint(4) NOT NULL COMMENT 'Upgrade status',
  `hash` varbinary(20) DEFAULT NULL COMMENT 'SHA1 hash of the upgrade file',
  PRIMARY KEY (`uuid`),
  KEY `idx_status` (`status`),
  KEY `idx_appears` (`appears`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `variables`
--

DROP TABLE IF EXISTS `variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variables` (
  `name` varchar(50) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) NOT NULL DEFAULT '0',
  `flag_required` enum('','account','env','role','farm','farmrole') NOT NULL DEFAULT '',
  `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `format` varchar(15) NOT NULL DEFAULT '',
  `validator` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_config_endpoints`
--

DROP TABLE IF EXISTS `webhook_config_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_config_endpoints` (
  `webhook_id` binary(16) NOT NULL COMMENT 'UUID',
  `endpoint_id` binary(16) NOT NULL COMMENT 'UUID',
  PRIMARY KEY (`webhook_id`,`endpoint_id`),
  KEY `idx_endpoint_id` (`endpoint_id`),
  CONSTRAINT `fk_4d800abd81968700c` FOREIGN KEY (`webhook_id`) REFERENCES `webhook_configs` (`webhook_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_6d8c137f54109dcaa` FOREIGN KEY (`endpoint_id`) REFERENCES `webhook_endpoints` (`endpoint_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_config_events`
--

DROP TABLE IF EXISTS `webhook_config_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_config_events` (
  `webhook_id` binary(16) NOT NULL COMMENT 'UUID',
  `event_type` varchar(128) NOT NULL,
  PRIMARY KEY (`webhook_id`,`event_type`),
  KEY `idx_event_type` (`event_type`),
  CONSTRAINT `fk_40db098cb4b5c6797` FOREIGN KEY (`webhook_id`) REFERENCES `webhook_configs` (`webhook_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_config_farms`
--

DROP TABLE IF EXISTS `webhook_config_farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_config_farms` (
  `webhook_id` binary(16) NOT NULL COMMENT 'UUID',
  `farm_id` int(11) NOT NULL,
  PRIMARY KEY (`webhook_id`,`farm_id`),
  KEY `idx_farm_id` (`farm_id`),
  CONSTRAINT `fk_24503b0f582804419` FOREIGN KEY (`webhook_id`) REFERENCES `webhook_configs` (`webhook_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_configs`
--

DROP TABLE IF EXISTS `webhook_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_configs` (
  `webhook_id` binary(16) NOT NULL COMMENT 'UUID',
  `level` tinyint(4) NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env, 8 - Farm',
  `name` varchar(50) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `post_data` text,
  `skip_private_gv` tinyint(1) DEFAULT '0',
  `timeout` int(2) NOT NULL DEFAULT '3',
  `attempts` int(2) NOT NULL DEFAULT '3',
  PRIMARY KEY (`webhook_id`),
  KEY `idx_level` (`level`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_name` (`name`(3)),
  CONSTRAINT `fk_4d3039820abc2a41c` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_dbedab24d097d2a71` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_endpoints`
--

DROP TABLE IF EXISTS `webhook_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_endpoints` (
  `endpoint_id` binary(16) NOT NULL COMMENT 'UUID',
  `level` tinyint(4) NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env',
  `account_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `url` text,
  `validation_token` binary(16) DEFAULT NULL COMMENT 'UUID',
  `is_valid` tinyint(1) NOT NULL DEFAULT '0',
  `security_key` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`endpoint_id`),
  KEY `fk_660a12dca2e1dae8a` (`account_id`),
  KEY `fk_816aef24d6e3f9aac` (`env_id`),
  CONSTRAINT `fk_660a12dca2e1dae8a` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_816aef24d6e3f9aac` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_history`
--

DROP TABLE IF EXISTS `webhook_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_history` (
  `history_id` binary(16) NOT NULL COMMENT 'UUID',
  `webhook_id` binary(16) NOT NULL COMMENT 'UUID',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endpoint_id` binary(16) NOT NULL COMMENT 'UUID',
  `event_id` char(36) NOT NULL COMMENT 'UUID',
  `farm_id` int(11) NOT NULL,
  `server_id` varchar(36) DEFAULT NULL,
  `event_type` varchar(128) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `response_code` smallint(6) DEFAULT NULL,
  `payload` text,
  `error_msg` text,
  `handle_attempts` int(2) DEFAULT '0',
  `dtlasthandleattempt` datetime DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_endpoint_id` (`endpoint_id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_status` (`status`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_farm_id` (`farm_id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-04-17  7:10:27
