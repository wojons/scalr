#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130729();
$ScalrUpdate->Run();

class Update20130729
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        //NOTHING TODO:

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
