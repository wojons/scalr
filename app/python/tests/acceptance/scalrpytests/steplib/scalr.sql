-- MySQL dump 10.13  Distrib 5.5.35, for Linux (x86_64)
--
-- Host: localhost    Database: scalr
-- ------------------------------------------------------
-- Server version	5.5.35-33.0

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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `account_user_dashboard`
--

DROP TABLE IF EXISTS `account_user_dashboard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_user_dashboard` (
  `user_id` int(11) NOT NULL,
  `env_id` int(11) NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `user_id` (`user_id`,`env_id`),
  KEY `env_id` (`env_id`),
  CONSTRAINT `account_user_dashboard_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `account_user_dashboard_ibfk_2` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
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
  KEY `name` (`name`)
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_account_alerts`
--

DROP TABLE IF EXISTS `backup_account_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_account_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT NULL,
  `message` text,
  `env_id` int(11) DEFAULT NULL,
  `dtcreated` datetime DEFAULT NULL,
  `dtresolved` datetime DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  `platform` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `backup_account_alerts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_account_audit`
--

DROP TABLE IF EXISTS `backup_account_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_account_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `action` varchar(45) DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  `comments` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_account_audit_clients1` (`account_id`),
  KEY `fk_account_audit_account_users1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_account_group_permissions`
--

DROP TABLE IF EXISTS `backup_account_group_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_account_group_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `controller` varchar(45) DEFAULT NULL,
  `permissions` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_account_group_permissions_account_groups1` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_account_groups`
--

DROP TABLE IF EXISTS `backup_account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_account_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT '1',
  `color` varchar(16) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `fk_account_groups_account_teams1` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_account_user_groups`
--

DROP TABLE IF EXISTS `backup_account_user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_account_user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_account_user_groups_account_users1` (`user_id`),
  KEY `fk_account_user_groups_account_groups1` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_aws_errors`
--

DROP TABLE IF EXISTS `backup_aws_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_aws_errors` (
  `guid` varchar(85) NOT NULL,
  `title` text,
  `pub_date` datetime DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`guid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_aws_regions`
--

DROP TABLE IF EXISTS `backup_aws_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_aws_regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_config`
--

DROP TABLE IF EXISTS `backup_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_debug_pm`
--

DROP TABLE IF EXISTS `backup_debug_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_debug_pm` (
  `ip` varchar(16) NOT NULL,
  `cnt` int(11) NOT NULL,
  UNIQUE KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_debug_rackspace`
--

DROP TABLE IF EXISTS `backup_debug_rackspace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_debug_rackspace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` varchar(36) DEFAULT NULL,
  `info` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_debug_ui`
--

DROP TABLE IF EXISTS `backup_debug_ui`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_debug_ui` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) DEFAULT NULL,
  `time` varchar(50) DEFAULT NULL,
  `ptime` varchar(50) DEFAULT NULL,
  `t1` varchar(10) DEFAULT NULL,
  `t2` varchar(10) DEFAULT NULL,
  `t3` varchar(10) DEFAULT NULL,
  `dtdate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_farm_stats`
--

DROP TABLE IF EXISTS `backup_farm_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_farm_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `bw_in` bigint(20) DEFAULT '0',
  `bw_out` bigint(20) DEFAULT '0',
  `bw_in_last` int(11) DEFAULT '0',
  `bw_out_last` int(11) DEFAULT '0',
  `month` int(2) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `dtlastupdate` int(11) DEFAULT NULL,
  `m1_small` int(11) DEFAULT '0',
  `m1_large` int(11) DEFAULT '0',
  `m1_xlarge` int(11) DEFAULT '0',
  `c1_medium` int(11) DEFAULT '0',
  `c1_xlarge` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`month`,`year`),
  KEY `NewIndex2` (`farmid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_garbage_queue`
--

DROP TABLE IF EXISTS `backup_garbage_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_garbage_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `data` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NewIndex1` (`clientid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_init_tokens`
--

DROP TABLE IF EXISTS `backup_init_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_init_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_instances_history`
--

DROP TABLE IF EXISTS `backup_instances_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_instances_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` varchar(20) DEFAULT NULL,
  `dtlaunched` int(11) DEFAULT NULL,
  `dtterminated` int(11) DEFAULT NULL,
  `uptime` int(11) DEFAULT NULL,
  `instance_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_ipaccess`
--

DROP TABLE IF EXISTS `backup_ipaccess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_ipaccess` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ipaddress` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_payment_redirects`
--

DROP TABLE IF EXISTS `backup_payment_redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_payment_redirects` (
  `id` int(11) DEFAULT NULL,
  `from_clientid` int(11) DEFAULT NULL,
  `to_clientid` int(11) DEFAULT NULL,
  `subscription_id` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_real_servers`
