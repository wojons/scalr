import sys
import time
import pymysql
import pymysql.cursors
import subprocess as subps

from scalrpy.util import dbmanager


def wait_sec(sec):
    time.sleep(sec)


def drop_db(config):
    conn = pymysql.connect(
            user=config['user'],
            passwd=config['pass'],
            host=config['host'],
            cursorclass=pymysql.cursors.DictCursor)
    conn.autocommit(True)
    cur = conn.cursor()
    try:
        cur.execute('drop database `%s`' % config['name'])
        return True
    except pymysql.err.InternalError as e:
        if e.args[0] in (1008, 1049):
            return True
        else:
            raise e
    finally:
        cur.close()
        conn.close()


def create_db(config):
    conn = pymysql.connect(
            user=config['user'],
            passwd=config['pass'],
            host=config['host'],
            cursorclass=pymysql.cursors.DictCursor)
    conn.autocommit(True)
    cur = conn.cursor()
    try:
        cur.execute('create database `%s`' % config['name'])
        return True
    except (pymysql.err.ProgrammingError, pymysql.err.InternalError) as e:
        if e.args[0] in (1007, 1049):
            return True
        else:
            raise e
    finally:
        cur.close()
        conn.close()


TABLES = {
        'clients':
                "CREATE TABLE `clients` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`name` varchar(255) DEFAULT NULL,"+\
                "`status` varchar(50) DEFAULT NULL,"+\
                "`isbilled` tinyint(1) DEFAULT '0',"+\
                "`dtdue` datetime DEFAULT NULL,"+\
                "`isactive` tinyint(1) DEFAULT '0',"+\
                "`fullname` varchar(60) DEFAULT NULL,"+\
                "`org` varchar(60) DEFAULT NULL,"+\
                "`country` varchar(60) DEFAULT NULL,"+\
                "`state` varchar(60) DEFAULT NULL,"+\
                "`city` varchar(60) DEFAULT NULL,"+\
                "`zipcode` varchar(60) DEFAULT NULL,"+\
                "`address1` varchar(60) DEFAULT NULL,"+\
                "`address2` varchar(60) DEFAULT NULL,"+\
                "`phone` varchar(60) DEFAULT NULL,"+\
                "`fax` varchar(60) DEFAULT NULL,"+\
                "`dtadded` datetime DEFAULT NULL,"+\
                "`iswelcomemailsent` tinyint(1) DEFAULT '0',"+\
                "`login_attempts` int(5) DEFAULT '0',"+\
                "`dtlastloginattempt` datetime DEFAULT NULL,"+\
                "`comments` text,"+\
                "`priority` int(4) NOT NULL DEFAULT '0',"+\
                "PRIMARY KEY (`id`)) "+\
                "ENGINE=InnoDB AUTO_INCREMENT=9587 DEFAULT CHARSET=latin1",
        'farms':
                "CREATE TABLE `farms` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`clientid` int(11) DEFAULT NULL,"+\
                "`env_id` int(11) NOT NULL,"+\
                "`name` varchar(255) DEFAULT NULL,"+\
                "`iscompleted` tinyint(1) DEFAULT '0',"+\
                "`hash` varchar(25) DEFAULT NULL,"+\
                "`dtadded` datetime DEFAULT NULL,"+\
                "`status` tinyint(1) DEFAULT '1',"+\
                "`dtlaunched` datetime DEFAULT NULL,"+\
                "`term_on_sync_fail` tinyint(1) DEFAULT '1',"+\
                "`region` varchar(255) DEFAULT 'us-east-1',"+\
                "`farm_roles_launch_order` tinyint(1) DEFAULT '0',"+\
                "`comments` text,"+\
                "`created_by_id` int(11) DEFAULT NULL,"+\
                "`created_by_email` varchar(250) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "KEY `clientid` (`clientid`),"+\
                "KEY `env_id` (`env_id`)"+\
                #"CONSTRAINT `farms_ibfk_1` FOREIGN KEY (`clientid`) "+\
                #"REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION"+\
                ") "+\
                "ENGINE=InnoDB AUTO_INCREMENT=12552 DEFAULT CHARSET=latin1",
        'farm_roles':
                "CREATE TABLE `farm_roles` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`farmid` int(11) DEFAULT NULL,"+\
                "`dtlastsync` datetime DEFAULT NULL,"+\
                "`reboot_timeout` int(10) DEFAULT '300',"+\
                "`launch_timeout` int(10) DEFAULT '300',"+\
                "`status_timeout` int(10) DEFAULT '20',"+\
                "`launch_index` int(5) DEFAULT '0',"+\
                "`role_id` int(11) DEFAULT NULL,"+\
                "`new_role_id` int(11) DEFAULT NULL,"+\
                "`platform` varchar(20) DEFAULT NULL,"+\
                "`cloud_location` varchar(50) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "KEY `role_id` (`role_id`),"+\
                "KEY `farmid` (`farmid`),"+\
                "KEY `platform` (`platform`)"+\
                #"CONSTRAINT `farm_roles_ibfk_1` FOREIGN KEY (`farmid`) "+\
                #"REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION"+\
                ")"+\
                "ENGINE=InnoDB AUTO_INCREMENT=43156 DEFAULT CHARSET=latin1",
        'messages':
                "CREATE TABLE `messages` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`messageid` varchar(75) DEFAULT NULL,"+\
                "`processing_time` float DEFAULT NULL,"+\
                "`status` tinyint(1) DEFAULT '0',"+\
                "`handle_attempts` int(2) DEFAULT '1',"+\
                "`dtlasthandleattempt` datetime DEFAULT NULL,"+\
                "`dtadded` datetime DEFAULT NULL,"+\
                "`message` longtext,"+\
                "`server_id` varchar(36) DEFAULT NULL,"+\
                "`type` enum('in','out') DEFAULT NULL,"+\
                "`message_name` varchar(30) DEFAULT NULL,"+\
                "`message_version` int(2) DEFAULT NULL,"+\
                "`message_format` enum('xml','json') DEFAULT NULL,"+\
                "`ipaddress` varchar(15) DEFAULT NULL,"+\
                "`event_id` varchar(36) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `server_message` (`messageid`(36),`server_id`),"+\
                "KEY `server_id` (`server_id`),"+\
                "KEY `messageid` (`messageid`),"+\
                "KEY `status` (`status`,`type`),"+\
                "KEY `message_name` (`message_name`),"+\
                "KEY `dt` (`dtlasthandleattempt`),"+\
                "KEY `msg_format` (`message_format`)"+\
                ") "+\
                "ENGINE=MyISAM AUTO_INCREMENT=42920410 "+\
                "DEFAULT CHARSET=latin1",
        'servers':
                "CREATE TABLE `servers` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`server_id` varchar(36) DEFAULT NULL,"+\
                "`farm_id` int(11) DEFAULT NULL,"+\
                "`farm_roleid` int(11) DEFAULT NULL,"+\
                "`client_id` int(11) DEFAULT NULL,"+\
                "`env_id` int(11) NOT NULL,"+\
                "`role_id` int(11) DEFAULT NULL,"+\
                "`platform` varchar(10) DEFAULT NULL,"+\
                "`status` varchar(25) DEFAULT NULL,"+\
                "`remote_ip` varchar(15) DEFAULT NULL,"+\
                "`local_ip` varchar(15) DEFAULT NULL,"+\
                "`dtadded` datetime DEFAULT NULL,"+\
                "`index` int(11) DEFAULT NULL,"+\
                "`dtshutdownscheduled` datetime DEFAULT NULL,"+\
                "`dtrebootstart` datetime DEFAULT NULL,"+\
                "`replace_server_id` varchar(36) DEFAULT NULL,"+\
                "`dtlastsync` datetime DEFAULT NULL,"+\
                "PRIMARY KEY (id),"+\
                "KEY serverid (server_id),"+\
                "KEY farm_roleid (farm_roleid),"+\
                "KEY farmid_status (farm_id,status),"+\
                "KEY local_ip (local_ip),"+\
                "KEY env_id (env_id),"+\
                "KEY role_id (role_id),"+\
                "KEY client_id (client_id) )"+\
                "ENGINE=InnoDB AUTO_INCREMENT=817009 "+\
                "DEFAULT CHARSET=latin1",
        'server_properties':
                "CREATE TABLE `server_properties` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`server_id` varchar(36) DEFAULT NULL,"+\
                "`name` varchar(255) DEFAULT NULL,"+\
                "`value` text,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `serverid_name` (`server_id`,`name`),"+\
                "KEY `serverid` (`server_id`),"+\
                "KEY `name_value` (`name`(20),`value`(20))"+\
                #"CONSTRAINT `server_properties_ibfk_1` FOREIGN KEY (`server_id`)"+\
                #"REFERENCES `servers` (`server_id`)"+\
                #"ON DELETE CASCADE ON UPDATE NO ACTION"+\
                ") "+\
                "ENGINE=InnoDB AUTO_INCREMENT=533922744 "+\
                "DEFAULT CHARSET=latin1",
        'farm_settings':
                "CREATE TABLE `farm_settings` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`farmid` int(11) DEFAULT NULL,"+\
                "`name` varchar(50) DEFAULT NULL,"+\
                "`value` text,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `farmid_name` (`farmid`,`name`)) "+\
                "ENGINE=InnoDB AUTO_INCREMENT=3173597 "+\
                "DEFAULT CHARSET=latin1",
        'role_behaviors':
                "CREATE TABLE `role_behaviors` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`role_id` int(11) DEFAULT NULL,"+\
                "`behavior` varchar(25) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `role_id_behavior` (`role_id`,`behavior`),"+\
                "KEY `role_id` (`role_id`)"+\
                #"CONSTRAINT `role_behaviors_ibfk_1` FOREIGN KEY (`role_id`) "+\
                #"REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION"+\
                ") "+\
                "ENGINE=InnoDB AUTO_INCREMENT=71741 "+\
                "DEFAULT CHARSET=latin1",
        'farm_role_settings':
                "CREATE TABLE `farm_role_settings` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`farm_roleid` int(11) DEFAULT NULL,"+\
                "`name` varchar(255) DEFAULT NULL,"+\
                "`value` text,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `unique` (`farm_roleid`,`name`),"+\
                "KEY `name` (`name`(30))"+\
                ") ENGINE=MyISAM AUTO_INCREMENT=293325591 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT",
        'events':
                "CREATE TABLE `events` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`farmid` int(11) DEFAULT NULL,"+\
                "`type` varchar(25) DEFAULT NULL,"+\
                "`dtadded` datetime DEFAULT NULL,"+\
                "`message` varchar(255) DEFAULT NULL,"+\
                "`ishandled` tinyint(1) DEFAULT '0',"+\
                "`short_message` varchar(255) DEFAULT NULL,"+\
                "`event_object` text,"+\
                "`event_id` varchar(36) DEFAULT NULL,"+\
                "`event_server_id` varchar(36) DEFAULT NULL,"+\
                "`msg_sent` int(11) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `event_id` (`event_id`),"+\
                "KEY `farmid` (`farmid`),"+\
                "KEY `event_server_id` (`event_server_id`)"+\
                ") ENGINE=MyISAM AUTO_INCREMENT=6865631 DEFAULT CHARSET=latin1",
        'farm_event_observers':
                "CREATE TABLE `farm_event_observers` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`farmid` int(11) DEFAULT NULL,"+\
                "`event_observer_name` varchar(255) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "KEY `NewIndex1` (`farmid`)"+\
                ") ENGINE=InnoDB AUTO_INCREMENT=943 DEFAULT CHARSET=latin1",
        'farm_event_observers_config':
                "CREATE TABLE `farm_event_observers_config` ("+\
                "`id` int(11) NOT NULL AUTO_INCREMENT,"+\
                "`observerid` int(11) DEFAULT NULL,"+\
                "`key` varchar(255) DEFAULT NULL,"+\
                "`value` varchar(255) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "KEY `NewIndex1` (`observerid`)"+\
                ") ENGINE=InnoDB AUTO_INCREMENT=18718 DEFAULT CHARSET=latin1",
        'dns_zones':
                "CREATE TABLE `dns_zones` ("+\
                "`id` int(10) unsigned NOT NULL AUTO_INCREMENT,"+\
                "`client_id` int(11) DEFAULT NULL,"+\
                "`env_id` int(11) NOT NULL,"+\
                "`farm_id` int(11) DEFAULT NULL,"+\
                "`farm_roleid` int(11) DEFAULT NULL,"+\
                "`zone_name` varchar(255) DEFAULT NULL,"+\
                "`status` varchar(255) DEFAULT NULL,"+\
                "`soa_owner` varchar(100) DEFAULT NULL,"+\
                "`soa_ttl` int(10) unsigned DEFAULT NULL,"+\
                "`soa_parent` varchar(100) DEFAULT NULL,"+\
                "`soa_serial` int(10) unsigned DEFAULT NULL,"+\
                "`soa_refresh` int(10) unsigned DEFAULT NULL,"+\
                "`soa_retry` int(10) unsigned DEFAULT NULL,"+\
                "`soa_expire` int(10) unsigned DEFAULT NULL,"+\
                "`soa_min_ttl` int(10) unsigned DEFAULT NULL,"+\
                "`dtlastmodified` datetime DEFAULT NULL,"+\
                "`axfr_allowed_hosts` tinytext,"+\
                "`allow_manage_system_records` tinyint(1) DEFAULT '0',"+\
                "`isonnsserver` tinyint(1) DEFAULT '0',"+\
                "`iszoneconfigmodified` tinyint(1) DEFAULT '0',"+\
                "`allowed_accounts` text,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `zones_index3945` (`zone_name`),"+\
                "KEY `farmid` (`farm_id`),"+\
                "KEY `clientid` (`client_id`),"+\
                "KEY `env_id` (`env_id`),"+\
                "KEY `iszoneconfigmodified` (`iszoneconfigmodified`)"+\
                ") ENGINE=InnoDB AUTO_INCREMENT=16135 DEFAULT CHARSET=latin1",
        'dns_zone_records':
                "CREATE TABLE `dns_zone_records` ("+\
                "`id` int(10) unsigned NOT NULL AUTO_INCREMENT,"+\
                "`zone_id` int(10) unsigned NOT NULL DEFAULT '0',"+\
                "`type` varchar(6) DEFAULT NULL,"+\
                "`ttl` int(10) unsigned DEFAULT NULL,"+\
                "`priority` int(10) unsigned DEFAULT NULL,"+\
                "`value` varchar(255) DEFAULT NULL,"+\
                "`name` varchar(255) DEFAULT NULL,"+\
                "`issystem` tinyint(1) DEFAULT NULL,"+\
                "`weight` int(10) DEFAULT NULL,"+\
                "`port` int(10) DEFAULT NULL,"+\
                "`server_id` varchar(36) DEFAULT NULL,"+\
                "PRIMARY KEY (`id`),"+\
                "UNIQUE KEY `zoneid` (`zone_id`,`type`(1),`value`,`name`)"+\
                ") ENGINE=InnoDB AUTO_INCREMENT=50136935 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC"
        }


