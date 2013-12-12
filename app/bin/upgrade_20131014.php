#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131014();
$ScalrUpdate->Run();

class Update20131014
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->BeginTrans();
        try {
            $db->Execute('ALTER TABLE `farm_lease_requests` CHANGE `requested_days` `request_days` INT(11)  NOT NULL');
            $db->Execute('ALTER TABLE `farm_lease_requests` ADD `request_time` DATETIME  NULL  AFTER `request_days`');
            $db->Execute('ALTER TABLE `farm_lease_requests` CHANGE `comment` `request_comment` TEXT  CHARACTER SET latin1  COLLATE latin1_swedish_ci  NULL');
            $db->Execute('ALTER TABLE `farm_lease_requests` ADD `request_user_id` INT(11)  NOT NULL  AFTER `request_comment`');
            $db->Execute('ALTER TABLE `farm_lease_requests` ADD `answer_comment` TEXT  NULL  AFTER `request_user_id`');
            $db->Execute('ALTER TABLE `farm_lease_requests` ADD `answer_user_id` TEXT  NULL  AFTER `answer_comment`');

            $db->Execute('DELETE FROM `farm_settings` WHERE name = ?', array('lease.extend.non.standard'));
            $db->CommitTrans();

        } catch (Exception $e) {
            $db->RollbackTrans();
            throw $e;
        }

        // convert notifications
        $rows = $db->GetAll('SELECT * FROM governance WHERE name = ? AND enabled = ?', array(Scalr_Governance::GENERAL_LEASE, 1));
        foreach ($rows as $val) {
            $value = json_decode($val['value'], true);
            $period = $value['defaultNotificationPeriod'];
            $key = Scalr\Farm\FarmLease::getKey();
            $value['notifications'] = array(
                array(
                    'key' => $key,
                    'period' => $period,
                    'to' => 'owner'
                )
            );
            unset($value['defaultNotificationPeriod']);

            $db->Execute('UPDATE `governance` SET `value` = ? WHERE env_id = ? AND `name` = ?', array(json_encode($value), $val['env_id'], $val['name']));

            $farms = $db->GetAll('SELECT fs.* FROM farm_settings fs JOIN farms f ON fs.farmid = f.id WHERE f.env_id = ? AND fs.name = ?', array($val['env_id'], DBFarm::SETTING_LEASE_NOTIFICATION_SEND));
            foreach ($farms as $f) {
                if ($f['value'] == 1) {
                    $value = array($key => 1);
                    $db->Execute('UPDATE farm_settings SET value = ? WHERE id = ?', array(json_encode($value), $f['id']));
                }
            }
        }

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