--

DROP TABLE IF EXISTS `backup_real_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_real_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmid` int(11) DEFAULT NULL,
  `ami_id` varchar(255) DEFAULT NULL,
  `ipaddress` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_rebundle_log`
--

DROP TABLE IF EXISTS `backup_rebundle_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_rebundle_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `roleid` int(11) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `message` text,
  `bundle_task_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_sensor_data`
--

DROP TABLE IF EXISTS `backup_sensor_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_sensor_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_roleid` int(11) DEFAULT NULL,
  `sensor_name` varchar(255) DEFAULT NULL,
  `sensor_value` varchar(255) DEFAULT NULL,
  `dtlastupdate` int(11) DEFAULT NULL,
  `raw_sensor_data` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`farm_roleid`,`sensor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_storage_backup_configs`
--

DROP TABLE IF EXISTS `backup_storage_backup_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_storage_backup_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(25) DEFAULT NULL,
  `backup_type` varchar(25) DEFAULT NULL,
  `volume_config` text,
  `farm_roleid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_roleid` (`farm_roleid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_wus_info`
--

DROP TABLE IF EXISTS `backup_wus_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_wus_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` int(11) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `about` text,
  `scalrabout` text,
  `isapproved` tinyint(1) DEFAULT '0',
  `url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NewIndex1` (`clientid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=1159 DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`),
  KEY `clientid` (`client_id`),
  KEY `env_id` (`env_id`),
  KEY `server_id` (`server_id`),
  KEY `status` (`status`),
  CONSTRAINT `bundle_tasks_ibfk_1` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cc_properties`
--

