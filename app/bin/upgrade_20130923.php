#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130923();
$ScalrUpdate->Run();

class Update20130923
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $rec = $db->GetAssoc('DESCRIBE messages');

        if (!empty($rec['message_format'])) {
            print "Process termination. This update has been already applied.\n";
            return;
        }

        $db->Execute("ALTER TABLE `messages` ADD `message_format` ENUM('xml','json') NULL AFTER `message_version`");
        $db->Execute("ALTER TABLE `messages` CHANGE `instance_id` `processing_time` FLOAT(15) NULL DEFAULT NULL;");
        $db->Execute("ALTER TABLE `messages` DROP `json_message`, DROP `isszr`;");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}