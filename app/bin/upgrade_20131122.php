#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131122();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131122
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

    public function hasTableReferencedColumn($referencedTable, $referencedColumn)
    {
        $row = $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME = ?
            AND REFERENCED_COLUMN_NAME = ?
            LIMIT 1
        ", array(
            Scalr::getContainer()->config('scalr.connections.mysql.name'), $referencedTable, $referencedColumn
        ));

        return isset($row['CONSTRAINT_NAME']) ? true : false;
    }

    public function hasTableColumn($table, $column)
    {
        $res = Scalr::getDb()->getRow("SHOW COLUMNS FROM " . $table . " WHERE `Field` = ?", array(
            $column
        ));

        return $res ? true : false;
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

        //Remove foreign key from server_properties table.
        //We need to perpetuate server properties for removed servers.
        if ($this->hasTableConstraint('server_properties', 'server_properties_ibfk_1')) {
            $db->Execute("ALTER TABLE `server_properties` DROP FOREIGN KEY `server_properties_ibfk_1`");
            $cnt++;
        }

        if (!$cnt) {
            print "Terminated. This upgrade has already been applied.\n";
            exit;
        }
    }
}