DROP TABLE IF EXISTS `cc_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cc_properties` (
  `cc_id` varbinary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`cc_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='CC properties';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ccs`
--

DROP TABLE IF EXISTS `ccs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ccs` (
  `cc_id` varbinary(16) NOT NULL COMMENT 'ID of the cost centre',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  PRIMARY KEY (`cc_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_name` (`name`(3)),
  CONSTRAINT `fk_ccs_clients` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Cost Centers';
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
) ENGINE=InnoDB AUTO_INCREMENT=644 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1;
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
  KEY `idx_dtdue` (`dtdue`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_definitions`
--

DROP TABLE IF EXISTS `event_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `env_id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`),
  KEY `farmid` (`farmid`),
  KEY `event_server_id` (`event_server_id`),
  KEY `idx_dtadded` (`dtadded`),
  KEY `idx_type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=3562 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  `target` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_role_script_id` (`farm_role_script_id`),
  CONSTRAINT `farm_role_scripting_targets_ibfk_3` FOREIGN KEY (`farm_role_script_id`) REFERENCES `farm_role_scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`),
  KEY `farmid` (`farmid`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `event_name` (`event_name`),
  KEY `UniqueIndex` (`scriptid`,`farmid`,`event_name`,`farm_roleid`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  `type` enum('1','2') DEFAULT NULL,
  PRIMARY KEY (`farm_roleid`,`name`),
  KEY `name` (`name`(30))
  /*CONSTRAINT `farm_role_settings_ibfk_1` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles`
 * (`id`) ON DELETE CASCADE ON UPDATE NO ACTION*/
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=latin1;
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
  KEY `platform` (`platform`)
  /*CONSTRAINT `farm_roles_ibfk_1` FOREIGN KEY (`farmid`) REFERENCES `farms` (`id`) ON DELETE
 * CASCADE ON UPDATE NO ACTION*/
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=latin1;
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
  /*CONSTRAINT `fk_farm_settings_farms` FOREIGN KEY (`farmid`) REFERENCES `farms` (`id`) ON DELETE
 * CASCADE*/
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
  CONSTRAINT `farms_ibfk_1` FOREIGN KEY (`clientid`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `global_variables`
--

DROP TABLE IF EXISTS `global_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `global_variables` (
  `env_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `farm_role_id` int(11) NOT NULL,
  `server_id` varchar(36) NOT NULL DEFAULT '',
  `name` varchar(30) NOT NULL,
  `value` text,
  `flag_final` tinyint(1) DEFAULT '0',
  `flag_required` tinyint(1) DEFAULT '0',
  `flag_hidden` tinyint(1) DEFAULT '0',
  `scope` enum('env','role','farm','farmrole','server') DEFAULT NULL,
  `format` varchar(15) DEFAULT NULL,
  `validator` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`env_id`,`role_id`,`farm_id`,`farm_role_id`,`server_id`,`name`),
  KEY `role_id` (`role_id`),
  KEY `farm_id` (`farm_id`),
  KEY `farm_role_id` (`farm_role_id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `fk_global_variables_client_environments_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
-- Table structure for table `logentries`
--

DROP TABLE IF EXISTS `logentries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logentries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serverid` varchar(36) NOT NULL,
  `message` text NOT NULL,
  `severity` tinyint(1) DEFAULT '0',
  `time` int(11) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `farmid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_time` (`time`),
  KEY `idx_farmid_severity` (`farmid`,`severity`)
) ENGINE=MyISAM AUTO_INCREMENT=6494 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
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
  KEY `serverid_isszr` (`server_id`),
  KEY `messageid` (`messageid`),
  KEY `status` (`status`,`type`),
  KEY `message_name` (`message_name`),
  KEY `dt` (`dtlasthandleattempt`),
  KEY `idx_type_status_dt` (`type`,`status`,`dtlasthandleattempt`)
) ENGINE=InnoDB AUTO_INCREMENT=941 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_properties`
--

DROP TABLE IF EXISTS `project_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_properties` (
  `project_id` varbinary(16) NOT NULL COMMENT 'projects.project_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`project_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Project properties';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `project_id` varbinary(16) NOT NULL COMMENT 'ID of the project',
  `cc_id` varbinary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  PRIMARY KEY (`project_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_name` (`name`(3)),
  KEY `idx_cc_id` (`cc_id`),
  CONSTRAINT `fk_projects_ccs` FOREIGN KEY (`cc_id`) REFERENCES `ccs` (`cc_id`),
  CONSTRAINT `fk_projects_clients` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Projects';
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
  KEY `role_id` (`role_id`)
  /*CONSTRAINT `role_behaviors_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION*/
) ENGINE=InnoDB AUTO_INCREMENT=347 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;
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
  `architecture` varchar(6) DEFAULT NULL,
  `os_family` varchar(25) DEFAULT NULL,
  `os_name` varchar(50) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `agent_version` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id_location` (`role_id`,`cloud_location`,`platform`),
  UNIQUE KEY `unique` (`role_id`,`image_id`(50),`cloud_location`,`platform`),
  KEY `NewIndex1` (`platform`),
  KEY `location` (`cloud_location`),
  CONSTRAINT `role_images_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=670 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `script_id` (`script_id`),
  CONSTRAINT `role_scripts_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `role_scripts_ibfk_2` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_software`
--

DROP TABLE IF EXISTS `role_software`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_software` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `software_name` varchar(45) DEFAULT NULL,
  `software_version` varchar(20) DEFAULT NULL,
  `software_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_software_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_tags`
--

DROP TABLE IF EXISTS `role_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_tags` (
  `role_id` int(11) NOT NULL DEFAULT '0',
  `tag` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`role_id`,`tag`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `role_tags_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
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
  `history` text,
  `generation` tinyint(4) DEFAULT '1',
  `os` varchar(60) DEFAULT NULL,
  `os_family` varchar(30) DEFAULT NULL,
  `os_generation` varchar(10) DEFAULT NULL,
  `os_version` varchar(10) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `added_by_userid` int(11) DEFAULT NULL,
  `added_by_email` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `NewIndex1` (`origin`),
  KEY `NewIndex2` (`client_id`),
  KEY `NewIndex3` (`env_id`),
  KEY `idx_os_family` (`os_family`)
) ENGINE=InnoDB AUTO_INCREMENT=52105 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles_queue`
--

DROP TABLE IF EXISTS `roles_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;
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
  `type` enum('script_exec','terminate_farm','launch_farm') DEFAULT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL COMMENT 'id of farm, farm_role from other tables',
  `target_server_index` int(11) DEFAULT NULL,
  `target_type` enum('farm','role','instance') DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scheduler2`
--

DROP TABLE IF EXISTS `scheduler2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduler2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `target_id` varchar(255) DEFAULT NULL COMMENT 'id of farm, farm_role or farm_role:index from other tables',
  `target_type` varchar(255) DEFAULT NULL COMMENT 'farm, role or instance type',
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `script_revisions`
--

DROP TABLE IF EXISTS `script_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `script_revisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scriptid` int(11) DEFAULT NULL,
  `revision` int(11) DEFAULT NULL,
  `script` longtext,
  `dtcreated` datetime DEFAULT NULL,
  `variables` text,
  `changed_by_id` int(11) DEFAULT NULL,
  `changed_by_email` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scriptid_revision` (`scriptid`,`revision`),
  CONSTRAINT `fk_script_revisions_scripts_id` FOREIGN KEY (`scriptid`) REFERENCES `scripts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;
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
  `dtadded` datetime DEFAULT NULL,
  `dtchanged` datetime DEFAULT NULL,
  `issync` tinyint(1) DEFAULT '0',
  `timeout` int(11) DEFAULT NULL,
  `clientid` int(11) DEFAULT '0',
  `env_id` int(11) DEFAULT '0',
  `created_by_id` int(11) DEFAULT NULL,
  `created_by_email` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clientid` (`clientid`),
  KEY `env_id` (`env_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=latin1;
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
  `role_id` int(11) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `status` varchar(25) DEFAULT NULL,
  `remote_ip` varchar(15) DEFAULT NULL,
  `local_ip` varchar(15) DEFAULT NULL,
  `dtadded` datetime DEFAULT NULL,
  `index` int(11) DEFAULT NULL,
  `cloud_location` varchar(50) DEFAULT NULL,
  `cloud_location_zone` varchar(50) DEFAULT NULL,
  `dtshutdownscheduled` datetime DEFAULT NULL,
  `dtrebootstart` datetime DEFAULT NULL,
  `replace_server_id` varchar(36) DEFAULT NULL,
  `dtlastsync` datetime DEFAULT NULL,
  `os_type` enum('linux','windows') DEFAULT 'linux',
  PRIMARY KEY (`id`),
  KEY `serverid` (`server_id`),
  KEY `farm_roleid` (`farm_roleid`),
  KEY `farmid_status` (`farm_id`,`status`),
  KEY `local_ip` (`local_ip`),
  KEY `env_id` (`env_id`),
  KEY `role_id` (`role_id`),
  KEY `client_id` (`client_id`),
  KEY `idx_dtshutdownscheduled` (`dtshutdownscheduled`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=latin1;
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
  `launch_reason` varchar(255) DEFAULT NULL,
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
  KEY `server_id` (`server_id`)
  /*CONSTRAINT `servers_history_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON
 * DELETE CASCADE ON UPDATE NO ACTION*/
) ENGINE=InnoDB AUTO_INCREMENT=646 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;
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
) ENGINE=MyISAM AUTO_INCREMENT=76 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services_chef_servers`
--

DROP TABLE IF EXISTS `services_chef_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services_chef_servers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `env_id` int(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `auth_key` text,
  `v_username` varchar(255) DEFAULT NULL,
  `v_auth_key` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=latin1;
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
  `error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main` (`farm_role_id`),
  CONSTRAINT `services_db_backups_history_ibfk_1` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
  KEY `farm_role_id` (`farm_role_id`),
  CONSTRAINT `services_mongodb_config_servers_ibfk_1` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ssh_keys`
--

DROP TABLE IF EXISTS `ssh_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ssh_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `env_id` int(11) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `private_key` text,
  `public_key` text,
  `cloud_location` varchar(255) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `cloud_key_name` varchar(255) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farmid` (`farm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=latin1;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=266663 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_07032014`
--

DROP TABLE IF EXISTS `syslog_07032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_07032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1445716 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_08032014`
--

DROP TABLE IF EXISTS `syslog_08032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_08032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1485337 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_09032014`
--

DROP TABLE IF EXISTS `syslog_09032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_09032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1417891 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_10032014`
--

DROP TABLE IF EXISTS `syslog_10032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_10032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1474453 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_11032014`
--

DROP TABLE IF EXISTS `syslog_11032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_11032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1474338 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_12032014`
--

DROP TABLE IF EXISTS `syslog_12032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_12032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1466886 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_13032014`
--

DROP TABLE IF EXISTS `syslog_13032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_13032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1474820 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_14032014`
--

DROP TABLE IF EXISTS `syslog_14032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_14032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1463551 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_15032014`
--

DROP TABLE IF EXISTS `syslog_15032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_15032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1462717 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_16032014`
--

DROP TABLE IF EXISTS `syslog_16032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_16032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1471400 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syslog_17032014`
--

DROP TABLE IF EXISTS `syslog_17032014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syslog_17032014` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1475205 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=414 DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;
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
  `event_type` varchar(128) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `response_code` smallint(6) DEFAULT NULL,
  `payload` text,
  PRIMARY KEY (`history_id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_endpoint_id` (`endpoint_id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_status` (`status`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_farm_id` (`farm_id`),
  CONSTRAINT `fk_4572d5cd4d19cd8c1` FOREIGN KEY (`endpoint_id`) REFERENCES `webhook_endpoints` (`endpoint_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_2fa13e63bff387aa2` FOREIGN KEY (`webhook_id`) REFERENCES `webhook_configs` (`webhook_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_35d689dd5c9d257f3` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
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

-- Dump completed on 2014-03-17  9:36:05
