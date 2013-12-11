#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131031();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131031
{

    public function hasTableConstraint($table, $constraintName)
    {
        $row = Scalr::getDb()->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_SCHEMA = '" . Scalr::config('scalr.connections.mysql.name') . "'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = '" . $table . "'
            AND CONSTRAINT_NAME = '" . $constraintName . "'
        ");

        return $row ? true : false;
    }

    public function hasTableColumn($table, $column)
    {
        $rec = Scalr::getDb()->GetAssoc('DESCRIBE ' . $table);
        if ($rec) {
            $rec = array_map('strtolower', array_keys($rec));
        }
        return $rec ? in_array(strtolower($column), $rec) : false;
    }

    public function hasTableIndex($table, $index)
    {
        $res = Scalr::getDb()->getRow("SHOW INDEXES FROM " . $table . " WHERE `key_name` = ?", array(
            $index
        ));

        return $res ? true : false;
    }

    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $cnt = 0;

        if (!$this->hasTableConstraint('servers_history', 'servers_history_ibfk_1')) {
            $db->Execute("
                ALTER TABLE `servers_history` ADD CONSTRAINT `servers_history_ibfk_1` FOREIGN KEY (`client_id`)
                REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ");
            $cnt++;
        }

        if ($this->hasTableConstraint('servers_history', 'client_id')) {
            $db->Execute("ALTER TABLE `servers_history` DROP FOREIGN KEY `client_id`");
            $cnt++;
        }

        if ($this->hasTableColumn('servers_history', 'dtterminated_scalr')) {
            $db->Execute("ALTER TABLE `servers_history` DROP `dtterminated_scalr`");
            $cnt++;
        }

        if ($this->hasTableColumn('servers_history', 'shutdown_confirmed')) {
            $db->Execute("ALTER TABLE `servers_history` DROP `shutdown_confirmed`");
            $cnt++;
        }

        if (!$this->hasTableIndex('autosnap_settings', 'idx_dtlastsnapshot')) {
            $db->Execute("ALTER TABLE `autosnap_settings` ADD INDEX `idx_dtlastsnapshot` (`dtlastsnapshot`)");
            $cnt++;
        }

        if (!$this->hasTableIndex('clients', 'idx_dtadded')) {
            $db->Execute("ALTER TABLE `clients` ADD INDEX `idx_dtadded` (`dtadded`)");
            $cnt++;
        }

        if (!$this->hasTableIndex('clients', 'idx_isactive')) {
            $db->Execute("ALTER TABLE `clients` ADD INDEX `idx_isactive` (`isactive`)");
            $cnt++;
        }

        if (!$this->hasTableIndex('clients', 'idx_dtdue')) {
            $db->Execute("ALTER TABLE `clients` ADD INDEX `idx_dtdue` (`dtdue`)");
            $cnt++;
        }

        if (!$this->hasTableConstraint('autosnap_settings', 'autosnap_settings_ibfk_1')) {
            $db->Execute("
                DELETE FROM `autosnap_settings`
                WHERE NOT EXISTS (
                    SELECT 1 FROM `client_environments` ce
                    WHERE ce.id = `autosnap_settings`.env_id
                )
            ");
            $db->Execute("
                ALTER TABLE `autosnap_settings` ADD CONSTRAINT `autosnap_settings_ibfk_1` FOREIGN KEY (`env_id`)
                REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ");
            $cnt++;
        }

        if (!$cnt) {
            print "Terminated. This upgrade has already been applied.\n";
            exit;
        }
    }
}
