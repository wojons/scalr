#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131118();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131118
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

        $res = $db->GetRow("SHOW INDEXES FROM `server_properties` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("ALTER TABLE `server_properties` DROP COLUMN `id`, ADD PRIMARY KEY (`server_id`, `name`)");
            $cnt++;
        }

        if ($this->hasTableIndex('server_properties', 'serverid_name')) {
            $db->Execute("ALTER TABLE `server_properties` DROP INDEX `serverid_name`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `account_team_envs` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("ALTER TABLE `account_team_envs` DROP COLUMN `id`, ADD PRIMARY KEY (`team_id`,`env_id`)");
            $cnt++;
        }

        if ($this->hasTableIndex('account_team_envs', 'idx_unique')) {
            $db->Execute("ALTER TABLE `account_team_envs` DROP INDEX `idx_unique`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `account_user_settings` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->BeginTrans();
            $db->Execute("DELETE FROM `account_user_settings` WHERE user_id IS NULL");
            $db->Execute("ALTER TABLE `account_user_settings` DROP COLUMN `id`, ADD PRIMARY KEY (`user_id`,`name`)");
            $db->CommitTrans();
            $cnt++;
        }

        if ($this->hasTableIndex('account_user_settings', 'userid_name')) {
            $db->Execute("ALTER TABLE `account_user_settings` DROP INDEX `userid_name`");
            $cnt++;
        }

        $res = $db->GetRow("SHOW INDEXES FROM `account_user_vars` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("ALTER TABLE `account_user_vars` DROP COLUMN `id`, ADD PRIMARY KEY (`user_id`,`name`)");
            $cnt++;
        }

        if ($this->hasTableIndex('account_user_vars', 'userid_name')) {
            $db->Execute("ALTER TABLE `account_user_vars` DROP INDEX `userid_name`");
            $cnt++;
        }

        $res = $db->GetRow("SHOW INDEXES FROM `client_settings` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->BeginTrans();
            $db->Execute("DELETE FROM `client_settings` WHERE `clientid` IS NULL");
            $db->Execute("ALTER TABLE `client_settings` DROP COLUMN `id`, ADD PRIMARY KEY (`clientid`,`key`)");
            $db->CommitTrans();
            $cnt++;
        }

        if ($this->hasTableIndex('client_settings', 'NewIndex1')) {
            $db->Execute("ALTER TABLE `client_settings` DROP INDEX `NewIndex1`");
            $cnt++;
        }

        $res = $db->GetRow("SHOW INDEXES FROM `farm_role_settings` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->BeginTrans();
            $db->Execute("DELETE FROM `farm_role_settings` WHERE `farm_roleid` IS NULL OR `name` IS NULL");
            $db->Execute("ALTER TABLE `farm_role_settings` DROP COLUMN `id`, ADD PRIMARY KEY (`farm_roleid`,`name`)");
            $db->CommitTrans();
            $cnt++;
        }

        if ($this->hasTableIndex('farm_role_settings', 'unique')) {
            $db->Execute("ALTER TABLE `farm_role_settings` DROP INDEX `unique`");
            $cnt++;
        }

        $res = $db->GetRow("SHOW INDEXES FROM `farm_role_storage_settings` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("
                ALTER TABLE `farm_role_storage_settings` DROP COLUMN `id`,
                    ADD PRIMARY KEY (`storage_config_id`,`name`)
            ");
            $cnt++;
        }

        if ($this->hasTableIndex('farm_role_storage_settings', 'storage_config')) {
            $db->Execute("ALTER TABLE `farm_role_storage_settings` DROP INDEX `storage_config`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `farm_settings` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("
                ALTER TABLE `farm_settings` DROP COLUMN `id`, ADD PRIMARY KEY (`farmid`,`name`)
            ");
            $cnt++;
        }

        if ($this->hasTableIndex('farm_settings', 'farmid_name')) {
            $db->Execute("ALTER TABLE `farm_settings` DROP INDEX `farmid_name`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `role_properties` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("
                ALTER TABLE `role_properties` DROP COLUMN `id`, ADD PRIMARY KEY (`role_id`,`name`)
            ");
            $cnt++;
        }

        if ($this->hasTableIndex('role_properties', 'NewIndex1')) {
            $db->Execute("ALTER TABLE `role_properties` DROP INDEX `NewIndex1`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `role_tags` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("
                ALTER TABLE `role_tags` DROP COLUMN `id`, ADD PRIMARY KEY (`role_id`,`tag`)
            ");
            $cnt++;
        }

        if ($this->hasTableIndex('role_tags', 'role_tag')) {
            $db->Execute("ALTER TABLE `role_tags` DROP INDEX `role_tag`");
            $cnt++;
        }


        $res = $db->GetRow("SHOW INDEXES FROM `services_mongodb_snapshots_map` WHERE `key_name` = 'PRIMARY' AND `column_name` = 'id'");
        if ($res) {
            $db->Execute("
                ALTER TABLE `services_mongodb_snapshots_map` DROP COLUMN `id`, ADD PRIMARY KEY (`farm_roleid`,`shard_index`)
            ");
            $cnt++;
        }

        if ($this->hasTableIndex('services_mongodb_snapshots_map', 'main')) {
            $db->Execute("ALTER TABLE `services_mongodb_snapshots_map` DROP INDEX `main`");
            $cnt++;
        }

        if (!$cnt) {
            print "Terminated. This upgrade has already been applied.\n";
            exit;
        }
    }
}