def create_table(config, name):
    conn = pymysql.connect(
            user=config['user'],
            passwd=config['pass'],
            host=config['host'],
            cursorclass=pymysql.cursors.DictCursor)
    conn.autocommit(True)
    cur = conn.cursor()
    try:
        cur.execute('use `%s`' % config['name'])
        cur.execute(TABLES[name])
        return True
    except pymysql.err.InternalError as e:
        if e.args[0] == 1050:
            return True
        else:
            raise e
    finally:
        cur.close()
        conn.close()


def start_daemon(name, config):
    cmd = 'python -m scalrpy.%s --start -vvvv -c %s' % (name, config)
    subps.Popen(cmd.split())

    time.sleep(0.5)

    ps = subps.Popen('ps -ef'.split(), stdout=subps.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()

    return 'scalrpy.%s --start -vvvv -c %s' % (name, config) in output


def stop_daemon(name, config):
    cmd = 'python -m scalrpy.%s --stop -vvvv -c %s' % (name, config)
    subps.Popen(cmd.split())

    time.sleep(5)

    ps = subps.Popen('ps -ef'.split(), stdout=subps.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()

    return 'scalrpy.%s --stop -vvvv -c %s' % (name, config) not in output


def start_service(name):
    cmd = 'service %s start' % name
    subps.call(cmd.split())

    return True


def stop_service(name):
    cmd = 'service %s stop' % name
    subps.call(cmd.split())

    return True
