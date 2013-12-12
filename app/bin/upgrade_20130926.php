#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130926();
$ScalrUpdate->Run();

class Update20130926
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $rows = $db->GetAll('SELECT * FROM account_user_settings WHERE name = ? AND value != ""', array(Scalr_Account_User::SETTING_SECURITY_IP_WHITELIST));
        foreach ($rows as $row) {
            $value = explode(',', $row['value']);
            $result = array();

            foreach ($value as $v) {
                $vC = Scalr_Util_Network::convertMaskToSubnet($v);
                if ($vC)
                    $result[] = $vC;
            }

            $val = serialize($result);
            $db->Execute('INSERT INTO account_user_vars (user_id, name, value) VALUES(?,?,?)', array($row['user_id'], Scalr_Account_User::VAR_SECURITY_IP_WHITELIST, $val));
        }

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}