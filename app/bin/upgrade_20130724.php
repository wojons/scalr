#!/usr/bin/env php
<?php

// Migration to new config script

define("NO_TEMPLATES", 1);

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130724();
$ScalrUpdate->Run();

class Update20130724
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $db->Execute("ALTER TABLE `role_images` DROP INDEX `unique`, ADD UNIQUE `unique` (`role_id`, `image_id`(50), `cloud_location`, `platform`);");
        $db->Execute("ALTER TABLE `role_images` DROP INDEX `role_id_location`, ADD UNIQUE `role_id_location` (`role_id`, `cloud_location`, `platform`);");

        $db->Execute("UPDATE server_properties SET name='cloudstack.server_id' WHERE name='cloudtsack.server_id'");
        $db->Execute("UPDATE server_properties SET name='cloudstack.name' WHERE name='cloudtsack.name'");
        $db->Execute("UPDATE server_properties SET name='cloudstack.launch_job_id' WHERE name='cloudtsack.launch_job_id'");
    }
}
